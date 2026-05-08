<?php
declare(strict_types=1);

function tm_json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/** @return array{id:int,role:string,company_id:?int,warehouse_id:?int,email:string,name:string}|null */
function tm_session_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'role' => (string) ($_SESSION['role'] ?? ''),
        'company_id' => isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : null,
        'warehouse_id' => isset($_SESSION['warehouse_id']) ? (int) $_SESSION['warehouse_id'] : null,
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'name' => (string) ($_SESSION['user_name'] ?? ''),
    ];
}

/** @return array{id:int,role:string,company_id:?int,warehouse_id:?int,email:string,name:string} */
function tm_require_user(): array
{
    $u = tm_session_user();
    if (!$u) {
        tm_json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
    return $u;
}

/**
 * @param string[] $roles
 * @return array{id:int,role:string,company_id:?int,warehouse_id:?int,email:string,name:string}
 */
function tm_require_roles(array $roles): array
{
    $u = tm_require_user();
    if (!in_array($u['role'], $roles, true)) {
        tm_json_response(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    return $u;
}

function tm_require_supervisor(): void
{
    tm_require_roles(['super_admin', 'admin', 'manager']);
}

function tm_log_activity(string $type, string $message, ?array $meta = null, ?int $companyId = null): void
{
    $pdo = tm_db();
    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (company_id, event_type, message, meta) VALUES (?, ?, ?, ?)'
    );
    $metaJson = $meta === null ? null : json_encode($meta, JSON_THROW_ON_ERROR);
    $stmt->execute([$companyId, $type, $message, $metaJson]);
}

function tm_expected_return_at(): string
{
    global $config;
    $hours = (int) ($config['expected_return_hours'] ?? 48);
    return (new DateTimeImmutable("+{$hours} hours"))->format('Y-m-d H:i:s');
}

/** Relative path under project root, e.g. uploads/tools/abc.png */
function tm_is_safe_tool_image_path(string $path): bool
{
    return (bool) preg_match('#^uploads/tools/[a-zA-Z0-9][a-zA-Z0-9._\-]{0,240}$#', $path);
}

function tm_tool_image_full_path(string $relative): string
{
    return dirname(__DIR__) . '/' . $relative;
}

function tm_delete_tool_image_file(?string $relative): void
{
    if ($relative === null || $relative === '') {
        return;
    }
    if (!tm_is_safe_tool_image_path($relative)) {
        return;
    }
    $full = tm_tool_image_full_path($relative);
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Find a tool in a company by barcode or NFC scan code.
 *
 * @return array<string, mixed>|null
 */
function tm_tool_by_company_scan_code(PDO $pdo, int $companyId, string $code): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, barcode, nfc_id, missing_flag, is_active FROM tools
         WHERE company_id = ? AND (barcode = ? OR (nfc_id IS NOT NULL AND nfc_id = ?)) LIMIT 1'
    );
    $stmt->execute([$companyId, $code, $code]);
    $row = $stmt->fetch();
    return $row ?: null;
}
