<?php
/**
 * Copy to config.local.php and override (config.local.php is gitignored if you use git).
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'tool_management',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    /** Hours until a checkout is considered overdue */
    'expected_return_hours' => (int) (getenv('EXPECTED_RETURN_HOURS') ?: 48),
    /** Days an open checkout must exceed to appear in "long outstanding" report */
    'long_outstanding_days' => (int) (getenv('LONG_OUTSTANDING_DAYS') ?: 30),
    'session_name' => 'tm_user',
];
