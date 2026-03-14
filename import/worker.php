<?php

set_time_limit(0);
ignore_user_abort(true);
ini_set('memory_limit','1024M');

// Force error logging to a file in the same directory
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

$lockFile = __DIR__ . "/worker.lock";
// Check if lock file is older than 5 minutes (zombie process)
if (file_exists($lockFile) && (time() - filemtime($lockFile) > 300)) {
    unlink($lockFile);
}

$lockHandle = fopen($lockFile, 'w');
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    die("Erro: Outra instância do worker já está rodando.");
}
// Touch file to update timestamp (heartbeat)
touch($lockFile);

$startTime = time();
$maxExecutionTime = 120; // 2 minutes per cycle

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

$connections = [];

function conn($db){
    global $password, $connections;
    if (isset($connections[$db]) && $connections[$db]->ping()) {
        return $connections[$db];
    }
    
    $conn = @new mysqli("localhost", $db, $password, $db);
    if ($conn->connect_error) {
        error_log("Falha na conexão com $db: " . $conn->connect_error);
        return false;
    }
    $connections[$db] = $conn;
    return $conn;
}

function closeAllConns() {
    global $connections;
    foreach ($connections as $c) {
        $c->close();
    }
    $connections = [];
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
            $q = $conn->query("SELECT (SUM(data_length+index_length)/1024/1024) size FROM information_schema.tables WHERE table_schema='$db'");
            if ($q) {
                $r = $q->fetch_assoc();
                $s["db"][$db]["size"] = round($r["size"] ?? 0, 2);
            }
            $conn->close();
        }
        $nextDbToUpdate = ($nextDbToUpdate + 1) % 32;
    }
}

function importar($pasta, $tabela){
    global $bancos, $lockFile, $lockHandle, $startTime, $maxExecutionTime;

    $s = status();
    if ($s['fase_completa'][$tabela] ?? false) return; // Skip if already done

    $root = dirname(__DIR__);
    $arquivos = glob("$root/export-cnpj-bd/$pasta/*.gz");
    if (empty($arquivos)) return;
    
    // Safety check: only mark as complete if we were surely at that phase
    // This is handled in the main loop now.

    foreach ($arquivos as $arquivo) {
        $fileKey = basename($arquivo);
        
        // Resume logic: skip files already fully processed
        if (isset($s['arquivos_processados'][$fileKey])) continue;

        $gz = gzopen($arquivo, "r");
        if (!$gz) continue;

        $headerLine = gzgets($gz);
        if (!$headerLine) { gzclose($gz); continue; }
        
        // Clean header: remove strange characters and mapping socio -> nome_socio to match our schema
        $headerLine = str_replace(["\r", "\n"], "", $headerLine);
        $header = str_getcsv($headerLine);
        
        foreach($header as $key => $val) {
            if ($val == "nome" && $tabela == "socio") $header[$key] = "nome_socio";
            if ($val == "qualificacao" && $tabela == "socio") $header[$key] = "qualificacao_socio";
        }

        $headerStr = implode(",", $header);

        // Identify the column to use for Sharding
        $shardColIndex = -1;
        if ($tabela == "empresas" || $tabela == "socio") {
            $shardColIndex = array_search("cnpj_basico", $header);
        } elseif ($tabela == "estabelecimento") {
            $shardColIndex = array_search("cnpj", $header);
            if ($shardColIndex === false) $shardColIndex = array_search("cnpj_basico", $header);
        }

        if ($shardColIndex === false || $shardColIndex === -1) {
            error_log("Worker: Erro ao identificar coluna de Shard para $tabela. Header: $headerStr");
            gzclose($gz);
            continue;
        }

        $lineCount = 0;
        $batch = [];
        $batchLimit = 5000;
        
        $rowsToSkip = $s['current_file_offset'][$fileKey] ?? 0;
        $skipped = 0;

        while (!gzeof($gz)) {
            $line = gzgets($gz);
            if (!$line) continue;

            if ($skipped < $rowsToSkip) {
                $skipped++;
                continue;
            }
            
            $row = str_getcsv($line);
            if (!isset($row[$shardColIndex]) || empty($row[$shardColIndex])) continue;

            // Sharding Algorithm: (cnpj_basico % 32)
            $cnpj_val = preg_replace('/[^0-9]/', '', $row[$shardColIndex]);
            if (strlen($cnpj_val) < 8) continue;
            
            $cnpj_basico = substr($cnpj_val, 0, 8);
            $shardIndex = (intval($cnpj_basico) % 32); 
            
            $batch[$shardIndex][] = $row;
            $lineCount++;

            // Heartbeat every 1000 lines
            if ($lineCount % 1000 == 0) {
                touch($lockFile);
            }

            if (count($batch, COUNT_RECURSIVE) - count($batch) >= 1000) {
                processBatch($batch, $tabela, $headerStr);
                
                $s = status();
                $now = time();
                $timeDiff = $now - $s["last_update"];
                if ($timeDiff > 0) {
                    $s["velocidade"] = round($lineCount / $timeDiff);
                }
                
                $s["linhas"] += $lineCount;
                $s["current_file_offset"][$fileKey] = ($s["current_file_offset"][$fileKey] ?? 0) + $lineCount;
                $s["last_update"] = $now;
                updateSizes($s);
                salvar($s);
                
                $batch = [];
                $lineCount = 0;

                // Self-termination / Auto-renewal logic
                if (time() - $startTime > $maxExecutionTime) {
                    gzclose($gz);
                    closeAllConns();
                    flock($lockHandle, LOCK_UN);
                    fclose($lockHandle);
                    
                    // Trigger successor before dying
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $url = "$protocol://$host" . $_SERVER['PHP_SELF'];
                    
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'ImportWorker/1.0');
                    curl_exec($ch);
                    curl_close($ch);
                    
                    error_log("Worker cycle finished. Successor triggered: $url");
                    exit("Cycle finished. Successor triggered.");
                }
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
        unset($s['current_file_offset'][$fileKey]);
        salvar($s);
        gzclose($gz);
    }

    // Mark as complete ONLY if we finished the loop of files
    $s = status();
    if (!empty($arquivos) && !isset($s['current_file_offset'])) {
        // This is a safety check: if we processed all files, mark phase complete
        $s['fase_completa'][$tabela] = true;
        salvar($s);
    }
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
        
        $dbKey = $tabela;
        if ($tabela == "estabelecimentos") $dbKey = "estabelecimento";
        if ($tabela == "socios") $dbKey = "socio";

        $s["db"][$db][$dbKey] += count($rows);
        // Removed $conn->close() here to keep connection cached
    }
    salvar($s);
}

// MAIN EXECUTION
$s = status();
$s["running"] = true;
$s["last_update"] = time();
if (!isset($s['fase_completa'])) $s['fase_completa'] = [];
salvar($s);

$fases = [
    "estabelecimentos" => "estabelecimento",
    "socios" => "socio"
];

foreach ($fases as $pasta => $tabela) {
    $s = status(); 
    if (!($s['fase_completa'][$tabela] ?? false)) {
        
        $root = dirname(__DIR__);
        $targetDir = $root . "/export-cnpj-bd/$pasta";
        $arquivos = glob("$targetDir/*.gz");
        
        error_log("Worker: Verificando pasta $targetDir. Arquivos: " . count($arquivos));

        if (empty($arquivos)) {
            error_log("Worker: Nao foram encontrados arquivos em $targetDir. Aguardando...");
            // Don't mark as complete automatically unless we are at the end of the script
            // and surely verified everything. For now, let's just skip this phase in this cycle.
            continue; 
        }

        $s["fase"] = $tabela;
        salvar($s);
        
        error_log("Worker: Iniciando fase: $tabela na pasta $pasta");
        importar($pasta, $tabela);
        
        // After importing, check if we finished all files in this folder
        $s = status();
        $completos = 0;
        foreach($arquivos as $f) {
            if (isset($s['arquivos_processados'][basename($f)])) $completos++;
        }
        
        error_log("Worker: Fase $tabela progresso: $completos / " . count($arquivos));

        if ($completos >= count($arquivos)) {
            $s['fase_completa'][$tabela] = true;
            error_log("Worker: Fase $tabela finalizada com sucesso.");
        }
        salvar($s);
        break; 
    }
}

$s = status();
$s["running"] = false;
$s["last_update"] = time();
$s["velocidade"] = 0;
salvar($s);

closeAllConns();
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
unlink($lockFile);
