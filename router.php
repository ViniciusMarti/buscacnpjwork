<?php
/**
 * Router para simular o .htaccess do Apache no servidor embutido do PHP (Localhost)
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Arquivos estáticos (imagens, CSS, JS, fontes)
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|woff2?|ttf|svg|ico)$/', $path)) {
    return false; // serve o arquivo como está
}

// 1. Roteamento para página de CNPJ (ex: /11465216000149)
if (preg_match('~^/([0-9]{14})/?$~', $path, $matches)) {
    $_GET['cnpj'] = $matches[1];
    include __DIR__ . '/cnpj.php';
    return true;
}

// 2. Roteamento Rankings Index
if ($path === '/rankings' || $path === '/rankings/') {
    include __DIR__ . '/rankings-index.php';
    return true;
}

// 3. Excluir pastas físicas conhecidas para não confundir com estados
$reserved = ['assets', 'config', 'database', 'scripts', 'sitemaps', 'contato', 'privacidade', 'sobre', 'analises', 'importador'];
$first_part = explode('/', ltrim($path, '/'))[0];

if (!in_array($first_part, $reserved)) {
    // 4. Ranking Cidade (ex: /amazonas/manaus)
    if (preg_match('~^/([a-z-]+)/([a-z0-9-]+)/?$~', $path, $matches)) {
        $_GET['estado_slug'] = $matches[1];
        $_GET['cidade_slug'] = $matches[2];
        if (file_exists(__DIR__ . '/cidade.php')) {
            include __DIR__ . '/cidade.php';
            return true;
        }
    }

    // 5. Ranking Estado (ex: /amazonas)
    if (preg_match('~^/([a-z-]+)/?$~', $path, $matches)) {
        $_GET['slug'] = $matches[1];
        if (file_exists(__DIR__ . '/ranking.php')) {
            include __DIR__ . '/ranking.php';
            return true;
        }
    }
}

// Fallback index
if ($path == '/' || $path == '/index.html') {
    if (file_exists(__DIR__ . '/index.php')) {
        include __DIR__ . '/index.php';
    } else if (file_exists(__DIR__ . '/index.html')) {
        include __DIR__ . '/index.html';
    }
    return true;
}

// URLs amigáveis .html (ex: /sobre carrega sobre.html)
$html_file = __DIR__ . $path . '.html';
if (file_exists($html_file)) {
    include $html_file;
    return true;
}

// URLs amigáveis .php (ex: /teste carrega teste.php)
$php_file = __DIR__ . $path . '.php';
if (file_exists($php_file)) {
    include $php_file;
    return true;
}

// Servir o arquivo real caso ele exista (ex: /404.php ou sitemap)
if (file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

// Se não achou nada
http_response_code(404);
if (file_exists(__DIR__ . '/404.php')) {
    include __DIR__ . '/404.php';
} else {
    echo "<h1>404 - Página não encontrada</h1>";
}
return true;
