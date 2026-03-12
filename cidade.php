<?php
// Conexão MySQL centralizada
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/utils.php';

$estado_slug = $_GET['estado_slug'] ?? '';
$cidade_slug = $_GET['cidade_slug'] ?? '';

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

$uf = $states[$estado_slug] ?? '';
$state_name = $state_names[$uf] ?? '';

if (!$uf) {
    header("HTTP/1.0 404 Not Found");
    die("<h1>Estado não encontrado</h1>");
}

try {
    $db = getDB();

    // Encontrar o nome real da cidade pelo slug de forma mais eficiente
    $real_city_name = '';
    
    // Tentar primeiro pelo cache pré-aquecido (instantâneo)
    $cache_file = __DIR__ . '/cache/cidades/' . strtolower($uf) . '_' . $cidade_slug . '.json';
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        $real_city_name = $cache_data['nome_real'] ?? '';
    }

    // Fallback: Busca via banco de dados (lento, apenas se o cache falhar)
    if (!$real_city_name) {
        foreach (getAllConnections() as $db_conn) {
            try {
                $stmt_c = $db_conn->prepare("SELECT DISTINCT municipio FROM dados_cnpj WHERE sigla_uf = :sigla_uf");
                $stmt_c->execute([':sigla_uf' => $uf]);
                
                while($row = $stmt_c->fetch(PDO::FETCH_ASSOC)) {
                    $name = $row['municipio'];
                    // Normalização p/ conferir slug (mesma lógica do pre_aquecer)
                    $s = strtolower(str_replace(' ', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
                    $s = preg_replace('/[^a-z0-9-]/', '', $s);
                    if ($s === $cidade_slug) {
                        $real_city_name = $name;
                        break 2; // Sai do while e do foreach
                    }
                }
            } catch (Exception $e) {
                error_log("Erro no banco durante busca de cidade: " . $e->getMessage());
                continue;
            }
        }
    }

    if (!$real_city_name) {
        header("HTTP/1.0 404 Not Found");
        die("<h1>Cidade não encontrada neste estado</h1>");
    }

    // 1. Stats Gerais da Cidade (Distribuído)
    $stats_cid = aggregateDistributed("
        SELECT COUNT(*) as count_total, SUM(capital_social) as capital_total 
        FROM dados_cnpj 
        WHERE situacao_cadastral = 'ATIVA' AND sigla_uf = :sigla_uf AND municipio = :city
    ", [':sigla_uf' => $uf, ':city' => $real_city_name]);

    $count_total = $stats_cid['count_total'] ?: 0;
    $capital_total = $stats_cid['capital_total'] ?: 0;

    // 2. Panorama da Cidade (Top CNAE Distribuído)
    $cnae_map = [];
    foreach (getAllConnections() as $db) {
        try {
            $stmt_cnae = $db->prepare("SELECT cnae_principal_descricao, COUNT(*) as c FROM dados_cnpj WHERE situacao_cadastral = 'ATIVA' AND sigla_uf = :sigla_uf AND municipio = :city AND cnae_principal_descricao NOT LIKE 'Consulte%' GROUP BY cnae_principal_descricao ORDER BY c DESC LIMIT 1");
            $stmt_cnae->execute([':sigla_uf' => $uf, ':city' => $real_city_name]);
            $r = $stmt_cnae->fetch(PDO::FETCH_ASSOC);
            if ($r) $cnae_map[$r['cnae_principal_descricao']] = ($cnae_map[$r['cnae_principal_descricao']] ?? 0) + $r['c'];
        } catch (Exception $e) {
            continue;
        }
    }
    arsort($cnae_map);
    $top_cnae = !empty($cnae_map) ? ['cnae_principal_descricao' => key($cnae_map), 'c' => current($cnae_map)] : null;

    // 3. Ranking Top 100 da Cidade (Distribuído)
    $ranking = fetchAllDistributed("SELECT * FROM dados_cnpj WHERE situacao_cadastral = 'ATIVA' AND sigla_uf = :sigla_uf AND municipio = :city", [':sigla_uf' => $uf, ':city' => $real_city_name], 'capital_social', 'DESC', 100);


} catch (PDOException $e) {
    die("Erro no banco de dados.");
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
    <?php $ano = date('Y'); $nome_cidade = titleCase($real_city_name); ?>
    <title>As 100 Maiores Empresas de <?php echo $nome_cidade; ?> (<?php echo $uf; ?>) em <?php echo $ano; ?> – Ranking Atualizado</title>
    <meta name="description" content="Descubra quais são as 100 maiores empresas de <?php echo $nome_cidade; ?> (<?php echo $uf; ?>). Lista atualizada, setores dominantes e dados empresariais detalhados.">
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

        .ranking-table-wrap { overflow-x: auto; background: var(--surface); border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-md); margin-top: 40px; }
        .ranking-table { width: 100%; border-collapse: collapse; text-align: left; }
        .ranking-table th { background: var(--bg); padding: 16px 24px; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        .ranking-table td { padding: 20px 24px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .ranking-table tr:hover { background: var(--bg); }
        .ranking-table .rank { color: var(--primary); font-weight: 900; }
        .ranking-table .name { font-weight: 700; color: var(--text); text-decoration: none; display: block; }
        .ranking-table .name:hover { color: var(--primary); }
        .ranking-table .cnpj { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; }
        
        @media (max-width: 768px) { 
            .stats-grid { grid-template-columns: 1fr; } 
            .ranking-table { min-width: 600px; } 
            .page-header { padding: 10px 0 20px !important; }
            h1 { font-size: 2.22rem !important; }
        }
        .page-header { padding: 40px 0 20px; text-align: left; border:none; background:none; position: relative; z-index: 1; }
    </style>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ItemList",
      "name": "100 Maiores Empresas de <?php echo $nome_cidade; ?> (<?php echo $uf; ?>)",
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
    <div class="bc">
        <a href="/">Início</a> > 
        <a href="/rankings/">Rankings</a> > 
        <a href="/rankings/<?php echo $estado_slug; ?>/"><?php echo $state_name; ?></a> > 
        <?php echo titleCase($real_city_name); ?>
    </div>
    
    <header class="page-header">
        <h1 style="font-size: clamp(2rem, 8vw, 3rem); margin-bottom:10px; line-height: 1.1;">Maiores Empresas em <?php echo titleCase($real_city_name); ?>, <?php echo $uf; ?></h1>
        <p style="font-weight:700; color:var(--primary); font-size:1.1rem; margin-bottom:12px;">Ranking de Capital Social • As 100 maiores da cidade</p>
        <p style="color:var(--text-muted); max-width:800px;">Consulte o panorama empresarial de <?php echo titleCase($real_city_name); ?>. Dados oficiais baseados no capital social declarado na Receita Federal.</p>
    </header>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Empresas na Cidade</h3>
            <div class="val"><?php echo number_format($count_total, 0, ',', '.'); ?></div>
        </div>
        <div class="stat-card">
            <h3>Capital Social em <?php echo titleCase($real_city_name); ?></h3>
            <div class="val money"><?php echo format_money_friendly($capital_total); ?></div>
        </div>
    </div>

    <h2 class="sec-title"><i class="fa-solid fa-magnifying-glass-chart mr-2"></i> Resumo do Mercado Local</h2>
    <div class="panorama-grid">
        <div class="p-item">
            <label>Setor Principal</label>
            <div class="v" title="<?php echo $top_cnae['cnae_principal_descricao']; ?>">
                <?php echo mb_strimwidth($top_cnae['cnae_principal_descricao'] ?? 'N/A', 0, 40, '...'); ?>
            </div>
        </div>
        <div class="p-item">
            <label>Estado</label>
            <div class="v"><?php echo $state_name; ?> (<?php echo $uf; ?>)</div>
        </div>
        <div class="p-item">
            <label>Nível de Atividade</label>
            <div class="v">Alta Concentração</div>
        </div>
    </div>

    <div class="ranking-table-wrap">
        <table class="ranking-table">
            <thead>
                <tr>
                    <th>Pos.</th>
                    <th>Empresa</th>
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
                    <td><span class="badge <?php echo ($emp['situacao_cadastral']=='ATIVA'?'ba':'bo'); ?>" style="scale: 0.8; margin-bottom:0;"><?php echo $emp['situacao_cadastral']; ?></span></td>
                    <td style="font-weight:700;"><?php echo format_money($emp['capital_social']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 60px; text-align: center;">
        <a href="/rankings/<?php echo $estado_slug; ?>/" class="btn-copy" style="padding: 15px 30px;">
            <i class="fa-solid fa-arrow-left mr-1"></i> Voltar para Ranking de <?php echo $state_name; ?>
        </a>
    </div>

</div>

<footer>
    <nav><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav>
    <p>© <?php echo date('Y'); ?> BuscaCNPJ Gratis — Baseada 100% em dados públicos abertos.</p>
</footer>


<!-- GTM Custom Events Tracker -->
<script src="/assets/gtm-events.js" defer></script>

</body>
</html>
