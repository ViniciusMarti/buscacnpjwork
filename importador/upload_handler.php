<?php
/**
 * Importador de CNPJ - Handler de Upload em Massa
 * Local: /public_html/importador/upload_handler.php
 */

header('Content-Type: application/json');

// Configurações
$BASE_SHARDS_PATH = dirname(__DIR__) . '/shards'; // Caminho absoluto manual

// Se a pasta base 'shards' não existe, tenta criar
if (!is_dir($BASE_SHARDS_PATH)) {
    @mkdir($BASE_SHARDS_PATH, 0777, true);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Método não permitido']));
}

$files = $_FILES['files'] ?? null;
$paths = $_POST['paths'] ?? []; // Caminhos relativos enviados pelo JS

if (!$files || empty($files['name'][0])) {
    die(json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']));
}

$results = [
    'success' => true,
    'uploaded' => [],
    'errors' => []
];

foreach ($files['name'] as $key => $originalName) {
    if ($files['error'][$key] !== UPLOAD_ERR_OK) {
        $results['errors'][] = "Erro no arquivo $originalName: Cod " . $files['error'][$key];
        continue;
    }

    // Usamos o caminho relativo (path) para detectar o banco, pois ele contém o nome da pasta
    $relativePath = strtolower($paths[$key] ?? $originalName);
    
    // 1. Detectar o Shard (Banco)
    // Procuramos por buscacnpj seguido de números (1 a 32)
    preg_match('/buscacnpj([1-9]|[12]\d|3[0-2])(?![0-9])/', $relativePath, $matches);
    $shardNum = isset($matches[1]) ? (int)$matches[1] : null;

    if (!$shardNum || $shardNum < 1 || $shardNum > 32) {
        $results['errors'][] = "Não foi possível identificar o Banco (1-32) no caminho: $relativePath";
        continue;
    }

    // 2. Detectar o Tipo
    // Pegamos apenas o nome do arquivo final para evitar que nomes de pastas interfiram
    $fileNameOnly = basename($relativePath);
    $detectedType = null;
    $types = ['estabelecimentos', 'empresas', 'socios']; // Ordem específica para evitar match parcial se houver
    
    foreach ($types as $t) {
        if (strpos($fileNameOnly, $t) !== false) {
            $detectedType = $t;
            break;
        }
    }

    // Fallback: Se não achou no nome do arquivo, procura no caminho todo (menos comum)
    if (!$detectedType) {
        foreach ($types as $t) {
            if (strpos($relativePath, $t) !== false) {
                $detectedType = $t;
                break;
            }
        }
    }

    if (!$detectedType) {
        $results['errors'][] = "Não foi possível identificar o tipo de dados (empresas, estabelecimentos, socios) no arquivo: $originalName";
        continue;
    }

    // 3. Definir Destino
    $targetDir = "{$BASE_SHARDS_PATH}/buscacnpj{$shardNum}";
    
    // Tenta criar o diretório se não existir com permissões amplas
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            $results['errors'][] = "Erro: Sem permissão para criar pasta do Banco {$shardNum} em: $targetDir";
            continue;
        }
        chmod($targetDir, 0777);
    }

    // Verifica se a pasta tem permissão de escrita
    if (!is_writable($targetDir)) {
        chmod($targetDir, 0777); // Tenta corrigir na hora
        if (!is_writable($targetDir)) {
            $results['errors'][] = "Erro: Pasta do Banco {$shardNum} não permite escrita.";
            continue;
        }
    }

    // Salva sempre no formato padrão que o importador espera
    $finalFileName = "{$detectedType}.csv.gz";
    if (strpos($relativePath, '.gz') === false && strpos($relativePath, '.zip') === false) {
        $finalFileName = "{$detectedType}.csv";
    }

    $targetFile = "{$targetDir}/{$finalFileName}";

    // Se já existe, tenta apagar antes (evita conflito de permissão em sobrescrita)
    if (file_exists($targetFile)) {
        @unlink($targetFile);
    }

    if (move_uploaded_file($files['tmp_name'][$key], $targetFile)) {
        chmod($targetFile, 0666);
        $results['uploaded'][] = "Banco {$shardNum} > {$finalFileName}";
    } else {
        $uploadErrValue = $files['error'][$key];
        $results['errors'][] = "Erro ao mover arquivo para {$targetFile} (PHP Error: {$uploadErrValue}). Verifique permissões da pasta shards.";
    }
}

if (empty($results['uploaded']) && !empty($results['errors'])) {
    $results['success'] = false;
}

echo json_encode($results);
