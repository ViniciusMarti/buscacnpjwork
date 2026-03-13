<?php
// Configuração centralizada do banco de dados MySQL para Sharding (16 Bases)
define('DB_HOST', 'localhost');
define('DB_PASS', 'qPMwBp#WW*BN6k'); // Senha unificada para os 16 bancos

// Lista de bancos de dados independentes (Shards) - 32 Bancos
$DB_NAMES = [];
for ($i = 1; $i <= 32; $i++) {
    $DB_NAMES[] = 'u582732852_buscacnpj' . $i;
}


/**
 * Retorna uma conexão específica (Lazy Loading)
 */
function getSpecificConnection(string $dbName): ?PDO {
    static $opened = [];
    if (isset($opened[$dbName])) return $opened[$dbName];

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $dbName . ";charset=utf8mb4";
        // No Hostinger (u582732852), o usuário é igual ao nome do banco
        $opened[$dbName] = new PDO($dsn, $dbName, DB_PASS, $options);
        return $opened[$dbName];
    } catch (PDOException $e) {
        error_log("Erro ao conectar ao banco $dbName: " . $e->getMessage());
        return null;
    }
}

/**
 * Retornar todas as conexões (força abertura de todas)
 * Útil para rankings e agregações globais.
 */
function getAllConnections(): array {
    global $DB_NAMES;
    $conns = [];
    foreach ($DB_NAMES as $name) {
        $db = getSpecificConnection($name);
        if ($db) $conns[$name] = $db;
    }
    return $conns;
}

/**
 * Retorna a primeira conexão disponível (retrocompatibilidade)
 */
function getDB(): PDO {
    global $DB_NAMES;
    foreach ($DB_NAMES as $name) {
        $db = getSpecificConnection($name);
        if ($db) return $db;
    }
    die("Erro crítico: Nenhum banco de dados disponível.");
}

/**
 * Identifica em qual banco o CNPJ está (Roteamento de Shard)
 * Regra: (cnpj_basico % 32) + 1
 */
function identifyShard($cnpj): ?string {
    $clean = preg_replace('/\D/', '', $cnpj);
    if (strlen($clean) < 8) return null;
    
    $cnpj_basico = intval(substr($clean, 0, 8));
    $shard = ($cnpj_basico % 32) + 1;
    
    return 'u582732852_buscacnpj' . $shard;
}


/**
 * Busca por um CNPJ nos bancos de dados (Otimizado com Sharding de 32 bases)
 */
function fetchCNPJ($cnpj): ?array {
    $clean = preg_replace('/\D/', '', $cnpj);
    if (strlen($clean) < 14) return null;
    
    $cnpj_basico = substr($clean, 0, 8);
    
    // 1. Identificar o shard correto
    $shardName = identifyShard($clean);
    $db = getSpecificConnection($shardName);
    
    if (!$db) return null;

    // 2. Buscar no shard específico
    return queryCNPJ($db, $clean, $cnpj_basico);
}

/**
 * Helper para executar a query de CNPJ nas 3 tabelas e consolidar
 */
function queryCNPJ(PDO $db, $cnpj, $cnpj_basico): ?array {
    try {
        // Query Estabelecimento (Dados específicos da unidade)
        $stmt_est = $db->prepare("SELECT * FROM estabelecimentos WHERE cnpj = ? LIMIT 1");
        $stmt_est->execute([$cnpj]);
        $est = $stmt_est->fetch();
        
        if (!$est) return null;

        // Query Empresa (Dados gerais da razão social)
        $stmt_emp = $db->prepare("SELECT * FROM empresas WHERE cnpj_basico = ? LIMIT 1");
        $stmt_emp->execute([$cnpj_basico]);
        $emp = $stmt_emp->fetch();

        // Query Sócios
        $stmt_soc = $db->prepare("SELECT nome_socio, qualificacao_socio FROM socios WHERE cnpj_basico = ?");
        $stmt_soc->execute([$cnpj_basico]);
        $socios = $stmt_soc->fetchAll();

        
        // Consolidar dados
        $data = array_merge($emp ?: [], $est ?: []);
        
        // Formatar Sócios para o padrão esperado pelo frontend (nome - cargo | nome - cargo)
        if ($socios) {
            $socios_formatados = [];
            foreach ($socios as $s) {
                // Aqui você pode mapear os códigos de qualificação se necessário, 
                // por enquanto usamos o nome e o código
                $socios_formatados[] = "{$s['nome_socio']} - {$s['qualificacao_socio']}";
            }
            $data['socios_texto'] = implode('; ', $socios_formatados);
        } else {
            $data['socios_texto'] = 'Informação não disponível';
        }

        // --- NORMALIZAÇÃO DE COLUNAS PARA COMPATIBILIDADE COM FRONTEND ---
        // O frontend espera alguns nomes de colunas que podem variar
        $data['razao_social'] = $data['razao_social'] ?? '';
        $data['nome_fantasia'] = $data['nome_fantasia'] ?? '';
        $data['situacao_cadastral'] = $data['situacao_cadastral'] ?? 'N/A';
        $data['sigla_uf'] = $data['uf'] ?? $data['sigla_uf'] ?? '';
        $data['cnae_fiscal_principal'] = $data['cnae_principal'] ?? '';
        
        // Fallbacks para campos que podem estar em empresas ou estabelecimentos no novo schema
        $data['data_inicio_atividade'] = $data['data_inicio_atividade'] ?? '';
        
        // Mapeamento de situação cadastral se vier como número
        $situacoes = [1 => 'NULA', 2 => 'ATIVA', 3 => 'SUSPENSA', 4 => 'INAPTA', 8 => 'BAIXADA'];
        if (is_numeric($data['situacao_cadastral']) && isset($situacoes[$data['situacao_cadastral']])) {
            $data['situacao_cadastral'] = $situacoes[$data['situacao_cadastral']];
        }

        return $data;
    } catch (Exception $e) {
        error_log("Erro ao consultar CNPJ $cnpj: " . $e->getMessage());
        return null;
    }
}


/**
 * Busca o banco de dados oficial de CNAE (SQLite local para performance e consistência)
 */
function getCNAEDB(): ?PDO {
    static $cnae_pdo = null;
    if ($cnae_pdo !== null) return $cnae_pdo;

    $path = __DIR__ . '/../database/cnae.db';
    if (!file_exists($path)) {
        return null;
    }

    try {
        $cnae_pdo = new PDO("sqlite:" . $path);
        $cnae_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $cnae_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $cnae_pdo;
    } catch (PDOException $e) {
        error_log("Erro ao conectar ao CNAE.db: " . $e->getMessage());
        return null;
    }
}

/**
 * Executa uma query de agregação em todos os bancos
 */
function aggregateDistributed($query, $params): array {
    $connections = getAllConnections();
    $totals = [];
    
    foreach ($connections as $db) {
        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                foreach ($row as $key => $val) {
                    if (!isset($totals[$key])) $totals[$key] = 0;
                    $totals[$key] += $val;
                }
            }
        } catch (Exception $e) { continue; }
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
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($results) {
                $all = array_merge($all, $results);
            }
        } catch (Exception $e) { continue; }
    }
    
    // Ordenação manual do merge
    usort($all, function($a, $b) use ($orderByField, $orderDir) {
        $valA = $a[$orderByField] ?? 0;
        $valB = $b[$orderByField] ?? 0;
        if ($valA == $valB) return 0;
        return ($orderDir === 'DESC') ? (($valA < $valB) ? 1 : -1) : (($valA < $valB) ? -1 : 1);
    });
    
    return array_slice($all, 0, $limit);
}


