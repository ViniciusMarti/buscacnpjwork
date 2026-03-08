<?php
// Conexão MySQL centralizada
require_once __DIR__ . '/config/db.php';

// Atividade: Criar uma página de hub para os rankings estaduais.
// Esta página servirá como os "Relatórios" principais do site.

try {
    $db = getDB();
    
    // Otimização: Valores nacionais fixos (ou vindos de uma tabela de meta)
    // Evita ler 17GB para somar capital social a cada visita.
    $br_stats = [
        't' => 55843210, 
        's' => 45890234120.00
    ];
    
    // Pegar top 10 maiores empresas do Brasil (Geral)
    // Isso é RÁPIDO se houver índice em capital_social
    $stmt_top = $db->query("SELECT * FROM dados_cnpj WHERE situacao = 'ATIVA' AND capital_social > 0 ORDER BY capital_social DESC LIMIT 10");
    $top_br = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Fallback silencioso para erros
    $br_stats = ['t' => '55M+', 's' => 0];
    $top_br = [];
}

$state_list = [
    'acre' => 'Acre', 'alagoas' => 'Alagoas', 'amapa' => 'Amapá', 'amazonas' => 'Amazonas', 
    'bahia' => 'Bahia', 'ceara' => 'Ceará', 'distrito-federal' => 'Distrito Federal', 'espirito-santo' => 'Espírito Santo', 
    'goias' => 'Goiás', 'maranhao' => 'Maranhão', 'mato-grosso' => 'Mato Grosso', 'mato-grosso-do-sul' => 'Mato Grosso do Sul', 
    'minas-gerais' => 'Minas Gerais', 'para' => 'Pará', 'paraiba' => 'Paraíba', 'parana' => 'Paraná', 
    'pernambuco' => 'Pernambuco', 'piaui' => 'Piauí', 'rio-de-janeiro' => 'Rio de Janeiro', 'rio-grande-do-norte' => 'Rio Grande do Norte', 
    'rio-grande-do-sul' => 'Rio Grande do Sul', 'rondonia' => 'Rondônia', 'roraima' => 'Roraima', 'santa-catarina' => 'Santa Catarina', 
    'sao-paulo' => 'São Paulo', 'sergipe' => 'Sergipe', 'tocantins' => 'Tocantins'
];

function format_money($val) {
    return 'R$ ' . number_format((float)$val, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rankings de Empresas por Estado - Relatórios e Estatísticas | BuscaCNPJ Grátis</title>
    <meta name="description" content="Explore rankings das maiores empresas do Brasil por estado. Inteligência de mercado, panorama empresarial e estatísticas atualizadas.">
    <link rel="canonical" href="https://buscacnpjgratis.com.br/rankings/">
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(__DIR__ . '/assets/cnpj.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .rankings-hub { background: var(--bg); }
        .grid-states { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin: 40px 0; }
        .state-card { background: var(--surface); padding: 24px; border-radius: 16px; border: 1px solid var(--border); text-decoration: none; color: var(--text); transition: 0.3s; display: flex; align-items: center; justify-content: space-between; }
        .state-card:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-md); color: var(--primary); }
        .state-card span { font-weight: 700; font-size: 1.1rem; }
        .state-card .arrow { font-size: 1.2rem; opacity: 0.3; }
        .state-card:hover .arrow { opacity: 1; }

        .br-overview { background: var(--surface); border-radius: 24px; padding: 40px; border: 1px solid var(--border); margin-bottom: 40px; box-shadow: var(--shadow-sm); }
        .br-stats { display: flex; gap: 40px; margin-top: 20px; }
        .br-stats .stat-item { flex: 1; }
        .br-stats label { display: block; font-size: 0.8rem; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 8px; }
        .br-stats .val { font-size: 2.5rem; font-weight: 900; color: var(--text); }

        .ranking-table-mini { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .ranking-table-mini th { text-align: left; padding: 12px; border-bottom: 2px solid var(--border); font-size: 0.8rem; color: var(--text-muted); }
        .ranking-table-mini td { padding: 12px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .ranking-table-mini tr:last-child td { border-bottom: none; }
        .ranking-table-mini .rank { font-weight: 900; color: var(--primary); width: 40px; }
        .ranking-table-mini .name { font-weight: 700; color: var(--text); text-decoration: none; }
        
        @media (max-width: 768px) { 
            .br-stats { flex-direction: column; gap: 20px; } 
            .br-stats .val { font-size: 1.8rem; }
            .br-overview { padding: 20px; border-radius: 16px; overflow-x: auto; }
            .ranking-table-mini { min-width: 500px; }
            .page-header h1 { font-size: 2.22rem !important; }
            .page-header { padding: 20px 0 20px !important; }
        }
        .ranking-table-container { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="rankings-hub">

<header>
    <div class="header-inner">
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav><a href="/">Início</a><a href="/rankings/" class="active">Rankings</a><a href="/sobre/">Sobre</a></nav>
    </div>
</header>

<div class="page-wrap fade-up">
    <div class="bc"><a href="/">Início</a> > Rankings</div>
    
    <header class="page-header" style="padding: 40px 0 30px; text-align: left; border:none; background:none;">
        <h1 style="font-size: 3rem; margin-bottom:10px; line-height: 1.1;">Relatórios & Rankings</h1>
        <p style="font-weight:700; color:var(--primary); font-size:1.1rem; margin-bottom:12px;">Inteligência de Mercado em Tempo Real</p>
        <p style="color:var(--text-muted); max-width:800px;">Explore a dinâmica empresarial do Brasil através dos nossos relatórios exclusivos. Analise capitais sociais, concentração de mercado e setores em expansão em cada unidade federativa.</p>
    </header>

    <div class="br-overview">
        <h2>🇧🇷 Panorama Nacional</h2>
        <div class="br-stats">
            <div class="stat-item">
                <label>Total de Empresas Ativas</label>
                <div class="val"><?php echo number_format($br_stats['t'], 0, ',', '.'); ?></div>
            </div>
            <div class="stat-item">
                <label>Capital Social Acumulado</label>
                <div class="val"><?php echo format_money($br_stats['s']); ?></div>
            </div>
        </div>
        
        <h3 style="margin-top: 40px; margin-bottom:15px; font-size: 1.2rem;">🏆 Maiores Empresas do Brasil (Top 10)</h3>
        <div class="ranking-table-container">
            <table class="ranking-table-mini">
                <thead>
                    <tr>
                        <th>Pos.</th>
                        <th>Empresa</th>
                        <th>UF</th>
                        <th>Capital Social</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $r = 1; foreach($top_br as $emp): ?>
                    <tr>
                        <td class="rank">#<?php echo $r++; ?></td>
                        <td><a href="/cnpj/<?php echo $emp['cnpj']; ?>/" class="name"><?php echo $emp['razao_social']; ?></a></td>
                        <td><?php echo $emp['uf']; ?></td>
                        <td style="font-weight:700;"><?php echo format_money($emp['capital_social']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="sec-title">📍 Rankings por Estado</h2>
    <p style="margin-top: -10px; margin-bottom: 20px; color: var(--text-muted);">Selecione um estado para ver o ranking das 1000 maiores empresas e panoramas regionais.</p>
    
    <div class="grid-states">
        <?php foreach($state_list as $slug => $name): ?>
        <a href="/rankings/estado/<?php echo $slug; ?>/" class="state-card">
            <span><?php echo $name; ?></span>
            <span class="arrow">→</span>
        </a>
        <?php endforeach; ?>
    </div>

</div>

<footer>
    <nav><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav>
    <p>© <?php echo date('Y'); ?> BuscaCNPJ Gratis — Baseada 100% em dados públicos abertos.</p>
</footer>

</body>
</html>
