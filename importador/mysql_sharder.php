<?php

class MySQLSharder {
    private $host = '193.203.175.195';
    private $password = 'qPMwBp#WW*BN6k';
    private $connections = [];
    private $dbPrefix = 'u582732852_buscacnpj';
    private $shardCount = 32;

    public function getShardId($cnpj_basico) {
        // shard = (cnpj_basico % 32) + 1
        return (intval($cnpj_basico) % $this->shardCount) + 1;
    }

    public function getConnection($shardId) {
        if (isset($this->connections[$shardId])) {
            return $this->connections[$shardId];
        }

        $dbName = $this->dbPrefix . $shardId;
        $user = $dbName; // As per user instructions: user = nome do banco

        try {
            $dsn = "mysql:host={$this->host};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, $user, $this->password, $options);
            $this->connections[$shardId] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            error_log("Connection failed for shard $shardId: " . $e->getMessage());
            throw $e;
        }
    }

    public function closeConnections() {
        $this->connections = [];
    }

    public function checkIfExist($table, $cnpj_basico, $shardId) {
        $pdo = $this->getConnection($shardId);
        $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE cnpj_basico = ? LIMIT 1");
        $stmt->execute([$cnpj_basico]);
        return (bool)$stmt->fetch();
    }

    public function batchInsert($table, $data, $shardId) {
        if (empty($data)) return;

        $pdo = $this->getConnection($shardId);
        
        $columns = array_keys($data[0]);
        $columnList = implode(', ', $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($data), $placeholders));

        $sql = "INSERT IGNORE INTO $table ($columnList) VALUES $allPlaceholders";
        
        $flatValues = [];
        foreach ($data as $row) {
            foreach ($columns as $col) {
                $flatValues[] = $row[$col];
            }
        }

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($flatValues);
    }
}
