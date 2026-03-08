<!DOCTYPE html><html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>CNPJ — BuscaCNPJ Gratis — Consulta Gratuita de Empresas</title>
    <meta name="description" content="Acesse a base de dados oficial e gratuita do CNPJ. Consulte a situação cadastral, contatos e endereço de qualquer empresa brasileira.">
    <?php $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
    <link rel="canonical" href="<?php echo $current_url; ?>">
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(__DIR__ . '/../assets/cnpj.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="home-page">
<header>
    <div class="header-inner">
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav><a href="/">Início</a><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a></nav>
    </div>
</header>

<div class="page-wrap fade-up" style="flex:1; display:flex; flex-direction:column; justify-content:center;">
    <div class="bc" style="justify-content:center; margin-bottom: 50px;"><a href="/">Início</a> / CNPJ</div>
    
    <div class="home-hero" style="margin-top:0; padding-bottom:100px;">
        <h1 style="font-size: clamp(2.5rem, 5vw, 3.5rem);">Explorar Empresas</h1>
        <p>Acesse as informações oficiais registradas na Receita Federal de todo o Brasil.</p>
        <div class="search-container" style="width: 100%; max-width: 600px; margin: 0 auto; border: 1px solid var(--border);">
            <input id="q" type="text" maxlength="18" placeholder="Digite o CNPJ..." onkeydown="if(event.key==='Enter')buscar()">
            <button onclick="buscar()">Consultar</button>
        </div>
        
        <div style="margin-top: 40px; display: flex; gap: 20px; flex-wrap: wrap; justify-content: center;">
            <div style="background: var(--surface); padding: 15px 25px; border-radius: 12px; border: 1px solid var(--border); font-size: 0.9rem; font-weight:600;">
                <span style="color:var(--primary);">+58M</span> empresas cadastradas
            </div>
             <div style="background: var(--surface); padding: 15px 25px; border-radius: 12px; border: 1px solid var(--border); font-size: 0.9rem; font-weight:600;">
                <span style="color:var(--primary);">100%</span> Gratuito
            </div>
            <div style="background: var(--surface); padding: 15px 25px; border-radius: 12px; border: 1px solid var(--border); font-size: 0.9rem; font-weight:600;">
                <span style="color:var(--primary);">Oficial</span> Dados do Governo
            </div>
        </div>
    </div>
</div>

<footer>
    <nav><a href="/">Início</a><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav>
    <p>© 2026 BuscaCNPJ Gratis — Consulta de CNPJ Rápida e Fácil.</p>
</footer>

<script>
function buscar(){
    var q = document.getElementById('q').value.replace(/\D/g,'');
    if(q.length === 14) window.location.href = './' + q + '/';
    else if(q.length > 0) alert('Erro: É necessário digitar os 14 dígitos do CNPJ para prosseguir.');
}
</script>
</body></html>
