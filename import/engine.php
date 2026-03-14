<?php

error_reporting(E_ALL);
ini_set('display_errors',1);

$statusFile = __DIR__ . "/status.json";

if (isset($_GET['reset']) || !file_exists($statusFile)) {
    $status=[
     "running"=>true,
     "fase"=>"empresas",
     "linhas"=>0,
     "inicio"=>time(),
     "last_update"=>time(),
     "velocidade"=>0,
     "eta"=>0,
     "db"=>[],
     "arquivos_processados"=>[],
     "fase_completa"=>[]
    ];

    for($i=1;$i<=32;$i++){
     $db="u582732852_buscacnpj".$i;
     $status["db"][$db]=[
      "empresas"=>0,
      "estabelecimento"=>0,
      "socio"=>0,
      "size"=>0
     ];
    }
    file_put_contents($statusFile, json_encode($status));
} else {
    // Resume logic: just update the running status
    $status = json_decode(file_get_contents($statusFile), true);
    $status["running"] = true;
    $status["last_update"] = time();
    file_put_contents($statusFile, json_encode($status));
}

// Trigger worker via Web (Hostinger safe)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['PHP_SELF']);
$url = "$protocol://$host$path/worker.php";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 1);
curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);

echo "IMPORTAÇÃO ATIVADA/RESUMIDA";
