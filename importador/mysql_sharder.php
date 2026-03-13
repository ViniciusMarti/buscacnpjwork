<?php
/**
 * Importador BigQuery -> MySQL Shards
 * Manuseia as conexões e roteamento de Shards
 * Local: /public_html/importador/mysql_sharder.php
 */

class MySQLSharder {
    private $host = '193.203.175.195';
    private $pass = 'qPMwBp#WW*BN6k';
    private $connections = [];
    private $batches = [];
    private $batchSize = 1000;

    /**
     * Identifica o shard correto baseado no CNPJ Básico
     */
    public function getShardId($cnpj_basico) {
        $basico = preg_replace('/\D/', '', $cnpj_basico);
        if (empty($basico)) return 1;
        return (intval($basico) % 32) + 1;
    }

    /**
     * Obtém conexão PDO para um shard específico (Lazy Loading)
     */
    private function getConnection($shardId) {
        $dbName = "u582732852_buscacnpj" . $shardId;
        if (isset($this->connections[$shardId])) {
            return $this->connections[$shardId];
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbName, $this->pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->connections[$shardId] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Falha ao conectar ao shard {$shardId}: " . $e->getMessage());
        }
    }

    /**
     * Adiciona um registro ao lote de inserção do shard correspondente
     */
    public function addToBatch($table, $data) {
        // CNPJ Básico é a chave para o sharding e verificação
        $cnpj_basico = $data['cnpj_basico'] ?? substr($data['cnpj'] ?? '', 0, 8);
        $shardId = $this->getShardId($cnpj_basico);

        if (!isset($this->batches[$shardId][$table])) {
            $this->batches[$shardId][$table] = [];
        }

        $this->batches[$shardId][$table][] = $data;

        if (count($this->batches[$shardId][$table]) >= $this->batchSize) {
            return $this->flushBatch($shardId, $table);
        }

        return 0;
    }

    /**
     * Executa a inserção em lote para um shard e tabela
     */
    public function flushBatch($shardId, $table) {
        if (empty($this->batches[$shardId][$table])) return 0;

        $data = $this->batches[$shardId][$table];
        $this->batches[$shardId][$table] = [];

        $pdo = $this->getConnection($shardId);
        
        // Obter colunas do primeiro registro
        $columns = array_keys($data[0]);
        $colString = implode(',', $columns);
        
        // Prepara placeholders (?,?,?)
        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(',', array_fill(0, count($data), $placeholders));

        // Query com IGNORE para evitar duplicados se o registro já existir
        $sql = "INSERT IGNORE INTO {$table} ({$colString}) VALUES {$allPlaceholders}";
        
        try {
            $stmt = $pdo->prepare($sql);
            $values = [];
            foreach ($data as $row) {
                foreach ($columns as $col) {
                    $values[] = $row[$col] ?? null;
                }
            }
            $stmt->execute($values);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Erro no batch MySQL (Shard $shardId, Tabela $table): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Finaliza todos os lotes pendentes
     */
    public function flushAll() {
        $total = 0;
        foreach ($this->batches as $shardId => $tables) {
            foreach ($tables as $table => $data) {
                $total += $this->flushBatch($shardId, $table);
            }
        }
        return $total;
    }

    public function closeConnections() {
        $this->connections = [];
    }
}
