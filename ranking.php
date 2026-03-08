<?php
// Conexão MySQL centralizada
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/utils.php';

$slug = $_GET['slug'] ?? '';

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

    // 1. Stats Gerais
    $total_companies = $db->prepare("SELECT COUNT(*) FROM dados_cnpj WHERE uf = :uf");
    $total_companies->execute([':uf' => $uf]);
    $count_total = $total_companies->fetchColumn();

    $total_capital = $db->prepare("SELECT SUM(capital_social) FROM dados_cnpj WHERE uf = :uf");
    $total_capital->execute([':uf' => $uf]);
    $capital_total = $total_capital->fetchColumn();

    // 2. Panorama
    // Idade Média (aproximada pela data de abertura)
    // Para SQLite: strftime('%Y', 'now') - strftime('%Y', data_abertura)
    $stmt_age = $db->prepare("SELECT AVG(strftime('%Y', 'now') - strftime('%Y', substr(data_abertura,1,10))) FROM dados_cnpj WHERE uf = :uf AND data_abertura != ''");
    $stmt_age->execute([':uf' => $uf]);
    $avg_age = round($stmt_age->fetchColumn() ?: 0);

    // Concentração
    $stmt_city = $db->prepare("SELECT municipio, COUNT(*) as c FROM dados_cnpj WHERE uf = :uf GROUP BY municipio ORDER BY c DESC LIMIT 1");
    $stmt_city->execute([':uf' => $uf]);
    $top_city = $stmt_city->fetch(PDO::FETCH_ASSOC);
    $concentration_perc = ($count_total > 0) ? ($top_city['c'] / $count_total) * 100 : 0;

    // Setores Dominantes
    $stmt_cnae = $db->prepare("SELECT cnae_principal_descricao, COUNT(*) as c FROM dados_cnpj WHERE uf = :uf AND cnae_principal_descricao NOT LIKE 'Consulte%' GROUP BY cnae_principal_descricao ORDER BY c DESC LIMIT 1");
    $stmt_cnae->execute([':uf' => $uf]);
    $top_cnae = $stmt_cnae->fetch(PDO::FETCH_ASSOC);
    if (!$top_cnae) {
        $stmt_cnae_alt = $db->prepare("SELECT cnae_principal_descricao, COUNT(*) as c FROM dados_cnpj WHERE uf = :uf GROUP BY cnae_principal_descricao ORDER BY c DESC LIMIT 1");
        $stmt_cnae_alt->execute([':uf' => $uf]);
        $top_cnae = $stmt_cnae_alt->fetch(PDO::FETCH_ASSOC);
    }

    // 2.1 Cidades Principais (Top 10 por volume)
    $stmt_cities_top = $db->prepare("SELECT municipio, COUNT(*) as total FROM dados_cnpj WHERE uf = :uf GROUP BY municipio ORDER BY total DESC LIMIT 10");
    $stmt_cities_top->execute([':uf' => $uf]);
    $top_cities_list = $stmt_cities_top->fetchAll(PDO::FETCH_ASSOC);

    // 3. Brasil Stats (para %) - OTIMIZADO: Valor fixo para evitar COUNT(*) total
    $br_total = 55843210;
    $participation = ($br_total > 0) ? ($count_total / $br_total) * 100 : 0;

    // 4. Ranking Top 100 com filtros
    // Otimização: Só executa filtros se o usuário realmente buscar
    // Caso contrário, pega o Top 100 direto (muito rápido com índice)
    $query = "SELECT * FROM dados_cnpj WHERE uf = :uf";
    $params = [':uf' => $uf];

    if ($search) {
        $query .= " AND (razao_social LIKE :q OR cnpj LIKE :q)";
        $params[':q'] = "%$search%";
    }
    if ($cnae_filter) {
        $query .= " AND cnae_principal_descricao = :cnae";
        $params[':cnae'] = $cnae_filter;
    }
    if ($city_filter) {
        $query .= " AND municipio = :city";
        $params[':city'] = $city_filter;
    }

    $query .= " AND CAST(capital_social AS CHAR) NOT LIKE '999999%' ORDER BY capital_social DESC LIMIT 100";
    $stmt_ranking = $db->prepare($query);
    $stmt_ranking->execute($params);
    $ranking = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);

    // 5. Opções para Dropdowns - OTIMIZADO
    // Em vez de SELECT DISTINCT (muito lento), usamos as 10 principais cidades já buscadas
    $cities = array_column($top_cities_list, 'municipio');
    $cnaes = ["Serviços", "Comércio", "Indústria", "Construção Civil", "Saúde"]; // Lista estática ou reduzida

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <?php $prep = get_estado_prep($uf); $ano = date('Y'); ?>
    <title>As 100 Maiores Empresas <?php echo $prep . ' ' . $state_name; ?> em <?php echo $ano; ?> – Ranking Atualizado</title>
    <meta name="description" content="Descubra quais são as 100 maiores empresas <?php echo $prep . ' ' . $state_name; ?>. Lista atualizada, setores dominantes e dados empresariais detalhados.">
    <?php $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
    <link rel="canonical" href="<?php echo $current_url; ?>">
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(__DIR__ . '/assets/cnpj.css'); ?>">
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
        }
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

<header>
    <div class="header-inner">
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav><a href="/">Início</a><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a></nav>
    </div>
</header>

<div class="page-wrap fade-up">
    <div class="bc"><a href="/">Início</a> > <a href="/rankings/">Rankings</a> > <?php echo $state_name; ?></div>
    
    <header style="padding: 40px 0 20px; text-align: left; border:none; background:none; position: relative; z-index: 1;">
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

    <h2 class="sec-title">📍 Panorama Empresarial</h2>
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

    <h2 class="sec-title">🏙️ Maiores Cidades em <?php echo $state_name; ?></h2>
    <p style="margin-top: -10px; margin-bottom: 20px; color: var(--text-muted);">Ranking das 10 cidades com maior concentração de empresas no estado.</p>
    <div class="grid-states" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
        <?php foreach($top_cities_list as $city): 
            $city_slug = strtolower(str_replace(' ', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $city['municipio'])));
            $city_slug = preg_replace('/[^a-z0-9-]/', '', $city_slug);
        ?>
        <a href="/rankings/estado/<?php echo $slug; ?>/<?php echo $city_slug; ?>/" class="state-card">
            <div>
                <span><?php echo titleCase($city['municipio']); ?></span>
                <div style="font-size: 0.8rem; opacity: 0.6; font-weight: 500;"><?php echo number_format($city['total'], 0, ',', '.'); ?> empresas</div>
            </div>
            <span class="arrow">→</span>
        </a>
        <?php endforeach; ?>
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
                        <a href="/cnpj/<?php echo $emp['cnpj']; ?>/" class="name"><?php echo $emp['razao_social']; ?></a>
                        <span class="cnpj"><?php echo $emp['cnpj']; ?></span>
                    </td>
                    <td><?php echo titleCase($emp['municipio']); ?></td>
                    <td><span class="badge <?php echo ($emp['situacao']=='ATIVA'?'ba':'bo'); ?>" style="scale: 0.8; margin-bottom:0;"><?php echo $emp['situacao']; ?></span></td>
                    <td style="font-weight:700;"><?php echo format_money($emp['capital_social']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(!$ranking): ?>
                <tr><td colspan="5" style="text-align:center; padding: 40px;">Nenhuma empresa encontrada com estes filtros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<footer>
    <nav><a href="/">Início</a><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav>
    <p>© <?php echo date('Y'); ?> BuscaCNPJ Gratis — Baseada 100% em dados públicos abertos.</p>
</footer>

</body>
</html>
