<?php
/**
 * Script para otimização do banco de dados SQLite (17GB)
 * Adiciona índices essenciais para que as buscas e rankings fiquem instantâneos.
 * 
 * INSTRUÇÕES: 
 * 1. Suba este arquivo para a pasta public_html/
 * 2. Acesse pelo navegador: seu-site.com.br/otimizar_banco.php
 * 3. Aguarde (pode demorar alguns minutos devido aos 17GB).
 * 4. Apague este arquivo após o uso por segurança.
 */

require_once __DIR__ . '/config/db.php';

// Aumentar o tempo limite de execução (fundamental para arquivos grandes)
set_time_limit(1800); // 30 minutos
ini_set('memory_limit', '512M');

echo "<h1>Otimizador de Banco de Dados</h1>";
echo "<p>Iniciando criação de índices no banco de 17GB... Por favor, não feche esta aba.</p>";
flush();

try {
    $db = getDB();
    
    // Lista de índices cruciais
    $queries = [
        "PRAGMA journal_mode = WAL;",
        "PRAGMA synchronous = NORMAL;",
        "CREATE INDEX IF NOT EXISTS idx_cnpj ON dados_cnpj(cnpj);",
        "CREATE INDEX IF NOT EXISTS idx_uf ON dados_cnpj(uf);",
        "CREATE INDEX IF NOT EXISTS idx_capital ON dados_cnpj(capital_social DESC);",
        "CREATE INDEX IF NOT EXISTS idx_municipio ON dados_cnpj(municipio);",
        "CREATE INDEX IF NOT EXISTS idx_razao ON dados_cnpj(razao_social);",
        "CREATE INDEX IF NOT EXISTS idx_uf_municipio ON dados_cnpj(uf, municipio);"
    ];

    foreach ($queries as $sql) {
        echo "Executando: <code>$sql</code> ... ";
        flush();
        $start = microtime(true);
        $db->exec($sql);
        $end = microtime(true);
        echo "<span style='color:green'>Sucesso (" . round($end - $start, 2) . "s)</span><br>";
        flush();
    }

    echo "<h2>✅ Otimização Concluída!</h2>";
    echo "<p>Agora as buscas por CNPJ e os Rankings serão instantâneos.</p>";

} catch (Exception $e) {
    echo "<h2>❌ Erro:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
