<?php
/**
 * Importador de CNPJ - Worker de Processamento
 * Local: /public_html/importador/import_worker.php
 */

set_time_limit(0);
ignore_user_abort(true);

// Configurações de Banco de Dados (conforme solicitado e verificado no db.php)
define('DB_HOST', 'localhost');
define('DB_USER', 'u582732852_vinicius0102m');
define('DB_PASS', 'qPMwBp#WW*BN6k');

$LOG_DIR = __DIR__ . '/logs';
$PROGRESS_FILE = $LOG_DIR . '/import_progress.json';
$ERROR_LOG = $LOG_DIR . '/import_errors.log';
$SHARDS_PATH = realpath(__DIR__ . '/../shards'); // /public_html/shards/

// Garante que o diretório de logs existe
if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0777, true);
}

// Ações via GET
$action = $_GET['action'] ?? '';

if ($action === 'reset') {
    if (file_exists($PROGRESS_FILE)) unlink($PROGRESS_FILE);
    echo json_encode(['status' => 'reset']);
    exit;
}

if ($action === 'pause') {
    updateProgress(['status' => 'paused']);
    echo json_encode(['status' => 'paused']);
    exit;
}

if ($action !== 'start') {
    echo json_encode(['status' => 'idle']);
    exit;
}

// --- Lógica de Importação ---

// 1. Carregar ou Inicializar Progresso
$progress = loadProgress();

if ($progress['status'] === 'running' && (time() - $progress['last_update'] < 10)) {
    echo json_encode(['status' => 'already_running']);
    exit;
}

$progress['status'] = 'running';
$progress['start_time'] = $progress['start_time'] ?? time();
updateProgress($progress);

// Ordem dos arquivos a importar
$filesToImport = ['empresas', 'estabelecimentos', 'socios'];

try {
    for ($shardIdx = $progress['current_shard_idx']; $shardIdx <= 32; $shardIdx++) {
        $progress['current_shard_idx'] = $shardIdx;
        $dbName = "u582732852_buscacnpj{$shardIdx}";
        $shardPath = "{$SHARDS_PATH}/buscacnpj{$shardIdx}";
        
        $progress['current_shard'] = "buscacnpj{$shardIdx}";
        $progress['shard_status'][$shardIdx] = 'processing';
        updateProgress($progress);

        // Conectar ao banco do shard atual
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $dbName . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            logError("Erro ao conectar no banco $dbName: " . $e->getMessage());
            $progress['shard_status'][$shardIdx] = 'error';
            $progress['error_count']++;
            updateProgress($progress);
            continue; // Tenta o próximo shard
        }

        foreach ($filesToImport as $fileKey) {
            // Se já processou esse arquivo neste shard, pula
            if ($progress['completed_files'][$shardIdx][$fileKey] ?? false) continue;
            
            // Verificação flexível de arquivos (pode ser .csv.gz ou apenas .csv se o Windows estiver ocultando)
            $possibleFiles = [
                "{$shardPath}/{$fileKey}.csv.gz",
                "{$shardPath}/{$fileKey}.csv",
                "{$shardPath}/{$fileKey}.gz"
            ];
            
            $foundFile = null;
            foreach ($possibleFiles as $f) {
                if (file_exists($f)) {
                    $foundFile = $f;
                    break;
                }
            }

            if (!$foundFile) {
                // Se o arquivo não existe, apenas marca como concluído/pulado e segue
                $progress['completed_files'][$shardIdx][$fileKey] = true;
                updateProgress($progress);
                continue;
            }

            $progress['current_file'] = basename($foundFile);
            updateProgress($progress);

            importFile($pdo, $fileKey, $foundFile, $progress);
            
            $progress['completed_files'][$shardIdx][$fileKey] = true;
            updateProgress($progress);
        }

        $progress['shard_status'][$shardIdx] = 'done';
        updateProgress($progress);
        
        // Limpar tabelas de progresso parciais de arquivo ao terminar um shard
        $progress['current_file_line'] = 0;
    }

    $progress['status'] = 'completed';
    updateProgress($progress);

} catch (Exception $e) {
    logError("Erro fatal no worker: " . $e->getMessage());
    $progress['status'] = 'error';
    updateProgress($progress);
}

/**
 * Função principal de processamento de um arquivo CSV.GZ
 */
function importFile(PDO $pdo, $type, $filePath, &$progress) {
    $handle = gzopen($filePath, 'rb');
    if (!$handle) {
        logError("Não foi possível abrir o stream gzip: $filePath");
        return;
    }

    $tableName = $type; // empresas, estabelecimentos, socios
    $batchSize = 2000;
    $batch = [];
    $lineCount = 0;
    $skipLines = $progress['current_file_line'] ?? 0;

    // Colunas esperadas simplificadas (Baseado no DB.php e RFB)
    $columnsMap = [
        'empresas' => ['cnpj_basico', 'razao_social', 'natureza_juridica', 'qualificacao_responsavel', 'capital_social', 'porte_empresa', 'ente_federativo_responsavel'],
        'estabelecimentos' => ['cnpj', 'identificador_matriz_filial', 'nome_fantasia', 'situacao_cadastral', 'data_situacao_cadastral', 'motivo_situacao_cadastral', 'nome_cidade_exterior', 'pais', 'data_inicio_atividade', 'cnae_principal', 'cnae_secundaria', 'tipo_logradouro', 'logradouro', 'numero', 'complemento', 'bairro', 'cep', 'uf', 'municipio', 'ddd_1', 'telefone_1', 'ddd_2', 'telefone_2', 'ddd_fax', 'fax', 'correio_eletronico', 'situacao_especial', 'data_situacao_especial'],
        'socios' => ['cnpj_basico', 'identificador_socio', 'nome_socio', 'cnpj_cpf_socio', 'qualificacao_socio', 'data_entrada_sociedade', 'pais', 'representante_legal', 'nome_representante', 'qualificacao_representante', 'faixa_etaria']
    ];

    $columns = $columnsMap[$type];
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO $tableName (" . implode(',', $columns) . ") VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);

    $pdo->beginTransaction();

    while (!gzeof($handle)) {
        $line = gzgets($handle, 16384);
        if ($line === false) break;

        $lineCount++;
        
        // Pular linhas já processadas (Retomada)
        if ($lineCount <= $skipLines) continue;

        $data = str_getcsv($line, ",", "\"");
        
        // Tratamento especial para montagem do CNPJ completo em estabelecimentos
        if ($type === 'estabelecimentos') {
            // RFB separa Cnpj 8, 4, 2. Nosso app quer 14.
            $fullCnpj = ($data[0] ?? '') . ($data[1] ?? '') . ($data[2] ?? '');
            
            // Reajusta o array para bater com as colunas do DB
            // Removemos os 3 primeiros (basico, ordem, dv) e colocamos o full no lugar
            array_splice($data, 0, 3, [$fullCnpj]);
        }

        // Corta ou completa o array para bater com as colunas
        $row = array_slice($data, 0, count($columns));
        while(count($row) < count($columns)) $row[] = null;

        $batch[] = $row;

        if (count($batch) >= $batchSize) {
            if (!executeBatch($stmt, $batch)) {
                $progress['error_count']++;
            }
            $pdo->commit();
            
            $progress['total_rows'] += count($batch);
            $progress['current_file_line'] = $lineCount;
            $progress['last_update'] = time();
            $progress['last_log'] = "Processado shard {$progress['current_shard']}, {$type}: {$lineCount} linhas...";
            
            updateProgress($progress);
            
            if (checkPaused()) {
                gzclose($handle);
                exit;
            }

            $batch = [];
            $pdo->beginTransaction();
        }
    }

    // Processar o resto do batch
    if (!empty($batch)) {
        executeBatch($stmt, $batch);
        $pdo->commit();
        $progress['total_rows'] += count($batch);
    }

    gzclose($handle);
}

function executeBatch($stmt, $batch) {
    try {
        foreach ($batch as $row) {
            $stmt->execute($row);
        }
        return true;
    } catch (Exception $e) {
        logError("Erro no batch: " . $e->getMessage());
        return false;
    }
}

function loadProgress() {
    global $PROGRESS_FILE;
    if (file_exists($PROGRESS_FILE)) {
        return json_decode(file_get_contents($PROGRESS_FILE), true);
    }
    return [
        'status' => 'idle',
        'current_shard_idx' => 1,
        'current_shard' => '',
        'current_file' => '',
        'current_file_line' => 0,
        'total_rows' => 0,
        'error_count' => 0,
        'start_time' => time(),
        'last_update' => time(),
        'shard_status' => array_fill(1, 32, 'pending'),
        'completed_files' => [],
        'last_log' => 'Iniciando...',
        'last_log_type' => 'info'
    ];
}

function updateProgress($data) {
    global $PROGRESS_FILE;
    $data['last_update'] = time();
    file_put_contents($PROGRESS_FILE, json_encode($data));
}

function checkPaused() {
    $p = loadProgress();
    return $p['status'] === 'paused';
}

function logError($msg) {
    global $ERROR_LOG;
    $time = date('Y-m-d H:i:s');
    file_put_contents($ERROR_LOG, "[$time] $msg\n", FILE_APPEND);
}
