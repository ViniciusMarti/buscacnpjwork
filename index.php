<?php
// Otimização: Evitar COUNT(*) pesado. Lemos de um cache atualizado via script.
$cache_file = __DIR__ . '/cache/total_empresas.txt';
if (file_exists($cache_file)) {
    $total_cnpjs = (int)file_get_contents($cache_file);
} else {
    $total_cnpjs = 55843210; // Fallback caso o cache ainda não exista
}
$display_count = number_format($total_cnpjs, 0, ',', '.');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuscaCNPJ Gratis — Consulta Gratuita de CNPJ</title>
    <meta name="description" content="Consulte dados oficiais de qualquer CNPJ do Brasil de forma gratuita, rápida e atualizada.">
    <?php $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
    <link rel="canonical" href="<?php echo $current_url; ?>">
    
    <!-- CSS & Fonts -->
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/cnpj.css'); ?>">
    <link href="/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        .home-page {
            min-height: 80vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1000px;
            width: 100%;
            margin-top: 60px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 30px;
            text-align: center;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .stat-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
        }
        .stat-card h3 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--primary);
            margin-bottom: 10px;
        }
        .stat-card p {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
        }
        .stat-card .desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 5px;
        }

        /* Quick Search Logos */
        .quick-search {
            margin-top: 60px;
            width: 100%;
            max-width: 1100px;
            text-align: center;
            padding: 0 20px;
        }
        .quick-search h2 {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.25em;
            color: var(--text-muted);
            margin-bottom: 30px;
            font-weight: 800;
            opacity: 0.6;
        }
        .logos-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px 40px;
            align-items: center;
        }
        .logos-grid a {
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            filter: grayscale(100%) opacity(0.4);
            padding: 12px 20px;
            border-radius: 20px;
            border: 1px solid transparent;
        }
        .logos-grid a:hover {
            filter: grayscale(0%) opacity(1);
            transform: translateY(-5px) scale(1.1);
            background: var(--surface);
            border-color: var(--border);
            box-shadow: var(--shadow-md);
        }
        .logos-grid img {
            height: 30px;
            width: auto;
            max-width: 130px;
            object-fit: contain;
        }

        @media (max-width: 768px) {
            .quick-search { margin-top: 40px; }
            .logos-grid { gap: 15px 25px; }
            .logos-grid img { height: 24px; }
            .logos-grid a { padding: 8px 12px; }
        }

        /* Dark Mode Adjustments */
        @media (prefers-color-scheme: dark) {
            .logos-grid a {
                filter: grayscale(100%) brightness(2.5) opacity(0.5);
                background: rgba(255, 255, 255, 0.03);
            }
            .logos-grid a:hover {
                filter: grayscale(0%) brightness(1) opacity(1);
                background: rgba(255, 255, 255, 0.08);
                border-color: rgba(255, 255, 255, 0.15);
            }
            .quick-search h2 {
                opacity: 0.4;
            }
        }
    </style>
</head>
<body class="home-page-body">
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WWPBCTLJ"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->


<header>
    <div class="header-inner">
        <a class="logo" href="/" aria-label="BuscaCNPJ Grátis - Ir para a página inicial">Busca<span>CNPJ</span> Grátis</a>
        <nav>
            <a href="/"><i class="fa-solid fa-house mr-1"></i> Início</a>
            <a href="/rankings/"><i class="fa-solid fa-chart-simple mr-1"></i> Rankings</a>
            <a href="/sobre/"><i class="fa-solid fa-circle-info mr-1"></i> Sobre</a>
        </nav>
    </div>
</header>

<main class="home-page">
    <div class="home-hero fade-up">
        <h1>Consulte qualquer CNPJ grátis.</h1>
        <p>Acesso simplificado aos dados públicos da Receita Federal em segundos.</p>
        
        <div class="search-container">
            <input id="q" type="text" maxlength="18" placeholder="Digite o CNPJ (apenas números)..." 
                   onkeydown="if(event.key==='Enter')buscar()" aria-label="Digite o CNPJ para consulta" autofocus>
            <button onclick="buscar()"><i class="fa-solid fa-magnifying-glass mr-2"></i> Consultar</button>
        </div>

        <div class="quick-search fade-up" style="animation-delay: 0.15s;">
            <h2>Busca Rápida</h2>
            <div class="logos-grid">
                <a href="/02261666000150/" title="Consulte CNPJ da Ambev"><img src="/assets/images/ambev_logo.png" alt="Ambev"></a>
                <a href="/00000000000191/" title="Consulte CNPJ do Banco do Brasil"><img src="/assets/images/banco-do-brasil_logo.png" alt="Banco do Brasil"></a>
                <a href="/60746948000112/" title="Consulte CNPJ do Bradesco"><img src="/assets/images/bradesco_logo.png" alt="Bradesco"></a>
                <a href="/60872504000123/" title="Consulte CNPJ do Itaú Unibanco"><img src="/assets/images/itau-unibanco_logo.png" alt="Itaú"></a>
                <a href="/33000167000101/" title="Consulte CNPJ da Petrobras"><img src="/assets/images/petrobras_logo.png" alt="Petrobras"></a>
                <a href="/33592510000154/" title="Consulte CNPJ da Vale"><img src="/assets/images/vale_logo.png" alt="Vale"></a>
                <a href="/18236120000158/" title="Consulte CNPJ do Nubank"><img src="/assets/images/nubank_logo.png" alt="Nubank"></a>
                <a href="/47960950000121/" title="Consulte CNPJ do Magazine Luiza"><img src="/assets/images/magazine-luiza_logo.png" alt="Magazine Luiza"></a>
                <a href="/07689002000189/" title="Consulte CNPJ da Embraer"><img src="/assets/images/embraer_logo.png" alt="Embraer"></a>
                <a href="/02916265000141/" title="Consulte CNPJ da JBS"><img src="/assets/images/jbs_logo.png" alt="JBS"></a>
                <a href="/38928424000100/" title="Consulte CNPJ da Cambly"><img src="/assets/images/cambly-logo.png" alt="Cambly"></a>
            </div>
        </div>
    </div>

    <div class="stats-grid fade-up" style="animation-delay: 0.2s;">
        <div class="stat-card">
            <h3>Base de Dados</h3>
            <p><?php echo $display_count; ?></p>
            <div class="desc">Empresas cadastradas</div>
        </div>
        <div class="stat-card">
            <h3>Fonte de Dados</h3>
            <p>Oficiais</p>
            <div class="desc">Receita Federal do Brasil</div>
        </div>
        <div class="stat-card">
            <h3>Custo</h3>
            <p>100% Grátis</p>
            <div class="desc">Sem taxas ou cadastros</div>
        </div>
    </div>
</main>

<footer>
    <nav>
        <a href="/">Início</a>
        <a href="/rankings/">Rankings</a>
        <a href="/sobre/">Sobre</a>
        <a href="/privacidade/">Privacidade</a>
        <a href="/contato/">Contato</a>
    </nav>
    <p>© <?php echo date('Y'); ?> BuscaCNPJ Gratis — Todos os direitos reservados.</p>
</footer>

<script>
function buscar(){
    var input = document.getElementById('q').value;
    var q = input.replace(/\D/g,'');
    if(q.length === 14) {
        window.location.href = '/' + q + '/';
    } else {
        alert('Por favor, digite um CNPJ válido com 14 dígitos.');
    }
}

// Suporte a digitação formatada (opcional, melhora UX)
document.getElementById('q').addEventListener('input', function (e) {
    var x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})/);
    e.target.value = !x[2] ? x[1] : x[1] + '.' + x[2] + '.' + x[3] + '/' + x[4] + (x[5] ? '-' + x[5] : '');
});
</script>


<!-- GTM Custom Events Tracker -->
<script src="/assets/gtm-events.js" defer></script>

</body>
</html>
