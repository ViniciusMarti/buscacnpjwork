<?php
echo "<pre>";
echo "DIR: " . __DIR__ . "\n";
$pasta = "estabelecimentos";
$basePath = realpath(__DIR__ . "/..");
echo "BASE: " . $basePath . "\n";
$targetDir = $basePath . "/export-cnpj-bd/$pasta";
echo "TARGET: " . $targetDir . "\n";
$arquivos = glob("$targetDir/*.gz");
echo "ARQUIVOS (Absolute): " . count($arquivos) . "\n";

$relDir = "../export-cnpj-bd/$pasta";
$arquivosRel = glob("$relDir/*.gz");
echo "ARQUIVOS (Relative): " . count($arquivosRel) . "\n";
if (count($arquivosRel)>0) echo "EX: " . $arquivosRel[0] . "\n";
echo "</pre>";
