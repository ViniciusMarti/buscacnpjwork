<?php
// Configurações básicas
$meta_title = "Contato — BuscaCNPJ Gratis";
$meta_description = "Entre em contato com o BuscaCNPJ Gratis para dúvidas, sugestões ou solicitações de remoção de dados.";
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
    <link href="/fontawesome/css/all.min.css" rel="stylesheet">
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
        .contact-box {
            padding: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            margin-top: 32px;
            text-align: center;
        }
        .contact-email {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            display: block;
            margin-top: 10px;
        }
        .contact-email:hover {
            color: var(--primary-hover);
        }
        .removal-form {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            margin-top: 24px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            padding: 14px 18px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-family: inherit;
            font-size: 1rem;
            color: var(--text);
            transition: border-color 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn-whatsapp {
            width: 100%;
            padding: 16px;
            background: #25D366;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: opacity 0.2s;
        }
        .btn-whatsapp:hover {
            opacity: 0.9;
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

<main class="page-wrap fade-up">
    <div class="content-page">
        <nav aria-label="Breadcrumb" class="bc" style="margin-bottom: 32px;">
            <a href="/">Início</a> / Contato
        </nav>

        <h1>Página de Contato</h1>
        
        <div class="info-box">
            <h3>Fale conosco</h3>
            <p>Para dúvidas, sugestões ou solicitações, nosso canal oficial é via e-mail.</p>

            <div class="contact-box">
                <p style="margin-bottom: 5px;">E-mail oficial para contato:</p>
                <a href="mailto:contato@buscacnpjgratis.com.br" class="contact-email">contato@buscacnpjgratis.com.br</a>
            </div>

            <hr style="margin: 40px 0; border: none; border-top: 1px solid var(--border);">

            <h3>Solicitação de remoção de dados</h3>
            <p><strong>Aviso Legal:</strong> Os dados exibidos neste portal são de caráter <strong>100% público</strong> e provenientes diretamente da base da <strong>Receita Federal do Brasil</strong> (em conformidade com a Lei nº 12.527/2011).</p>
            
            <p>Se você é o representante legal e deseja solicitar a remoção, preencha o formulário abaixo para enviar sua solicitação via WhatsApp:</p>

            <div class="removal-form">
                <div class="form-group">
                    <label for="f-cnpj">CNPJ da Empresa</label>
                    <input type="text" id="f-cnpj" class="form-input" placeholder="00.000.000/0000-00">
                </div>
                <div class="form-group">
                    <label for="f-email">E-mail de Contato</label>
                    <input type="email" id="f-email" class="form-input" placeholder="seu@email.com">
                </div>
                <div class="form-group">
                    <label for="f-celular">Celular / WhatsApp</label>
                    <input type="tel" id="f-celular" class="form-input" placeholder="(00) 00000-0000">
                </div>
                <button onclick="enviarWhatsapp()" class="btn-whatsapp">
                    Solicitar via WhatsApp
                </button>
            </div>

            <script>
            function enviarWhatsapp() {
                const cnpj = document.getElementById('f-cnpj').value;
                const email = document.getElementById('f-email').value;
                const celular = document.getElementById('f-celular').value;
                
                if(!cnpj || !email || !celular) {
                    alert('Por favor, preencha todos os campos.');
                    return;
                }
                
                const mensagem = `Olá, solicito a remoção de dados do site BuscaCNPJ Grátis.\n\n` +
                                `CNPJ: ${cnpj}\n` +
                                `E-mail: ${email}\n` +
                                `Celular: ${celular}`;
                
                const url = `https://wa.me/5541999783444?text=${encodeURIComponent(mensagem)}`;
                window.open(url, '_blank');
            }
            </script>

            <h3>Tempo de resposta</h3>
            <p>Analisamos cada mensagem individualmente e respondemos em até <strong>5 dias úteis</strong>. Note que para remoção definitiva de dados das bases públicas, a alteração deve ser feita diretamente no cadastro da <strong>Receita Federal</strong>.</p>
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
