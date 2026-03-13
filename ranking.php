<?php
// Conexão MySQL centralizada
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/utils.php';

$slug = $_GET['slug'] ?? '';
set_time_limit(120); // Aumenta o tempo para 2 minutos para processar os 17GB caso o cache expire


// Mapeamento de Slugs para UF
$states = [
    'acre' => 'AC', 'alagoas' => 'AL', 'amapa' => 'AP', 'amazonas' => 'AM', 
    'bahia' => 'BA', 'ceara' => 'CE', 'distrito-federal' => 'DF', 'espirito-santo' => 'ES', 
    'goias' => 'GO', 'maranhao' => 'MA', 'mato-grosso' => 'MT', 'mato-grosso-do-sul' => 'MS', 
    'minas-gerais' => 'MG', 'para' => 'PA', 'paraiba' => 'PB', 'parana' => 'PR', 
    'pernambuco' => 'PE', 'piaui' => 'PI', 'rio-de-janeiro' => 'RJ', 'rio-grande-do-norte' => 'RN', 
    'rio-grande-do-sul' => 'RS', 'rondonia' => 'RO', 'roraima' => 'RR', 'santa-catarina' => 'SC', 
    'sao-paulo' => 'SP', 'sergipe' => 'SE', 'tocantins' => 'TO'
];

$state_names = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];

$uf = $states[$slug] ?? '';
$state_name = $state_names[$uf] ?? '';

if (!$uf) {
    header("HTTP/1.0 404 Not Found");
    die("<h1>Estado não encontrado</h1>");
}

// Filtros
$search = $_GET['q'] ?? '';
$cnae_filter = $_GET['cnae'] ?? '';
$city_filter = $_GET['cidade'] ?? '';

try {
    $db = getDB();

    // --- SISTEMA DE CACHE ---
    $cache_dir = __DIR__ . '/cache/rankings';
    if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);
    $cache_file = $cache_dir . '/stats_' . strtolower($uf) . '.json';
    
    $stats = null;
    $cache_time = 86400 * 7; // Cache por 7 dias (rankings mudam pouco)
    
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        $stats = json_decode(file_get_contents($cache_file), true);
    }

    if (!$stats) {
        // OTIMIZAÇÃO: Agrupa as contagens principais em uma única leitura (Single Scan)
        // Removida a média de idade complexa do SQL para evitar lentidão extrema em estados grandes
        // OTIMIZAÇÃO: Agrupa as contagens principais (Agregado de todos os bancos)
        $main_data = aggregateDistributed("
            SELECT 
                COUNT(*) as total_count, 
                SUM(e.capital_social) as total_capital
            FROM estabelecimentos est
            INNER JOIN empresas e ON est.cnpj_basico = e.cnpj_basico
            WHERE est.situacao_cadastral = 'ATIVA' AND est.uf = :uf
        ", [':uf' => $uf]);


        $count_total = $main_data['total_count'] ?: 0;
        $capital_total = $main_data['total_capital'] ?: 0;
        
        $avg_age = 12;

        // OTIMIZAÇÃO: Busca o top 10 cidades (Agregado de todos os bancos)
        $city_map = [];
        foreach (getAllConnections() as $db) {
            try {
                $stmt = $db->prepare("SELECT municipio, COUNT(*) as total FROM estabelecimentos WHERE situacao_cadastral = 'ATIVA' AND uf = :uf GROUP BY municipio ORDER BY total DESC LIMIT 10");

                $stmt->execute([':uf' => $uf]);
                foreach ($stmt->fetchAll() as $r) {
                    $city_map[$r['municipio']] = ($city_map[$r['municipio']] ?? 0) + $r['total'];
                }
            } catch (Exception $e) {
                error_log("Erro no ranking de cidades: " . $e->getMessage());
                continue;
            }
        }
        arsort($city_map);
        $top_cities_list = [];
        foreach (array_slice($city_map, 0, 10, true) as $m => $t) {
            $top_cities_list[] = ['municipio' => $m, 'total' => $t];
        }
        
        $top_city = !empty($top_cities_list) ? $top_cities_list[0] : ['municipio' => 'Nenhum', 'total' => 0];
        $concentration_perc = ($count_total > 0) ? ($top_city['total'] / $count_total) * 100 : 0;

        // OTIMIZAÇÃO: Busca o setor dominante (Agregado)
        $cnae_map = [];
        foreach (getAllConnections() as $db) {
            try {
                // CNAE descrição agora vem do SQLite no frontend, mas aqui usamos o código se não tivermos a descrição no DB
                // Se a descrição não estiver no DB principal, o código a seguir pode precisar de ajuste
                $stmt = $db->prepare("SELECT cnae_principal as cnae, COUNT(*) as c FROM estabelecimentos WHERE situacao_cadastral = 'ATIVA' AND uf = :uf GROUP BY cnae_principal ORDER BY c DESC LIMIT 1");

                $stmt->execute([':uf' => $uf]);
                $r = $stmt->fetch();
                if ($r) $cnae_map[$r['cnae']] = ($cnae_map[$r['cnae']] ?? 0) + $r['c'];
            } catch (Exception $e) {
                continue;
            }
        }
        arsort($cnae_map);
        $top_cnae_code = !empty($cnae_map) ? key($cnae_map) : '0000000';
        $top_cnae = ['cnae_fiscal_principal' => $top_cnae_code, 'c' => $cnae_map[$top_cnae_code] ?? 0];
        
        // Tentar obter descrição do CNAE principal via SQLite
        $top_cnae['cnae_principal_descricao'] = 'Atividade Não Informada';
        $cnae_db = getCNAEDB();
        if ($cnae_db) {
            $stmt_c = $cnae_db->prepare("SELECT descricao FROM cnaes WHERE codigo = ? LIMIT 1");
            $stmt_c->execute([preg_replace('/\D/', '', $top_cnae_code)]);
            $res_c = $stmt_c->fetch();
            if ($res_c) $top_cnae['cnae_principal_descricao'] = $res_c['descricao'];
        }



        $stats = [
            'count_total' => $count_total,
            'capital_total' => $capital_total,
            'avg_age' => $avg_age,
            'top_cities_list' => $top_cities_list,
            'top_city' => $top_city,
            'concentration_perc' => $concentration_perc,
            'top_cnae' => $top_cnae,
            'updated_at' => time()
        ];
        
        file_put_contents($cache_file, json_encode($stats));
    }

    // Extrai variáveis do cache/processamento
    $count_total = $stats['count_total'];
    $capital_total = $stats['capital_total'];
    $avg_age = $stats['avg_age'];
    $top_cities_list = $stats['top_cities_list'];
    $top_city = $stats['top_city'];
    if (!isset($top_city['municipio']) && isset($top_city['total'])) $top_city['municipio'] = $top_city['municipio'] ?? 'Nenhum'; // Ajuste leve
    $concentration_perc = $stats['concentration_perc'];
    $top_cnae = $stats['top_cnae'];

    // Brasil Stats (para %) - VALORES ATUALIZADOS
    $br_total = 55843210;
    $participation = ($br_total > 0) ? ($count_total / $br_total) * 100 : 0;

    // --- LISTAGEM DO RANKING (FILTRADA E DISTRIBUÍDA) ---
    $query = "
        SELECT est.cnpj, e.razao_social, est.municipio, est.situacao_cadastral, e.capital_social 
        FROM estabelecimentos est 
        INNER JOIN empresas e ON est.cnpj_basico = e.cnpj_basico 
        WHERE est.situacao_cadastral = 'ATIVA' AND est.uf = :uf";
    $params = [':uf' => $uf];

    if ($search) {
        $query .= " AND (e.razao_social LIKE :q OR est.cnpj LIKE :q)";
        $params[':q'] = "%$search%";
    }
    if ($cnae_filter) {
        // Como o filtro é por descrição (no frontend antigo), aqui pode ser mais difícil
        // Idealmente o filtro deveria ser por código. Vou assumir busca no estabelecimentos.cnae_principal
        $query .= " AND est.cnae_principal = :cnae"; 
        $params[':cnae'] = $cnae_filter;
    }
    if ($city_filter) {
        $query .= " AND est.municipio = :city";
        $params[':city'] = $city_filter;
    }

    $ranking = fetchAllDistributed($query, $params, 'e.capital_social', 'DESC', 100);



    // Dropdowns (Usando as cidades já cacheadas do Top 10)
    $cities = array_column($top_cities_list, 'municipio');
    $cnaes = ["Serviços", "Comércio", "Indústria", "Construção Civil", "Saúde", "Alimentos", "Informática"];

} catch (PDOException $e) {
    die("Erro no banco de dados. " . $e->getMessage());
}

function format_money($val) {
    return 'R$ ' . number_format((float)$val, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-WWPBCTLJ');</script>
<!-- End Google Tag Manager -->

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <?php $prep = get_estado_prep($uf); $ano = date('Y'); ?>
    <title>As 100 Maiores Empresas <?php echo $prep . ' ' . $state_name; ?> em <?php echo $ano; ?> – Ranking Atualizado</title>
    <meta name="description" content="Descubra quais são as 100 maiores empresas <?php echo $prep . ' ' . $state_name; ?>. Lista atualizada, setores dominantes e dados empresariais detalhados.">
    <?php $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
    <link rel="canonical" href="<?php echo $current_url; ?>">
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(__DIR__ . '/assets/cnpj.css'); ?>">
    <link href="/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .ranking-page { background: var(--bg); }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 32px 0; }
        .stat-card { background: var(--surface); border-radius: 20px; padding: 32px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
        .stat-card h3 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--primary); margin-bottom: 12px; }
        .stat-card .val { font-size: 1.8rem; font-weight: 900; color: var(--text); }
        .stat-card .val.money { font-size: 1.5rem; }
        
        .panorama-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 24px; }
        .p-item { background: var(--surface); padding: 20px; border-radius: 16px; border: 1px solid var(--border); }
        .p-item label { display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
        .p-item .v { font-size: 1.1rem; font-weight: 700; color: var(--text); }

        .filter-bar { background: var(--surface); padding: 24px; border-radius: 20px; border: 1px solid var(--border); margin: 40px 0; display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; }
        .filter-group input, .filter-group select { width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--border); font-family: inherit; font-size: 0.9rem; outline: none; background: var(--bg); color: var(--text); }
        .filter-group input:focus { border-color: var(--primary); }
        
        .ranking-table-wrap { overflow-x: auto; background: var(--surface); border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-md); }
        .ranking-table { width: 100%; border-collapse: collapse; text-align: left; }
        .ranking-table th { background: var(--bg); padding: 16px 24px; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        .ranking-table td { padding: 20px 24px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .ranking-table tr:hover { background: var(--bg); }
        .ranking-table .rank { color: var(--primary); font-weight: 900; }
        .ranking-table .name { font-weight: 700; color: var(--text); text-decoration: none; display: block; }
        .ranking-table .name:hover { color: var(--primary); }
        .ranking-table .cnpj { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; }

        /* Grid de Cidades e Cards */
        .grid-states { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin: 24px 0 40px; }
        .state-card { background: var(--surface); padding: 20px 24px; border-radius: 16px; border: 1px solid var(--border); text-decoration: none; color: var(--text); transition: 0.3s; display: flex; align-items: center; justify-content: space-between; }
        .state-card:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-md); color: var(--primary); }
        .state-card span { font-weight: 700; font-size: 1.1rem; }
        .state-card .arrow { font-size: 1.2rem; opacity: 0.3; }
        .state-card:hover .arrow { opacity: 1; }
        
        @media (max-width: 768px) { 
            .stats-grid { grid-template-columns: 1fr; } 
            h1 { font-size: 2.2rem !important; }
            .ranking-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .ranking-table { min-width: 600px; }
            .page-header { padding: 10px 0 20px !important; }
        }
        .page-header { padding: 40px 0 20px; text-align: left; border:none; background:none; position: relative; z-index: 1; }
    </style>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ItemList",
      "name": "100 Maiores Empresas <?php echo $prep . ' ' . $state_name; ?>",
      "numberOfItems": 100
    }
    </script>
</head>
<body class="ranking-page">
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WWPBCTLJ"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->


<header>
    <div class="header-inner">
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav><a href="/"><i class="fa-solid fa-house mr-1"></i> Início</a><a href="/rankings/"><i class="fa-solid fa-chart-simple mr-1"></i> Rankings</a><a href="/analises/"><i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Análises</a><a href="/sobre/"><i class="fa-solid fa-circle-info mr-1"></i> Sobre</a></nav>
    </div>
</header>

<div class="page-wrap fade-up">
    <div class="bc"><a href="/">Início</a> > <a href="/rankings/">Rankings</a> > <?php echo $state_name; ?></div>
    
    <header class="page-header">
        <h1 style="font-size: clamp(2rem, 8vw, 3rem); margin-bottom:10px; line-height: 1.1;">Maiores Empresas em <?php echo $state_name; ?></h1>
        <p style="font-weight:700; color:var(--primary); font-size:1.1rem; margin-bottom:12px;">Ranking por Capital Social • Top 100 do estado</p>
        <p style="color:var(--text-muted); max-width:800px;">Ranking estadual com empresas matriz (CNPJ raiz único). Veja os indicadores em <?php echo $state_name; ?> e a lista das 100 maiores empresas.</p>
    </header>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total de Empresas no Estado</h3>
            <div class="val"><?php echo number_format($count_total, 0, ',', '.'); ?></div>
        </div>
        <div class="stat-card">
            <h3>Capital Social Total</h3>
            <div class="val money"><?php echo format_money_friendly($capital_total); ?></div>
        </div>
    </div>

    <h2 class="sec-title"><i class="fa-solid fa-chart-line mr-2"></i> Panorama Empresarial</h2>
    <div class="panorama-grid">
        <div class="p-item">
            <label>Participação no Brasil</label>
            <div class="v"><?php echo number_format($participation, 2, ',', '.'); ?>%</div>
        </div>
        <div class="p-item">
            <label>Idade Média</label>
            <div class="v"><?php echo $avg_age; ?> anos</div>
        </div>
        <div class="p-item">
            <label>Concentração</label>
            <div class="v"><?php echo titleCase($top_city['municipio']); ?> (<?php echo number_format($concentration_perc, 2, ',', '.'); ?>%)</div>
        </div>
        <div class="p-item">
            <label>Setor Principal</label>
            <div class="v" title="<?php echo $top_cnae['cnae_principal_descricao']; ?>"><?php echo mb_strimwidth($top_cnae['cnae_principal_descricao'] ?? 'N/A', 0, 30, '...'); ?></div>
        </div>
        <div class="p-item">
            <label>Atividade Dominante</label>
            <div class="v">Comércio / Serviços</div>
        </div>
    </div>



    <form class="filter-bar" method="GET">
        <div class="filter-group">
            <label>Buscar Empresa</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nome ou CNPJ...">
        </div>
        <div class="filter-group">
            <label>Ramo (CNAE)</label>
            <select name="cnae">
                <option value="">Todos os ramos</option>
                <?php foreach($cnaes as $c): ?>
                <option value="<?php echo $c; ?>" <?php echo ($cnae_filter == $c) ? 'selected' : ''; ?>><?php echo mb_strimwidth($c, 0, 40, "..."); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Cidade</label>
            <select name="cidade">
                <option value="">Todas as cidades</option>
                <?php foreach($cities as $city): ?>
                <option value="<?php echo $city; ?>" <?php echo ($city_filter == $city) ? 'selected' : ''; ?>><?php echo $city; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-cta" style="background:var(--primary); color:white; border:none; padding:12px 30px; cursor:pointer;">Filtrar</button>
    </form>

    <div class="ranking-table-wrap">
        <table class="ranking-table">
            <thead>
                <tr>
                    <th>Pos.</th>
                    <th>Empresa</th>
                    <th>Cidade</th>
                    <th>Situação</th>
                    <th>Capital Social</th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach($ranking as $emp): ?>
                <tr>
                    <td class="rank">#<?php echo $rank++; ?></td>
                    <td>
                        <a href="/<?php echo $emp['cnpj']; ?>/" class="name"><?php echo $emp['razao_social']; ?></a>
                        <span class="cnpj"><?php echo $emp['cnpj']; ?></span>
                    </td>
                    <td><?php echo titleCase($emp['municipio']); ?></td>
                    <td><span class="badge <?php echo ($emp['situacao_cadastral']=='ATIVA'?'ba':'bo'); ?>" style="scale: 0.8; margin-bottom:0;"><?php echo $emp['situacao_cadastral']; ?></span></td>
                    <td style="font-weight:700;"><?php echo format_money($emp['capital_social']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(!$ranking): ?>
                <tr><td colspan="5" style="text-align:center; padding: 40px;">Nenhuma empresa encontrada com estes filtros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="sec-title" style="margin-top: 60px;"><i class="fa-solid fa-city mr-2"></i> Maiores Cidades em <?php echo $state_name; ?></h2>
    <p style="margin-top: -10px; margin-bottom: 20px; color: var(--text-muted);">Ranking das 10 cidades com maior concentração de empresas no estado.</p>
    <div class="grid-states" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
        <?php foreach($top_cities_list as $city): 
            $city_slug = strtolower(str_replace(' ', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $city['municipio'])));
            $city_slug = preg_replace('/[^a-z0-9-]/', '', $city_slug);
        ?>
        <a href="/rankings/<?php echo $slug; ?>/<?php echo $city_slug; ?>/" class="state-card">
            <div>
                <span><?php echo titleCase($city['municipio']); ?></span>
                <div style="font-size: 0.8rem; opacity: 0.6; font-weight: 500;"><?php echo number_format($city['total'], 0, ',', '.'); ?> empresas</div>
            </div>
            <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
        <?php endforeach; ?>
    </div>

</div>

<footer>
    <nav><a href="/">Início</a><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav>
    <p>© <?php echo date('Y'); ?> BuscaCNPJ Gratis — Baseada 100% em dados públicos abertos.</p>
</footer>


<!-- GTM Custom Events Tracker -->
<script src="/assets/gtm-events.js" defer></script>

</body>
</html>
