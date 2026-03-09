<?php
require_once __DIR__ . '/config/db.php';
try {
    $db = getDB();
    echo "<h1>Diagnóstico de Performance MySQL</h1>";
    
    echo "<h2>1. Verificando Índices da tabela dados_cnpj:</h2>";
    $indices = $db->query("SHOW INDEX FROM dados_cnpj")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'><tr><th>Coluna</th><th>Nome do Índice</th><th>Não Único</th></tr>";
    foreach ($indices as $idx) {
        echo "<tr><td>{$idx['Column_name']}</td><td>{$idx['Key_name']}</td><td>{$idx['Non_unique']}</td></tr>";
    }
    echo "</table>";

    echo "<h2>2. Testando Query Simples por CNPJ (Limpo):</h2>";
    $teste_cnpj = '58347012000177';
    $start = microtime(true);
    $stmt = $db->prepare("SELECT cnpj FROM dados_cnpj WHERE cnpj = :cnpj LIMIT 1");
    $stmt->execute([':cnpj' => $teste_cnpj]);
    $stmt->fetch();
    $end = microtime(true);
    echo "<p>Tempo para SELECT simples: <b>" . round($end - $start, 4) . "s</b></p>";

    echo "<h2>3. Testando Query de Filiais (LIKE):</h2>";
    $base = substr($teste_cnpj, 0, 8) . '%';
    $start = microtime(true);
    $stmt = $db->prepare("SELECT cnpj FROM dados_cnpj WHERE cnpj LIKE :base LIMIT 5");
    $stmt->execute([':base' => $base]);
    $stmt->fetchAll();
    $end = microtime(true);
    echo "<p>Tempo para SELECT LIKE (Filiais): <b>" . round($end - $start, 4) . "s</b></p>";

    echo "<h2>4. Verificando Estrutura das Colunas:</h2>";
    $cols = $db->query("DESCRIBE dados_cnpj")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'><tr><th>Campo</th><th>Tipo</th><th>Key</th></tr>";
    foreach ($cols as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
