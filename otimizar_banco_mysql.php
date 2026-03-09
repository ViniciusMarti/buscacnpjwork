<?php
/**
 * Script para otimização do banco de dados MySQL
 * Adiciona índices essenciais para que as buscas e rankings fiquem instantâneos.
 * 
 * INSTRUÇÕES: 
 * 1. Suba este arquivo para a pasta public_html/
 * 2. Acesse pelo navegador: seu-site.com.br/otimizar_banco_mysql.php
 */

require_once __DIR__ . '/config/db.php';

// Aumentar o tempo limite de execução
set_time_limit(1800); // 30 minutos
ini_set('memory_limit', '512M');

echo "<h1>Otimizador de Banco de Dados MySQL</h1>";
echo "<p>Iniciando criação de índices... Por favor, não feche esta aba.</p>";
flush();

try {
    $connections = getAllConnections();
    
    foreach ($connections as $dbName => $db) {
        echo "<h2>Processando Banco: $dbName</h2>";
        
        // Lista de índices cruciais para MySQL
        $queries = [
            "ALTER TABLE dados_cnpj MODIFY COLUMN cnpj VARCHAR(14)",
            "ALTER TABLE dados_cnpj MODIFY COLUMN uf CHAR(2)",
            "ALTER TABLE dados_cnpj MODIFY COLUMN situacao VARCHAR(20)",
            "ALTER TABLE dados_cnpj MODIFY COLUMN capital_social DECIMAL(18,2)",
            
            "CREATE INDEX idx_cnpj ON dados_cnpj(cnpj)",
            "CREATE INDEX idx_uf ON dados_cnpj(uf)",
            "CREATE INDEX idx_municipio ON dados_cnpj(municipio)",
            "CREATE INDEX idx_capital ON dados_cnpj(capital_social)",
            "CREATE INDEX idx_situacao ON dados_cnpj(situacao)",
            
            // ÍNDICES COMPOSTOS PARA RANKINGS (MUITO IMPORTANTES)
            "CREATE INDEX idx_ranking_uf ON dados_cnpj(uf, situacao, capital_social)",
            "CREATE INDEX idx_ranking_br ON dados_cnpj(situacao, capital_social)"
        ];

        foreach ($queries as $sql) {
            echo "Executando: <code>$sql</code> ... ";
            flush();
            try {
                $start = microtime(true);
                $db->exec($sql);
                $end = microtime(true);
                echo "<span style='color:green'>Sucesso (" . round($end - $start, 2) . "s)</span><br>";
            } catch (Exception $e) {
                echo "<span style='color:orange'>Aviso: " . $e->getMessage() . "</span><br>";
            }
            flush();
        }
    }

    echo "<h2>✅ Otimização Concluída em todos os bancos!</h2>";
    echo "<p>Agora as buscas por CNPJ e os Rankings no MySQL serão rápidos em toda a base.</p>";

} catch (Exception $e) {

    echo "<h2>❌ Erro Crítico:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
