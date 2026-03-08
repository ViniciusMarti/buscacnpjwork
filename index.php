<?php
// Conexão MySQL centralizada
require_once __DIR__ . '/config/db.php';

$total_cnpjs = 0;

try {
    $db = getDB();
    $total_cnpjs = $db->query("SELECT COUNT(*) FROM dados_cnpj")->fetchColumn() ?: 0;
} catch (Exception $e) {
    $total_cnpjs = "milhões de";
}

// Formatação do número
$display_count = is_numeric($total_cnpjs) ? number_format($total_cnpjs, 0, ',', '.') : $total_cnpjs;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuscaCNPJ Gratis — Consulta Gratuita de CNPJ</title>
    <meta name="description" content="Consulte dados oficiais de qualquer CNPJ do Brasil de forma gratuita, rápida e atualizada.">
    
    <!-- CSS & Fonts -->
    <link rel="stylesheet" href="/assets/cnpj.css?v=1.7.1">
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
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav>
            <a href="/sobre/">Sobre</a>
            <a href="/contato/">Contato</a>
        </nav>
    </div>
</header>

<main class="home-page">
    <div class="home-hero fade-up">
        <h1>Consulte qualquer CNPJ grátis.</h1>
        <p>Acesso simplificado aos dados públicos da Receita Federal em segundos.</p>
        
        <div class="search-container">
            <input id="q" type="text" maxlength="18" placeholder="Digite o CNPJ (apenas números)..." 
                   onkeydown="if(event.key==='Enter')buscar()" autofocus>
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
