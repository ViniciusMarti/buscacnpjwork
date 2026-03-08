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

    // Encontrar o nome real da cidade pelo slug de forma mais eficiente via índice
    $stmt_c = $db->prepare("SELECT DISTINCT municipio FROM dados_cnpj WHERE uf = :uf");
    $stmt_c->execute([':uf' => $uf]);
    $real_city_name = '';
    
    while($row = $stmt_c->fetch(PDO::FETCH_ASSOC)) {
        $name = $row['municipio'];
        $s = strtolower(str_replace(' ', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        $s = preg_replace('/[^a-z0-9-]/', '', $s);
        if ($s === $cidade_slug) {
            $real_city_name = $name;
            break;
        }
    }

    if (!$real_city_name) {
        header("HTTP/1.0 404 Not Found");
        die("<h1>Cidade não encontrada neste estado</h1>");
    }

    // 1. Stats Gerais da Cidade
    $total_companies = $db->prepare("SELECT COUNT(*) FROM dados_cnpj WHERE uf = :uf AND municipio = :city");
    $total_companies->execute([':uf' => $uf, ':city' => $real_city_name]);
    $count_total = $total_companies->fetchColumn();

    $total_capital = $db->prepare("SELECT SUM(capital_social) FROM dados_cnpj WHERE uf = :uf AND municipio = :city");
    $total_capital->execute([':uf' => $uf, ':city' => $real_city_name]);
    $capital_total = $total_capital->fetchColumn();

    // 2. Panorama da Cidade
    $stmt_cnae = $db->prepare("SELECT cnae_principal_descricao, COUNT(*) as c FROM dados_cnpj WHERE uf = :uf AND municipio = :city AND cnae_principal_descricao NOT LIKE 'Consulte%' GROUP BY cnae_principal_descricao ORDER BY c DESC LIMIT 1");
    $stmt_cnae->execute([':uf' => $uf, ':city' => $real_city_name]);
    $top_cnae = $stmt_cnae->fetch(PDO::FETCH_ASSOC);
    if (!$top_cnae) {
        $stmt_cnae_alt = $db->prepare("SELECT cnae_principal_descricao, COUNT(*) as c FROM dados_cnpj WHERE uf = :uf AND municipio = :city GROUP BY cnae_principal_descricao ORDER BY c DESC LIMIT 1");
        $stmt_cnae_alt->execute([':uf' => $uf, ':city' => $real_city_name]);
        $top_cnae = $stmt_cnae_alt->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Ranking Top 100 da Cidade
    $stmt_ranking = $db->prepare("SELECT * FROM dados_cnpj WHERE uf = :uf AND municipio = :city ORDER BY capital_social DESC LIMIT 100");
    $stmt_ranking->execute([':uf' => $uf, ':city' => $real_city_name]);
    $ranking = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Maiores Empresas em <?php echo titleCase($real_city_name); ?> (<?php echo $uf; ?>) - Ranking Top 100 | GestãoMax</title>
    <meta name="description" content="Ranking das 100 maiores empresas de <?php echo titleCase($real_city_name); ?>, <?php echo $uf; ?> por capital social. Estatísticas e inteligência de mercado.">
    <?php $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
    <link rel="canonical" href="<?php echo $current_url; ?>">
    <link rel="stylesheet" href="/assets/cnpj.css?v=1.7.1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .ranking-page { background: #fdfdfd; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 32px 0; }
        .stat-card { background: white; border-radius: 20px; padding: 32px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
        .stat-card h3 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--primary); margin-bottom: 12px; }
        .stat-card .val { font-size: 1.8rem; font-weight: 900; color: var(--text); }
        .stat-card .val.money { font-size: 1.5rem; }
        
        .panorama-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 24px; }
        .p-item { background: var(--bg); padding: 20px; border-radius: 16px; border: 1px solid var(--border); }
        .p-item label { display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
        .p-item .v { font-size: 1.1rem; font-weight: 700; color: var(--text); }

        .ranking-table-wrap { overflow-x: auto; background: white; border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-md); margin-top: 40px; }
        .ranking-table { width: 100%; border-collapse: collapse; text-align: left; }
        .ranking-table th { background: #f8fafc; padding: 16px 24px; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        .ranking-table td { padding: 20px 24px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .ranking-table tr:hover { background: #fdfdfd; }
        .ranking-table .rank { color: var(--primary); font-weight: 900; }
        .ranking-table .name { font-weight: 700; color: var(--text); text-decoration: none; display: block; }
        .ranking-table .name:hover { color: var(--primary); }
        .ranking-table .cnpj { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; }
        
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } .ranking-table { min-width: 600px; } }
    </style>
</head>
<body class="ranking-page">

<header>
    <div class="header-inner">
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav><a href="/">Início</a><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a></nav>
    </div>
</header>

<div class="page-wrap fade-up">
    <div class="bc">
        <a href="/">Início</a> > 
        <a href="/rankings/">Rankings</a> > 
        <a href="/rankings/estado/<?php echo $estado_slug; ?>/"><?php echo $state_name; ?></a> > 
        <?php echo titleCase($real_city_name); ?>
    </div>
    
    <header style="padding: 40px 0 20px; text-align: left; border:none; background:none;">
        <h1 style="font-size: 3rem; margin-bottom:10px;">Maiores Empresas em <?php echo titleCase($real_city_name); ?>, <?php echo $uf; ?></h1>
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

    <h2 class="sec-title">📍 Resumo do Mercado Local</h2>
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
                        <a href="/cnpj/<?php echo $emp['cnpj']; ?>/" class="name"><?php echo $emp['razao_social']; ?></a>
                        <span class="cnpj"><?php echo $emp['cnpj']; ?></span>
                    </td>
                    <td><span class="badge <?php echo ($emp['situacao']=='ATIVA'?'ba':'bo'); ?>" style="scale: 0.8; margin-bottom:0;"><?php echo $emp['situacao']; ?></span></td>
                    <td style="font-weight:700;"><?php echo format_money($emp['capital_social']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 60px; text-align: center;">
        <a href="/rankings/estado/<?php echo $estado_slug; ?>/" class="btn-copy" style="padding: 15px 30px;">
            ← Voltar para Ranking de <?php echo $state_name; ?>
        </a>
    </div>

</div>

<footer>
    <nav><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav>
    <p>© <?php echo date('Y'); ?> GestãoMax — Inteligência de Mercado e Dados B2B.</p>
</footer>

</body>
</html>
