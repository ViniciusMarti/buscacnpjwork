<!DOCTYPE html><html lang="pt-BR">
<head>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-WWPBCTLJ');</script>
<!-- End Google Tag Manager -->

    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>CNPJ não encontrado | BuscaCNPJ Gratis</title>
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(__DIR__ . '/assets/cnpj.css'); ?>">
    <link href="/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .error-page {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
        }
        .error-card {
            padding: 3rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        .error-card h1 { font-size: 5rem; margin: 0; color: var(--primary); line-height: 1; }
        .error-card h2 { font-size: 1.8rem; color: var(--text); margin-top: 10px; margin-bottom: 20px; }
        .error-card p { font-size: 1.1rem; color: var(--text-muted); margin-bottom: 2rem; line-height: 1.6; }
        .btn-group { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 20px; }
        .btn { display: inline-block; padding: 1rem 2rem; background: var(--primary); color: white; text-decoration: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, background 0.2s; }
        .btn:hover { transform: translateY(-2px); background: #2563eb; }
        .btn-secondary { background: #334155; }
        .btn-secondary:hover { background: #475569; }
        
        .search-container {
            position: relative;
            max-width: 100%;
            margin: 0 auto 30px auto;
        }
        .search-container input {
            width: 100%;
            padding: 20px 25px;
            border-radius: 15px;
            border: 2px solid var(--border);
            background: rgba(255,255,255,0.05); /* adapta a dark mode */
            color: var(--text);
            font-size: 1.2rem;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.3s ease;
        }
        .search-container input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
        }
        .search-container button {
            position: absolute;
            right: 8px;
            top: 8px;
            bottom: 8px;
            padding: 0 30px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .search-container button:hover {
            background: var(--accent);
            transform: scale(1.05);
        }
        
        @media (max-width: 600px) {
            .search-container input { padding-right: 20px; padding-bottom: 70px; }
            .search-container button { position: absolute; bottom: 8px; right: 8px; left: 8px; top: auto; height: 50px; width: auto; padding: 0; }
        }
    </style>
</head>
<body>
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
            <a href="/analises/"><i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Análises</a>
            <a href="/sobre/"><i class="fa-solid fa-circle-info mr-1"></i> Sobre</a>
        </nav>
    </div>
</header>

<?php 
$cnpj_display = isset($cnpj) ? htmlspecialchars($cnpj) : '';
if (strlen($cnpj_display) === 14) {
    $cnpj_display = preg_replace("/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/", "$1.$2.$3/$4-$5", $cnpj_display);
} elseif (isset($_GET['cnpj'])) {
    $cnpj_display = htmlspecialchars($_GET['cnpj']);
}
?>
<div class="page-wrap fade-up">
    <div class="error-page">
        <div class="error-card">
            <h1>404</h1>
            <h2>CNPJ não encontrado</h2>
            <p>O CNPJ solicitado <?php if($cnpj_display) echo "<strong>" . $cnpj_display . "</strong>"; ?> não consta em nossa base de dados ou é inválido.</p>
            
            <div class="search-container">
                <input id="q" type="text" maxlength="18" placeholder="Digite outro CNPJ (apenas números)..." 
                       onkeydown="if(event.key==='Enter')buscar()" aria-label="Digite o CNPJ para consulta" autofocus>
                <button onclick="buscar()">Consultar</button>
            </div>

            <div class="btn-group">
                <a href="/" class="btn">Voltar ao Início</a>
                <a href="/rankings/" class="btn btn-secondary">Acessar Rankings</a>
            </div>
        </div>
    </div>
</div>

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

document.getElementById('q').addEventListener('input', function (e) {
    var x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})/);
    e.target.value = !x[2] ? x[1] : x[1] + '.' + x[2] + '.' + x[3] + '/' + x[4] + (x[5] ? '-' + x[5] : '');
});
</script>

<!-- GTM Custom Events Tracker -->
<script src="/assets/gtm-events.js" defer></script>

</body>
</html>
