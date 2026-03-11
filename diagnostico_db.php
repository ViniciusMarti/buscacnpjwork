<?php
/**
 * Diagnóstico e Correção de Banco de Dados
 * Verifica se a coluna 'municipio' existe em todos os bancos e tenta corrigir se for o caso.
 */
require_once __DIR__ . '/config/db.php';

echo "<h1>Diagnóstico de Bancos de Dados</h1>";

$connections = getAllConnections();

foreach ($connections as $name => $db) {
    echo "<h2>Verificando Banco: $name</h2>";
    try {
        $stmt = $db->query("DESCRIBE dados_cnpj");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $found = false;
        foreach ($cols as $col) {
            if ($col['Field'] === 'municipio') {
                $found = true;
                break;
            }
        }

        if ($found) {
            echo "<p style='color:green'>✅ Coluna 'municipio' encontrada.</p>";
        } else {
            echo "<p style='color:red'>❌ Coluna 'municipio' NÃO encontrada! Tentando adicionar...</p>";
            // Tenta adicionar a coluna. Em MySQL, geralmente é VARCHAR.
            // Pelo importador_seguro.php, parece estar entre 'cep' (se existir) ou após 'bairro'
            try {
                $db->exec("ALTER TABLE dados_cnpj ADD COLUMN municipio VARCHAR(255) AFTER bairro");
                echo "<p style='color:green'>✅ Coluna 'municipio' adicionada com sucesso.</p>";
                $db->exec("CREATE INDEX idx_municipio ON dados_cnpj(municipio)");
                echo "<p style='color:green'>✅ Índice idx_municipio criado.</p>";
            } catch (Exception $e2) {
                echo "<p style='color:red'>Erro ao adicionar coluna: " . $e2->getMessage() . "</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Erro ao acessar banco: " . $e->getMessage() . "</p>";
    }
}

echo "<hr><p>Verificação concluída. Tente acessar as páginas de ranking novamente.</p>";
