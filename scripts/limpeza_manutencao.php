<?php
/**
 * Script de Manutenção Automática: Limpeza de Cache e Logs
 */
require_once __DIR__ . '/../config/db.php';

// Diretórios para limpar
$dirs = [
    __DIR__ . '/../cache/rankings',
    __DIR__ . '/../cache/cidades',
    __DIR__ . '/../scripts', // Logs dentro de scripts
];

$max_age_days = 30; // 30 dias de cache
$max_log_size = 10 * 1024 * 1024; // 10MB para logs

$log_maintenance = __DIR__ . '/manutencao.log';
$timestamp = date('Y-m-d H:i:s');

file_put_contents($log_maintenance, "[$timestamp] Iniciando manutenção...\n", FILE_APPEND);

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;

    echo "Limpando diretório: $dir\n";
    $files = glob($dir . '/*');

    foreach ($files as $file) {
        if (!is_file($file)) continue;

        $filename = basename($file);
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        // 1. Limpar arquivos de Cache (.json) antigos
        if ($ext === 'json') {
            if (time() - filemtime($file) > ($max_age_days * 86400)) {
                unlink($file);
                file_put_contents($log_maintenance, "[$timestamp] Removido cache antigo: $filename\n", FILE_APPEND);
            }
        }

        // 2. Rotacionar logs (.log) muito grandes
        if ($ext === 'log') {
            if (filesize($file) > $max_log_size) {
                // Mantém as últimas 1000 linhas e apaga o resto
                $lines = file($file);
                $trimmed = array_slice($lines, -1000);
                file_put_contents($file, implode("", $trimmed));
                file_put_contents($log_maintenance, "[$timestamp] Log rotacionado (truncado): $filename\n", FILE_APPEND);
            }
        }
    }
}

file_put_contents($log_maintenance, "[$timestamp] Manutenção concluída.\n", FILE_APPEND);
echo "Sucesso! Limpeza realizada.\n";
