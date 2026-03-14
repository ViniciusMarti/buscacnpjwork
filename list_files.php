<?php
$pastas = ['empresas', 'estabelecimentos', 'socios'];
foreach ($pastas as $p) {
    $arquivos = glob(__DIR__ . "/export-cnpj-bd/$p/*.gz");
    echo "Pasta $p: " . count($arquivos) . " arquivos .gz\n";
    if (count($arquivos) > 0) {
        echo "Ex: " . basename($arquivos[0]) . "\n";
    }
}
