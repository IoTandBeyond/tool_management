<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth_supervisor.php';

$input = [];
$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            tm_json_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
        }
    }
}
$action = $_GET['action'] ?? ($input['action'] ?? '');

if ($action === '') {
    tm_json_response(['ok' => false, 'error' => 'Missing action'], 400);
}

$pdo = tm_db();

switch ($action) {

    case 'warehouses_public': {
        $cid = (int) ($input['company_id'] ?? $_GET['company_id'] ?? 0);
        if ($cid < 1) {
            tm_json_response(['ok' => false, 'error' => 'company_id required'], 400);
        }
        $stmt = $pdo->prepare(
            'SELECT id, warehouse_name, warehouse_address FROM warehouses WHERE company_id = ? AND deleted_flag = 0 ORDER BY warehouse_name'
        );
        $stmt->execute([$cid]);
        tm_json_response(['ok' => true, 'warehouses' => $stmt->fetchAll()]);
    }

    case 'validate_operator': {
        $employeeId = trim((string) ($input['employee_id'] ?? ''));
        $warehouseId = (int) ($input['warehouse_id'] ?? 0);
        if ($employeeId === '' || $warehouseId < 1) {
            tm_json_response(['ok' => false, 'error' => 'Employee ID and warehouse required'], 400);
        }
        $stmt = $pdo->prepare(
            'SELECT o.id, o.name, o.employee_id, o.department, o.company_id, o.warehouse_id
             FROM operators o
             INNER JOIN warehouses w ON w.id = o.warehouse_id AND w.deleted_flag = 0
             WHERE o.employee_id = ? AND o.warehouse_id = ? LIMIT 1'
        );
        $stmt->execute([$employeeId, $warehouseId]);
        $op = $stmt->fetch();
        if (!$op) {
            tm_json_response(['ok' => false, 'error' => 'Employee ID not found for this warehouse'], 404);
        }
        tm_json_response(['ok' => true, 'operator' => $op]);
    }

    case 'operator_borrowed': {
        $operatorId = (int) ($input['operator_id'] ?? 0);
        if ($operatorId < 1) {
            tm_json_response(['ok' => false, 'error' => 'Invalid operator'], 400);
        }
        $stmt = $pdo->prepare(
            'SELECT t.id AS transaction_id, t.checkout_at, t.expected_return_at, t.warehouse_id,
                    t.tool_id, tl.name AS tool_name, tl.barcode, tl.nfc_id, tl.missing_flag,
                    tl.image AS tool_image
             FROM transactions t
             INNER JOIN tools tl ON tl.id = t.tool_id
             WHERE t.operator_id = ? AND t.checkin_at IS NULL
             ORDER BY t.checkout_at DESC'
        );
        $stmt->execute([$operatorId]);
        tm_json_response(['ok' => true, 'borrowed' => $stmt->fetchAll()]);
    }

    case 'checkout_tool': {
        $operatorId = (int) ($input['operator_id'] ?? 0);
        $warehouseId = (int) ($input['warehouse_id'] ?? 0);
        $code = trim((string) ($input['code'] ?? ''));
        if ($operatorId < 1 || $warehouseId < 1 || $code === '') {
            tm_json_response(['ok' => false, 'error' => 'Operator, warehouse, and scan code required'], 400);
        }
        $stmt = $pdo->prepare(
            'SELECT o.id, o.name, o.company_id, o.warehouse_id FROM operators o WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$operatorId]);
        $operator = $stmt->fetch();
        if (!$operator || (int) $operator['warehouse_id'] !== $warehouseId) {
            tm_json_response(['ok' => false, 'error' => 'Invalid operator for this warehouse'], 400);
        }
        $companyId = (int) $operator['company_id'];
        $stmt = $pdo->prepare('SELECT company_id FROM warehouses WHERE id = ? AND deleted_flag = 0 LIMIT 1');
        $stmt->execute([$warehouseId]);
        $whCo = $stmt->fetchColumn();
        if ($whCo === false || (int) $whCo !== $companyId) {
            tm_json_response(['ok' => false, 'error' => 'Warehouse mismatch'], 400);
        }
        $tool = tm_tool_by_company_scan_code($pdo, $companyId, $code);
        if (!$tool) {
            tm_json_response(['ok' => false, 'error' => 'Tool not found for this code'], 404);
        }
        if ((int) $tool['missing_flag'] === 1) {
            tm_json_response(['ok' => false, 'error' => 'Tool is marked missing — contact staff'], 409);
        }
        if ((int) $tool['is_active'] !== 1) {
            tm_json_response(['ok' => false, 'error' => 'Tool is not active in catalog'], 409);
        }
        $tid = (int) $tool['id'];
        $expected = tm_expected_return_at();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, stock_qty FROM tool_warehouse_assignment
                 WHERE tool_id = ? AND warehouse_id = ? AND company_id = ? AND deleted_flag = 0 FOR UPDATE'
            );
            $stmt->execute([$tid, $warehouseId, $companyId]);
            $twa = $stmt->fetch();
            if (!$twa || (int) $twa['stock_qty'] < 1) {
                throw new RuntimeException('no_stock');
            }
            $pdo->prepare(
                'UPDATE tool_warehouse_assignment SET stock_qty = stock_qty - 1 WHERE id = ? AND stock_qty >= 1'
            )->execute([(int) $twa['id']]);
            if ($pdo->query('SELECT ROW_COUNT()')->fetchColumn() === 0) {
                throw new RuntimeException('stock');
            }
            $ins = $pdo->prepare(
                'INSERT INTO transactions (company_id, warehouse_id, tool_id, operator_id, checkout_at, checkin_at, expected_return_at)
                 VALUES (?, ?, ?, ?, NOW(), NULL, ?)'
            );
            $ins->execute([$companyId, $warehouseId, $tid, $operatorId, $expected]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            if ($e->getMessage() === 'no_stock' || $e->getMessage() === 'stock') {
                tm_json_response(['ok' => false, 'error' => 'No units available at this warehouse'], 409);
            }
            tm_json_response(['ok' => false, 'error' => 'Could not check out tool — try again'], 409);
        }
        tm_log_activity('checkout', 'Tool checked out', [
            'tool_id' => $tid,
            'tool_name' => $tool['name'],
            'operator_id' => $operatorId,
            'operator_name' => $operator['name'],
            'warehouse_id' => $warehouseId,
        ], $companyId);
        tm_json_response([
            'ok' => true,
            'tool' => ['id' => $tid, 'name' => $tool['name'], 'barcode' => $tool['barcode']],
            'expected_return_at' => $expected,
        ]);
    }

    case 'kiosk_tool_lookup': {
        $operatorId = (int) ($input['operator_id'] ?? 0);
        $warehouseId = (int) ($input['warehouse_id'] ?? 0);
        $code = trim((string) ($input['code'] ?? ''));
        if ($operatorId < 1 || $warehouseId < 1 || $code === '') {
            tm_json_response(['ok' => false, 'error' => 'Operator, warehouse, and scan code required'], 400);
        }
        $stmt = $pdo->prepare(
            'SELECT o.id, o.name, o.company_id, o.warehouse_id FROM operators o WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$operatorId]);
        $operator = $stmt->fetch();
        if (!$operator || (int) $operator['warehouse_id'] !== $warehouseId) {
            tm_json_response(['ok' => false, 'error' => 'Invalid operator for this warehouse'], 400);
        }
        $companyId = (int) $operator['company_id'];
        $stmt = $pdo->prepare('SELECT company_id FROM warehouses WHERE id = ? AND deleted_flag = 0 LIMIT 1');
        $stmt->execute([$warehouseId]);
        $whCo = $stmt->fetchColumn();
        if ($whCo === false || (int) $whCo !== $companyId) {
            tm_json_response(['ok' => false, 'error' => 'Warehouse mismatch'], 400);
        }
        $tool = tm_tool_by_company_scan_code($pdo, $companyId, $code);
        if (!$tool) {
            tm_json_response(['ok' => false, 'error' => 'Tool not found for this code'], 404);
        }
        if ((int) $tool['missing_flag'] === 1) {
            tm_json_response(['ok' => false, 'error' => 'Tool is marked missing — contact staff'], 409);
        }
        if ((int) $tool['is_active'] !== 1) {
            tm_json_response(['ok' => false, 'error' => 'Tool is not active in catalog'], 409);
        }
        $tid = (int) $tool['id'];
        $stmt = $pdo->prepare(
            'SELECT stock_qty FROM tool_warehouse_assignment
             WHERE tool_id = ? AND warehouse_id = ? AND company_id = ? AND deleted_flag = 0'
        );
        $stmt->execute([$tid, $warehouseId, $companyId]);
        $stock = $stmt->fetchColumn();
        if ($stock === false) {
            tm_json_response(['ok' => false, 'error' => 'Tool not stocked at this warehouse'], 409);
        }
        tm_json_response([
            'ok' => true,
            'tool' => ['id' => $tid, 'name' => $tool['name'], 'barcode' => $tool['barcode']],
            'stock_qty' => (int) $stock,
        ]);
    }

    case 'checkout_batch': {
        $operatorId = (int) ($input['operator_id'] ?? 0);
        $warehouseId = (int) ($input['warehouse_id'] ?? 0);
        $codes = $input['codes'] ?? null;
        if (!is_array($codes)) {
            tm_json_response(['ok' => false, 'error' => 'codes must be an array'], 400);
        }
        $codes = array_values(array_filter(array_map(static function ($c) {
            return trim((string) $c);
        }, $codes), static function ($c) {
            return $c !== '';
        }));
        if ($operatorId < 1 || $warehouseId < 1) {
            tm_json_response(['ok' => false, 'error' => 'Operator and warehouse required'], 400);
        }
        if ($codes === []) {
            tm_json_response(['ok' => false, 'error' => 'No tools to check out'], 400);
        }
        if (count($codes) > 50) {
            tm_json_response(['ok' => false, 'error' => 'Too many items at once (max 50)'], 400);
        }
        $stmt = $pdo->prepare(
            'SELECT o.id, o.name, o.company_id, o.warehouse_id FROM operators o WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$operatorId]);
        $operator = $stmt->fetch();
        if (!$operator || (int) $operator['warehouse_id'] !== $warehouseId) {
            tm_json_response(['ok' => false, 'error' => 'Invalid operator for this warehouse'], 400);
        }
        $companyId = (int) $operator['company_id'];
        $stmt = $pdo->prepare('SELECT company_id FROM warehouses WHERE id = ? AND deleted_flag = 0 LIMIT 1');
        $stmt->execute([$warehouseId]);
        $whCo = $stmt->fetchColumn();
        if ($whCo === false || (int) $whCo !== $companyId) {
            tm_json_response(['ok' => false, 'error' => 'Warehouse mismatch'], 400);
        }
        $resolved = [];
        $counts = [];
        foreach ($codes as $code) {
            $tool = tm_tool_by_company_scan_code($pdo, $companyId, $code);
            if (!$tool) {
                tm_json_response(['ok' => false, 'error' => 'Tool not found for code: ' . $code], 404);
            }
            if ((int) $tool['missing_flag'] === 1) {
                tm_json_response(['ok' => false, 'error' => 'Tool marked missing: ' . $tool['name']], 409);
            }
            if ((int) $tool['is_active'] !== 1) {
                tm_json_response(['ok' => false, 'error' => 'Tool inactive: ' . $tool['name']], 409);
            }
            $tid = (int) $tool['id'];
            $counts[$tid] = ($counts[$tid] ?? 0) + 1;
            $resolved[] = ['tool_id' => $tid, 'tool' => $tool, 'code' => $code];
        }
        $expected = tm_expected_return_at();
        $pdo->beginTransaction();
        try {
            foreach ($counts as $tid => $need) {
                $stmt = $pdo->prepare(
                    'SELECT id, stock_qty FROM tool_warehouse_assignment
                     WHERE tool_id = ? AND warehouse_id = ? AND company_id = ? AND deleted_flag = 0 FOR UPDATE'
                );
                $stmt->execute([$tid, $warehouseId, $companyId]);
                $twa = $stmt->fetch();
                if (!$twa || (int) $twa['stock_qty'] < $need) {
                    $name = 'Tool';
                    foreach ($resolved as $r) {
                        if ((int) $r['tool_id'] === $tid) {
                            $name = $r['tool']['name'];
                            break;
                        }
                    }
                    $have = $twa ? (int) $twa['stock_qty'] : 0;
                    throw new RuntimeException('insufficient|' . $name . '|' . $need . '|' . $have);
                }
            }
            foreach ($counts as $tid => $need) {
                $stmt = $pdo->prepare(
                    'UPDATE tool_warehouse_assignment SET stock_qty = stock_qty - ?
                     WHERE tool_id = ? AND warehouse_id = ? AND company_id = ? AND deleted_flag = 0 AND stock_qty >= ?'
                );
                $stmt->execute([$need, $tid, $warehouseId, $companyId, $need]);
                if ($stmt->rowCount() < 1) {
                    throw new RuntimeException('stock_update');
                }
            }
            $ins = $pdo->prepare(
                'INSERT INTO transactions (company_id, warehouse_id, tool_id, operator_id, checkout_at, checkin_at, expected_return_at)
                 VALUES (?, ?, ?, ?, NOW(), NULL, ?)'
            );
            foreach ($resolved as $row) {
                $tid = (int) $row['tool_id'];
                $ins->execute([$companyId, $warehouseId, $tid, $operatorId, $expected]);
                tm_log_activity('checkout', 'Tool checked out', [
                    'tool_id' => $tid,
                    'tool_name' => $row['tool']['name'],
                    'operator_id' => $operatorId,
                    'operator_name' => $operator['name'],
                    'warehouse_id' => $warehouseId,
                ], $companyId);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $msg = $e->getMessage();
            if (strpos($msg, 'insufficient|') === 0) {
                $parts = explode('|', $msg, 5);
                $tname = $parts[1] ?? 'Tool';
                $need = $parts[2] ?? '?';
                $have = $parts[3] ?? '?';
                tm_json_response([
                    'ok' => false,
                    'error' => 'Not enough stock for ' . $tname . ' (need ' . $need . ' for this borrow, ' . $have . ' available at this warehouse)',
                ], 409);
            }
            tm_json_response(['ok' => false, 'error' => 'Could not complete borrow — try again'], 409);
        }
        tm_json_response([
            'ok' => true,
            'count' => count($resolved),
            'expected_return_at' => $expected,
        ]);
    }

    case 'return_tool': {
        $operatorId = (int) ($input['operator_id'] ?? 0);
        $toolId = (int) ($input['tool_id'] ?? 0);
        $code = trim((string) ($input['code'] ?? ''));
        if ($operatorId < 1 || $toolId < 1 || $code === '') {
            tm_json_response(['ok' => false, 'error' => 'Operator, tool, and scan code required'], 400);
        }
        $stmt = $pdo->prepare(
            'SELECT id, name, barcode, nfc_id, company_id FROM tools WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$toolId]);
        $tool = $stmt->fetch();
        if (!$tool) {
            tm_json_response(['ok' => false, 'error' => 'Tool not found'], 404);
        }
        $match = ($tool['barcode'] === $code) || ($tool['nfc_id'] !== null && $tool['nfc_id'] === $code);
        if (!$match) {
            tm_json_response(['ok' => false, 'error' => 'Scanned code does not match this tool — please scan the correct item'], 409);
        }
        $stmt = $pdo->prepare(
            'SELECT id, warehouse_id, company_id FROM transactions WHERE tool_id = ? AND operator_id = ? AND checkin_at IS NULL LIMIT 1'
        );
        $stmt->execute([$toolId, $operatorId]);
        $tx = $stmt->fetch();
        if (!$tx) {
            tm_json_response(['ok' => false, 'error' => 'No open checkout for this tool and operator'], 409);
        }
        $wid = (int) $tx['warehouse_id'];
        $cid = (int) $tx['company_id'];
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE transactions SET checkin_at = NOW() WHERE id = ?')->execute([(int) $tx['id']]);
            $pdo->prepare(
                'UPDATE tool_warehouse_assignment SET stock_qty = stock_qty + 1
                 WHERE tool_id = ? AND warehouse_id = ? AND company_id = ? AND deleted_flag = 0'
            )->execute([$toolId, $wid, $cid]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            tm_json_response(['ok' => false, 'error' => 'Could not complete return'], 409);
        }
        $stmt = $pdo->prepare('SELECT name FROM operators WHERE id = ? LIMIT 1');
        $stmt->execute([$operatorId]);
        $operatorName = (string) $stmt->fetchColumn();
        tm_log_activity('return', 'Tool returned', [
            'tool_id' => $toolId,
            'tool_name' => $tool['name'],
            'operator_id' => $operatorId,
            'operator_name' => $operatorName,
            'warehouse_id' => $wid,
        ], $cid);
        tm_json_response(['ok' => true, 'tool' => ['id' => $toolId, 'name' => $tool['name']]]);
    }

    case 'dashboard_stats': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $inStock = 0;
        $checkedOut = 0;
        $missing = 0;
        $overdue = 0;
        if ($u['role'] === 'super_admin') {
            $inStock = (int) $pdo->query(
                'SELECT COALESCE(SUM(stock_qty),0) FROM tool_warehouse_assignment WHERE deleted_flag = 0'
            )->fetchColumn();
            $checkedOut = (int) $pdo->query(
                'SELECT COUNT(*) FROM transactions WHERE checkin_at IS NULL'
            )->fetchColumn();
            $missing = (int) $pdo->query('SELECT COUNT(*) FROM tools WHERE missing_flag = 1 AND is_active = 1')->fetchColumn();
            $overdue = (int) $pdo->query(
                'SELECT COUNT(*) FROM transactions WHERE checkin_at IS NULL AND expected_return_at < NOW()'
            )->fetchColumn();
        } elseif ($u['role'] === 'admin' && $u['company_id']) {
            $cid = $u['company_id'];
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(twa.stock_qty),0) FROM tool_warehouse_assignment twa
                 WHERE twa.deleted_flag = 0 AND twa.company_id = ?'
            );
            $stmt->execute([$cid]);
            $inStock = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE checkin_at IS NULL AND company_id = ?');
            $stmt->execute([$cid]);
            $checkedOut = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tools WHERE missing_flag = 1 AND is_active = 1 AND company_id = ?');
            $stmt->execute([$cid]);
            $missing = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM transactions WHERE checkin_at IS NULL AND expected_return_at < NOW() AND company_id = ?'
            );
            $stmt->execute([$cid]);
            $overdue = (int) $stmt->fetchColumn();
        } elseif ($u['role'] === 'manager' && $u['warehouse_id']) {
            $wid = $u['warehouse_id'];
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(stock_qty),0) FROM tool_warehouse_assignment WHERE deleted_flag = 0 AND warehouse_id = ?'
            );
            $stmt->execute([$wid]);
            $inStock = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE checkin_at IS NULL AND warehouse_id = ?');
            $stmt->execute([$wid]);
            $checkedOut = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM tools t INNER JOIN tool_warehouse_assignment twa ON twa.tool_id = t.id
                 WHERE t.missing_flag = 1 AND t.is_active = 1 AND twa.warehouse_id = ? AND twa.deleted_flag = 0'
            );
            $stmt->execute([$wid]);
            $missing = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM transactions WHERE checkin_at IS NULL AND expected_return_at < NOW() AND warehouse_id = ?'
            );
            $stmt->execute([$wid]);
            $overdue = (int) $stmt->fetchColumn();
        }
        tm_json_response([
            'ok' => true,
            'stats' => [
                'in_stock' => $inStock,
                'checked_out' => $checkedOut,
                'missing' => $missing,
                'overdue_transactions' => $overdue,
            ],
        ]);
    }

    case 'activity_feed': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $limit = min(50, max(1, (int) ($input['limit'] ?? 20)));
        $sql = 'SELECT id, event_type, message, meta, created_at FROM activity_log WHERE 1=1';
        $params = [];
        if (($u['role'] === 'admin' || $u['role'] === 'manager') && $u['company_id']) {
            $sql .= ' AND (company_id IS NULL OR company_id = ?)';
            $params[] = $u['company_id'];
        }
        // Integer LIMIT in SQL — PDO + MySQL often errors on bound LIMIT ? (native prepared statements).
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $meta = null;
            if (!empty($row['meta'])) {
                $decoded = json_decode((string) $row['meta'], true);
                $meta = is_array($decoded) ? $decoded : null;
            }
            $items[] = [
                'id' => (int) $row['id'],
                'event_type' => $row['event_type'],
                'message' => $row['message'],
                'created_at' => $row['created_at'],
                'meta' => $meta,
            ];
        }
        tm_json_response(['ok' => true, 'items' => $items]);
    }

    case 'login': {
        $email = trim((string) ($input['email'] ?? $input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($email === '' || $password === '') {
            tm_json_response(['ok' => false, 'error' => 'Email and password required'], 400);
        }
        $stmt = $pdo->prepare(
            'SELECT id, email, password_hash, role, company_id, warehouse_id, name FROM users WHERE email = ? AND deleted_flag = 0 LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            tm_json_response(['ok' => false, 'error' => 'Invalid credentials'], 401);
        }
        $_SESSION['user_id'] = (int) $row['id'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['company_id'] = $row['company_id'] !== null ? (int) $row['company_id'] : null;
        $_SESSION['warehouse_id'] = $row['warehouse_id'] !== null ? (int) $row['warehouse_id'] : null;
        $_SESSION['user_email'] = $row['email'];
        $_SESSION['user_name'] = $row['name'];
        tm_json_response([
            'ok' => true,
            'email' => $row['email'],
            'username' => $row['email'],
            'name' => $row['name'],
            'role' => $row['role'],
            'company_id' => $_SESSION['company_id'],
            'warehouse_id' => $_SESSION['warehouse_id'],
        ]);
    }

    case 'logout': {
        tm_require_user();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        tm_json_response(['ok' => true]);
    }

    case 'me': {
        $u = tm_session_user();
        if (!$u) {
            tm_json_response(['ok' => false, 'logged_in' => false]);
        }
        tm_json_response([
            'ok' => true,
            'logged_in' => true,
            'email' => $u['email'],
            'username' => $u['email'],
            'name' => $u['name'],
            'role' => $u['role'],
            'company_id' => $u['company_id'],
            'warehouse_id' => $u['warehouse_id'],
        ]);
    }

    case 'companies_list': {
        tm_require_roles(['super_admin']);
        $rows = $pdo->query(
            'SELECT id, name, address, telephone, contact_name, contact_phone, created_at
             FROM companies WHERE deleted_flag = 0 ORDER BY name'
        )->fetchAll();
        tm_json_response(['ok' => true, 'companies' => $rows]);
    }

    case 'company_save': {
        tm_require_roles(['super_admin']);
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            tm_json_response(['ok' => false, 'error' => 'Name required'], 400);
        }
        $address = trim((string) ($input['address'] ?? ''));
        $telephone = trim((string) ($input['telephone'] ?? ''));
        $contactName = trim((string) ($input['contact_name'] ?? ''));
        $contactPhone = trim((string) ($input['contact_phone'] ?? ''));
        $address = $address === '' ? null : $address;
        $telephone = $telephone === '' ? null : $telephone;
        $contactName = $contactName === '' ? null : $contactName;
        $contactPhone = $contactPhone === '' ? null : $contactPhone;
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE companies SET name = ?, address = ?, telephone = ?, contact_name = ?, contact_phone = ? WHERE id = ?'
            )->execute([$name, $address, $telephone, $contactName, $contactPhone, $id]);
            tm_json_response(['ok' => true, 'id' => $id]);
        }
        $pdo->prepare(
            'INSERT INTO companies (name, address, telephone, contact_name, contact_phone) VALUES (?,?,?,?,?)'
        )->execute([$name, $address, $telephone, $contactName, $contactPhone]);
        tm_json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    case 'warehouses_list': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $filterCo = (int) ($input['company_id'] ?? 0);
        $sql = 'SELECT w.id, w.company_id, w.warehouse_name, w.warehouse_address, w.manager_name, w.created_at, c.name AS company_name,
                       (SELECT u.name FROM users u WHERE u.warehouse_id = w.id AND u.role = \'manager\' AND u.deleted_flag = 0 ORDER BY u.id ASC LIMIT 1) AS manager_user_name
                FROM warehouses w
                INNER JOIN companies c ON c.id = w.company_id
                WHERE w.deleted_flag = 0';
        $params = [];
        if ($u['role'] === 'super_admin' && $filterCo > 0) {
            $sql .= ' AND w.company_id = ?';
            $params[] = $filterCo;
        } elseif ($u['role'] === 'admin' && $u['company_id']) {
            $sql .= ' AND w.company_id = ?';
            $params[] = $u['company_id'];
        } elseif ($u['role'] === 'manager' && $u['warehouse_id']) {
            $sql .= ' AND w.id = ?';
            $params[] = $u['warehouse_id'];
        }
        $sql .= ' ORDER BY w.warehouse_name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $stored = isset($row['manager_name']) ? trim((string) $row['manager_name']) : '';
            $linked = isset($row['manager_user_name']) ? trim((string) $row['manager_user_name']) : '';
            unset($row['manager_user_name']);
            $row['manager_display'] = $stored !== '' ? $stored : ($linked !== '' ? $linked : null);
        }
        unset($row);
        tm_json_response(['ok' => true, 'warehouses' => $rows]);
    }

    case 'warehouse_save': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = trim((string) ($input['warehouse_name'] ?? ''));
        $addr = trim((string) ($input['warehouse_address'] ?? ''));
        $managerName = trim((string) ($input['manager_name'] ?? ''));
        $managerName = $managerName === '' ? null : $managerName;
        $cid = (int) ($input['company_id'] ?? 0);
        if ($u['role'] === 'admin') {
            $cid = (int) $u['company_id'];
        }
        if ($u['role'] === 'manager') {
            $wid = (int) ($u['warehouse_id'] ?? 0);
            if ($id < 1 || $wid < 1 || $id !== $wid) {
                tm_json_response(['ok' => false, 'error' => 'Forbidden'], 403);
            }
            if ($name === '') {
                tm_json_response(['ok' => false, 'error' => 'Name required'], 400);
            }
            $pdo->prepare(
                'UPDATE warehouses SET warehouse_name=?, warehouse_address=?, manager_name=? WHERE id=? AND deleted_flag=0'
            )->execute([$name, $addr === '' ? null : $addr, $managerName, $id]);
            tm_json_response(['ok' => true, 'id' => $id]);
        }
        if ($name === '' || $cid < 1) {
            tm_json_response(['ok' => false, 'error' => 'Name and company required'], 400);
        }
        $uid = $u['id'];
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT company_id FROM warehouses WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetchColumn();
            if ($existing === false) {
                tm_json_response(['ok' => false, 'error' => 'Not found'], 404);
            }
            if ($u['role'] === 'admin' && (int) $existing !== $cid) {
                tm_json_response(['ok' => false, 'error' => 'Forbidden'], 403);
            }
            $pdo->prepare(
                'UPDATE warehouses SET warehouse_name=?, warehouse_address=?, company_id=?, manager_name=? WHERE id=?'
            )->execute([$name, $addr === '' ? null : $addr, $cid, $managerName, $id]);
            tm_json_response(['ok' => true, 'id' => $id]);
        }
        $pdo->prepare(
            'INSERT INTO warehouses (company_id, warehouse_name, warehouse_address, manager_name, user_id) VALUES (?,?,?,?,?)'
        )->execute([$cid, $name, $addr === '' ? null : $addr, $managerName, $uid]);
        tm_json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    case 'users_list': {
        $u = tm_require_roles(['super_admin', 'admin']);
        $filterCo = (int) ($input['company_id'] ?? 0);
        if ($u['role'] === 'super_admin') {
            $sql = 'SELECT u.id, u.email, u.name, u.role, u.company_id, u.warehouse_id, u.created_at,
                           c.name AS company_name, w.warehouse_name
                    FROM users u
                    LEFT JOIN companies c ON c.id = u.company_id
                    LEFT JOIN warehouses w ON w.id = u.warehouse_id
                    WHERE u.deleted_flag = 0';
            $params = [];
            if ($filterCo > 0) {
                $sql .= ' AND (u.company_id = ? OR u.company_id IS NULL)';
                $params[] = $filterCo;
            }
            $sql .= ' ORDER BY u.role, u.email';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $cid = (int) $u['company_id'];
            $stmt = $pdo->prepare(
                'SELECT u.id, u.email, u.name, u.role, u.company_id, u.warehouse_id, u.created_at,
                        c.name AS company_name, w.warehouse_name
                 FROM users u
                 LEFT JOIN companies c ON c.id = u.company_id
                 LEFT JOIN warehouses w ON w.id = u.warehouse_id
                 WHERE u.deleted_flag = 0 AND u.company_id = ? AND u.role <> \'super_admin\'
                 ORDER BY u.role, u.email'
            );
            $stmt->execute([$cid]);
        }
        tm_json_response(['ok' => true, 'users' => $stmt->fetchAll()]);
    }

    case 'user_save': {
        $u = tm_require_roles(['super_admin', 'admin']);
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $email = trim((string) ($input['email'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $role = (string) ($input['role'] ?? '');
        $pass = (string) ($input['password'] ?? '');
        $cid = isset($input['company_id']) ? ($input['company_id'] === '' || $input['company_id'] === null ? null : (int) $input['company_id']) : null;
        $wid = isset($input['warehouse_id']) ? ($input['warehouse_id'] === '' || $input['warehouse_id'] === null ? null : (int) $input['warehouse_id']) : null;
        if ($email === '' || $name === '' || !in_array($role, ['admin', 'manager'], true)) {
            tm_json_response(['ok' => false, 'error' => 'Email, name, and role (admin or manager) required'], 400);
        }
        if ($u['role'] === 'admin') {
            $cid = $u['company_id'];
            if ($role === 'admin' && $u['id'] !== (int) ($input['id'] ?? 0)) {
                // admin can create another admin for company
            }
        }
        if ($u['role'] === 'super_admin' && $role === 'manager' && ($wid === null || $cid === null)) {
            tm_json_response(['ok' => false, 'error' => 'Manager requires company and warehouse'], 400);
        }
        if ($role === 'manager' && ($wid === null || $cid === null)) {
            tm_json_response(['ok' => false, 'error' => 'Manager requires warehouse'], 400);
        }
        if ($role === 'admin') {
            $wid = null;
            if ($cid === null || $cid < 1) {
                tm_json_response(['ok' => false, 'error' => 'Company admin requires company_id'], 400);
            }
        }
        $hash = $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : null;
        if ($id > 0) {
            if ($hash) {
                $pdo->prepare(
                    'UPDATE users SET email=?, name=?, role=?, company_id=?, warehouse_id=?, password_hash=? WHERE id=?'
                )->execute([$email, $name, $role, $cid, $wid, $hash, $id]);
            } else {
                $pdo->prepare(
                    'UPDATE users SET email=?, name=?, role=?, company_id=?, warehouse_id=? WHERE id=?'
                )->execute([$email, $name, $role, $cid, $wid, $id]);
            }
            tm_json_response(['ok' => true, 'id' => $id]);
        }
        if ($pass === '') {
            tm_json_response(['ok' => false, 'error' => 'Password required for new user'], 400);
        }
        $pdo->prepare(
            'INSERT INTO users (company_id, warehouse_id, role, email, password_hash, name) VALUES (?,?,?,?,?,?)'
        )->execute([$cid, $wid, $role, $email, password_hash($pass, PASSWORD_DEFAULT), $name]);
        tm_json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    case 'user_delete': {
        $u = tm_require_roles(['super_admin', 'admin']);
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1 || $id === $u['id']) {
            tm_json_response(['ok' => false, 'error' => 'Invalid'], 400);
        }
        $stmt = $pdo->prepare('SELECT company_id, role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            tm_json_response(['ok' => false, 'error' => 'Not found'], 404);
        }
        if ($row['role'] === 'super_admin') {
            tm_json_response(['ok' => false, 'error' => 'Cannot delete'], 403);
        }
        if ($u['role'] === 'admin' && (int) $row['company_id'] !== (int) $u['company_id']) {
            tm_json_response(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $pdo->prepare('UPDATE users SET deleted_flag = 1 WHERE id = ?')->execute([$id]);
        tm_json_response(['ok' => true]);
    }

    case 'categories_list': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $filterCo = (int) ($input['company_id'] ?? 0);
        if ($u['role'] === 'admin' || $u['role'] === 'manager') {
            $filterCo = (int) $u['company_id'];
        }
        if ($filterCo < 1) {
            tm_json_response(['ok' => false, 'error' => 'company_id required'], 400);
        }
        $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE company_id = ? ORDER BY name');
        $stmt->execute([$filterCo]);
        tm_json_response(['ok' => true, 'categories' => $stmt->fetchAll()]);
    }

    case 'category_save': {
        $u = tm_require_roles(['super_admin', 'admin']);
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = trim((string) ($input['name'] ?? ''));
        $cid = (int) ($input['company_id'] ?? 0);
        if ($u['role'] === 'admin') {
            $cid = (int) $u['company_id'];
        }
        if ($name === '' || $cid < 1) {
            tm_json_response(['ok' => false, 'error' => 'Name and company required'], 400);
        }
        if ($id > 0) {
            $pdo->prepare('UPDATE categories SET name = ? WHERE id = ? AND company_id = ?')->execute([$name, $id, $cid]);
            tm_json_response(['ok' => true, 'id' => $id]);
        }
        $pdo->prepare('INSERT INTO categories (company_id, name) VALUES (?, ?)')->execute([$cid, $name]);
        tm_json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    case 'tools_list': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $filterCo = (int) ($input['company_id'] ?? 0);
        if ($u['role'] === 'admin' || $u['role'] === 'manager') {
            $filterCo = (int) $u['company_id'];
        }
        if ($u['role'] === 'super_admin' && $filterCo < 1) {
            tm_json_response(['ok' => false, 'error' => 'company_id required'], 400);
        }
        if ($u['role'] === 'manager') {
            $wid = (int) $u['warehouse_id'];
            $stmt = $pdo->prepare(
                'SELECT t.id, t.company_id, t.name, t.barcode, t.nfc_id, t.description, t.image,
                        t.missing_flag, t.is_active, t.created_at, c.name AS category_name, c.id AS category_id,
                        twa.stock_qty, twa.id AS assignment_id, w.warehouse_name
                 FROM tools t
                 INNER JOIN tool_warehouse_assignment twa ON twa.tool_id = t.id AND twa.warehouse_id = ? AND twa.deleted_flag = 0
                 INNER JOIN warehouses w ON w.id = twa.warehouse_id AND w.deleted_flag = 0
                 LEFT JOIN categories c ON c.id = t.category_id AND c.company_id = t.company_id
                 WHERE t.company_id = ?
                 ORDER BY t.name'
            );
            $stmt->execute([$wid, $filterCo]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT t.id, t.company_id, t.name, t.barcode, t.nfc_id, t.description, t.image,
                        t.missing_flag, t.is_active, t.created_at, c.name AS category_name, c.id AS category_id
                 FROM tools t
                 LEFT JOIN categories c ON c.id = t.category_id AND c.company_id = t.company_id
                 WHERE t.company_id = ?
                 ORDER BY t.name'
            );
            $stmt->execute([$filterCo]);
        }
        $tools = $stmt->fetchAll();
        foreach ($tools as &$t) {
            $mf = (int) ($t['missing_flag'] ?? 0);
            $ia = (int) ($t['is_active'] ?? 0);
            $t['missing_flag'] = $mf;
            $t['is_active'] = $ia;
            if ($mf === 1) {
                $t['catalog_status'] = 'missing';
            } elseif ($ia !== 1) {
                $t['catalog_status'] = 'inactive';
            } else {
                $t['catalog_status'] = 'ok';
            }
        }
        unset($t);
        if ($u['role'] !== 'manager') {
            foreach ($tools as &$t) {
                $st = $pdo->prepare(
                    'SELECT twa.id, twa.warehouse_id, twa.stock_qty, w.warehouse_name
                     FROM tool_warehouse_assignment twa
                     INNER JOIN warehouses w ON w.id = twa.warehouse_id AND w.deleted_flag = 0
                     WHERE twa.tool_id = ? AND twa.deleted_flag = 0'
                );
                $st->execute([(int) $t['id']]);
                $t['assignments'] = $st->fetchAll();
            }
            unset($t);
        }
        tm_json_response(['ok' => true, 'tools' => $tools]);
    }

    case 'upload_tool_image': {
        tm_require_roles(['super_admin', 'admin', 'manager']);
        if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
            tm_json_response(['ok' => false, 'error' => 'No image file'], 400);
        }
        $f = $_FILES['image'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            tm_json_response(['ok' => false, 'error' => 'Upload failed'], 400);
        }
        if (($f['size'] ?? 0) > 2 * 1024 * 1024) {
            tm_json_response(['ok' => false, 'error' => 'Image must be 2MB or smaller'], 400);
        }
        $tmp = (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            tm_json_response(['ok' => false, 'error' => 'Invalid upload'], 400);
        }
        $info = @getimagesize($tmp);
        if ($info === false) {
            tm_json_response(['ok' => false, 'error' => 'File is not a valid image'], 400);
        }
        $mime = $info['mime'] ?? '';
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($extMap[$mime])) {
            tm_json_response(['ok' => false, 'error' => 'Use JPEG, PNG, WebP, or GIF'], 400);
        }
        $ext = $extMap[$mime];
        $dir = __DIR__ . '/uploads/tools';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            tm_json_response(['ok' => false, 'error' => 'Cannot create uploads directory'], 500);
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!@move_uploaded_file($tmp, $dest)) {
            tm_json_response(['ok' => false, 'error' => 'Could not save image'], 500);
        }
        tm_json_response(['ok' => true, 'path' => 'uploads/tools/' . $name]);
    }

    case 'tool_save': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = trim((string) ($input['name'] ?? ''));
        $barcode = trim((string) ($input['barcode'] ?? ''));
        $nfc = trim((string) ($input['nfc_id'] ?? ''));
        $nfc = $nfc === '' ? null : $nfc;
        $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (int) $input['category_id'] : null;
        $description = trim((string) ($input['description'] ?? ''));
        $missingFlag = !empty($input['missing_flag']) ? 1 : 0;
        $isActive = isset($input['is_active']) ? (!empty($input['is_active']) ? 1 : 0) : 1;
        $stockQty = isset($input['stock_qty']) ? max(0, (int) $input['stock_qty']) : null;
        $whForStock = isset($input['warehouse_id']) ? (int) $input['warehouse_id'] : 0;

        $cid = (int) ($input['company_id'] ?? 0);
        if ($u['role'] === 'admin' || $u['role'] === 'manager') {
            $cid = (int) $u['company_id'];
        }
        if ($u['role'] === 'manager') {
            $whForStock = (int) $u['warehouse_id'];
        }
        if ($name === '' || $barcode === '') {
            tm_json_response(['ok' => false, 'error' => 'Name and barcode required'], 400);
        }
        if ($cid < 1) {
            tm_json_response(['ok' => false, 'error' => 'company_id required'], 400);
        }

        $imageProvided = array_key_exists('image', $input);
        $newImagePath = null;
        $clearImage = false;
        if ($imageProvided) {
            $imgIn = $input['image'];
            if ($imgIn === null || $imgIn === '') {
                $clearImage = true;
            } else {
                $newImagePath = trim((string) $imgIn);
                if (!tm_is_safe_tool_image_path($newImagePath)) {
                    tm_json_response(['ok' => false, 'error' => 'Invalid image path'], 400);
                }
                if (!is_file(tm_tool_image_full_path($newImagePath))) {
                    tm_json_response(['ok' => false, 'error' => 'Image file not found'], 400);
                }
            }
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT company_id, image FROM tools WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row || (int) $row['company_id'] !== $cid) {
                tm_json_response(['ok' => false, 'error' => 'Not found'], 404);
            }
            if ($u['role'] === 'manager') {
                $chk = $pdo->prepare(
                    'SELECT 1 FROM tool_warehouse_assignment WHERE tool_id = ? AND warehouse_id = ? AND deleted_flag = 0'
                );
                $chk->execute([$id, $u['warehouse_id']]);
                if (!$chk->fetch()) {
                    tm_json_response(['ok' => false, 'error' => 'Forbidden'], 403);
                }
            }
            $oldImage = !empty($row['image']) ? (string) $row['image'] : null;
            if (!$imageProvided) {
                $finalImage = $oldImage;
            } elseif ($clearImage) {
                if ($oldImage) {
                    tm_delete_tool_image_file($oldImage);
                }
                $finalImage = null;
            } else {
                if ($oldImage && $oldImage !== $newImagePath) {
                    tm_delete_tool_image_file($oldImage);
                }
                $finalImage = $newImagePath;
            }
            $pdo->prepare(
                'UPDATE tools SET name=?, barcode=?, nfc_id=?, category_id=?, description=?, image=?, missing_flag=?, is_active=? WHERE id=? AND company_id=?'
            )->execute([$name, $barcode, $nfc, $categoryId, $description, $finalImage, $missingFlag, $isActive, $id, $cid]);

            if ($stockQty !== null && $whForStock > 0) {
                $pdo->prepare(
                    'INSERT INTO tool_warehouse_assignment (tool_id, company_id, warehouse_id, stock_qty)
                     VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE stock_qty = VALUES(stock_qty), deleted_flag = 0, updated_at = CURRENT_TIMESTAMP'
                )->execute([$id, $cid, $whForStock, $stockQty]);
            }
            tm_json_response(['ok' => true, 'id' => $id]);
        }

        if ($stockQty === null || $whForStock < 1) {
            tm_json_response(['ok' => false, 'error' => 'warehouse_id and stock_qty required for new tool'], 400);
        }
        $finalInsertImage = null;
        if ($imageProvided && !$clearImage && $newImagePath !== null) {
            $finalInsertImage = $newImagePath;
        }
        $pdo->prepare(
            'INSERT INTO tools (company_id, name, barcode, nfc_id, category_id, description, image, missing_flag, is_active)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$cid, $name, $barcode, $nfc, $categoryId, $description, $finalInsertImage, $missingFlag, $isActive]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO tool_warehouse_assignment (tool_id, company_id, warehouse_id, stock_qty) VALUES (?,?,?,?)'
        )->execute([$newId, $cid, $whForStock, $stockQty]);
        tm_json_response(['ok' => true, 'id' => $newId]);
    }

    case 'tool_delete': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            tm_json_response(['ok' => false, 'error' => 'Invalid id'], 400);
        }
        $stmt = $pdo->prepare('SELECT company_id, image FROM tools WHERE id = ?');
        $stmt->execute([$id]);
        $tool = $stmt->fetch();
        if (!$tool) {
            tm_json_response(['ok' => false, 'error' => 'Not found'], 404);
        }
        if ($u['role'] === 'manager') {
            $chk = $pdo->prepare(
                'SELECT COUNT(*) FROM tool_warehouse_assignment WHERE tool_id = ? AND warehouse_id <> ? AND deleted_flag = 0'
            );
            $chk->execute([$id, $u['warehouse_id']]);
            if ((int) $chk->fetchColumn() > 0) {
                tm_json_response(['ok' => false, 'error' => 'Tool exists in other warehouses — ask company admin'], 403);
            }
        }
        if ($u['role'] !== 'super_admin') {
            if ((int) $tool['company_id'] !== (int) $u['company_id']) {
                tm_json_response(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE tool_id = ? AND checkin_at IS NULL');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            tm_json_response(['ok' => false, 'error' => 'Cannot delete: active checkouts'], 409);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE tool_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            tm_json_response(['ok' => false, 'error' => 'Cannot delete: has transaction history'], 409);
        }
        $oldImage = !empty($tool['image']) ? (string) $tool['image'] : null;
        $pdo->prepare('DELETE FROM tool_warehouse_assignment WHERE tool_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM tools WHERE id = ?')->execute([$id]);
        if ($oldImage) {
            tm_delete_tool_image_file($oldImage);
        }
        tm_json_response(['ok' => true]);
    }

    case 'operators_list': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        [$scopeSql, $scopeParams] = tm_sql_scope_operators($u);
        $sql = "SELECT o.id, o.name, o.employee_id, o.department, o.company_id, o.warehouse_id, o.created_at,
                       c.name AS company_name, w.warehouse_name
                FROM operators o
                LEFT JOIN companies c ON c.id = o.company_id
                LEFT JOIN warehouses w ON w.id = o.warehouse_id
                WHERE {$scopeSql}";
        $stmt = $pdo->prepare($sql . ' ORDER BY o.name');
        $stmt->execute($scopeParams);
        tm_json_response(['ok' => true, 'operators' => $stmt->fetchAll()]);
    }

    case 'operator_save': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = trim((string) ($input['name'] ?? ''));
        $emp = trim((string) ($input['employee_id'] ?? ''));
        $dept = trim((string) ($input['department'] ?? ''));
        $dept = $dept === '' ? null : $dept;
        $cid = (int) ($input['company_id'] ?? 0);
        $wid = (int) ($input['warehouse_id'] ?? 0);
        if ($u['role'] === 'super_admin') {
            // company_id and warehouse_id from request
        } elseif ($u['role'] === 'manager') {
            $cid = (int) $u['company_id'];
            $wid = (int) $u['warehouse_id'];
        } else {
            $cid = (int) $u['company_id'];
        }
        if ($name === '' || $emp === '' || $cid < 1 || $wid < 1) {
            tm_json_response(['ok' => false, 'error' => 'Name, employee ID, company, warehouse required'], 400);
        }
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE operators SET name=?, employee_id=?, department=?, company_id=?, warehouse_id=? WHERE id=?'
            )->execute([$name, $emp, $dept, $cid, $wid, $id]);
            tm_json_response(['ok' => true, 'id' => $id]);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO operators (company_id, warehouse_id, name, employee_id, department) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$cid, $wid, $name, $emp, $dept]);
        tm_json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }

    case 'operator_delete': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            tm_json_response(['ok' => false, 'error' => 'Invalid id'], 400);
        }
        $stmt = $pdo->prepare('SELECT company_id, warehouse_id FROM operators WHERE id = ?');
        $stmt->execute([$id]);
        $op = $stmt->fetch();
        if (!$op) {
            tm_json_response(['ok' => false, 'error' => 'Not found'], 404);
        }
        if ($u['role'] === 'manager' && (int) $op['warehouse_id'] !== (int) $u['warehouse_id']) {
            tm_json_response(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE operator_id = ? AND checkin_at IS NULL');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            tm_json_response(['ok' => false, 'error' => 'Cannot delete: active checkouts'], 409);
        }
        $pdo->prepare('DELETE FROM operators WHERE id = ?')->execute([$id]);
        tm_json_response(['ok' => true]);
    }

    case 'history': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        [$wSql, $wParams] = tm_sql_scope_transactions($u);
        $op = isset($input['operator_id']) ? (int) $input['operator_id'] : 0;
        $tool = isset($input['tool_id']) ? (int) $input['tool_id'] : 0;
        $from = trim((string) ($input['date_from'] ?? ''));
        $to = trim((string) ($input['date_to'] ?? ''));
        $sql = 'SELECT tr.id, tr.checkout_at, tr.checkin_at, tr.expected_return_at, tr.warehouse_id,
                       w.warehouse_name,
                       o.name AS operator_name, o.employee_id,
                       tl.name AS tool_name, tl.barcode
                FROM transactions tr
                INNER JOIN operators o ON o.id = tr.operator_id
                INNER JOIN tools tl ON tl.id = tr.tool_id
                INNER JOIN warehouses w ON w.id = tr.warehouse_id
                WHERE ' . $wSql;
        $params = $wParams;
        if ($op > 0) {
            $sql .= ' AND tr.operator_id = ?';
            $params[] = $op;
        }
        if ($tool > 0) {
            $sql .= ' AND tr.tool_id = ?';
            $params[] = $tool;
        }
        if ($from !== '') {
            $sql .= ' AND tr.checkout_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $sql .= ' AND tr.checkout_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        $sql .= ' ORDER BY tr.checkout_at DESC LIMIT 500';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        tm_json_response(['ok' => true, 'transactions' => $stmt->fetchAll()]);
    }

    case 'report_checked_out_by_operator': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        [$wSql, $wParams] = tm_sql_scope_transactions($u);
        $sql = "SELECT o.id, o.name, o.employee_id, w.warehouse_name, COUNT(tr.id) AS open_count
                FROM operators o
                INNER JOIN warehouses w ON w.id = o.warehouse_id
                INNER JOIN transactions tr ON tr.operator_id = o.id AND tr.checkin_at IS NULL AND {$wSql}
                GROUP BY o.id, o.name, o.employee_id, w.warehouse_name
                ORDER BY open_count DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($wParams);
        tm_json_response(['ok' => true, 'rows' => $stmt->fetchAll()]);
    }

    case 'report_overdue': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        [$wSql, $wParams] = tm_sql_scope_transactions($u);
        $sql = "SELECT tr.id, tr.checkout_at, tr.expected_return_at,
                       o.name AS operator_name, o.employee_id,
                       tl.name AS tool_name, tl.barcode, w.warehouse_name
                FROM transactions tr
                INNER JOIN operators o ON o.id = tr.operator_id
                INNER JOIN tools tl ON tl.id = tr.tool_id
                INNER JOIN warehouses w ON w.id = tr.warehouse_id
                WHERE tr.checkin_at IS NULL AND tr.expected_return_at < NOW() AND {$wSql}";
        $stmt = $pdo->prepare($sql . ' ORDER BY tr.expected_return_at ASC');
        $stmt->execute($wParams);
        tm_json_response(['ok' => true, 'rows' => $stmt->fetchAll()]);
    }

    case 'report_long_outstanding': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        global $config;
        $days = (int) ($config['long_outstanding_days'] ?? 30);
        [$wSql, $wParams] = tm_sql_scope_transactions($u);
        $sql = "SELECT tr.id, tr.checkout_at, tr.expected_return_at,
                    o.name AS operator_name, o.employee_id,
                    tl.name AS tool_name, tl.barcode, w.warehouse_name
             FROM transactions tr
             INNER JOIN operators o ON o.id = tr.operator_id
             INNER JOIN tools tl ON tl.id = tr.tool_id
             INNER JOIN warehouses w ON w.id = tr.warehouse_id
             WHERE tr.checkin_at IS NULL AND tr.checkout_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND {$wSql}";
        $params = array_merge([$days], $wParams);
        $stmt = $pdo->prepare($sql . ' ORDER BY tr.checkout_at ASC');
        $stmt->execute($params);
        tm_json_response(['ok' => true, 'days_threshold' => $days, 'rows' => $stmt->fetchAll()]);
    }

    case 'report_missing_tools': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $cid = $u['company_id'];
        if ($u['role'] === 'super_admin') {
            $sql = 'SELECT id, name, barcode, nfc_id, description, updated_at FROM tools WHERE missing_flag = 1 AND is_active = 1 ORDER BY name';
            $rows = $pdo->query($sql)->fetchAll();
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, name, barcode, nfc_id, description, updated_at FROM tools WHERE missing_flag = 1 AND is_active = 1 AND company_id = ? ORDER BY name'
            );
            $stmt->execute([$cid]);
            $rows = $stmt->fetchAll();
        }
        tm_json_response(['ok' => true, 'rows' => $rows]);
    }

    case 'report_most_used_tools': {
        $u = tm_require_roles(['super_admin', 'admin', 'manager']);
        $limit = min(50, max(1, (int) ($input['limit'] ?? 20)));
        [$wSql, $wParams] = tm_sql_scope_transactions($u);
        $sql = "SELECT tl.id, tl.name, tl.barcode, COUNT(tr.id) AS checkout_count
             FROM transactions tr
             INNER JOIN tools tl ON tl.id = tr.tool_id
             WHERE {$wSql}
             GROUP BY tl.id, tl.name, tl.barcode
             ORDER BY checkout_count DESC
             LIMIT " . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($wParams);
        tm_json_response(['ok' => true, 'rows' => $stmt->fetchAll()]);
    }

    default:
        tm_json_response(['ok' => false, 'error' => 'Unknown action'], 400);
}
