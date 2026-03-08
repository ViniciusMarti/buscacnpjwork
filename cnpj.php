<?php
// Conexão MySQL centralizada
require_once __DIR__ . '/config/db.php';

$cnpj = preg_replace('/[^0-9]/', '', $_GET['cnpj'] ?? '');

if (strlen($cnpj) !== 14) {
    header("HTTP/1.0 404 Not Found");
    die("<h1>CNPJ Inválido</h1>");
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM dados_cnpj WHERE cnpj = :cnpj");
    $stmt->execute([':cnpj' => $cnpj]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        header("HTTP/1.0 404 Not Found");
        include('404.php');
        die();
    }
} catch (PDOException $e) {
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}

// Funções de formatação
function format_cnpj($cnpj) {
    return preg_replace("/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/", "$1.$2.$3/$4-$5", $cnpj);
}
function format_money($val) {
    return 'R$ ' . number_format((float)$val, 2, ',', '.');
}

// Prepara dados para exibição
$cnpj_f = format_cnpj($data['cnpj']);
$nome = strtoupper(trim($data['razao_social']));

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
    $data['uf'] = "--";
    $data['telefone'] = "—";
    $data['email'] = "—";
    $data['cnae_principal_descricao'] = "Sincronização em andamento";
    $data['cnae_principal_codigo'] = "Aguarde";
    $data['capital_social'] = 0;
    $data['porte'] = "—";
    $data['data_abertura'] = '';
    $data['cnaes_secundarios'] = '';
    $data['quadro_societario'] = 'Informação não disponível no momento';
} else {
    $situacao = strtoupper($data['situacao'] ?: 'N/A');
}

$badge_class = ($situacao === 'ATIVA') ? 'ba' : (($situacao === 'INAPTA' || $situacao === 'BAIXADA') ? 'ro' : 'bo');

// SEO Optimizations
$meta_title = "$nome - CNPJ $cnpj_f - $situacao";
if (strlen($meta_title) > 60) {
    // Se o nome for muito longo, tentamos encurtar mantendo o CNPJ e Situação
    $available_space = 60 - strlen(" - CNPJ $cnpj_f - $situacao");
    $short_nome = mb_strimwidth($nome, 0, $available_space, "...");
    $meta_title = "$short_nome - CNPJ $cnpj_f - $situacao";
}

$cidade_uf = ($data['municipio'] && $data['uf']) ? $data['municipio'] . '/' . $data['uf'] : ($data['municipio'] ?: $data['uf'] ?: 'Brasil');
$meta_description = "Dados completos da $nome (CNPJ $cnpj_f). Confira o endereço em $cidade_uf, situação cadastral $situacao, CNAE, capital social e quadro de sócios.";
if (strlen($meta_description) > 155) {
    $meta_description = mb_strimwidth($meta_description, 0, 155, "...");
}

?>
<!DOCTYPE html><html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?php echo $meta_title; ?></title>
    <meta name="description" content="<?php echo $meta_description; ?>">
    <link rel="canonical" href="https://buscacnpjgratis.com.br/cnpj/<?php echo $cnpj; ?>/">
    <link rel="stylesheet" href="/assets/cnpj.css?v=1.7.1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script type="application/ld+json">{"@context": "https://schema.org", "@type": "Organization", "name": "<?php echo $nome; ?>", "taxID": "<?php echo $cnpj_f; ?>"}</script>
</head>
<body>
<header><div class="header-inner"><a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a><nav><a href="/">Início</a><a href="/sobre/">Sobre</a></nav></div></header>
<div class="page-wrap fade-up">
    <div class="bc"><a href="/">Início</a> / <a href="/cnpj/">CNPJ</a> / <?php echo $cnpj_f; ?></div>
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
    
    <h2 class="sec-title">Dados de Registro</h2>
    <div class="info-grid">
        <div class="info-box"><label>Razão Social</label><p><?php echo $nome; ?></p></div>
        <div class="info-box"><label>Nome Fantasia</label><p><?php echo $data['nome_fantasia'] ?: '—'; ?></p></div>
        <div class="info-box"><label>Data de Abertura</label><p>
            <?php 
                $data_abertura = $data['data_abertura'];
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
        <div class="info-box"><label>Situação</label><p><?php echo $situacao; ?></p></div>
        <div class="info-box"><label>Porte</label><p><?php echo $data['porte'] ?: 'N/A'; ?></p></div>
        <div class="info-box"><label>Capital Social</label><p><?php echo format_money($data['capital_social']); ?></p></div>
    </div>

    <h2 class="sec-title">Localização & Contato</h2>
    <div class="info-grid">
        <div class="info-box" style="grid-column: span 2;"><label>Endereço</label><p><?php echo $data['logradouro'] . ', ' . $data['numero'] . ($data['complemento'] ? ' - ' . $data['complemento'] : ''); ?></p></div>
        <div class="info-box"><label>Bairro</label><p><?php echo $data['bairro'] ?: '—'; ?></p></div>
        <div class="info-box"><label>Cidade/UF</label><p><?php echo $data['municipio'] . ' — ' . $data['uf']; ?></p></div>
        <div class="info-box"><label>Telefone</label><p><?php echo $data['telefone'] ?: '—'; ?></p></div>
        <div class="info-box"><label>Email</label><p><?php echo strtolower($data['email']) ?: '—'; ?></p></div>
    </div>

    <h2 class="sec-title">Atividades Econômicas</h2>
    <div class="info-box" style="margin-bottom:24px;">
        <label>Atividade Principal</label>
        <p><?php echo $data['cnae_principal_descricao'] . ' (CNAE ' . $data['cnae_principal_codigo'] . ')'; ?></p>
    </div>
    <div class="info-box">
        <label>Atividades Secundárias</label>
        <ul style="list-style:none; padding-top:10px;">
            <?php 
            $sec_str = trim($data['cnaes_secundarios']);
            if(!$sec_str) {
                echo "<li>—</li>";
            } else {
                $separador_sec = strpos($sec_str, ';') !== false ? ';' : '|';
                $sec = explode($separador_sec, $sec_str);
                foreach($sec as $s) {
                    $s = trim($s);
                    if ($s) echo "<li>$s</li>";
                }
            }
            ?>
        </ul>
    </div>

    <h2 class="sec-title">Quadro Societário</h2>
    <div class="info-box">
        <ul style="list-style:none; padding-top:10px;">
            <?php 
            $socios_str = trim($data['quadro_societario']);
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

</div>
<footer><nav><a href="/">Início</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav><p>© 2026 BuscaCNPJ Gratis — Todos os direitos reservados.</p></footer>
<script>
function copyText(txt, btn) {
    navigator.clipboard.writeText(txt).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = "Copiado!";
        setTimeout(() => { btn.innerHTML = originalText; }, 2000);
    }).catch(() => {});
}
</script>
</body></html>
