<?php
// cron_import.php - Pipeline ETL Entry Point

require_once 'import_worker.php';

// Set project root
chdir(__DIR__);

// Ensure error logging to file instead of stdout
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/import_errors.log');

try {
    // Key file path (User must provide this)
    $keyFile = __DIR__ . '/buscacnpj-490113-6da549c016bb.json';
    
    if (!file_exists($keyFile)) {
        throw new Exception("Google Credentials file ($keyFile) missing. Please upload it to /importador/.");
    }

    $worker = new ImportWorker($keyFile);
    echo "Starting import process..." . PHP_EOL;
    $worker->run();
    echo "Import completed successfully." . PHP_EOL;

} catch (Exception $e) {
    file_put_contents('logs/import_errors.log', date('[Y-m-d H:i:s] ') . "CRITICAL: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
