<?php
// Sobre o BuscaCNPJ Gratis
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-BR2RRQXGCB"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-BR2RRQXGCB');
    </script>
    <title>Sobre o BuscaCNPJ Gratis — Consulta de CNPJ Gratuita</title>
    <meta name="description" content="Saiba mais sobre o BuscaCNPJ Gratis, ferramenta gratuita de consulta de CNPJ de empresas brasileiras com dados da Receita Federal.">
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    
    <link rel="canonical" href="https://buscacnpjgratis.com.br/sobre/">
    
    <!-- CSS & Fonts -->
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/cnpj.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        .sobre-page { min-height: 100vh; display: flex; flex-direction: column; background: var(--bg); }
        .hero-section { padding: 60px 20px; text-align: center; background: radial-gradient(circle at center, var(--primary-glow) 0%, transparent 60%); }
        .hero-section h1 { font-size: clamp(2.5rem, 5vw, 3.5rem); margin-bottom: 15px; line-height: 1.1; }
        .hero-section p { font-size: 1.1rem; color: var(--text-muted); max-width: 600px; margin: 0 auto; }
        
        .content-wrap { max-width: 900px; margin: 0 auto; padding: 40px 20px; flex: 1; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-top: 40px; margin-bottom: 40px; }
        .feature-card { background: var(--surface); padding: 32px; border-radius: 20px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: transform 0.3s; }
        .feature-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: var(--primary); }
        .feature-card .icon { font-size: 2rem; margin-bottom: 16px; }
        .feature-card h3 { font-size: 1.2rem; margin-bottom: 12px; color: var(--text); }
        .feature-card p { font-size: 0.95rem; color: var(--text-muted); line-height: 1.6; }
        
        .info-block { background: var(--surface); padding: 40px; border-radius: 24px; border: 1px solid var(--border); margin-bottom: 32px; }
        .info-block h2 { font-size: 1.8rem; margin-bottom: 16px; color: var(--text); }
        .info-block p { font-size: 1.05rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 16px; }
        .info-block strong { color: var(--text); }
        .info-block a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .info-block a:hover { text-decoration: underline; }
    </style>
    
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "AboutPage",
      "name": "Sobre o BuscaCNPJ Gratis",
      "url": "https://buscacnpjgratis.com.br/sobre/",
      "description": "Ferramenta gratuita de consulta de CNPJ do Brasil.",
      "publisher": {
        "@type": "Organization",
        "name": "GestãoMax",
        "url": "https://buscacnpjgratis.com.br"
      }
    }
    </script>
</head>
<body class="sobre-page">

<header>
    <div class="header-inner">
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav>
            <a href="/">Início</a>
            <a href="/rankings/">Rankings</a>
            <a href="/sobre/" class="active">Sobre</a>
        </nav>
    </div>
</header>

<div class="hero-section fade-up">
    <h1>Sobre a Plataforma</h1>
    <p>Conheça a inteligência por trás do BuscaCNPJ Gratis. Dados oficiais, consultas rápidas e transparência para o mercado brasileiro.</p>
</div>

<div class="content-wrap fade-up">
    <div class="features-grid">
        <div class="feature-card">
            <div class="icon">🚀</div>
            <h3>Rápido e Direto ao Ponto</h3>
            <p>O <strong>BuscaCNPJ Gratis</strong> é uma ferramenta de consulta pública. Centralizamos, de forma rápida e acessível, os dados em um formato limpo — sem cadastro, sem custo e sem complicação.</p>
        </div>
        
        <div class="feature-card">
            <div class="icon">🏛️</div>
            <h3>Fonte Oficial</h3>
            <p>Todas as informações vêm diretamente da <strong>Receita Federal do Brasil</strong>. Elas são estritamente públicas e informativas, sincronizadas para refletir a situação legal de cada CNPJ.</p>
        </div>
        
        <div class="feature-card">
            <div class="icon">⚙️</div>
            <h3>Tecnologia de Ponta</h3>
            <p>Alimentado por um motor de banco de dados robusto de alta performance, gerenciando milhões de registros empresariais com buscas instantâneas focadas na excelente experiência do usuário.</p>
        </div>
    </div>
    
    <div class="info-block">
        <h2>Como Funciona?</h2>
        <p>Basta digitar o CNPJ na <a href="/">página inicial</a> (com ou sem pontuação) e clicar em pesquisar. Nosso sistema retornará automaticamente a razão social, contatos, quadro societário (QSA), atividades econômicas (CNAE) e situação cadastral consolidada.</p>
    </div>
    
    <div class="info-block">
        <h2>Compromisso com a Privacidade</h2>
        <p>A transparência é fundamental para nós. <strong>Nenhum dado pessoal de nossos visitantes é coletado ou rastreado.</strong> Os dados exibidos sobre as empresas são dados públicos de mercado regulados pela transparência governamental. Para mais detalhes, consulte a nossa <a href="/privacidade/">Política de Privacidade</a>.</p>
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
    <p>As informações têm caráter público e informativo. Atualizado em 2026.</p>
    <p style="margin-top: 5px;">© <?php echo date('Y'); ?> <a href="https://buscacnpjgratis.com.br" style="color: inherit; text-decoration: none; font-weight: 500;">GestãoMax</a> — Inteligência de Mercado B2B.</p>
</footer>

</body>
</html>