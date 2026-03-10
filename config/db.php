<?php
// Configuração centralizada do banco de dados MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'u582732852_buscacnpj1'); // Usuário padrão (usado em conexões simples)
define('DB_PASS', 'qPMwBp#WW*BN6k');     // Senha atual (manter até que o usuário informe outra)

// Lista de bancos de dados disponíveis devido a limitações de tamanho
$DB_NAMES = [
    'u582732852_buscacnpj1',
    'u582732852_buscacnpj2',
    'u582732852_buscacnpj3',
    'u582732852_buscacnpj4',
    'u582732852_buscacnpj5'
];

/**
 * Retorna todas as conexões ativas
 */
function getAllConnections(): array {
    static $connections = [];
    if (empty($connections)) {
        global $DB_NAMES;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        foreach ($DB_NAMES as $dbName) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $dbName . ";charset=utf8mb4";
                // No Hostinger (u582732852), o nome do usuário costuma ser idêntico ao nome do banco
                $connections[$dbName] = new PDO($dsn, $dbName, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log de erro silencioso para não quebrar o site se um banco cair
                error_log("Erro ao conectar ao banco $dbName: " . $e->getMessage());
            }
        }
    }
    return $connections;
}

/**
 * Retorna a conexão principal (para retrocompatibilidade)
 */
function getDB(): PDO {
    $conns = getAllConnections();
    if (empty($conns)) {
        die("Erro crítico: Nenhum banco de dados disponível.");
    }
    return reset($conns);
}

/**
 * Busca por um CNPJ em todos os bancos de dados
 */
function fetchCNPJ($cnpj): ?array {
    foreach (getAllConnections() as $db) {
        try {
            $stmt = $db->prepare("SELECT * FROM dados_cnpj WHERE cnpj = :cnpj LIMIT 1");
            $stmt->execute([':cnpj' => $cnpj]);
            $data = $stmt->fetch();
            if ($data) return $data;
        } catch (Exception $e) {
            continue;
        }
    }
    return null;
}

/**
 * Busca o banco de dados oficial de CNAE (assumindo que está no banco principal)
 */
function getCNAEDB(): ?PDO {
    return getDB();
}

/**
 * Executa uma query de agregação (COUNT, SUM) em todos os bancos
 */
function aggregateDistributed($query, $params): array {
    $connections = getAllConnections();
    $totals = [];
    
    foreach ($connections as $db) {
        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach ($row as $key => $val) {
                if (!isset($totals[$key])) $totals[$key] = 0;
                $totals[$key] += $val;
            }
        } catch (Exception $e) {
            continue;
        }
    }
    return $totals;
}

/**
 * Executa uma listagem distribuída com ordenação e limite em PHP
 */
function fetchAllDistributed($baseQuery, $params, $orderByField, $orderDir = 'DESC', $limit = 100): array {
    $connections = getAllConnections();
    $all = [];
    
    foreach ($connections as $db) {
        try {
            $stmt = $db->prepare("$baseQuery ORDER BY $orderByField $orderDir LIMIT $limit");
            $stmt->execute($params);
            $all = array_merge($all, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            continue;
        }
    }
    
    // Ordenação manual do merge
    usort($all, function($a, $b) use ($orderByField, $orderDir) {
        $valA = $a[$orderByField] ?? 0;
        $valB = $b[$orderByField] ?? 0;
        if ($valA == $valB) return 0;
        
        if ($orderDir === 'DESC') {
            return ($valA < $valB) ? 1 : -1;
        } else {
            return ($valA < $valB) ? -1 : 1;
        }
    });
    
    return array_slice($all, 0, $limit);
}


