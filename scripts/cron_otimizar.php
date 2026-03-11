<?php
/**
 * Script de Otimização Silenciosa para Cron Job
 */
require_once __DIR__ . '/../config/db.php';

// Configurações de tempo e memória para processos longos
set_time_limit(1800); 
ini_set('memory_limit', '512M');

$log_file = __DIR__ . '/otimizacao.log';
$timestamp = date('Y-m-d H:i:s');

file_put_contents($log_file, "[$timestamp] Iniciando otimização via Cron...\n", FILE_APPEND);

try {
    $connections = getAllConnections();
    
    foreach ($connections as $dbName => $db) {
        $queries = [
            // Conversão de tipos para garantir performance
            "ALTER TABLE dados_cnpj MODIFY COLUMN cnpj VARCHAR(14)",
            "ALTER TABLE dados_cnpj MODIFY COLUMN uf CHAR(2)",
            "ALTER TABLE dados_cnpj MODIFY COLUMN situacao VARCHAR(20)",
            "ALTER TABLE dados_cnpj MODIFY COLUMN capital_social DECIMAL(18,2)",
            
            // Índices básicos
            "CREATE INDEX idx_cnpj ON dados_cnpj(cnpj)",
            "CREATE INDEX idx_uf ON dados_cnpj(uf)",
            "CREATE INDEX idx_municipio ON dados_cnpj(municipio)",
            "CREATE INDEX idx_capital ON dados_cnpj(capital_social)",
            "CREATE INDEX idx_situacao ON dados_cnpj(situacao)",
            
            // Índices compostos para rankings
            "CREATE INDEX idx_ranking_uf ON dados_cnpj(uf, situacao, capital_social)",
            "CREATE INDEX idx_ranking_br ON dados_cnpj(situacao, capital_social)"
        ];

        foreach ($queries as $sql) {
            try {
                $db->exec($sql);
                file_put_contents($log_file, "[$timestamp] [$dbName] Sucesso: " . substr($sql, 0, 50) . "...\n", FILE_APPEND);
            } catch (Exception $e) {
                // Silencioso para índices já existentes
            }
        }
        
        // OTIMIZAÇÃO: Atualiza estatísticas do banco p/ o otimizador de query
        try {
            $db->exec("ANALYZE TABLE dados_cnpj");
            file_put_contents($log_file, "[$timestamp] [$dbName] ANALYZE TABLE concluído.\n", FILE_APPEND);
        } catch (Exception $e) {}
    }
    file_put_contents($log_file, "[$timestamp] Otimização das 16 bases concluída.\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($log_file, "[$timestamp] Erro Crítico: " . $e->getMessage() . "\n", FILE_APPEND);
}
