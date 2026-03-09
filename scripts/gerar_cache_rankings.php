<?php
/**
 * Script para pré-gerar o cache dos rankings de todos os estados.
 * Isso evita que o primeiro usuário a acessar a página sofra com o timeout.
 * 
 * Uso: php scripts/gerar_cache_rankings.php
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/utils.php';

set_time_limit(0); // Sem limite de tempo
ini_set('memory_limit', '1G');

$states_data = get_states_data();
$states = $states_data['slugs'];

$db = getDB();
$cache_dir = __DIR__ . '/../cache/rankings';
if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);

echo "Iniciando geração de cache para " . count($states) . " estados...\n";

foreach ($states as $slug => $uf) {
    echo "Processando $uf ($slug)... ";
    $start_state = microtime(true);

    try {
        // 1. Contagens Principais
        $stmt_main = $db->prepare("
            SELECT 
                COUNT(*) as total_count, 
                SUM(capital_social) as total_capital
            FROM dados_cnpj 
            WHERE situacao = 'ATIVA' AND uf = :uf
        ");
        $stmt_main->execute([':uf' => $uf]);
        $main_data = $stmt_main->fetch(PDO::FETCH_ASSOC);

        $count_total = $main_data['total_count'] ?: 0;
        $capital_total = $main_data['total_capital'] ?: 0;

        // 2. Top Cidades
        $stmt_cities_top = $db->prepare("
            SELECT municipio, COUNT(*) as total 
            FROM dados_cnpj 
            WHERE situacao = 'ATIVA' AND uf = :uf 
            GROUP BY municipio 
            ORDER BY total DESC 
            LIMIT 10
        ");
        $stmt_cities_top->execute([':uf' => $uf]);
        $top_cities_list = $stmt_cities_top->fetchAll(PDO::FETCH_ASSOC);
        
        $top_city = !empty($top_cities_list) ? $top_cities_list[0] : ['municipio' => 'Nenhum', 'total' => 0];
        $concentration_perc = ($count_total > 0) ? ($top_city['total'] / $count_total) * 100 : 0;

        // 3. Setor Dominante
        $stmt_cnae = $db->prepare("
            SELECT cnae_principal_descricao, COUNT(*) as c 
            FROM dados_cnpj 
            WHERE situacao = 'ATIVA' AND uf = :uf 
            AND cnae_principal_descricao NOT LIKE 'Consulte%' 
            GROUP BY cnae_principal_descricao 
            ORDER BY c DESC LIMIT 1
        ");
        $stmt_cnae->execute([':uf' => $uf]);
        $top_cnae = $stmt_cnae->fetch(PDO::FETCH_ASSOC) ?: ['cnae_principal_descricao' => 'Nenhum', 'c' => 0];

        $stats = [
            'count_total' => $count_total,
            'capital_total' => $capital_total,
            'avg_age' => 12, // Simplificado
            'top_cities_list' => $top_cities_list,
            'top_city' => $top_city,
            'concentration_perc' => $concentration_perc,
            'top_cnae' => $top_cnae,
            'updated_at' => time()
        ];
        
        $cache_file = $cache_dir . '/stats_' . strtolower($uf) . '.json';
        file_put_contents($cache_file, json_encode($stats));

        $end_state = microtime(true);
        echo "OK (" . round($end_state - $start_state, 2) . "s)\n";

    } catch (Exception $e) {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}

// --- NOVO: GERAR CACHE NACIONAL (TOP 10 BRASIL) ---
echo "Processando Ranking Nacional (Brasil)... ";
try {
    $start_br = microtime(true);
    $stmt_top = $db->query("SELECT * FROM dados_cnpj WHERE situacao = 'ATIVA' AND capital_social > 0 ORDER BY capital_social DESC LIMIT 10");
    $top_br = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
    
    $cache_file_br = $cache_dir . '/stats_brazil.json';
    file_put_contents($cache_file_br, json_encode($top_br));
    
    $end_br = microtime(true);
    echo "OK (" . round($end_br - $start_br, 2) . "s)\n";
} catch (Exception $e) {
    echo "ERRO NACIONAL: " . $e->getMessage() . "\n";
}

echo "\nFIM! Cache (Estados + Brasil) gerado com sucesso.\n";
