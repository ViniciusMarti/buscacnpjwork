<?php
// Conexão MySQL centralizada
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/utils.php';

$cnpj = preg_replace('/[^0-9]/', '', $_GET['cnpj'] ?? '');

if (strlen($cnpj) !== 14) {
    header("HTTP/1.0 404 Not Found");
    include('404-cnpj.php');
    die();
}

try {
    $data = fetchCNPJ($cnpj);

    if (!$data) {
        header("HTTP/1.0 404 Not Found");
        include('404-cnpj.php');
        die();
    }
} catch (Exception $e) {
    die("Erro ao consultar os bancos de dados: " . $e->getMessage());
}


// Funções de formatação
function format_cnpj($cnpj) {
    return preg_replace("/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/", "$1.$2.$3/$4-$5", $cnpj);
}
function format_money($val) {
    return 'R$ ' . number_format((float)$val, 2, ',', '.');
}
function str_limit($str, $limit, $end = '...') {
    if (strlen($str) <= $limit) return $str;
    return substr($str, 0, $limit - strlen($end)) . $end;
}

// Prepara dados para exibição
$cnpj_f = format_cnpj($data['cnpj']);
$nome = strtoupper(trim($data['razao_social']));

$tempo_abertura_texto = '—';
if (!empty($data['data_inicio_atividade']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['data_inicio_atividade'])) {
    try {
        $abertura_dt = new DateTime($data['data_inicio_atividade']);
        $hoje_dt = new DateTime();
        if ($abertura_dt <= $hoje_dt) {
            $diff = $hoje_dt->diff($abertura_dt);
            $partes_tempo = [];
            if ($diff->y > 0) $partes_tempo[] = $diff->y . ($diff->y == 1 ? ' ano' : ' anos');
            if ($diff->m > 0) $partes_tempo[] = $diff->m . ($diff->m == 1 ? ' mês' : ' meses');
            if ($diff->d > 0) $partes_tempo[] = $diff->d . ($diff->d == 1 ? ' dia' : ' dias');
            
            if (empty($partes_tempo)) {
                $tempo_abertura_texto = 'Hoje';
            } else if (count($partes_tempo) == 1) {
                $tempo_abertura_texto = $partes_tempo[0];
            } else {
                $ultimo = array_pop($partes_tempo);
                $tempo_abertura_texto = implode(', ', $partes_tempo) . ' e ' . $ultimo;
            }
        }
    } catch (Exception $e) {
        $tempo_abertura_texto = '—';
    }
}

$is_updating = empty($nome);
if ($is_updating) {
    $nome = "CADASTRO EM ATUALIZAÇÃO";
    $situacao = "AGUARDANDO SYNC";
    $data['nome_fantasia'] = "Processando informações junto à base unificada...";
    $data['logradouro'] = "Aguardando sincronização de dados cadastrais";
    $data['numero'] = "S/N";
    $data['complemento'] = "";
    $data['bairro'] = "—";
    $data['municipio'] = "Aguardando";
    $data['sigla_uf'] = "--";
    $data['telefone_1'] = "—";
    $data['email'] = "—";
    $data['cnae_principal_descricao'] = "Sincronização em andamento";
    $data['cnae_fiscal_principal'] = "Aguarde";
    $data['capital_social'] = 0;
    $data['porte'] = "—";
    $data['data_inicio_atividade'] = '';
    $data['cnae_fiscal_secundária'] = '';
    $data['socios_texto'] = 'Informação não disponível no momento';
} else {
    $situacao = strtoupper($data['situacao_cadastral'] ?: 'N/A');
}

// Helpers para Breadcrumb
$state_data = get_states_data();
$uf_db = strtoupper($data['sigla_uf'] ?? '');
$state_name_bc = $state_data['names'][$uf_db] ?? 'Brasil';
$state_slug_bc = array_search($uf_db, $state_data['slugs']) ?: '';

$city_name_bc = titleCase($data['municipio'] ?? '');
$city_slug_bc = $city_name_bc ? slugify($city_name_bc) : '';

$badge_class = ($situacao === 'ATIVA') ? 'ba' : (($situacao === 'INAPTA' || $situacao === 'BAIXADA') ? 'ro' : 'bo');

// SEO Optimizations
$meta_title = "$nome - CNPJ $cnpj_f - $situacao";
if (strlen($meta_title) > 60) {
    // Se o nome for muito longo, tentamos encurtar mantendo o CNPJ e Situação
    $available_space = 60 - strlen(" - CNPJ $cnpj_f - $situacao");
    $short_nome = str_limit($nome, $available_space, "...");
    $meta_title = "$short_nome - CNPJ $cnpj_f - $situacao";
}

$cidade_uf = ($data['municipio'] && $data['sigla_uf']) ? $data['municipio'] . '/' . $data['sigla_uf'] : ($data['municipio'] ?: $data['sigla_uf'] ?: 'Brasil');
$meta_description = "Dados completos da $nome (CNPJ $cnpj_f). Confira o endereço em $cidade_uf, situação cadastral $situacao, CNAE, capital social e quadro de sócios.";
if (strlen($meta_description) > 155) {
    $meta_description = str_limit($meta_description, 155, "...");
}

// CNAE Details Lookup - Atualiza a descrição com base no novo banco
$cnae_db = getCNAEDB();
if ($cnae_db !== null) {
    try {
        // 1. Atividade Principal
        if (!empty($data['cnae_fiscal_principal'])) {
            $cnae_clean = preg_replace('/[^0-9]/', '', $data['cnae_fiscal_principal']);
            // Tenta buscar no SQLite (tabela cnaes, colunas codigo e descricao)
            $stmt_cnae = $cnae_db->prepare("SELECT descricao FROM cnaes WHERE codigo = :cnae LIMIT 1");
            $stmt_cnae->execute([':cnae' => $cnae_clean]);
            $cnae_info = $stmt_cnae->fetch(PDO::FETCH_ASSOC);
            if ($cnae_info && !empty($cnae_info['descricao'])) {
                $data['cnae_principal_descricao'] = $cnae_info['descricao'];
            }
        }

        // 2. Atividades Secundárias
        $sec_str = trim($data['cnae_fiscal_secundaria']);
        if ($sec_str) {
            $separador_sec = strpos($sec_str, ';') !== false ? ';' : '|';
            $sec_codes = explode($separador_sec, $sec_str);
            $sec_com_descricao = [];
            
            foreach ($sec_codes as $code) {
                $code = trim($code);
                if (!$code) continue;
                $code_clean = preg_replace('/[^0-9]/', '', $code);
                $stmt_sec = $cnae_db->prepare("SELECT descricao FROM cnaes WHERE codigo = :cnae LIMIT 1");
                $stmt_sec->execute([':cnae' => $code_clean]);
                $res_sec = $stmt_sec->fetch(PDO::FETCH_ASSOC);
                
                if ($res_sec && !empty($res_sec['descricao'])) {
                    $sec_com_descricao[] = $res_sec['descricao'] . ' (' . $code . ')';
                } else {
                    $sec_com_descricao[] = $code;
                }
            }
            $data['secundarias_processadas'] = $sec_com_descricao;
        }
    } catch (Exception $e) { /* Silencioso */ }
}

// SEO Text & FAQ Generation (Programmatic SEO)
$data_ab_formatada = (!empty($data['data_inicio_atividade']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['data_inicio_atividade'])) ? date('d/m/Y', strtotime($data['data_inicio_atividade'])) : ($data['data_inicio_atividade'] ?: 'N/A');
$texto_sobre = "A empresa $nome de CNPJ $cnpj_f, foi fundada em $data_ab_formatada na cidade {$data['municipio']} no estado {$data['sigla_uf']}. Sua atividade principal, conforme a Receita Federal, é {$data['cnae_principal_descricao']}. Sua situação cadastral até o momento é $situacao.";

$faq_questions = [
    [
        "q" => "De quem é o CNPJ $cnpj_f?",
        "a" => "O CNPJ pertence à empresa $nome."
    ],
    [
        "q" => "Qual o endereço da empresa $nome?",
        "a" => "A empresa está localizada na {$data['logradouro']}, {$data['numero']} " . ($data['complemento'] ? "({$data['complemento']}), " : "") . "{$data['bairro']}, em {$data['municipio']}/{$data['sigla_uf']}."
    ],
    [
        "q" => "Qual a atividade principal de $nome?",
        "a" => "A atividade principal registrada é {$data['cnae_principal_descricao']} (CNAE {$data['cnae_fiscal_principal']})."
    ],
    [
        "q" => "A quanto tempo a empresa $nome está aberta?",
        "a" => "A empresa está aberta há aproximadamente $tempo_abertura_texto."
    ],
    [
        "q" => "Qual o telefone de $nome?",
        "a" => "O telefone registrado é " . ($data['telefone_1'] ?: "não informado") . "."
    ],
    [
        "q" => "Qual o email de $nome?",
        "a" => "O email de contato é " . (strtolower($data['email']) ?: "não informado") . "."
    ],
    [
        "q" => "Qual a data de abertura de $nome?",
        "a" => "A empresa foi aberta em $data_ab_formatada."
    ]
];

// Lógica de Filiais / Matriz (Internal Link Building)
$cnpj_base = substr($cnpj, 0, 8);
$cnpj_ordem = substr($cnpj, 8, 4);
$is_matriz = ($cnpj_ordem === '0001');

$outras_unidades = [];
$dados_matriz = null;

try {
    $connections = getAllConnections();
    if ($is_matriz) {
        // Busca filiais ATIVAS (limitado para performance e UX)
        foreach ($connections as $db) {
            $stmt_unidades = $db->prepare("SELECT cnpj, municipio, sigla_uf FROM dados_cnpj WHERE cnpj LIKE :base AND cnpj != :atual AND situacao_cadastral = 'ATIVA' LIMIT 50");
            $stmt_unidades->execute([':base' => $cnpj_base . '%', ':atual' => $cnpj]);
            $res = $stmt_unidades->fetchAll(PDO::FETCH_ASSOC);
            if ($res) {
                $outras_unidades = array_merge($outras_unidades, $res);
            }
            if (count($outras_unidades) >= 50) {
                $outras_unidades = array_slice($outras_unidades, 0, 50);
                break;
            }
        }
    } else {
        // Busca a Matriz
        foreach ($connections as $db) {
            $stmt_matriz = $db->prepare("SELECT cnpj, municipio, sigla_uf FROM dados_cnpj WHERE cnpj LIKE :matriz_padrao LIMIT 1");
            $stmt_matriz->execute([':matriz_padrao' => $cnpj_base . '0001%']);
            $dados_matriz = $stmt_matriz->fetch(PDO::FETCH_ASSOC);
            if ($dados_matriz) break;
        }
    }
} catch (Exception $e) {
    // Silencioso
}



$faq_schema = [
    "@context" => "https://schema.org",
    "@type" => "FAQPage",
    "mainEntity" => array_map(function($faq) {
        return [
            "@type" => "Question",
            "name" => $faq['q'],
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => $faq['a']
            ]
        ];
    }, $faq_questions)
];
$faq_schema_json = json_encode($faq_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>
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
    <title><?php echo $meta_title; ?></title>
    <meta name="description" content="<?php echo $meta_description; ?>">
    <?php $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
    <link rel="canonical" href="<?php echo $current_url; ?>">
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(__DIR__ . '/assets/cnpj.css'); ?>">
    <link href="/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script type="application/ld+json">{"@context": "https://schema.org", "@type": "Organization", "name": "<?php echo $nome; ?>", "taxID": "<?php echo $cnpj_f; ?>"}</script>
    <script type="application/ld+json"><?php echo $faq_schema_json; ?></script>
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WWPBCTLJ"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<header><div class="header-inner"><a class="logo" href="/" aria-label="BuscaCNPJ Grátis - Ir para a página inicial">Busca<span>CNPJ</span> Grátis</a><nav><a href="/"><i class="fa-solid fa-house mr-1"></i> Início</a><a href="/rankings/"><i class="fa-solid fa-chart-simple mr-1"></i> Rankings</a><a href="/analises/"><i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Análises</a><a href="/sobre/"><i class="fa-solid fa-circle-info mr-1"></i> Sobre</a></nav></div></header>
<div class="page-wrap fade-up">
    <nav aria-label="Breadcrumb" class="bc">
        <a href="/">Início</a> 
        <?php if ($state_slug_bc): ?>
            / <a href="/rankings/<?php echo $state_slug_bc; ?>/"><?php echo $state_name_bc; ?></a>
        <?php endif; ?>
        <?php if ($city_slug_bc && $state_slug_bc): ?>
            / <a href="/rankings/<?php echo $state_slug_bc; ?>/<?php echo $city_slug_bc; ?>/"><?php echo $city_name_bc; ?></a>
        <?php endif; ?>
        / <?php echo $cnpj_f; ?>
    </nav>
    <div class="company-hero">
        <div class="badge <?php echo $badge_class; ?>"><?php echo $situacao; ?></div>
        <h1 class="company-title"><?php echo $nome; ?></h1>
        <?php if ($data['nome_fantasia']): ?>
        <p style="color:var(--text-muted); font-size: 0.9rem; margin-top:-10px; margin-bottom:10px;"><?php echo strtoupper($data['nome_fantasia']); ?></p>
        <?php endif; ?>
        <p style="color:var(--text-muted); font-weight:600; margin-bottom: 20px;">CNPJ <?php echo $cnpj_f; ?></p>
        <div class="copy-group">
            <button class="btn-copy" onclick="copyText('<?php echo addslashes($nome); ?>', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar Nome</button>
            <button class="btn-copy" onclick="copyText('<?php echo $cnpj; ?>', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copiar CNPJ</button>
            <button class="btn-copy" onclick="copyText('<?php echo $cnpj_f; ?>', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Formatado</button>
        </div>
    </div>
    
    <div class="info-box" style="margin-bottom:24px;">
        <p style="font-size: 1.05rem; line-height: 1.6; color: var(--text-color); margin: 0;"><?php echo $texto_sobre; ?></p>
    </div>

    <h2 class="sec-title">Dados de Registro</h2>
    <div class="info-grid">
        <div class="info-box"><div class="data-label">Razão Social</div><p><?php echo $nome; ?></p></div>
        <div class="info-box"><div class="data-label">Nome Fantasia</div><p><?php echo $data['nome_fantasia'] ?: '—'; ?></p></div>
        <div class="info-box"><div class="data-label">Data de Abertura</div><p>
            <?php 
                $data_abertura = $data['data_inicio_atividade'];
                if ($data_abertura) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_abertura)) {
                        echo date('d/m/Y', strtotime($data_abertura));
                    } else {
                        echo $data_abertura;
                    }
                } else {
                    echo '—';
                }
            ?>
        </p></div>
        <div class="info-box"><div class="data-label">Tempo de Abertura</div><p><?php echo $tempo_abertura_texto; ?></p></div>
        <div class="info-box"><div class="data-label">Situação</div><p><?php echo $situacao; ?></p></div>
        <div class="info-box"><div class="data-label">Natureza Jurídica</div><p><?php echo $data['natureza_juridica'] ?: '—'; ?></p></div>
        <div class="info-box"><div class="data-label">Porte</div><p><?php echo $data['porte'] ?: 'N/A'; ?></p></div>
        <div class="info-box"><div class="data-label">Capital Social</div><p><?php echo format_money($data['capital_social']); ?></p></div>
    </div>

    <!-- CAMBLY AD -->
    <section class="promo-banner fade-up">
        <div class="promo-content">
            <div class="promo-brand">
                <img src="/assets/cambly-logo.png" alt="Cambly Logo">
            </div>
            <h3>Comece hoje. Fale inglês com confiança.</h3>
            <p>Chega de travar quando precisa falar inglês. Aprenda de forma prática com aulas online feitas para quem quer evoluir rápido, com professores experientes e alunos do mundo todo. Estude no seu ritmo, pratique conversação desde o início e ganhe confiança para usar o inglês na vida real.</p>
            
            <ul class="promo-benefits">
                <li><i class="fas fa-check-circle"></i> Professores experientes focados em conversação</li>
                <li><i class="fas fa-check-circle"></i> Aulas online ao vivo em pequenos grupos</li>
                <li><i class="fas fa-check-circle"></i> Prática ilimitada de fala, pronúncia e gramática</li>
            </ul>

            <div class="promo-price">
                <div class="price-details">
                    <span class="price-old">De R$ 93/mês</span>
                    <div class="price-new">
                        <span class="amount">R$ 37</span>
                        <span class="term">/mês</span>
                    </div>
                </div>
                <span class="badge">MENOR PREÇO</span>
            </div>
            
            <a href="https://www.cambly.com/invite/VINICIUSCODES?st=031124&referralCode=VINICIUSCODES" class="promo-cta" target="_blank" rel="sponsored">
                Quero começar a aprender inglês →
            </a>
            
            <p class="disclaimer">
                * Preço sujeito a alteração sem aviso prévio.
            </p>
        </div>
        <div class="promo-image"></div>
    </section>
    <!-- /CAMBLY AD -->

    <h2 class="sec-title">Localização & Contato</h2>
    <div class="info-grid">
        <div class="info-box" style="grid-column: span 2;"><div class="data-label">Endereço</div><p><?php echo $data['logradouro'] . ', ' . $data['numero'] . ($data['complemento'] ? ' - ' . $data['complemento'] : ''); ?></p></div>
        <div class="info-box"><div class="data-label">Bairro</div><p><?php echo $data['bairro'] ?: '—'; ?></p></div>
        <div class="info-box"><div class="data-label">Cidade/UF</div><p><?php echo $data['municipio'] . ' — ' . $data['sigla_uf']; ?> <?php echo $data['id_municipio'] ? '<span style="font-size:0.7rem; color:var(--text-muted);">('.$data['id_municipio'].')</span>' : ''; ?></p></div>
        <div class="info-box"><div class="data-label">Telefone</div><p><?php 
            $tel1 = ($data['ddd_1'] ? "({$data['ddd_1']}) " : "") . $data['telefone_1'];
            $tel2 = ($data['ddd_2'] ? "({$data['ddd_2']}) " : "") . $data['telefone_2'];
            echo $tel1 ?: '—'; 
            if ($tel2) echo " / " . $tel2;
        ?></p></div>
        <div class="info-box"><div class="data-label">Email</div><p><?php echo strtolower($data['email']) ?: '—'; ?></p></div>
    </div>

    <!-- Google Maps Card -->
    <div class="info-box fade-up" style="margin-top: 24px; padding: 0; overflow: hidden; border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-lg); position: relative;">
        <div style="position: absolute; top: 15px; left: 15px; z-index: 10; background: var(--surface); padding: 8px 16px; border-radius: 100px; font-size: 0.75rem; font-weight: 800; color: var(--primary); box-shadow: var(--shadow-md); border: 1px solid var(--border); display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-location-dot"></i> LOCALIZAÇÃO NO MAPA
        </div>
        <iframe 
            width="100%" 
            height="400" 
            frameborder="0" 
            style="border:0; display: block; filter: contrast(1.05) saturate(1.1);" 
            src="https://maps.google.com/maps?q=<?php echo urlencode($data['logradouro'] . ', ' . $data['numero'] . ' - ' . $data['bairro'] . ', ' . $data['municipio'] . ' - ' . $data['sigla_uf']); ?>&output=embed" 
            allowfullscreen>
        </iframe>
    </div>

    <!-- Botões de Navegação -->
    <div class="nav-buttons fade-up" style="display: flex; gap: 16px; margin-top: 16px; margin-bottom: 32px; flex-wrap: wrap;">
        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($data['logradouro'] . ', ' . $data['numero'] . ' - ' . $data['bairro'] . ', ' . $data['municipio'] . ' - ' . $data['sigla_uf']); ?>" 
           target="_blank" 
           class="btn-nav" 
           style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 12px; padding: 16px 24px; background: white; border: 1px solid #e2e8f0; border-radius: 16px; text-decoration: none; color: #1e293b; font-weight: 700; transition: all 0.2s; box-shadow: var(--shadow-sm); min-width: 200px;">
            <img src="/assets/Google_Maps_icon_(2020).svg.png" alt="Google Maps" style="height: 24px; width: auto;">
            Abrir no Google Maps
        </a>
        <a href="https://waze.com/ul?q=<?php echo urlencode($data['logradouro'] . ', ' . $data['numero'] . ' - ' . $data['bairro'] . ', ' . $data['municipio'] . ' - ' . $data['sigla_uf']); ?>" 
           target="_blank" 
           class="btn-nav" 
           style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 12px; padding: 16px 24px; background: white; border: 1px solid #e2e8f0; border-radius: 16px; text-decoration: none; color: #1e293b; font-weight: 700; transition: all 0.2s; box-shadow: var(--shadow-sm); min-width: 200px;">
            <img src="/assets/pngimg.com - waze_PNG40.png" alt="Waze" style="height: 24px; width: auto;">
            Abrir no Waze
        </a>
    </div>

    <h2 class="sec-title">Opções Tributárias</h2>
    <div class="info-grid" style="margin-bottom:24px;">
        <div class="info-box">
            <div class="data-label">Optante pelo Simples</div>
            <p><?php 
                if ($data['opcao_simples'] == '1') {
                    $dt = $data['data_opcao_simples'];
                    echo 'SIM' . ($dt ? ' (Desde ' . date('d/m/Y', strtotime($dt)) . ')' : '');
                } else {
                    echo 'NÃO';
                }
            ?></p>
        </div>
        <div class="info-box">
            <div class="data-label">Optante pelo MEI</div>
            <p><?php 
                if ($data['opcao_mei'] == '1') {
                    $dt_mei = $data['data_opcao_mei'];
                    echo 'SIM' . ($dt_mei ? ' (Desde ' . date('d/m/Y', strtotime($dt_mei)) . ')' : '');
                } else {
                    echo 'NÃO';
                }
            ?></p>
        </div>
    </div>

    <h2 class="sec-title">Atividades Econômicas</h2>
    <div class="info-box" style="margin-bottom:24px;">
        <div class="data-label">Atividade Principal</div>
        <p><?php echo $data['cnae_principal_descricao'] . ' (CNAE ' . $data['cnae_fiscal_principal'] . ')'; ?></p>
    </div>
    <div class="info-box">
        <div class="data-label">Atividades Secundárias</div>
        <ul style="list-style:none; padding-top:10px;">
            <?php 
            if (isset($data['secundarias_processadas']) && !empty($data['secundarias_processadas'])) {
                foreach ($data['secundarias_processadas'] as $s) {
                    echo "<li>$s</li>";
                }
            } else {
                echo "<li>—</li>";
            }
            ?>
        </ul>
    </div>

    <h2 class="sec-title">Quadro Societário</h2>
    <div class="info-box">
        <ul style="list-style:none; padding-top:10px;">
            <?php 
            $socios_str = trim($data['socios_texto']);
            if(!$socios_str || $socios_str == 'Informação não disponível') {
                echo "<li>Informação não disponível</li>";
            } else {
                // Tenta dividir por ; (novo padrão premium) ou | (padrão antigo do primeiro script)
                $separador = strpos($socios_str, ';') !== false ? ';' : '|';
                $socios = explode($separador, $socios_str);
                foreach($socios as $socio) {
                    $socio = trim($socio);
                    if ($socio) {
                        $parts = explode(' - ', $socio, 2);
                        $s_nome = strtoupper(trim($parts[0]));
                        $s_cargo = isset($parts[1]) ? trim($parts[1]) : 'Sócio';
                        echo "<li><strong>$s_nome</strong>" . (isset($parts[1]) ? " <span>$s_cargo</span>" : "") . "</li>";
                    }
                }
            }
            ?>
        </ul>
    </div>

    <h2 class="sec-title">Filiais desta empresa</h2>
    <div class="info-box" style="margin-bottom: 32px;">
        <?php if (!$is_matriz && $dados_matriz): ?>
            <!-- Cenário B: Filial visualizando a Matriz -->
            <h3 style="font-size: 1.1rem; margin-bottom: 12px; color: var(--text-bold);">Sede / Matriz</h3>
            <p style="margin-bottom: 10px;">Esta empresa é uma <strong>FILIAL</strong>. A sede principal está localizada em:</p>
            <ul style="list-style:none;">
                <li>
                    <strong><?php echo $nome; ?> (MATRIZ)</strong> - <?php echo format_cnpj($dados_matriz['cnpj']); ?> 
                    <a href="/<?php echo $dados_matriz['cnpj']; ?>/" style="color:var(--primary); font-weight:600;">(<?php echo $dados_matriz['sigla_uf'] . ', ' . $dados_matriz['municipio']; ?>)</a>
                </li>
            </ul>
        <?php elseif ($is_matriz && count($outras_unidades) > 0): ?>
            <!-- Cenário A: Matriz com Filiais -->
            <h3 style="font-size: 1.1rem; margin-bottom: 12px; color: var(--text-bold);">Filiais</h3>
            <p style="margin-bottom: 15px;">Total de <strong><?php echo count($outras_unidades); ?></strong> <?php echo count($outras_unidades) > 1 ? 'filiais ativas' : 'filial ativa'; ?> registradas. (Listagem de unidades com situação cadastral ativa):</p>
            <ul style="list-style:none; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px;">
                <?php foreach($outras_unidades as $f): ?>
                <li style="font-size: 0.9rem; padding: 8px; background: rgba(0,0,0,0.02); border-radius: 6px;">
                    <strong><?php echo str_limit($nome, 25); ?></strong> - <?php echo format_cnpj($f['cnpj']); ?> 
                    <br>
                    <a href="/<?php echo $f['cnpj']; ?>/" style="color:var(--primary); font-weight:600;">(<?php echo $f['sigla_uf'] . ', ' . $f['municipio']; ?>)</a>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <!-- Cenário C: Empresa Única -->
            <p style="color: var(--text-muted); margin: 0;">A empresa <strong><?php echo $nome; ?></strong> não possui outras filiais ativas registradas no momento ou é uma unidade única.</p>
        <?php endif; ?>
    </div>

    <h2 class="sec-title">Perguntas Frequentes (FAQ)</h2>
    <div class="faq-container" style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 32px;">
        <?php foreach($faq_questions as $faq): ?>
        <div class="faq-item info-box" style="margin-bottom: 0;">
            <h3 style="font-size: 1.05rem; margin-bottom: 8px; color: var(--text-bold); font-weight: 600;"><?php echo $faq['q']; ?></h3>
            <p style="color: var(--text-color); margin: 0; line-height: 1.5; font-weight: 400;"><?php echo $faq['a']; ?></p>
        </div>
        <?php endforeach; ?>
    </div>

</div>
<footer><nav><a href="/">Início</a><a href="/rankings/">Rankings</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav><p>© 2026 BuscaCNPJ Gratis — Todos os direitos reservados.</p></footer>
<script>
function copyText(txt, btn) {
    navigator.clipboard.writeText(txt).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = "Copiado!";
        setTimeout(() => { btn.innerHTML = originalText; }, 2000);
    }).catch(() => {});
}
</script>

<!-- GTM Custom Events Tracker -->
<script src="/assets/gtm-events.js" defer></script>

</body></html>
