<?php
// Configuração centralizada do banco de dados MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'u582732852_buscacnpj1'); // Usuário padrão (usado em conexões simples)
define('DB_PASS', 'qPMwBp#WW*BN6k');     // Senha atual (manter até que o usuário informe outra)

// Lista de bancos de dados disponíveis devido a limitações de tamanho
$DB_NAMES = [
    'u582732852_buscacnpj6',
    'u582732852_buscacnpj5',
    'u582732852_buscacnpj4',
    'u582732852_buscacnpj3',
    'u582732852_buscacnpj2',
    'u582732852_buscacnpj1'
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
    $clean = preg_replace('/\D/', '', $cnpj);
    // Formatos possíveis no banco: 12345678000199 ou 12.345.678/0001-99
    $formatted = preg_replace("/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/", "$1.$2.$3/$4-$5", $clean);
    
    foreach (getAllConnections() as $db) {
        try {
            $stmt = $db->prepare("SELECT * FROM dados_cnpj WHERE cnpj = :cnpj OR cnpj = :formatted LIMIT 1");
            $stmt->execute([':cnpj' => $clean, ':formatted' => $formatted]);
            $data = $stmt->fetch();
            
            if ($data) {
                // HEURÍSTICA DE CORREÇÃO PARA DADOS DESLOCADOS (Shift Fix)
                // Se a Razão Social é numérica (CNAE) e o Nome Fantasia parece uma Razão Social (Texto longo)
                // Ou se os campos estão vindo com códigos no lugar de nomes.
                $is_rs_numeric = is_numeric(preg_replace('/\D/', '', $data['razao_social'] ?? ''));
                $is_nf_numeric = is_numeric(preg_replace('/\D/', '', $data['nome_fantasia'] ?? ''));
                
                if ($is_rs_numeric && !$is_nf_numeric && strlen($data['nome_fantasia']) > 10) {
                    // Shift detectado: Provável que [CNAE] caiu em [Razão Social] e [Razão Social] em [Nome Fantasia]
                    $data['cnae_principal_codigo'] = $data['razao_social'];
                    $data['razao_social'] = $data['nome_fantasia'];
                    $data['nome_fantasia'] = $data['situacao']; // Situacao pode estar no Nome Fantasia
                    $data['situacao'] = $data['logradouro'];   // E assim por diante...
                }
                
                // Caso específico relatado pelo usuário (Múltiplos campos numéricos no início)
                if ($is_rs_numeric && $is_nf_numeric) {
                    if (strlen(preg_replace('/\D/', '', $data['razao_social'])) >= 7) {
                        $data['cnae_principal_codigo'] = $data['razao_social'];
                    }
                }

                // HEURÍSTICA: Detecção de linha CSV fundida na Razão Social
                // Se encontrar vírgulas e termos como 'SAC' ou nomes de cidades/UFs
                if (strpos($data['razao_social'] ?? '', ',') !== false && count(explode(',', $data['razao_social'])) > 3) {
                    $parts = explode(',', $data['razao_social']);
                    // Exemplo: BOX 05,CENTRO,01023001,SAO PAULO,SP,,1166835094,47...
                    // Tenta mapear campos de endereço baseados em posições comuns de erro
                    if (count($parts) >= 5) {
                        $data['logradouro'] = $data['logradouro'] ?: trim($parts[0]);
                        $data['bairro'] = $data['bairro'] ?: trim($parts[1]);
                        $data['cep'] = $data['cep'] ?: trim($parts[2]);
                        $data['municipio'] = $data['municipio'] ?: trim($parts[3]);
                        $data['uf'] = $data['uf'] ?: trim($parts[4]);
                        
                        // Tenta encontrar a Razão Social real em outro campo se estiver disponível
                        if (!empty($data['nome_fantasia']) && !is_numeric($data['nome_fantasia'])) {
                            $data['razao_social'] = $data['nome_fantasia'];
                        } else {
                            // Se não tiver onde pegar o nome, pelo menos remove o endereço do campo de nome
                            $data['razao_social'] = 'EMPRESA REGISTRADA (' . $data['cnpj'] . ')';
                        }
                    }
                }
                
                return $data;
            }
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


