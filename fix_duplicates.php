<?php
set_time_limit(0);
require_once __DIR__ . '/config/db.php';

$bancos = [];
for($i=1;$i<=32;$i++){
    $bancos[]="u582732852_buscacnpj".$i;
}

$password = DB_PASS; // Using password from config

foreach ($bancos as $db) {
    echo "Processando $db...\n";
    $conn = @new mysqli("localhost", $db, $password, $db);
    if ($conn->connect_error) {
        echo "Falha na conexão com $db\n";
        continue;
    }

    // 1. Empresas: Adicionar PK em cnpj_basico
    echo "  - Ajustando 'empresas'...\n";
    $conn->query("ALTER TABLE empresas ADD PRIMARY KEY (cnpj_basico)"); 
    // Ignore error if already has PK or duplicates exist. 
    // If duplicates exist, we need to clean them first.
    if ($conn->errno == 1062) { // Duplicate entry
         echo "    ! Duplicados encontrados em 'empresas'. Limpando...\n";
         $conn->query("CREATE TABLE empresas_new LIKE empresas");
         $conn->query("ALTER TABLE empresas_new ADD PRIMARY KEY (cnpj_basico)");
         $conn->query("INSERT IGNORE INTO empresas_new SELECT * FROM empresas");
         $conn->query("RENAME TABLE empresas TO empresas_old, empresas_new TO empresas");
         $conn->query("DROP TABLE empresas_old");
    }

    // 2. Estabelecimentos: Adicionar PK em cnpj
    echo "  - Ajustando 'estabelecimentos'...\n";
    $conn->query("ALTER TABLE estabelecimentos ADD PRIMARY KEY (cnpj)");
    if ($conn->errno == 1062) {
         echo "    ! Duplicados encontrados em 'estabelecimentos'. Limpando...\n";
         $conn->query("CREATE TABLE estabelecimentos_new LIKE estabelecimentos");
         $conn->query("ALTER TABLE estabelecimentos_new ADD PRIMARY KEY (cnpj)");
         $conn->query("INSERT IGNORE INTO estabelecimentos_new SELECT * FROM estabelecimentos");
         $conn->query("RENAME TABLE estabelecimentos TO estabelecimentos_old, estabelecimentos_new TO estabelecimentos");
         $conn->query("DROP TABLE estabelecimentos_old");
    }

    // 3. Sócios: Adicionar Índice Único (Não há PK natural simples)
    echo "  - Ajustando 'socios'...\n";
    $conn->query("ALTER TABLE socios ADD UNIQUE INDEX idx_socio_unique (cnpj_basico, nome_socio(191), qualificacao_socio)");
    if ($conn->errno == 1062) {
         echo "    ! Duplicados encontrados em 'socios'. Limpando...\n";
         $conn->query("CREATE TABLE socios_new LIKE socios");
         $conn->query("ALTER TABLE socios_new ADD UNIQUE INDEX idx_socio_unique (cnpj_basico, nome_socio(191), qualificacao_socio)");
         $conn->query("INSERT IGNORE INTO socios_new SELECT * FROM socios");
         $conn->query("RENAME TABLE socios TO socios_old, socios_new TO socios");
         $conn->query("DROP TABLE socios_old");
    }

    $conn->close();
    echo "Concluído $db\n\n";
}

echo "TODOS OS BANCOS FORAM PROCESSADOS E PROTEGIDOS CONTRA DUPLICATAS.";
