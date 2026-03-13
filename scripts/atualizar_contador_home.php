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
    echo "Consultando total de empresas em todos os bancos MySQL...\n";
    
    // Consulta real agregada - Usamos estabelecimentos para contar todas as unidades
    $res = aggregateDistributed("SELECT COUNT(*) as total FROM estabelecimentos", []);

    $total = $res['total'] ?: 0;


    if ($total > 0) {
        file_put_contents($cache_file, $total);
        echo "Sucesso! Total de $total empresas salvo em cache.\n";
    } else {
        echo "Aviso: A consulta retornou 0. Cache não atualizado.\n";
    }

} catch (Exception $e) {
    die("Erro ao atualizar contador: " . $e->getMessage() . "\n");
}
