<?php
// Configuração centralizada do banco de dados MySQL para Sharding (16 Bases)
define('DB_HOST', 'localhost');
define('DB_PASS', 'qPMwBp#WW*BN6k'); // Senha unificada para os 16 bancos

// Lista de bancos de dados independentes (Shards)
$DB_NAMES = [
    'u582732852_buscacnpj1',  'u582732852_buscacnpj2',  'u582732852_buscacnpj3',
    'u582732852_buscacnpj4',  'u582732852_buscacnpj5',  'u582732852_buscacnpj6',
    'u582732852_buscacnpj7',  'u582732852_buscacnpj8',  'u582732852_buscacnpj9',
    'u582732852_buscacnpj10', 'u582732852_buscacnpj11', 'u582732852_buscacnpj12',
    'u582732852_buscacnpj13', 'u582732852_buscacnpj14', 'u582732852_buscacnpj15',
    'u582732852_buscacnpj16'
];

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
 * Se for implementado um critério (ex: Modulo 16 ou UF), atualizar aqui.
 * Por enquanto, retorna null para forçar busca em todos os shards.
 */
function identifyShard($cnpj): ?string {
    // Exemplo de lógica futura: return 'u582732852_buscacnpj' . (intval(substr($cnpj, 0, 2)) % 16 + 1);
    return null; 
}

/**
 * Busca por um CNPJ nos bancos de dados (Otimizado com Lazy Search)
 */
function fetchCNPJ($cnpj): ?array {
    $clean = preg_replace('/\D/', '', $cnpj);
    $formatted = preg_replace("/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/", "$1.$2.$3/$4-$5", $clean);
    
    // 1. Tentar identificar o shard específico (Routing)
    $shardHint = identifyShard($clean);
    if ($shardHint) {
        $db = getSpecificConnection($shardHint);
        if ($db) {
            $data = queryCNPJ($db, $clean, $formatted);
            if ($data) return $data;
        }
    }

    // 2. Se não houver dica ou não encontrar na dica, percorre todos os shards (Lazy)
    global $DB_NAMES;
    foreach ($DB_NAMES as $name) {
        if ($name === $shardHint) continue; // Pula o que já tentou
        $db = getSpecificConnection($name);
        if (!$db) continue;

        $data = queryCNPJ($db, $clean, $formatted);
        if ($data) return $data;
    }

    return null;
}

/**
 * Helper para executar a query de CNPJ com heurísticas de correção
 */
function queryCNPJ(PDO $db, $clean, $formatted): ?array {
    try {
        $stmt = $db->prepare("SELECT * FROM dados_cnpj WHERE cnpj = :cnpj OR cnpj = :formatted LIMIT 1");
        $stmt->execute([':cnpj' => $clean, ':formatted' => $formatted]);
        $data = $stmt->fetch();
        
        if ($data) {
            // Heurística de correção para dados deslocados
            $is_rs_numeric = is_numeric(preg_replace('/\D/', '', $data['razao_social'] ?? ''));
            $is_nf_numeric = is_numeric(preg_replace('/\D/', '', $data['nome_fantasia'] ?? ''));
            
            if ($is_rs_numeric && !$is_nf_numeric && strlen($data['nome_fantasia']) > 10) {
                $data['cnae_fiscal_principal'] = $data['razao_social'];
                $data['razao_social'] = $data['nome_fantasia'];
                $data['nome_fantasia'] = $data['situacao_cadastral'];
                $data['situacao_cadastral'] = $data['logradouro'];
            }
            
            if ($is_rs_numeric && $is_nf_numeric && strlen(preg_replace('/\D/', '', $data['razao_social'])) >= 7) {
                $data['cnae_fiscal_principal'] = $data['razao_social'];
            }

            // --- NORMALIZAÇÃO DE COLUNAS (Fallback para diferentes schemas) ---
            $data['data_inicio_atividade'] = $data['data_inicio_atividade'] ?? $data['data_abertura'] ?? '';
            $data['cnae_principal_descricao'] = $data['cnae_principal_descricao'] ?? $data['cnae_fiscal_principal_descricao'] ?? '';
            $data['cnae_fiscal_secundaria'] = $data['cnae_fiscal_secundaria'] ?? $data['cnae_fiscal_secundária'] ?? '';
            $data['situacao_cadastral'] = $data['situacao_cadastral'] ?? $data['situacao'] ?? 'N/A';
            $data['sigla_uf'] = $data['sigla_uf'] ?? $data['uf'] ?? '';
            $data['telefone_1'] = $data['telefone_1'] ?? $data['telefone'] ?? '';

            // Detecção de linha CSV fundida (Endereço na Razão Social)
            if (strpos($data['razao_social'] ?? '', ',') !== false && count(explode(',', $data['razao_social'])) > 3) {
                $parts = explode(',', $data['razao_social']);
                if (count($parts) >= 5) {
                    $data['logradouro'] = $data['logradouro'] ?: trim($parts[0]);
                    $data['bairro'] = $data['bairro'] ?: trim($parts[1]);
                    $data['cep'] = $data['cep'] ?: trim($parts[2]);
                    $data['municipio'] = $data['municipio'] ?: trim($parts[3]);
                    $data['sigla_uf'] = $data['sigla_uf'] ?: trim($parts[4]);
                    $data['razao_social'] = (!empty($data['nome_fantasia']) && !is_numeric($data['nome_fantasia'])) 
                                            ? $data['nome_fantasia'] : 'EMPRESA REGISTRADA (' . $data['cnpj'] . ')';
                }
            }
            return $data;
        }
    } catch (Exception $e) {
        return null;
    }
    return null;
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


