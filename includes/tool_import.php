<?php
declare(strict_types=1);

/**
 * Parse CSV or XLSX (first sheet) into rows of string cells.
 *
 * @return list<list<string>>
 */
function tm_spreadsheet_to_rows(string $filePath, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        return tm_csv_to_rows($filePath);
    }
    if ($ext === 'xlsx') {
        return tm_xlsx_to_rows($filePath);
    }
    throw new InvalidArgumentException('Upload a .csv or .xlsx file. For older .xls, save as .xlsx or .csv in Excel.');
}

/**
 * @return list<list<string>>
 */
function tm_csv_to_rows(string $filePath): array
{
    $fh = fopen($filePath, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Cannot read CSV file');
    }
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($fh);
    }
    $rows = [];
    while (($row = fgetcsv($fh)) !== false) {
        $cells = array_map(static fn ($c) => trim((string) $c), $row);
        if ($cells === [] || (count($cells) === 1 && $cells[0] === '')) {
            continue;
        }
        $rows[] = $cells;
    }
    fclose($fh);
    return $rows;
}

/**
 * @return list<list<string>>
 */
function tm_xlsx_to_rows(string $filePath): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('XLSX import requires the PHP Zip extension');
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Cannot open XLSX file');
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sx = @simplexml_load_string($sharedXml);
        if ($sx !== false) {
            $sx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($sx->si as $si) {
                $parts = [];
                if (isset($si->t)) {
                    $parts[] = (string) $si->t;
                }
                foreach ($si->r as $run) {
                    if (isset($run->t)) {
                        $parts[] = (string) $run->t;
                    }
                }
                $shared[] = trim(implode('', $parts));
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('XLSX has no sheet1');
    }

    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('Invalid XLSX sheet data');
    }
    $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $grid = [];
    foreach ($sheet->sheetData->row as $row) {
        $rowIndex = (int) ($row['r'] ?? 0);
        if ($rowIndex < 1) {
            $rowIndex = count($grid) + 1;
        }
        $line = [];
        foreach ($row->c as $cell) {
            $ref = (string) ($cell['r'] ?? '');
            $colLetters = preg_replace('/\d+/', '', $ref);
            $colIndex = $colLetters !== '' ? tm_xlsx_col_to_index($colLetters) : count($line);
            $type = (string) ($cell['t'] ?? '');
            $value = '';
            if (isset($cell->v)) {
                $raw = (string) $cell->v;
                if ($type === 's') {
                    $value = $shared[(int) $raw] ?? '';
                } else {
                    $value = $raw;
                }
            } elseif (isset($cell->is->t)) {
                $value = (string) $cell->is->t;
            }
            $line[$colIndex] = trim($value);
        }
        if ($line !== []) {
            ksort($line);
            $grid[$rowIndex - 1] = array_values($line);
        }
    }
    ksort($grid);
    return array_values($grid);
}

function tm_xlsx_col_to_index(string $letters): int
{
    $letters = strtoupper($letters);
    $n = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

/**
 * @param list<list<string>> $rows
 * @return array{imported:int, skipped:int, errors:list<array{row:int,message:string}>}
 */
function tm_import_tools_from_spreadsheet(PDO $pdo, array $rows, int $companyId, string $assetType, int $userId): array
{
    if ($rows === []) {
        throw new InvalidArgumentException('File is empty');
    }
    if (!in_array($assetType, ['consumable', 'measurement'], true)) {
        throw new InvalidArgumentException('Invalid asset type');
    }
    if ($assetType === 'measurement' && $companyId !== tm_measurement_company_id()) {
        throw new InvalidArgumentException('Measurement equipment can only be imported for company id ' . tm_measurement_company_id());
    }

    $headerRow = array_shift($rows);
    $map = tm_import_header_map($headerRow);
    if (!isset($map['name'], $map['barcode'], $map['warehouse'])) {
        throw new InvalidArgumentException('Header row must include name, barcode, and warehouse columns');
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];

    $stmtCo = $pdo->prepare('SELECT id FROM companies WHERE id = ? AND deleted_flag = 0');
    $stmtCo->execute([$companyId]);
    if (!$stmtCo->fetch()) {
        throw new InvalidArgumentException('Company not found');
    }

    $whByName = [];
    $stmtWh = $pdo->prepare(
        'SELECT id, warehouse_name FROM warehouses WHERE company_id = ? AND deleted_flag = 0'
    );
    $stmtWh->execute([$companyId]);
    foreach ($stmtWh->fetchAll() as $wh) {
        $key = strtolower(trim((string) $wh['warehouse_name']));
        $whByName[$key] = (int) $wh['id'];
    }

    $catByName = [];
    $stmtCat = $pdo->prepare('SELECT id, name FROM categories WHERE company_id = ?');
    $stmtCat->execute([$companyId]);
    foreach ($stmtCat->fetchAll() as $cat) {
        $catByName[strtolower(trim((string) $cat['name']))] = (int) $cat['id'];
    }

    $insTool = $pdo->prepare(
        'INSERT INTO tools (company_id, asset_type, name, barcode, nfc_id, category_id, description, uom, range_spec, brand_model, tool_condition, location, last_maintenance, missing_flag, is_active)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insTwa = $pdo->prepare(
        'INSERT INTO tool_warehouse_assignment (tool_id, company_id, warehouse_id, stock_qty) VALUES (?,?,?,?)'
    );
    $insCat = $pdo->prepare('INSERT INTO categories (company_id, name) VALUES (?, ?)');

    $rowNum = 1;
    foreach ($rows as $cells) {
        $rowNum++;
        $get = static function (string $key) use ($map, $cells): string {
            if (!isset($map[$key])) {
                return '';
            }
            $i = $map[$key];
            return isset($cells[$i]) ? trim((string) $cells[$i]) : '';
        };

        $name = $get('name');
        $barcode = $get('barcode');
        $whLabel = $get('warehouse');
        if ($name === '' && $barcode === '') {
            continue;
        }
        if ($name === '' || $barcode === '') {
            $errors[] = ['row' => $rowNum, 'message' => 'Name and barcode are required'];
            $skipped++;
            continue;
        }

        $whKey = strtolower($whLabel);
        if ($whKey === '' || !isset($whByName[$whKey])) {
            $errors[] = ['row' => $rowNum, 'message' => 'Warehouse not found: ' . ($whLabel ?: '(empty)')];
            $skipped++;
            continue;
        }
        $warehouseId = $whByName[$whKey];

        $stockRaw = $get('stock');
        $stockQty = $stockRaw === '' ? 1 : max(0, (int) $stockRaw);

        $stmtDup = $pdo->prepare('SELECT id FROM tools WHERE company_id = ? AND barcode = ? LIMIT 1');
        $stmtDup->execute([$companyId, $barcode]);
        if ($stmtDup->fetch()) {
            $errors[] = ['row' => $rowNum, 'message' => 'Duplicate barcode: ' . $barcode];
            $skipped++;
            continue;
        }

        $nfc = $get('nfc');
        $nfc = $nfc === '' ? null : $nfc;
        $description = $get('description');
        $description = $description === '' ? null : $description;

        $categoryId = null;
        $catName = $get('category');
        if ($catName !== '') {
            $catKey = strtolower($catName);
            if (!isset($catByName[$catKey])) {
                $insCat->execute([$companyId, $catName]);
                $catByName[$catKey] = (int) $pdo->lastInsertId();
            }
            $categoryId = $catByName[$catKey];
        }

        $missingFlag = tm_import_bool($get('missing')) ? 1 : 0;
        $isActive = tm_import_bool($get('active'), true) ? 1 : 0;

        $uom = null;
        $rangeSpec = null;
        $brandModel = null;
        $toolCondition = null;
        $location = null;
        $lastMaintenance = null;
        if ($assetType === 'measurement') {
            $uom = tm_import_nullable_str($get('uom'));
            $rangeSpec = tm_import_nullable_str($get('range'));
            $brandModel = tm_import_nullable_str($get('brand_model'));
            $toolCondition = tm_import_nullable_str($get('condition'));
            $location = tm_import_nullable_str($get('location'));
            $lastMaint = $get('last_maintenance');
            $lastMaintenance = $lastMaint === '' ? null : tm_import_date($lastMaint);
            if ($lastMaint !== '' && $lastMaintenance === null) {
                $errors[] = ['row' => $rowNum, 'message' => 'Invalid last_maintenance date: ' . $lastMaint];
                $skipped++;
                continue;
            }
        }

        try {
            $insTool->execute([
                $companyId,
                $assetType,
                $name,
                $barcode,
                $nfc,
                $categoryId,
                $description,
                $uom,
                $rangeSpec,
                $brandModel,
                $toolCondition,
                $location,
                $lastMaintenance,
                $missingFlag,
                $isActive,
            ]);
            $toolId = (int) $pdo->lastInsertId();
            $insTwa->execute([$toolId, $companyId, $warehouseId, $stockQty]);
            $imported++;
        } catch (Throwable $e) {
            $errors[] = ['row' => $rowNum, 'message' => 'Could not import row: ' . $e->getMessage()];
            $skipped++;
        }
    }

    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
}

/**
 * @param list<string> $headerRow
 * @return array<string, int>
 */
function tm_import_header_map(array $headerRow): array
{
    $aliases = [
        'name' => ['name', 'tool name', 'tool_name', 'item'],
        'barcode' => ['barcode', 'bar code', 'bc'],
        'nfc' => ['nfc', 'nfc_id', 'nfc id', 'hfc', 'hfc_id'],
        'category' => ['category', 'category_name'],
        'description' => ['description', 'desc', 'notes'],
        'warehouse' => ['warehouse', 'warehouse_name', 'warehouse name', 'wh'],
        'stock' => ['stock', 'stock_qty', 'stock qty', 'quantity', 'qty'],
        'missing' => ['missing', 'missing_flag', 'marked missing'],
        'active' => ['active', 'is_active', 'active in catalog'],
        'uom' => ['uom', 'unit', 'unit of measure'],
        'range' => ['range', 'range_spec', 'measuring range'],
        'brand_model' => ['brand_model', 'brand model', 'brand/model', 'brand', 'model'],
        'condition' => ['condition', 'tool_condition'],
        'location' => ['location'],
        'last_maintenance' => ['last_maintenance', 'last maintenance', 'last_maint'],
    ];

    $map = [];
    foreach ($headerRow as $index => $label) {
        $norm = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $label), '_'));
        foreach ($aliases as $key => $names) {
            if (in_array($norm, $names, true)) {
                $map[$key] = (int) $index;
                break;
            }
        }
    }
    return $map;
}

function tm_import_bool(string $value, bool $default = false): bool
{
    $v = strtolower(trim($value));
    if ($v === '') {
        return $default;
    }
    return in_array($v, ['1', 'yes', 'y', 'true', 'active'], true);
}

function tm_import_nullable_str(string $value): ?string
{
    $v = trim($value);
    return $v === '' ? null : $v;
}

function tm_import_date(string $value): ?string
{
    $v = trim($value);
    if ($v === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v;
    }
    $ts = strtotime($v);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
}

/** @return list<string> */
function tm_import_template_headers(string $assetType): array
{
    if ($assetType === 'measurement') {
        return [
            'name', 'barcode', 'nfc_id', 'uom', 'range', 'brand_model', 'condition', 'location',
            'last_maintenance', 'warehouse', 'stock_qty', 'missing', 'active',
        ];
    }
    return [
        'name', 'barcode', 'nfc_id', 'category', 'description', 'warehouse', 'stock_qty', 'missing', 'active',
    ];
}

/** @return list<string> */
function tm_import_template_example(string $assetType): array
{
    if ($assetType === 'measurement') {
        return [
            'Digital caliper', 'ME-BC-001', '', 'each', '0-150mm', 'Mitutoyo 500-196',
            'Good', 'Shelf A1', '2025-01-15', 'Main Warehouse', '1', '0', '1',
        ];
    }
    return [
        'Safety gloves (pair)', 'PPE-GLV-001', '', 'Safety', 'Nitrile large', 'Main Warehouse', '50', '0', '1',
    ];
}

function tm_import_template_filename(string $assetType): string
{
    return $assetType === 'measurement'
        ? 'measurement_equipment_import_template.csv'
        : 'consumable_tools_import_template.csv';
}
