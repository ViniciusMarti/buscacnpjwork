<?php
set_time_limit(0);
ignore_user_abort(true);
require_once __DIR__ . '/config/db.php';

// Ativa o flush para output em tempo real
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

$password = DB_PASS;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Super Cleaner CNPJ - Faxina Pesada</title>
    <style>
        body { background: #0f172a; color: #10b981; font-family: 'Courier New', monospace; padding: 20px; line-height: 1.5; }
        .log-line { border-bottom: 1px solid #1e293b; padding: 5px 0; }
        .error { color: #ef4444; font-weight: bold; }
        .success { color: #22c55e; font-weight: bold; }
        .info { color: #38bdf8; }
        .progress { position: sticky; top: 0; background: #1e293b; padding: 10px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #334155; }
        #scroll-anchor { height: 1px; }
    </style>
    <script>
        function scrollToBottom() {
            window.scrollTo(0, document.body.scrollHeight);
        }
        setInterval(scrollToBottom, 500);
    </script>
</head>
<body>
<div class='progress' id='p-bar'>Iniciando Faxina Pesada nos 32 Shards...</div>";

function logMe($msg, $class = "") {
    echo "<div class='log-line $class'>[" . date("H:i:s") . "] $msg</div>";
    @flush();
}

$start = isset($_GET['start']) ? (int)$_GET['start'] : 1;
$end = isset($_GET['end']) ? (int)$_GET['end'] : 32;

logMe("Configurando faxina para Shards de $start até $end...", "info");

for ($i = $start; $i <= $end; $i++) {
    $db = "u582732852_buscacnpj" . $i;
    echo "<script>document.getElementById('p-bar').innerText = 'Limpando Banco: $db ($i / $end)';</script>";
    
    logMe("--- Conectando ao Banco: $db ---", "info");
    
    $conn = @new mysqli("localhost", $db, $password, $db);
    if ($conn->connect_error) {
        logMe("ERRO DE CONEXÃO: " . $conn->connect_error, "error");
        continue;
    }

    $tables = [
        'empresas' => 'cnpj_basico',
        'estabelecimento' => 'cnpj',
        'socio' => ['cnpj_basico', 'nome_socio', 'qualificacao_socio']
    ];

    foreach ($tables as $table => $pk) {
        logMe("Verificando duplicatas na tabela [$table]...");
        
        // Verifica se a tabela existe
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        if ($res->num_rows == 0) {
            logMe("Tabela [$table] não existe neste shard. Pulando.", "error");
            continue;
        }

        // 1. Criar nova tabela com estrutura correta
        $conn->query("DROP TABLE IF EXISTS {$table}_new");
        $conn->query("CREATE TABLE {$table}_new LIKE $table");
        
        // 2. Adicionar Chave Primária ou Unique Index
        if (is_array($pk)) {
            $pkItems = implode(",", $pk);
            $q = $conn->query("ALTER TABLE {$table}_new ADD UNIQUE INDEX idx_unique_clean ($pkItems)");
            logMe("Adicionando Índice Único Composto ($pkItems)...");
        } else {
            $q = $conn->query("ALTER TABLE {$table}_new ADD PRIMARY KEY ($pk)");
            logMe("Adicionando Chave Primária em ($pk)...");
        }

        if (!$q) {
            logMe("Falha ao preparar estrutura em {$table}_new: " . $conn->error, "error");
            continue;
        }

        // 3. Migrar dados usando INSERT IGNORE para deletar duplicatas
        logMe("Migrando dados e deletando duplicatas (isso pode demorar)...");
        $conn->query("INSERT IGNORE INTO {$table}_new SELECT * FROM $table");
        $affected = $conn->affected_rows;
        $total = $conn->query("SELECT COUNT(*) as total FROM $table")->fetch_assoc()['total'];
        $dups = $total - $affected;
        
        logMe("Sucesso! [$affected] registros únicos preservados. [$dups] duplicatas removidas.", "success");

        // 4. Trocar as tabelas
        $conn->query("DROP TABLE IF EXISTS {$table}_old");
        $conn->query("RENAME TABLE $table TO {$table}_old, {$table}_new TO $table");
        $conn->query("DROP TABLE {$table}_old");
        
        logMe("Tabela [$table] selada e limpa.", "success");
    }

    $conn->close();
    logMe("Shard $db finalizado.", "success");
}

echo "<div class='success' style='font-size: 24px; margin-top: 40px;'>=== FAXINA CONCLUÍDA COM SUCESSO! ===</div>
<p>Todos os bancos foram limpos e protegidos contra duplicatas.</p>
<div id='scroll-anchor'></div>
</body>
</html>";
