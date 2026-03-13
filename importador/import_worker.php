<?php
/**
 * Importador BigQuery -> MySQL Shards (Worker)
 * Local: /public_html/importador/import_worker.php
 */

set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/bigquery_client.php';
require_once __DIR__ . '/mysql_sharder.php';

$LOG_DIR = __DIR__ . '/logs';
$PROGRESS_FILE = $LOG_DIR . '/import_progress.json';
$BIGQUERY_KEY = __DIR__ . '/google_keys.json'; // O usuário deve colocar o JSON aqui

$action = $_GET['action'] ?? '';

if ($action === 'reset') {
    if (file_exists($PROGRESS_FILE)) unlink($PROGRESS_FILE);
    echo json_encode(['status' => 'reset']);
    exit;
}

// Inicializar componentes
try {
    $sharder = new MySQLSharder();
    $bq = new BigQueryClient($BIGQUERY_KEY);
} catch (Exception $e) {
    die(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
}

// Carregar progresso
$progress = loadProgress();
if ($progress['status'] === 'running' && (time() - $progress['last_update'] < 10)) {
    exit; // Já rodando
}

if ($action !== 'start') exit;

$progress['status'] = 'running';
$progress['start_time'] = $progress['start_time'] ?? time();
updateProgress($progress);

$tablesToProcess = ['empresas', 'estabelecimentos', 'socios'];

try {
    foreach ($tablesToProcess as $table) {
        // Se já concluiu esta tabela, pula
        if ($progress['completed_tables'][$table] ?? false) continue;

        $progress['current_table'] = $table;
        updateProgress($progress);

        // Montar SQL do BigQuery
        // Filtro: ano=2026, mes=2 (conforme solicitado)
        $sql = "SELECT * FROM `basedosdados.br_me_cnpj.{$table}` WHERE ano = 2026 AND mes = 2";
        
        // Se temos um pageToken salvo, retomamos dele
        $pageToken = $progress['bq_page_token'] ?? null;
        $jobId = $progress['bq_job_id'] ?? null;

        if ($jobId && $pageToken) {
            $result = $bq->getNextPage($jobId, $pageToken);
        } else {
            $result = $bq->query($sql);
            $progress['bq_job_id'] = $result['jobId'];
        }

        while (true) {
            if (empty($result['rows'])) break;

            foreach ($result['rows'] as $row) {
                $sharder->addToBatch($table, $row);
                $progress['total_rows']++;
            }

            // Flush final para o que restou nos batches desse chunk
            $sharder->flushAll();

            $progress['bq_page_token'] = $result['pageToken'];
            $progress['last_update'] = time();
            updateProgress($progress);

            // Se terminou as páginas do BigQuery
            if (!$result['pageToken']) break;

            // Verificar se o usuário pausou
            if (checkPaused()) exit;

            // Próxima página
            $result = $bq->getNextPage($progress['bq_job_id'], $progress['bq_page_token']);
        }

        $progress['completed_tables'][$table] = true;
        unset($progress['bq_page_token'], $progress['bq_job_id']); // Limpa para a próxima tabela
        updateProgress($progress);
    }

    $progress['status'] = 'completed';
    updateProgress($progress);

} catch (Exception $e) {
    file_put_contents($LOG_DIR . '/import_errors.log', "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . "\n", FILE_APPEND);
    $progress['status'] = 'error';
    $progress['last_error'] = $e->getMessage();
    updateProgress($progress);
}


// --- Funções Auxiliares ---

function loadProgress() {
    global $PROGRESS_FILE;
    if (file_exists($PROGRESS_FILE)) {
        return json_decode(file_get_contents($PROGRESS_FILE), true);
    }
    return [
        'status' => 'idle',
        'total_rows' => 0,
        'current_table' => '',
        'completed_tables' => [],
        'bq_job_id' => null,
        'bq_page_token' => null,
        'start_time' => time(),
        'last_update' => time()
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
