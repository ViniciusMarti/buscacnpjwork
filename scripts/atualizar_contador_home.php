<?php
/**
 * Script para atualizar o cache do contador total de empresas.
 * Deve ser executado via Cron Job 1 ou 2 vezes por dia.
 */

require_once __DIR__ . '/../config/db.php';

$cache_file = __DIR__ . '/../cache/total_empresas.txt';
$cache_dir = dirname($cache_file);

if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

try {
    $db = getDB();
    echo "Consultando total de empresas no MySQL...\n";
    
    // Consulta real (pode demorar alguns segundos, por isso rodamos via script de cache)
    $stmt = $db->query("SELECT COUNT(*) FROM dados_cnpj");
    $total = $stmt->fetchColumn();

    if ($total > 0) {
        file_put_contents($cache_file, $total);
        echo "Sucesso! Total de $total empresas salvo em cache.\n";
    } else {
        echo "Aviso: A consulta retornou 0. Cache não atualizado.\n";
    }

} catch (Exception $e) {
    die("Erro ao atualizar contador: " . $e->getMessage() . "\n");
}
