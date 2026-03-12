<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/utils.php';

$title = "Análises de Mercado e Estatísticas de CNPJ no Brasil | BuscaCNPJ Grátis";
$description = "Descubra quais são as maiores empresas, cidades mais industrializadas e regiões mais promissoras do Brasil. Relatórios criados via dados oficiais da Receita Federal.";
$canonical = "https://buscacnpjgratis.com.br/analises/";
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
    <title><?php echo $title; ?></title>
    <meta name="description" content="<?php echo $description; ?>">
    <link rel="canonical" href="<?php echo $canonical; ?>">
    
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/cnpj.css'); ?>">
    <link href="/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        .analises-page { background: var(--bg); }
        .hero { padding: 60px 20px; text-align: center; background: radial-gradient(circle at center, var(--primary-glow) 0%, transparent 60%); }
        .hero h1 { font-size: clamp(2.5rem, 5vw, 3.5rem); margin-bottom: 15px; line-height: 1.1; }
        .hero p { font-size: 1.1rem; color: var(--text-muted); max-width: 700px; margin: 0 auto; }
        
        .c { max-width: 1000px; margin: 40px auto; padding: 0 20px; min-height: 50vh; }
        .bc { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 30px; font-weight: 600; }
        .bc a { color: var(--text-muted); text-decoration: none; transition: 0.2s; }
        .bc a:hover { color: var(--text); }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-top: 20px; }
        .card { background: var(--surface); padding: 30px; border-radius: 20px; border: 1px solid var(--border); text-decoration: none; display: flex; flex-direction: column; transition: transform 0.3s, box-shadow 0.3s; }
        .card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: var(--shadow-md); }
        .card .icon { font-size: 2.5rem; margin-bottom: 20px; }
        .card h2 { font-size: 1.3rem; color: var(--text); margin-bottom: 10px; line-height: 1.3; }
        .card p { font-size: 0.95rem; color: var(--text-muted); flex: 1; margin-bottom: 20px; line-height: 1.6; }
        .card .btn { font-size: 0.9rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body class="analises-page">
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WWPBCTLJ"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->


<header>
    <div class="header-inner">
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav>
            <a href="/"><i class="fa-solid fa-house mr-1"></i> Início</a>
            <a href="/rankings/"><i class="fa-solid fa-chart-simple mr-1"></i> Rankings</a>
            <a href="/analises/" class="active" style="color:var(--text);"><i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Análises</a>
            <a href="/sobre/"><i class="fa-solid fa-circle-info mr-1"></i> Sobre</a>
        </nav>
    </div>
</header>

<div class="hero fade-up">
    <h1>Análises & Inteligência de Mercado</h1>
    <p>Respostas baseadas em dados atualizados da Receita Federal sobre o panorama econômico e distribuição de CNPJs pelo Brasil.</p>
</div>

<div class="c fade-up">
    <div class="bc"><a href="/">Início</a> > Análises</div>
    
    <div class="grid">
        <a href="/analises/maiores-empresas-do-brasil/" class="card">
            <div class="icon">💰</div>
            <h2>As Maiores e Mais Valiosas Empresas do Brasil</h2>
            <p>Descubra as 20 empresas mais ricas do país, quais valem mais que 1 bilhão e como está distribuído o super capital.</p>
            <div class="btn">Ler análise →</div>
        </a>
        
        <a href="/analises/cidades-mais-industrializadas/" class="card">
            <div class="icon">🏭</div>
            <h2>As 10 Cidades Mais Industrializadas do Brasil</h2>
            <p>Onde ficam as maiores fábricas do país? Conheça os estados e as cidades que abrigam os polos industriais nacionais.</p>
            <div class="btn">Ler análise →</div>
        </a>
        
        <a href="/analises/cidades-com-mais-empresas/" class="card">
            <div class="icon">📈</div>
            <h2>Cidades com Mais Empresas e Mais Promissoras</h2>
            <p>Veja qual cidade e qual região concentram o maior número de empresas ativas, comércios e startups na atualidade.</p>
            <div class="btn">Ler análise →</div>
        </a>
    </div>
</div>

<footer style="margin-top: 80px; text-align: center; padding: 40px; border-top: 1px solid var(--border); color: var(--text-muted);">
    <p>© <?php echo date('Y'); ?> BuscaCNPJ Gratis — Baseada 100% em dados públicos abertos.</p>
</footer>


<!-- GTM Custom Events Tracker -->
<script src="/assets/gtm-events.js" defer></script>

</body>
</html>
