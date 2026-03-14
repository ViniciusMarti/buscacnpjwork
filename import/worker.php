<?php

set_time_limit(0);
ignore_user_abort(true);
ini_set('memory_limit','1024M');

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

function updateSize($dbIndex, &$s) {
    global $bancos, $password;
    $db = $bancos[$dbIndex];
    $conn = @new mysqli("localhost", $db, $password, $db);
    if ($conn && !$conn->connect_error) {
        $q = $conn->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) size FROM information_schema.tables WHERE table_schema='$db'");
        if ($q) {
            $r = $q->fetch_assoc();
            $s["db"][$db]["size"] = $r["size"] ?? 0;
        }
        $conn->close();
    }
}

function conn($db){
 global $password;
 // Hostinger usually uses 'localhost' or '127.0.0.1'
 // The user used $db as both username AND database name in their snippet, 
 // which is common in some Hostinger setups, but often the username is different.
 // I'll stick to their snippet: new mysqli("localhost",$db,$password,$db)
 $conn = new mysqli("localhost", $db, $password, $db);
 if ($conn->connect_error) {
     // Log error but don't stop the whole process if one DB is down? 
     // For now, following their logic.
     return false;
 }
 return $conn;
}

function importar($pasta,$tabela){

 global $bancos;

 $arquivos=glob("../export-cnpj-bd/$pasta/*.gz");
 if (empty($arquivos)) {
     error_log("Nenhum arquivo encontrado em ../export-cnpj-bd/$pasta/");
     return;
 }

 $bancoIndex=0;

 foreach($arquivos as $arquivo){

  $gz=gzopen($arquivo,"r");
  if (!$gz) continue;
  
  $headerLine = gzgets($gz);
  if (!$headerLine) {
      gzclose($gz);
      continue;
  }
  $header=str_getcsv($headerLine);

  $rows=[];
  $batch=5000; // Increased batch size
  $countBatch = 0;

  while(!gzeof($gz)){

   $line=gzgets($gz);
   if(!$line) continue;

   $rows[]=str_getcsv($line);

   if(count($rows)>=$batch){

    $s=status();
    $db=$bancos[$bancoIndex];
    $conn=conn($db);

    if ($conn) {
        $cols=implode(",",$header);
        $values=[];

        foreach($rows as $r){
         $esc=array_map([$conn,'real_escape_string'],$r);
         $values[]="('".implode("','",$esc)."')";
        }

        $sql="INSERT IGNORE INTO $tabela ($cols) VALUES ".implode(",",$values);
        $conn->query($sql);
        $conn->close();

        $s["linhas"]+=count($rows);
        $s["db"][$db][$tabela]+=count($rows);

        $tempo=time()-$s["inicio"];
        if($tempo>0){
         $s["velocidade"]=round($s["linhas"]/$tempo);
        }

        $countBatch++;
        // Update status in file only every 5 batches to avoid excessive I/O
        // And update DB size only occasionally
        if ($countBatch % 5 == 0) {
            updateSize($bancoIndex, $s);
            salvar($s);
        }
    }

    $rows=[];

    $bancoIndex++;
    if($bancoIndex>=count($bancos))$bancoIndex=0;
   }

  }

  // Final status save for the file
  salvar($s);
  gzclose($gz);
 }
 // Final status save for the phase
 updateSize($bancoIndex, $s);
 salvar($s);
}

// Update phase in status
$s = status();
$s["fase"] = "empresas";
salvar($s);
importar("empresas","empresas");

$s = status();
$s["fase"] = "estabelecimentos";
salvar($s);
importar("estabelecimentos","estabelecimento");

$s = status();
$s["fase"] = "socios";
salvar($s);
importar("socios","socio");

$s=status();
$s["running"]=false;
salvar($s);
