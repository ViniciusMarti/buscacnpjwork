<?php
// Otimização: Evitar COUNT(*) em tabela de 17GB a cada load. 
// Usamos um valor aproximado da base para performance instantânea.
$total_cnpjs = 55843210; 
$display_count = number_format($total_cnpjs, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuscaCNPJ Gratis — Consulta Gratuita de CNPJ</title>
    <meta name="description" content="Consulte dados oficiais de qualquer CNPJ do Brasil de forma gratuita, rápida e atualizada.">
    <?php $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
    <link rel="canonical" href="<?php echo $current_url; ?>">
    
    <!-- CSS & Fonts -->
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/cnpj.css'); ?>">
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
    </style>
</head>
<body class="home-page-body">

<header>
    <div class="header-inner">
        <a class="logo" href="/" aria-label="BuscaCNPJ Grátis - Ir para a página inicial">Busca<span>CNPJ</span> Grátis</a>
        <nav>
            <a href="/">Início</a>
            <a href="/rankings/">Rankings</a>
            <a href="/sobre/">Sobre</a>
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
            <button onclick="buscar()">Consultar</button>
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
        window.location.href = '/cnpj/' + q + '/';
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

</body>
</html>
