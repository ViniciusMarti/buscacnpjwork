<!DOCTYPE html><html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Página não encontrada | BuscaCNPJ Gratis</title>
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(__DIR__ . '/assets/cnpj.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; }
        .error-card { padding: 3rem; background: var(--card-bg); border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); max-width: 500px; }
        h1 { font-size: 5rem; margin: 0; color: var(--primary); }
        p { font-size: 1.2rem; color: var(--text-muted); margin-bottom: 2rem; }
        .btn { display: inline-block; padding: 1rem 2rem; background: var(--primary); color: white; text-decoration: none; border-radius: 10px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="error-card">
        <h1>404</h1>
        <h2>Página não encontrada</h2>
        <p>O conteúdo solicitado não consta em nossa base de dados ou foi movido.</p>
        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
            <a href="/" class="btn">Início</a>
            <a href="/rankings/" class="btn" style="background:#334155;">Rankings</a>
        </div>
    </div>
</body>
</html>
