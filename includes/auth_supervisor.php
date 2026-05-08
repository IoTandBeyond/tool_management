<?php
declare(strict_types=1);

global $config;
if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session_name'] ?? 'tm_supervisor');
    session_start();
}
