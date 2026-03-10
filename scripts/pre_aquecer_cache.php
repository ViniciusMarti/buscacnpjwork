<?php
/**
 * Script para pré-aquecer o cache das cidades mais importantes.
 * Isso evita a busca custosa de nomes de cidades via slug no MySQL.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/utils.php';

set_time_limit(0);
ini_set('memory_limit', '1G');

$cache_dir = __DIR__ . '/../cache/cidades';
if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);

$log_file = __DIR__ . '/pre_aquecer.log';
$timestamp = date('Y-m-d H:i:s');

file_put_contents($log_file, "[$timestamp] Iniciando aquecimento de cache...\n", FILE_APPEND);

try {
    $connections = getAllConnections();
    
    foreach ($connections as $dbName => $db) {
        echo "Processando cidades no banco: $dbName\n";
        
        // Busca as cidades com mais empresas ativas (as mais prováveis de serem acessadas)
        $stmt = $db->query("
            SELECT uf, municipio, COUNT(*) as total 
            FROM dados_cnpj 
            WHERE situacao = 'ATIVA' 
            GROUP BY uf, municipio 
            HAVING total > 100
            ORDER BY total DESC 
            LIMIT 500
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $uf = $row['uf'];
            $municipio = $row['municipio'];
            
            // Gera o slug da cidade (mesma lógica do cidade.php)
            $slug = strtolower(str_replace(' ', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $municipio)));
            $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
            
            $cache_file = $cache_dir . '/' . strtolower($uf) . '_' . $slug . '.json';
            
            // Salva o nome real p/ o slug de forma instantânea
            $data = [
                'nome_real' => $municipio,
                'uf' => $uf,
                'total_empresas' => $row['total'],
                'gerado_em' => time()
            ];
            
            file_put_contents($cache_file, json_encode($data));
        }
    }
    
    file_put_contents($log_file, "[$timestamp] Aquecimento concluído.\n", FILE_APPEND);
    echo "Sucesso! Cache de cidades gerado.\n";

} catch (Exception $e) {
    file_put_contents($log_file, "[$timestamp] Erro: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Erro: " . $e->getMessage() . "\n";
}
