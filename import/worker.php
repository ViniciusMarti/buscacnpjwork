<?php

set_time_limit(0);
ignore_user_abort(true);
ini_set('memory_limit','1024M');

// Force error logging to a file in the same directory
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

$lockFile = __DIR__ . "/worker.lock";
$lockHandle = fopen($lockFile, 'w');
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    die("Erro: Outra instância do worker já está rodando.");
}

$password="qPMwBp#WW*BN6k";

$bancos=[];
for($i=1;$i<=32;$i++){
 $bancos[]="u582732852_buscacnpj".$i;
}

function status(){
 return json_decode(file_get_contents(__DIR__ . "/status.json"),true);
}

function salvar($s){
 file_put_contents(__DIR__ . "/status.json",json_encode($s), LOCK_EX);
}

function conn($db){
 global $password;
 $conn = @new mysqli("localhost", $db, $password, $db);
 if ($conn->connect_error) {
     error_log("Falha na conexão com $db: " . $conn->connect_error);
     return false;
 }
 return $conn;
}

// Global state to track which DB size to update next
$nextDbToUpdate = 0;

function updateSizes(&$s) {
    global $bancos, $password, $nextDbToUpdate;
    
    // Update only 2 databases per call to save time
    for ($i=0; $i<2; $i++) {
        $db = $bancos[$nextDbToUpdate];
        $conn = @new mysqli("localhost", $db, $password, $db);
        if ($conn && !$conn->connect_error) {
            $q = $conn->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) size FROM information_schema.tables WHERE table_schema='$db'");
            if ($q) {
                $r = $q->fetch_assoc();
                $s["db"][$db]["size"] = $r["size"] ?? 0;
            }
            $conn->close();
        }
        $nextDbToUpdate = ($nextDbToUpdate + 1) % 32;
    }
}

function importar($pasta, $tabela){
    global $bancos;

    $s = status();
    if ($s['fase_completa'][$tabela] ?? false) return; // Skip if already done

    $arquivos = glob("../export-cnpj-bd/$pasta/*.gz");
    if (empty($arquivos)) return;

    foreach ($arquivos as $arquivo) {
        $fileKey = basename($arquivo);
        
        // Resume logic: skip files already fully processed
        if (isset($s['arquivos_processados'][$fileKey])) continue;

        $gz = gzopen($arquivo, "r");
        if (!$gz) continue;

        $headerLine = gzgets($gz);
        if (!$headerLine) { gzclose($gz); continue; }
        $header = str_getcsv($headerLine);
        $headerStr = implode(",", $header);

        $lineCount = 0;
        $batch = [];
        $batchLimit = 10000; // Large batch to group by shard

        while (!gzeof($gz)) {
            $line = gzgets($gz);
            if (!$line) continue;
            
            $row = str_getcsv($line);
            if (empty($row[0])) continue;

            // Sharding Algorithm: (cnpj_basico % 32) + 1
            $cnpj_basico = preg_replace('/[^0-9]/', '', $row[0]);
            if (!$cnpj_basico) continue;
            $shardIndex = (intval($cnpj_basico) % 32); 
            
            $batch[$shardIndex][] = $row;
            $lineCount++;

            if ($lineCount >= $batchLimit) {
                processBatch($batch, $tabela, $headerStr);
                
                $s = status();
                $s["linhas"] += $lineCount;
                $s["last_update"] = time();
                updateSizes($s);
                salvar($s);
                
                $batch = [];
                $lineCount = 0;
            }
        }

        // Finalize remaining batch for this file
        if (!empty($batch)) {
            processBatch($batch, $tabela, $headerStr);
            $s = status();
            $s["linhas"] += $lineCount;
            $s["last_update"] = time();
            salvar($s);
        }

        $s = status();
        $s['arquivos_processados'][$fileKey] = true;
        salvar($s);
        gzclose($gz);
    }

    $s = status();
    $s['fase_completa'][$tabela] = true;
    salvar($s);
}

function processBatch($batchByShard, $tabela, $headerStr) {
    global $bancos;
    $s = status();
    
    foreach ($batchByShard as $shardIndex => $rows) {
        $db = $bancos[$shardIndex];
        $conn = conn($db);
        if (!$conn) continue;

        $values = [];
        foreach ($rows as $r) {
            $esc = array_map([$conn, 'real_escape_string'], $r);
            $values[] = "('" . implode("','", $esc) . "')";
        }

        $sql = "INSERT IGNORE INTO $tabela ($headerStr) VALUES " . implode(",", $values);
        if (!$conn->query($sql)) {
            error_log("Erro no SQL para $db: " . $conn->error);
        }
        
        $s["db"][$db][$tabela] += count($rows);
        $conn->close();
    }
    salvar($s);
}

// MAIN EXECUTION
$s = status();
$s["running"] = true;
$s["last_update"] = time();
salvar($s);

$s["fase"] = "empresas"; salvar($s);
importar("empresas", "empresas");

$s["fase"] = "estabelecimentos"; salvar($s);
importar("estabelecimentos", "estabelecimento");

$s["fase"] = "socios"; salvar($s);
importar("socios", "socio");

$s = status();
$s["running"] = false;
$s["last_update"] = time();
salvar($s);

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
unlink($lockFile);
