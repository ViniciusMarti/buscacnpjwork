#!/usr/bin/env python3
"""
gerar_paginas_site.py — BuscaCNPJ.work
Gera todas as páginas estáticas do site além das páginas de empresa:
  - index.html          (home com busca)
  - sitemap.xml         (todas as URLs)
  - sobre/index.html    (sobre o site)
  - privacidade/index.html
  - contato/index.html
"""

import os
import json
from datetime import datetime

BASE_DIR      = "site-cnpj"
DOMAIN        = "https://buscacnpj.work"
PROGRESS_FILE = "progresso.json"

# ── Lê o progresso.json para montar a lista de empresas ─────────────────────
def carregar_dados():
    if not os.path.exists(PROGRESS_FILE):
        print("⚠️  progresso.json não encontrado. Continuando sem lista de empresas.")
        return [], []

    with open(PROGRESS_FILE, "r", encoding="utf-8") as f:
        prog = json.load(f)

    processed   = prog.get("processed", [])
    index_links = prog.get("index_links", [])
    return processed, index_links

# ── Formatador de CNPJ ───────────────────────────────────────────────────────
def fmt_cnpj(cnpj: str) -> str:
    c = cnpj.zfill(14)
    return f"{c[:2]}.{c[2:5]}.{c[5:8]}/{c[8:12]}-{c[12:]}"

# ── CSS compartilhado (mesmo do gerador_v2) ──────────────────────────────────
CSS = """
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --text:#333; --muted:#888; --border:#ebebeb; --bg:#fff; --dark:#1a1a1a; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                 Helvetica, Arial, sans-serif;
    line-height: 1.7; color: var(--text); background: var(--bg); font-size: 1rem;
  }
  header {
    padding: 14px 24px; border-bottom: 1px solid var(--border);
    position: sticky; top: 0; background: rgba(255,255,255,0.97);
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 999;
  }
  .header-inner {
    max-width: 1100px; margin: 0 auto;
    display: flex; align-items: center; gap: 24px;
  }
  .logo { font-weight: 800; font-size: 1rem; text-decoration: none;
          color: var(--dark); letter-spacing: -0.3px; }
  header nav { display: flex; gap: 22px; align-items: center; flex: 1; }
  header nav a { text-decoration: none; color: #555; font-size: 0.95rem; transition: color 0.15s; }
  header nav a:hover { color: var(--dark); }
  .container { max-width: 800px; margin: 0 auto; padding: 40px 20px 60px; }
  .hero {
    padding: 64px 24px; text-align: center;
    border-bottom: 1px solid var(--border); margin-bottom: 48px;
  }
  .hero h1 { font-size: 2.2rem; margin-bottom: 10px; font-weight: 800;
             letter-spacing: -0.5px; color: #111; }
  .hero p  { color: var(--muted); font-size: 1.05rem; margin-bottom: 28px; }
  .search-row { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
  .search-row input {
    padding: 12px 16px; font-size: 1rem; border: 1px solid var(--border);
    border-radius: 6px; width: 320px; max-width: 100%; outline: none;
    font-family: inherit; color: #111;
  }
  .search-row input:focus { border-color: #aaa; }
  .search-row button {
    padding: 12px 22px; background: var(--dark); color: #fff;
    font-size: 0.95rem; font-weight: 600; border: none; border-radius: 6px;
    cursor: pointer; font-family: inherit; transition: background 0.15s;
  }
  .search-row button:hover { background: #333; }
  h1 { font-size: 1.9rem; color: #111; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 16px; }
  h2 { font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
       letter-spacing: 1px; color: var(--muted); border-bottom: 1px solid var(--border);
       padding-bottom: 8px; margin: 32px 0 16px; }
  p  { color: #444; margin-bottom: 16px; font-size: 0.97rem; }
  ul.data-list { list-style: none; }
  ul.data-list li {
    padding: 12px 0; border-bottom: 1px solid var(--border);
    font-size: 0.95rem; color: #333;
  }
  ul.data-list li:last-child { border-bottom: none; }
  ul.data-list li a { color: var(--dark); text-decoration: none; font-weight: 500; }
  ul.data-list li a:hover { text-decoration: underline; }
  ul.data-list li span { color: var(--muted); font-size: 0.85rem; }
  .grid-cards {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px; margin-top: 16px;
  }
  .card-link {
    display: block; padding: 16px; border: 1px solid var(--border);
    border-radius: 8px; text-decoration: none; color: var(--dark);
    transition: border-color 0.15s;
  }
  .card-link:hover { border-color: #aaa; }
  .card-link strong { display: block; font-size: 0.92rem; margin-bottom: 4px; }
  .card-link span { font-size: 0.78rem; color: var(--muted); }
  hr { border: none; border-top: 1px solid var(--border); margin: 32px 0; }
  footer {
    border-top: 1px solid var(--border); padding: 32px 20px;
    text-align: center; color: var(--muted); font-size: 0.85rem;
  }
  footer a { color: var(--muted); text-decoration: none; }
  footer a:hover { color: var(--dark); text-decoration: underline; }
  footer nav { display: flex; justify-content: center; gap: 20px;
               margin-bottom: 12px; flex-wrap: wrap; }
  a { color: var(--dark); }
  @media (prefers-color-scheme: dark) {
    :root { --text:#e8e8e8; --muted:#777; --border:#2f2f2f; --bg:#191919; --dark:#f0f0f0; }
    header { background: rgba(25,25,25,0.97) !important; }
    h1 { color: #f0f0f0; } .hero h1 { color: #f0f0f0; }
    p, ul.data-list li { color: #c9c9c9; }
    .search-row input { background: #2a2a2a; color: #e8e8e8; border-color: #3a3a3a; }
    .search-row button { background: #e8e8e8; color: #111; }
    .card-link { background: #222; }
  }
</style>
"""

# ── HEADER / FOOTER comuns ───────────────────────────────────────────────────
def header():
    return f"""<header>
  <div class="header-inner">
    <a class="logo" href="{DOMAIN}">buscacnpj.work</a>
    <nav>
      <a href="{DOMAIN}">início</a>
      <a href="{DOMAIN}/sobre/">sobre</a>
    </nav>
  </div>
</header>"""

def footer():
    return f"""<footer>
  <nav>
    <a href="{DOMAIN}/">Início</a>
    <a href="{DOMAIN}/sobre/">Sobre</a>
    <a href="{DOMAIN}/privacidade/">Privacidade</a>
    <a href="{DOMAIN}/contato/">Contato</a>
  </nav>
  <p>© 2026 <a href="{DOMAIN}">BuscaCNPJ.work</a> — Dados públicos da Receita Federal do Brasil.</p>
</footer>"""

def busca_js():
    return """<script>
  function buscar() {
    var raw = document.getElementById('cnpjInput').value.replace(/\D/g,'');
    if (raw.length === 14) {
      window.location.href = './cnpj/' + raw + '/';
    } else {
      alert('Digite um CNPJ válido com 14 dígitos.');
    }
  }
  document.getElementById('cnpjInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') buscar();
  });
</script>"""

def base_head(title, desc, canonical, extra=""):
    return f"""<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{title}</title>
  <meta name="description" content="{desc}">
  <link rel="canonical" href="{canonical}">
  <meta property="og:title" content="{title}">
  <meta property="og:description" content="{desc}">
  <meta property="og:type" content="website">
  <meta property="og:url" content="{canonical}">
  {extra}
  {CSS}
</head>"""

def salvar(caminho_relativo: str, html: str):
    caminho = os.path.join(BASE_DIR, caminho_relativo)
    os.makedirs(os.path.dirname(caminho), exist_ok=True)
    with open(caminho, "w", encoding="utf-8") as f:
        f.write(html)
    print(f"  ✅  Gerado: site-cnpj/{caminho_relativo}")

# ════════════════════════════════════════════════════════════════════════════
# 1. HOME — index.html
# ════════════════════════════════════════════════════════════════════════════
def gerar_home(index_links: list, total: int):
    # Lista principal (todas as empresas em grid)
    cards = "".join([
        f"""<a class="card-link" href="cnpj/{c}/">
              <strong>{n[:40] + ("…" if len(n) > 40 else "")}</strong>
              <span>{fmt_cnpj(c)}</span>
            </a>"""
        for c, n in index_links
    ])

    schema = json.dumps({
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "BuscaCNPJ.work",
        "url": DOMAIN,
        "description": "Consulta gratuita de CNPJ de empresas brasileiras",
        "potentialAction": {
            "@type": "SearchAction",
            "target": f"{DOMAIN}/cnpj/{{cnpj}}/",
            "query-input": "required name=cnpj"
        }
    }, ensure_ascii=False)

    html = f"""<!DOCTYPE html>
<html lang="pt-BR">
{base_head(
    "BuscaCNPJ.work — Consulta Gratuita de CNPJ",
    "Consulte dados públicos de qualquer empresa brasileira pelo CNPJ. Situação cadastral, endereço, sócios e atividades direto da Receita Federal. Gratuito.",
    f"{DOMAIN}/",
    extra=f'<script type="application/ld+json">{schema}</script>'
)}
<body>
{header()}

<div class="hero">
  <h1>Consulta de CNPJ</h1>
  <p>Dados públicos de empresas brasileiras direto da Receita Federal.</p>
  <div class="search-row">
    <input type="text" id="cnpjInput" maxlength="18"
           placeholder="00.000.000/0000-00" autocomplete="off">
    <button onclick="buscar()">Consultar</button>
  </div>
  <p style="font-size:0.8rem;color:#aaa;margin-top:12px">
    Digite apenas os números ou o CNPJ formatado
  </p>
</div>

<div class="container" style="padding-top:0">

  <div>
    <h2>Empresas cadastradas ({total:,})</h2>
    <div class="grid-cards">{cards}</div>
  </div>

  <hr>

  <div>
    <h2>O que é o BuscaCNPJ.work?</h2>
    <p>O <strong>BuscaCNPJ.work</strong> é uma ferramenta gratuita para consultar dados públicos de empresas brasileiras registradas na Receita Federal. Todas as informações exibidas são de domínio público.</p>
    <p>Você pode consultar <strong>razão social, nome fantasia, situação cadastral, endereço, sócios e atividades econômicas (CNAE)</strong> de qualquer CNPJ ativo ou baixado.</p>
  </div>

</div>

{footer()}
{busca_js()}
</body>
</html>"""

    salvar("index.html", html)

# ════════════════════════════════════════════════════════════════════════════
# 2. SOBRE — sobre/index.html
# ════════════════════════════════════════════════════════════════════════════
def gerar_sobre(total: int):
    html = f"""<!DOCTYPE html>
<html lang="pt-BR">
{base_head(
    "Sobre o BuscaCNPJ.work — Consulta de CNPJ",
    "Saiba mais sobre o BuscaCNPJ.work, a ferramenta gratuita de consulta de CNPJ de empresas brasileiras com dados da Receita Federal.",
    f"{DOMAIN}/sobre/"
)}
<body>
{header()}

<div class="container">

  <h1>Sobre o BuscaCNPJ.work</h1>

  <h2>O que é</h2>
  <p>O <strong>BuscaCNPJ.work</strong> é um site de consulta pública de CNPJs de empresas brasileiras. Nosso objetivo é centralizar, de forma rápida e acessível, os dados da Receita Federal em um formato fácil de ler.</p>
  <p>Atualmente o banco de dados conta com <strong>{total:,} empresas cadastradas</strong>, com novas páginas sendo adicionadas regularmente.</p>

  <h2>Fonte dos dados</h2>
  <p>Todas as informações exibidas são provenientes de fontes públicas, principalmente da <strong>Receita Federal do Brasil</strong>, disponibilizadas via APIs abertas como a <a href="https://brasilapi.com.br" target="_blank" rel="noopener">BrasilAPI</a> e a <a href="https://minhareceita.org" target="_blank" rel="noopener">Minha Receita</a>.</p>
  <p>Os dados têm caráter estritamente público e informativo. Nenhuma informação sigilosa ou privada é coletada ou exibida.</p>

  <h2>Como usar</h2>
  <p>Digite o CNPJ (apenas números ou no formato XX.XXX.XXX/XXXX-XX) na barra de busca da <a href="{DOMAIN}">página inicial</a> e clique em <strong>Consultar</strong>.</p>
  <p>Se a empresa ainda não estiver em nossa base, ela será adicionada nas próximas atualizações.</p>

  <h2>Tecnologia</h2>
  <p>O site é 100% estático, sem banco de dados, gerado automaticamente por scripts Python e hospedado no <strong>Cloudflare Pages</strong>. Isso garante carregamento ultra-rápido e zero downtime.</p>

</div>

{footer()}
</body>
</html>"""

    salvar("sobre/index.html", html)

# ════════════════════════════════════════════════════════════════════════════
# 3. PRIVACIDADE — privacidade/index.html
# ════════════════════════════════════════════════════════════════════════════
def gerar_privacidade():
    html = f"""<!DOCTYPE html>
<html lang="pt-BR">
{base_head(
    "Política de Privacidade — BuscaCNPJ.work",
    "Leia a política de privacidade do BuscaCNPJ.work. Não coletamos dados pessoais dos usuários.",
    f"{DOMAIN}/privacidade/"
)}
<body>
{header()}

<div class="container">

  <h1>Política de Privacidade</h1>
  <p style="color:var(--muted);font-size:0.85rem">Última atualização: {datetime.now().strftime("%d/%m/%Y")}</p>

  <h2>Coleta de dados</h2>
  <p>O BuscaCNPJ.work <strong>não coleta dados pessoais</strong> dos seus visitantes. Não utilizamos formulários de cadastro, login ou qualquer mecanismo de identificação de usuários.</p>

  <h2>Dados exibidos</h2>
  <p>Todas as informações de CNPJ exibidas neste site são <strong>dados públicos</strong> disponibilizados pela Receita Federal do Brasil. Não tratamos, nem armazenamos, dados privados de pessoas físicas.</p>

  <h2>Cookies e rastreamento</h2>
  <p>Podemos utilizar ferramentas de análise de tráfego (como Google Analytics) para entender o volume de acessos ao site. Esses dados são agregados e anônimos, sem identificação individual.</p>

  <h2>Links externos</h2>
  <p>Este site pode conter links para fontes externas como a BrasilAPI e a Receita Federal. Não nos responsabilizamos pelo conteúdo ou pelas políticas de privacidade desses serviços.</p>

  <h2>Contato</h2>
  <p>Dúvidas sobre privacidade? Entre em <a href="{DOMAIN}/contato/">contato</a>.</p>

</div>

{footer()}
</body>
</html>"""

    salvar("privacidade/index.html", html)

# ════════════════════════════════════════════════════════════════════════════
# 4. CONTATO — contato/index.html
# ════════════════════════════════════════════════════════════════════════════
def gerar_contato():
    html = f"""<!DOCTYPE html>
<html lang="pt-BR">
{base_head(
    "Contato — BuscaCNPJ.work",
    "Entre em contato com o BuscaCNPJ.work para dúvidas, sugestões ou solicitações de remoção de dados.",
    f"{DOMAIN}/contato/"
)}
<body>
{header()}

<div class="container">

  <h1>Contato</h1>

  <h2>Fale conosco</h2>
  <p>Para dúvidas, sugestões ou solicitações de remoção de informações, envie um e-mail para:</p>
  <p><strong>contato@buscacnpj.work</strong></p>

  <h2>Solicitação de remoção</h2>
  <p>Caso você seja representante legal de uma empresa e deseje solicitar a remoção ou correção de dados exibidos neste site, envie sua solicitação para o e-mail acima com o CNPJ e a justificativa.</p>
  <p>Todas as informações exibidas são de domínio público e provenientes da Receita Federal. Analisaremos cada caso individualmente.</p>

  <h2>Tempo de resposta</h2>
  <p>Respondemos em até <strong>5 dias úteis</strong>.</p>

</div>

{footer()}
</body>
</html>"""

    salvar("contato/index.html", html)

# ════════════════════════════════════════════════════════════════════════════
# 5. SITEMAP — sitemap.xml
# ════════════════════════════════════════════════════════════════════════════
def gerar_sitemap(processed: list):
    today = datetime.now().strftime("%Y-%m-%d")

    paginas_fixas = [
        (f"{DOMAIN}/",              "daily",   "1.0"),
        (f"{DOMAIN}/sobre/",        "monthly", "0.5"),
        (f"{DOMAIN}/privacidade/",  "yearly",  "0.3"),
        (f"{DOMAIN}/contato/",      "monthly", "0.4"),
    ]

    urls = ""
    for loc, freq, pri in paginas_fixas:
        urls += f"  <url><loc>{loc}</loc><changefreq>{freq}</changefreq><priority>{pri}</priority></url>\n"

    for c in processed:
        urls += (f"  <url><loc>{DOMAIN}/cnpj/{c}/</loc>"
                 f"<lastmod>{today}</lastmod>"
                 f"<changefreq>monthly</changefreq>"
                 f"<priority>0.8</priority></url>\n")

    xml = f"""<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{urls}</urlset>"""

    salvar("sitemap.xml", xml)

# ════════════════════════════════════════════════════════════════════════════
# 6. ROBOTS.TXT
# ════════════════════════════════════════════════════════════════════════════
def gerar_robots():
    content = f"""User-agent: *
Allow: /

Sitemap: {DOMAIN}/sitemap.xml
"""
    salvar("robots.txt", content)

# ════════════════════════════════════════════════════════════════════════════
# MAIN
# ════════════════════════════════════════════════════════════════════════════
def main():
    processed, index_links = carregar_dados()
    total = len(processed)

    print()
    print("=" * 55)
    print("  BuscaCNPJ.work — Gerador de Páginas do Site")
    print(f"  Empresas no progresso.json: {total:,}")
    print("=" * 55)
    print()

    gerar_home(index_links, total)
    gerar_sobre(total)
    gerar_privacidade()
    gerar_contato()
    gerar_sitemap(processed)
    gerar_robots()

    print()
    print("=" * 55)
    print("  ✅  TODAS AS PÁGINAS GERADAS COM SUCESSO!")
    print()
    print("  Estrutura criada:")
    print("  site-cnpj/")
    print("  ├── index.html")
    print("  ├── sitemap.xml")
    print("  ├── robots.txt")
    print("  ├── sobre/index.html")
    print("  ├── privacidade/index.html")
    print("  ├── contato/index.html")
    print("  └── cnpj/  (já existente)")
    print()
    print("  Próximo passo: git add . && git push")
    print("=" * 55)


if __name__ == "__main__":
    main()
