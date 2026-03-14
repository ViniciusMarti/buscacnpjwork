<?php

set_time_limit(0);
ini_set('memory_limit','1024M');

$password="qPMwBp#WW*BN6k";

$bancos=[];
for($i=1;$i<=32;$i++){
 $bancos[]="u582732852_buscacnpj".$i;
}

function status(){
 return json_decode(file_get_contents(__DIR__ . "/status.json"),true);
}

function salvar($s){
 file_put_contents(__DIR__ . "/status.json",json_encode($s));
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
  $batch=2000;

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

        $sql="INSERT INTO $tabela ($cols) VALUES ".implode(",",$values);
        $conn->query($sql);
        $conn->close();

        $s["linhas"]+=count($rows);
        $s["db"][$db][$tabela]+=count($rows);

        $tempo=time()-$s["inicio"];
        if($tempo>0){
         $s["velocidade"]=round($s["linhas"]/$tempo);
        }

        salvar($s);
    }

    $rows=[];

    $bancoIndex++;
    if($bancoIndex>=count($bancos))$bancoIndex=0;
   }

  }

  gzclose($gz);
 }

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
