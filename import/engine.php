<?php

error_reporting(E_ALL);
ini_set('display_errors',1);
set_time_limit(0);

// Ensure the directory exists for status.json
if (!file_exists(__DIR__)) {
    mkdir(__DIR__, 0777, true);
}

$status=[
 "running"=>true,
 "fase"=>"empresas",
 "linhas"=>0,
 "inicio"=>time(),
 "velocidade"=>0,
 "eta"=>0,
 "db"=>[]
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

file_put_contents(__DIR__ . "/status.json",json_encode($status));

// Note: Background execution logic for Linux/Hostinger as requested
exec("php worker.php > /dev/null 2>&1 &");

echo "IMPORTAÇÃO INICIADA";
