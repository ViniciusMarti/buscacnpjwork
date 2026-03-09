<?php
require_once __DIR__ . '/config/db.php';
try {
    $db = getDB();
    echo "=== DB PATH: " . DB_PATH . " ===\n";
    if (!file_exists(DB_PATH)) {
        die("ERRO: Banco não encontrado no caminho " . DB_PATH . "\n");
    }
    
    echo "\n=== TABELAS ===\n";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);

    if (in_array('dados_cnpj', $tables)) {
        echo "\n=== ÍNDICES (dados_cnpj) ===\n";
        $indices = $db->query("PRAGMA index_list(dados_cnpj)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indices as $idx) {
            echo "Index: {$idx['name']}\n";
            print_r($db->query("PRAGMA index_info({$idx['name']})")->fetchAll(PDO::FETCH_ASSOC));
        }
        
        echo "\n=== CONTAGEM (SP) ===\n";
        $start = microtime(true);
        $count = $db->query("SELECT COUNT(*) FROM dados_cnpj WHERE uf = 'SP' AND situacao = 'ATIVA'")->fetchColumn();
        $end = microtime(true);
        echo "Contagem SP: $count (Tempo: " . round($end - $start, 4) . "s)\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
