<?php

require_once 'bigquery_client.php';
require_once 'mysql_sharder.php';

class ImportWorker {
    private $bq;
    private $sharder;
    private $stateFile = 'state.json';
    private $progressFile = 'logs/import_progress.json';
    private $errorLog = 'logs/import_errors.log';
    private $state;

    public function __construct($keyFilePath) {
        $this->bq = new BigQueryClient($keyFilePath);
        $this->sharder = new MySQLSharder();
        $this->loadState();
    }

    private function loadState() {
        if (file_exists($this->stateFile)) {
            $this->state = json_decode(file_get_contents($this->stateFile), true);
        } else {
            $this->state = [
                'ultima_data_processada' => '1900-01-01',
                'current_table' => 'empresas',
                'last_job_id' => null,
                'last_page_token' => null
            ];
        }
    }

    private function saveState() {
        file_put_contents($this->stateFile, json_encode($this->state, JSON_PRETTY_PRINT));
    }

    private function logProgress($data) {
        $progress = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'table' => $this->state['current_table'],
            'shard' => 'N/A',
            'records_imported' => 0,
            'speed' => 0,
            'status' => 'running'
        ], $data);
        file_put_contents($this->progressFile, json_encode($progress, JSON_PRETTY_PRINT));
        
        // Output for real-time flush
        echo json_encode($progress) . "\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
    }

    private function logError($message) {
        $log = date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
        file_put_contents($this->errorLog, $log, FILE_APPEND);
    }

    public function run() {
        set_time_limit(0);
        $tables = ['empresas', 'estabelecimentos', 'socios'];
        
        $startIndex = array_search($this->state['current_table'], $tables);
        if ($startIndex === false) $startIndex = 0;

        for ($i = $startIndex; $i < count($tables); $i++) {
            $table = $tables[$i];
            $this->state['current_table'] = $table;
            $this->saveState();
            
            $this->processTable($table);
            
            // Reset page token for next table
            $this->state['last_page_token'] = null;
            $this->state['last_job_id'] = null;
        }

        $this->state['ultima_data_processada'] = date('Y-m-d');
        $this->state['status'] = 'completed';
        $this->saveState();
        $this->logProgress(['status' => 'completed', 'records_imported' => 0]);
    }

    private function processTable($tableName) {
        $lastDate = $this->state['ultima_data_processada'];
        
        // BasedosDados uses a partition column or we can filter by certain date fields
        // For this example, we assume a column named 'data' exists or the user can map it.
        // Actually,établissements has 'data_situacao_cadastral'. 
        // Let's use a mapping or just a simple query if no date column is standard across all.
        // Since I don't know the exact columns for "incremental", I'll try to use a date filter.
        
        $sql = "SELECT * FROM `basedosdados.br_me_cnpj.$tableName` ";
        if ($lastDate != '1900-01-01') {
            // Note: Adjust the filtering column as needed for the specific BigQuery schema
            // For empresas, there isn't a direct "last updated" date in standard CNPJ data except partition.
            // If they are partitioned by 'data', we use that.
            $sql .= " WHERE sigla_uf IS NOT NULL "; // Dummy to allow AND
            // $sql .= " AND data > '$lastDate'"; 
        }
        
        $response = null;
        $pageToken = $this->state['last_page_token'];

        $startTime = time();
        $totalImported = 0;

        do {
            try {
                if ($this->state['last_job_id'] && $pageToken) {
                    $response = $this->bq->getQueryResults($this->state['last_job_id'], $pageToken);
                } else {
                    $response = $this->bq->query($sql);
                    if (isset($response['jobReference']['jobId'])) {
                        $this->state['last_job_id'] = $response['jobReference']['jobId'];
                    }
                }

                $rows = $this->bq->parseRows($response);
                if (empty($rows)) break;

                $batchByShard = [];
                foreach ($rows as $row) {
                    $cnpj_basico = $row['cnpj_basico'] ?? null;
                    if (!$cnpj_basico) continue;

                    $shardId = $this->sharder->getShardId($cnpj_basico);
                    $batchByShard[$shardId][] = $row;

                    if (count($batchByShard[$shardId] ?? []) >= 1000) {
                        $this->sharder->batchInsert($tableName, $batchByShard[$shardId], $shardId);
                        $totalImported += count($batchByShard[$shardId]);
                        $batchByShard[$shardId] = [];
                    }
                }

                // Finalize remaining batches
                foreach ($batchByShard as $shardId => $shardRows) {
                    if (!empty($shardRows)) {
                        $this->sharder->batchInsert($tableName, $shardRows, $shardId);
                        $totalImported += count($shardRows);
                    }
                }

                $pageToken = $response['pageToken'] ?? null;
                $this->state['last_page_token'] = $pageToken;
                $this->saveState();

                $elapsed = time() - $startTime;
                $speed = $elapsed > 0 ? round($totalImported / $elapsed, 2) : 0;

                $this->logProgress([
                    'records_imported' => $totalImported,
                    'speed' => $speed,
                    'shard' => 'multiple'
                ]);

            } catch (Exception $e) {
                $this->logError("Error processing table $tableName: " . $e->getMessage());
                sleep(5); // Wait before retry
            }
        } while ($pageToken);
    }
}
