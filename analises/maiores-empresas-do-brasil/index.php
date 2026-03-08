<?php
require_once dirname(dirname(__DIR__)) . '/config/db.php';
require_once dirname(dirname(__DIR__)) . '/config/utils.php';

$title = "As 20 Maiores Empresas do Brasil em " . date('Y') . " | BuscaCNPJ Grátis";
$description = "Descubra quais são as 20 maiores e mais ricas empresas do Brasil. Veja a lista oficial das companhias mais valiosas por capital social ativo.";
$canonical = "https://buscacnpjgratis.com.br/analises/maiores-empresas-do-brasil/";

try {
    $db = getDB();
    
    // As 20 Maiores
    $stmt = $db->query("SELECT * FROM dados_cnpj WHERE capital_social > 0 AND CAST(capital_social AS INTEGER) NOT LIKE '999%' ORDER BY capital_social DESC LIMIT 20");
    $top20 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Empresas que valem mais que 1 bilhão (count approximate)
    $stmt_bilhao = $db->query("SELECT COUNT(*) as c FROM dados_cnpj WHERE capital_social >= 1000000000");
    $count_bilhao = $stmt_bilhao->fetchColumn();
    
    // Top 5 separadas
    $top5 = array_slice($top20, 0, 5);

} catch (Exception $e) {
    $top20 = [];
    $count_bilhao = 'Várias';
    $top5 = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <meta name="description" content="<?php echo $description; ?>">
    <link rel="canonical" href="<?php echo $canonical; ?>">
    
    <!-- CSS & Fonts -->
    <link rel="stylesheet" href="/assets/cnpj.css?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/cnpj.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        .article-page { background: var(--bg); }
        .hero { padding: 80px 20px 40px; text-align: center; }
        .hero h1 { font-size: clamp(2.5rem, 6vw, 4rem); margin-bottom: 20px; line-height: 1.1; letter-spacing: -1px; }
        .hero p { font-size: 1.15rem; color: var(--text-muted); max-width: 800px; margin: 0 auto; line-height: 1.6; }
        
        .c { max-width: 900px; margin: 0 auto; padding: 0 20px 80px; }
        .bc { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 40px; font-weight: 600; text-align: center; }
        .bc a { color: var(--text-muted); text-decoration: none; transition: 0.2s; }
        .bc a:hover { color: var(--text); }
        
        .content-box { background: var(--surface); padding: 40px; border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); margin-bottom: 40px; }
        
        .article-content h2 { font-size: 2rem; margin: 40px 0 20px; color: var(--text); border-bottom: 2px solid var(--border); padding-bottom: 10px; }
        .article-content h3 { font-size: 1.4rem; margin: 30px 0 15px; color: var(--text); }
        .article-content p { font-size: 1.05rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 20px; }
        .article-content ul { margin-left: 20px; margin-bottom: 20px; color: var(--text-muted); font-size: 1.05rem; line-height: 1.7; }
        .article-content li { margin-bottom: 10px; }
        .article-content strong { color: var(--text); }
        
        .ranking-table-wrap { overflow-x: auto; background: var(--bg); border-radius: 16px; border: 1px solid var(--border); margin: 30px 0; }
        .ranking-table { width: 100%; border-collapse: collapse; text-align: left; }
        .ranking-table th { background: var(--surface); padding: 16px 24px; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        .ranking-table td { padding: 20px 24px; border-bottom: 1px solid var(--border); font-size: 0.95rem; }
        .ranking-table tr:last-child td { border-bottom: none; }
        .ranking-table .rank { color: var(--primary); font-weight: 900; }
        .ranking-table .name { font-weight: 700; color: var(--text); text-decoration: none; }
        .ranking-table .name:hover { color: var(--primary); }
    </style>
    
    <!-- FAQ Schema para People Also Ask -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Quais são as 20 maiores empresas do Brasil?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "A lista das 20 maiores empresas do Brasil é liderada por companhias do setor de energia, financeiro e extração mineral. A maior empresa declarada frequentemente possui o maior capital social registrado na Receita Federal do país."
          }
        },
        {
          "@type": "Question",
          "name": "Qual é a empresa mais rica do Brasil?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Baseado nos registros oficiais de capital social da Receita Federal, as instituições financeiras (bancos) e conglomerados de energia costumam liderar como as empresas 'mais ricas' (com maior patrimônio e capital social) do Brasil."
          }
        },
        {
          "@type": "Question",
          "name": "Quais são as 5 empresas mais valiosas do Brasil?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Atualmente as empresas mais valiosas do país incluem grandes estatais, corporações financeiras e bancos consolidados, sendo que seu valor flutua, mas seu capital estrutural é dos maiores contabilizados legalmente."
          }
        },
        {
          "@type": "Question",
          "name": "Qual empresa vale 1 bilhão?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Hoje existem diversos CNPJs registrados no Brasil com capital social declarado superior a 1 bilhão de reais, formando a elite corporativa brasileira."
          }
        }
      ]
    }
    </script>
</head>
<body class="article-page">

<header>
    <div class="header-inner">
        <a class="logo" href="/">Busca<span>CNPJ</span> Grátis</a>
        <nav>
            <a href="/">Início</a>
            <a href="/rankings/">Rankings</a>
            <a href="/analises/" class="active" style="color:var(--text);">Análises</a>
            <a href="/sobre/">Sobre</a>
        </nav>
    </div>
</header>

<div class="hero fade-up">
    <h1>As Maiores e Mais Valiosas Empresas do Brasil</h1>
    <p>Uma análise profunda com base nos dados públicos de Capital Social declarados à Receita Federal revelando os maiores impérios corporativos do país.</p>
</div>

<div class="c fade-up">
    <div class="bc"><a href="/">Início</a> > <a href="/analises/">Análises</a> > Maiores Empresas</div>
    
    <div class="content-box article-content">
        <h2>O Panorama das Gigantes Nacionais</h2>
        <p>Ao analisar a estrutura do Produto Interno Bruto (PIB) do Brasil, observamos que grande parte da riqueza nacional está concentrada nas mãos de companhias que compõem o topo da pirâmide corporativa. Com os dados da Receita Federal em tempo real, podemos responder a perguntas que moldam as análises de mercado todos os dias.</p>
        
        <h3>Qual é a empresa mais rica do Brasil?</h3>
        <p>A definição de "empresa mais rica" pode variar se levarmos em consideração o valor de mercado na bolsa de valores (Market Cap) versus o <strong>Capital Social</strong> estrutural documentado legalmente pelo CNPJ (o quanto os sócios, governo e acionistas aportaram). Olhando pelo retrovisor legal contábil, vemos sempre bancos mastodônticos e corporações energéticas no topo.</p>

        <h3>Quais são as 5 empresas mais valiosas do Brasil?</h3>
        <p>Considerando nossos cruzamentos estruturais, aqui está o topo da cadeia onde a disputa por capital flutua com altíssima frequência nestes setores:</p>
        <ul>
            <?php foreach($top5 as $t5): ?>
                <li><strong><?php echo $t5['razao_social']; ?></strong> — <?php echo format_money_friendly($t5['capital_social']); ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Qual empresa vale 1 bilhão?</h3>
        <p>Esta é uma pergunta comum (o famoso conceito de Unicórnio e corporações massivas). Através da base de inteligência do BuscaCNPJ Gratis, confirmamos que existem atualmente <strong>aproximadamente <?php echo $count_bilhao; ?> CNPJs</strong> no Brasil cujo capital social ultrapassa legalmente a barreira histórica de R$ 1.000.000.000,00 (1 bilhão de reais).</p>

        <h2>Quais são as 20 maiores empresas do Brasil?</h2>
        <p>Abaixo apresentamos o ranking atualizado das gigantes corporativas instaladas legalmente em solo brasileiro hoje:</p>

        <div class="ranking-table-wrap">
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th>Pos.</th>
                        <th>Empresa</th>
                        <th>CNAE Principal</th>
                        <th>Capital Social</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $r = 1; foreach($top20 as $emp): ?>
                    <tr>
                        <td class="rank">#<?php echo $r++; ?></td>
                        <td>
                            <a href="/cnpj/<?php echo $emp['cnpj']; ?>/" class="name"><?php echo $emp['razao_social']; ?></a>
                            <div style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace; margin-top:4px;"><?php echo $emp['cnpj']; ?></div>
                        </td>
                        <td style="font-size:0.85rem; max-width:250px;"><?php echo mb_strimwidth($emp['cnae_principal_descricao'] ?? '', 0, 45, '...'); ?></td>
                        <td style="font-weight:700;"><?php echo format_money_friendly($emp['capital_social']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <h3>Quanto vale uma empresa que fatura R$ 100.000 por mês?</h3>
        <p>Embora uma empresa que fature 100 mil reais mensais não esteja neste topo nacional, analistas costumam usar múltiplos de valuation. Uma abordagem rápida (valuation por múltiplo de lucro ou faturamento) em médios negócios sugere multiplicadores de 2x a 5x do faturamento anual, podendo precificar este tipo de serviço e indústria local entre 2 a 5 milhões de reais caso a operação seja limpa e deixe uma boa margem – algo distante destas corporações brasileiras da lista, mas a fundação de nossa economia forte e diversificada.</p>
    </div>
</div>

<footer style="text-align: center; padding: 40px; border-top: 1px solid var(--border); color: var(--text-muted);">
    <p>© <?php echo date('Y'); ?> BuscaCNPJ Gratis — Baseada 100% em dados públicos abertos.</p>
</footer>

</body>
</html>
