<?php
header('Content-Type: application/json');
$diag = [
    'php_version' => PHP_VERSION,
    'disabled_functions' => ini_get('disable_functions'),
    'exec_enabled' => function_exists('exec'),
    'shell_exec_enabled' => function_exists('shell_exec'),
    'writable_logs' => is_writable(__DIR__ . '/logs'),
    'writable_state' => is_writable(__DIR__ . '/state.json'),
    'current_user' => get_current_user(),
    'script_path' => __DIR__ . "/cron_import.php",
    'script_exists' => file_exists(__DIR__ . "/cron_import.php")
];
echo json_encode($diag, JSON_PRETTY_PRINT);
