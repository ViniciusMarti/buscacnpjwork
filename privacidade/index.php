<?php
// Configurações básicas
$meta_title = "Política de Privacidade — BuscaCNPJ Gratis";
$meta_description = "Saiba como o BuscaCNPJ Gratis lida com seus dados. Informações públicas da Receita Federal e compromisso com a transparência.";
$current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
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
    <title><?php echo $meta_title; ?></title>
    <meta name="description" content="<?php echo $meta_description; ?>">
    <link rel="canonical" href="<?php echo $current_url; ?>">
    
    <!-- CSS & Fonts -->
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/cnpj.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        .content-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }
        .content-page h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 24px;
            letter-spacing: -0.02em;
        }
        .content-page h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            margin-top: 32px;
            margin-bottom: 12px;
        }
        .content-page p {
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 16px;
        }
        .content-page strong {
            color: var(--text);
        }
        .last-update {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 40px;
            display: block;
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
            <a href="/">Início</a>
            <a href="/rankings/">Rankings</a>
            <a href="/sobre/">Sobre</a>
        </nav>
    </div>
</header>

<main class="page-wrap fade-up">
    <div class="content-page">
        <nav aria-label="Breadcrumb" class="bc" style="margin-bottom: 32px;">
            <a href="/">Início</a> / Privacidade
        </nav>

        <h1>Política de Privacidade</h1>
        <span class="last-update">Última atualização: 05/03/2026</span>

        <div class="info-box">
            <h3>1. Coleta de dados pessoais</h3>
            <p>O BuscaCNPJ Gratis <strong>não coleta dados pessoais</strong> dos visitantes. Não há formulários de cadastro, login ou rastreamento individual de usuários.</p>

            <h3>2. Dados exibidos</h3>
            <p>Todas as informações de CNPJ exibidas são <strong>dados públicos</strong> da Receita Federal do Brasil, conforme a Lei de Acesso à Informação (Lei nº 12.527/2011). Não armazenamos nem tratamos dados privados de pessoas físicas.</p>

            <h3>3. Cookies e analytics</h3>
            <p>Podemos utilizar ferramentas de análise de tráfego (Google Tag Manager/Analytics) com dados agregados e anônimos para entender o volume de acessos. Nenhum dado individual é identificado.</p>

            <h3>4. Links externos</h3>
            <p>Este site pode conter links para serviços externos como a BrasilAPI e Receita Federal. Não nos responsabilizamos pelas políticas de privacidade desses serviços operados por terceiros.</p>

            <h3>5. LGPD</h3>
            <p>Em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), ressaltamos que não realizamos o tratamento de dados pessoais dos visitantes deste site.</p>

            <h3>6. Contato</h3>
            <p>Dúvidas sobre nossa política? Entre em <a href="/contato/" style="color: var(--primary); font-weight: 600;">contato</a>.</p>
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

<!-- GTM Custom Events Tracker -->
<script src="/assets/gtm-events.js" defer></script>

</body>
</html>
