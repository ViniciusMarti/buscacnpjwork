<?php
/**
 * Importador de CNPJ - Handler de Upload
 * Local: /public_html/importador/upload_handler.php
 */

header('Content-Type: application/json');

// Configurações
$BASE_SHARDS_PATH = realpath(__DIR__ . '/../shards');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Método não permitido']));
}

$shard = $_POST['shard'] ?? '';
$type = $_POST['type'] ?? ''; // empresas, estabelecimentos, socios
$file = $_FILES['file'] ?? null;

if (!$shard || !$type || !$file) {
    die(json_encode(['success' => false, 'message' => 'Dados incompletos']));
}

// Validar Shard (1-32)
$shardInt = (int)$shard;
if ($shardInt < 1 || $shardInt > 32) {
    die(json_encode(['success' => false, 'message' => 'Shard inválido']));
}

// Validar Tipo
$validTypes = ['empresas', 'estabelecimentos', 'socios'];
if (!in_array($type, $validTypes)) {
    die(json_encode(['success' => false, 'message' => 'Tipo de arquivo inválido']));
}

// Validar Extensão
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext2 = pathinfo(str_replace('.gz', '', $file['name']), PATHINFO_EXTENSION);

if (!str_ends_with($file['name'], '.csv.gz') && !str_ends_with($file['name'], '.gz')) {
     // Permite .gz genérico caso o usuário tenha renomeado
}

$targetDir = "{$BASE_SHARDS_PATH}/buscacnpj{$shardInt}";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$targetFile = "{$targetDir}/{$type}.csv.gz";

// Verificar erros de upload (importante para arquivos grandes)
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite definido no php.ini (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite definido no formulário HTML.',
        UPLOAD_ERR_PARTIAL    => 'O upload foi feito apenas parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo foi enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco.',
        UPLOAD_ERR_EXTENSION  => 'Uma extensão do PHP interrompeu o upload.',
    ];
    $msg = $errors[$file['error']] ?? 'Erro desconhecido no upload.';
    die(json_encode(['success' => false, 'message' => $msg]));
}

// Mover arquivo
if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    chmod($targetFile, 0644);
    echo json_encode([
        'success' => true, 
        'message' => "Arquivo {$type}.csv.gz enviado com sucesso para Shard {$shardInt}",
        'path' => $targetFile
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao mover o arquivo para a pasta final. Verifique permissões.']);
}
