<?php
/**
 * Importador de CNPJ - API de Progresso
 * Local: /public_html/importador/progress.php
 */

header('Content-Type: application/json');

$PROGRESS_FILE = __DIR__ . '/logs/import_progress.json';

if (!file_exists($PROGRESS_FILE)) {
    echo json_encode([
        'status' => 'idle',
        'total_rows' => 0,
        'rate' => 0,
        'elapsed_time' => '00:00:00',
        'error_count' => 0,
        'file_percent' => 0,
        'total_percent' => 0,
        'current_shard' => '-',
        'current_file' => '-',
        'shard_status' => array_fill(1, 32, 'pending')
    ]);
    exit;
}

$data = json_decode(file_get_contents($PROGRESS_FILE), true);

// Cálculos de Tempo
$now = time();
$startTime = $data['start_time'] ?? $now;
$elapsedSeconds = max(1, $now - $startTime);
$hours = floor($elapsedSeconds / 3600);
$mins = floor(($elapsedSeconds % 3600) / 60);
$secs = $elapsedSeconds % 60;
$data['elapsed_time'] = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

// Cálculo de Taxa (Linhas/seg)
$data['rate'] = round($data['total_rows'] / $elapsedSeconds);

// Cálculo de Porcentagem Total
// Considerando 32 shards e 3 arquivos por shard = 96 etapas
$stepsCompleted = 0;
if (isset($data['completed_files'])) {
    foreach ($data['completed_files'] as $shardFiles) {
        foreach ($shardFiles as $done) {
            if ($done) $stepsCompleted++;
        }
    }
}
$data['total_percent'] = round(($stepsCompleted / 96) * 100);

// Porcentagem do arquivo atual (Estimativa baseada em linhas, fixa em 0 se não souber total)
// Como é streaming, não sabemos o final sem ler tudo. Mostramos apenas progresso visual de atividade.
$data['file_percent'] = ($data['current_file_line'] % 100); 

echo json_encode($data);
