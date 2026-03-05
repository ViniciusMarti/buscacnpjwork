#!/usr/bin/env python3
"""
gerador.py — BuscaCNPJ.work
Gera site estático com páginas de empresas brasileiras via BrasilAPI + Minha Receita.
"""

import requests
import os
import time
import json
import logging
from datetime import datetime

# ─── CONFIGURAÇÕES ────────────────────────────────────────────────────────────
BASE_DIR       = "site-cnpj"
DOMAIN         = "https://buscacnpj.work"
PROGRESS_FILE  = "progresso.json"
MAX_PAGES      = 1000       # Altere para gerar mais páginas
SLEEP_OK       = 4.0        # Delay entre requisições bem-sucedidas (segundos)
SLEEP_404      = 0.3        # Delay para CNPJs não encontrados
BACKOFF_BASE   = 60         # Tempo inicial de espera após rate-limit (segundos)
BACKOFF_MAX    = 900        # Tempo máximo de espera (15 min)
BRASIL_COOLDOWN_ERROS = 3   # Nº de 429 para desativar BrasilAPI temporariamente

# APIs
API_BRASIL       = "https://brasilapi.com.br/api/cnpj/v1/"
API_MINHA_RECEITA = "https://minhareceita.org/"

# CNPJs iniciais conhecidos (grandes empresas garantidas)
SEED_CNPJS = [
    "33000167000101",  # Petrobras
    "00000000000191",  # Banco do Brasil
    "33592510000154",  # Eletrobras
    "06066228000121",  # Itaú
    "64170450000105",  # Bradesco
    "19131243000197",  # Ambev
    "00360305000104",  # Caixa Econômica
    "02429144000193",  # Embraer
    "33683111000280",  # SERPRO
    "05486851000115",  # Nubank
]

# ─── LOGGING ──────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%H:%M:%S",
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler("gerador.log", encoding="utf-8"),
    ]
)
log = logging.getLogger(__name__)

# ─── VALIDADOR DE CNPJ ────────────────────────────────────────────────────────
def validar_cnpj(cnpj: str) -> bool:
    """Valida CNPJ pelo algoritmo dos dígitos verificadores."""
    cnpj = "".join(d for d in cnpj if d.isdigit())
    if len(cnpj) != 14 or len(set(cnpj)) == 1:
        return False
    def digito(cnpj_digits, pesos):
        s = sum(a * b for a, b in zip(cnpj_digits, pesos)) % 11
        return 0 if s < 2 else 11 - s
    pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
    pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
    nums = [int(x) for x in cnpj]
    return nums[12] == digito(nums[:12], pesos1) and nums[13] == digito(nums[:13], pesos2)

# ─── PROGRESSO ────────────────────────────────────────────────────────────────
def load_progress() -> dict:
    if os.path.exists(PROGRESS_FILE):
        try:
            with open(PROGRESS_FILE, "r") as f:
                return json.load(f)
        except Exception:
            pass
    return {"processed": [], "last_int": 33000167000101, "index_links": []}

def save_progress(processed: list, last_int: int, index_links: list):
    with open(PROGRESS_FILE, "w") as f:
        json.dump({"processed": processed, "last_int": last_int, "index_links": index_links}, f)

# ─── FORMATADORES ─────────────────────────────────────────────────────────────
def fmt_cnpj(cnpj: str) -> str:
    c = cnpj.zfill(14)
    return f"{c[:2]}.{c[2:5]}.{c[5:8]}/{c[8:12]}-{c[12:]}"

def fmt_brl(valor) -> str:
    try:
        return f"R$ {float(valor):,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")
    except Exception:
        return "R$ 0,00"

def fmt_date(d: str) -> str:
    try:
        return datetime.strptime(d, "%Y-%m-%d").strftime("%d/%m/%Y")
    except Exception:
        return d or "N/A"

# ─── NORMALIZAÇÃO DE DADOS ────────────────────────────────────────────────────
def normalizar(data: dict) -> dict:
    """Unifica campos das duas APIs em um dicionário padrão."""
    cnpj = data.get("cnpj", "")
    cnpj_limpo = "".join(d for d in cnpj if d.isdigit())
    return {
        "cnpj":              cnpj_limpo,
        "razao_social":      data.get("razao_social") or data.get("razão_social") or "N/A",
        "nome_fantasia":     data.get("nome_fantasia") or data.get("nome_comercial") or "",
        "situacao":          data.get("descricao_situacao_cadastral")
                             or data.get("descrição_situação_cadastral")
                             or data.get("situacao_cadastral_str") or "N/A",
        "data_abertura":     fmt_date(data.get("data_inicio_atividade", "")),
        "porte":             data.get("porte") or "N/A",
        "natureza_juridica": data.get("natureza_juridica") or "N/A",
        "capital_social":    fmt_brl(data.get("capital_social", 0)),
        "email":             data.get("email") or "",
        "telefone":          data.get("ddd_telefone_1") or "",
        "logradouro":        data.get("logradouro") or "N/A",
        "numero":            data.get("numero") or "S/N",
        "complemento":       data.get("complemento") or "",
        "bairro":            data.get("bairro") or "N/A",
        "municipio":         data.get("municipio") or data.get("município") or "N/A",
        "uf":                data.get("uf") or "N/A",
        "cep":               data.get("cep") or "N/A",
        "cnae_principal":    data.get("cnae_fiscal_descricao")
                             or data.get("cnae_fiscal_descrição") or "N/A",
        "cnaes_secundarios": data.get("cnaes_secundarios", []),
        "qsa":               data.get("qsa", []),
    }

# ─── CSS CENTRAL ──────────────────────────────────────────────────────────────
CSS = """
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #f0f4f8; color: #2d3748; line-height: 1.7;
  }
  .container { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
  .topbar {
    background: #1a56db; color: #fff; padding: 14px 20px;
    display: flex; align-items: center; gap: 16px;
  }
  .topbar a { color: #fff; text-decoration: none; font-weight: 600; }
  .topbar .brand { font-size: 20px; font-weight: 800; letter-spacing: -0.5px; }
  .card {
    background: #fff; border-radius: 12px; padding: 28px;
    margin-bottom: 20px; border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
  }
  h1 { font-size: 22px; font-weight: 800; color: #1a202c; margin-bottom: 6px; }
  h2 { font-size: 16px; font-weight: 700; color: #1a56db;
       border-bottom: 2px solid #ebf4ff; padding-bottom: 8px; margin-bottom: 16px; }
  .badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; margin-bottom: 18px;
  }
  .badge-ativa   { background: #d1fae5; color: #065f46; }
  .badge-baixada { background: #fee2e2; color: #991b1b; }
  .badge-outros  { background: #fef3c7; color: #92400e; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
  .field label { display: block; font-size: 11px; font-weight: 700;
                 text-transform: uppercase; color: #718096; margin-bottom: 3px; }
  .field p { font-size: 15px; color: #2d3748; }
  ul.data-list { list-style: none; }
  ul.data-list li {
    background: #f7fafc; border-left: 4px solid #1a56db;
    border-radius: 6px; padding: 10px 14px; margin-bottom: 8px; font-size: 14px;
  }
  ul.data-list li strong { display: block; color: #2d3748; }
  ul.data-list li span { color: #718096; font-size: 12px; }
  .search-hero {
    background: linear-gradient(135deg, #1a56db 0%, #1e40af 100%);
    color: #fff; padding: 60px 20px; text-align: center;
  }
  .search-hero h1 { font-size: 32px; color: #fff; margin-bottom: 10px; }
  .search-hero p { font-size: 16px; opacity: .85; margin-bottom: 28px; }
  .search-form { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
  .search-form input {
    padding: 14px 18px; font-size: 16px; border: none; border-radius: 8px;
    width: 340px; max-width: 100%;
  }
  .search-form button {
    padding: 14px 24px; background: #10b981; color: #fff; font-size: 16px;
    font-weight: 700; border: none; border-radius: 8px; cursor: pointer;
  }
  .search-form button:hover { background: #059669; }
  footer { text-align: center; padding: 40px 0 20px; color: #a0aec0; font-size: 13px; }
  a { color: #1a56db; text-decoration: none; }
  a:hover { text-decoration: underline; }
</style>
"""

# ─── HTML DA EMPRESA ──────────────────────────────────────────────────────────
def gerar_html_empresa(data: dict) -> str:
    d = normalizar(data)
    cnpj_f   = fmt_cnpj(d["cnpj"])
    nome_exib = d["nome_fantasia"] if d["nome_fantasia"] else d["razao_social"]

    sit = d["situacao"].upper()
    if "ATIVA" in sit:
        badge_class, badge_txt = "badge-ativa", "Ativa"
    elif "BAIXADA" in sit or "INAPTA" in sit or "CANCELADA" in sit:
        badge_class, badge_txt = "badge-baixada", sit.title()
    else:
        badge_class, badge_txt = "badge-outros", sit.title()

    socios_html = "".join([
        f"""<li>
              <strong>{s.get("nome_socio","N/A")}</strong>
              <span>{s.get("qualificacao_socio","Sócio")} · 
                    Desde {fmt_date(s.get("data_entrada_sociedade",""))}</span>
            </li>"""
        for s in d["qsa"]
    ]) or "<li><strong>Informação não disponível</strong></li>"

    cnaes_html = "".join([
        f'<li><strong>{c.get("codigo","")}</strong> – {c.get("descricao","")}</li>'
        for c in d["cnaes_secundarios"]
    ]) or "<li>Não possui atividades secundárias</li>"

    contato = ""
    if d["telefone"]:
        contato += f'<div class="field"><label>Telefone</label><p>{d["telefone"]}</p></div>'
    if d["email"]:
        contato += f'<div class="field"><label>E-mail</label><p>{d["email"]}</p></div>'

    return f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{nome_exib} – CNPJ {cnpj_f} | BuscaCNPJ.work</title>
  <meta name="description" content="Consulta CNPJ {cnpj_f}: {d["razao_social"]}. Situação {d["situacao"]}, localizada em {d["municipio"]}/{d["uf"]}. Dados públicos da Receita Federal.">
  <link rel="canonical" href="{DOMAIN}/cnpj/{d["cnpj"]}/">
  {CSS}
</head>
<body>
  <nav class="topbar">
    <span class="brand">🔍 BuscaCNPJ</span>
    <a href="{DOMAIN}">Início</a>
  </nav>

  <div class="container">

    <div class="card">
      <h1>{nome_exib}</h1>
      <span class="badge {badge_class}">{badge_txt}</span>
      <div class="grid-2">
        <div class="field"><label>CNPJ</label><p>{cnpj_f}</p></div>
        <div class="field"><label>Razão Social</label><p>{d["razao_social"]}</p></div>
        <div class="field"><label>Nome Fantasia</label><p>{d["nome_fantasia"] or "–"}</p></div>
        <div class="field"><label>Data de Abertura</label><p>{d["data_abertura"]}</p></div>
        <div class="field"><label>Porte</label><p>{d["porte"]}</p></div>
        <div class="field"><label>Natureza Jurídica</label><p>{d["natureza_juridica"]}</p></div>
        <div class="field"><label>Capital Social</label><p>{d["capital_social"]}</p></div>
        <div class="field"><label>Situação Cadastral</label><p>{d["situacao"]}</p></div>
      </div>
    </div>

    <div class="card">
      <h2>📍 Localização</h2>
      <div class="grid-2">
        <div class="field"><label>Logradouro</label>
          <p>{d["logradouro"]}, {d["numero"]} {d["complemento"]}</p></div>
        <div class="field"><label>Bairro</label><p>{d["bairro"]}</p></div>
        <div class="field"><label>Município / UF</label><p>{d["municipio"]} – {d["uf"]}</p></div>
        <div class="field"><label>CEP</label><p>{d["cep"]}</p></div>
      </div>
    </div>

    {"<div class='card'><h2>📞 Contato</h2><div class='grid-2'>" + contato + "</div></div>" if contato else ""}

    <div class="card">
      <h2>🏭 Atividades Econômicas</h2>
      <div class="field" style="margin-bottom:16px">
        <label>Atividade Principal (CNAE)</label>
        <p>{d["cnae_principal"]}</p>
      </div>
      <div class="field"><label>Atividades Secundárias</label></div>
      <ul class="data-list" style="margin-top:8px">{cnaes_html}</ul>
    </div>

    <div class="card">
      <h2>👥 Quadro de Sócios e Administradores</h2>
      <ul class="data-list">{socios_html}</ul>
    </div>

  </div>

  <footer>© 2026 <a href="{DOMAIN}">BuscaCNPJ.work</a> — Dados públicos da Receita Federal do Brasil.</footer>
</body>
</html>"""

# ─── HTML DO INDEX ────────────────────────────────────────────────────────────
def gerar_index(index_links: list):
    cards = "".join([
        f"""<li><a href="cnpj/{c}/">{n} <span style="color:#718096;font-size:13px">({fmt_cnpj(c)})</span></a></li>"""
        for c, n in index_links[:60]
    ])
    content = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BuscaCNPJ.work – Consulta de Empresas Brasileiras</title>
  <meta name="description" content="Consulte dados públicos de empresas brasileiras pelo CNPJ. Informações da Receita Federal gratuitamente.">
  <link rel="canonical" href="{DOMAIN}/">
  {CSS}
</head>
<body>
  <div class="search-hero">
    <h1>🔍 BuscaCNPJ.work</h1>
    <p>Consulte dados públicos de qualquer empresa brasileira pelo CNPJ</p>
    <div class="search-form">
      <input type="text" id="cnpjInput" maxlength="18"
             placeholder="Digite o CNPJ (apenas números ou formatado)" autocomplete="off">
      <button onclick="buscar()">Buscar</button>
    </div>
    <p style="margin-top:12px;font-size:13px;opacity:.7">Exemplo: 33.000.167/0001-01</p>
  </div>

  <div class="container">
    <div class="card">
      <h2>Empresas em Destaque</h2>
      <ul class="data-list">{cards}</ul>
    </div>
  </div>

  <footer>© 2026 <a href="{DOMAIN}" style="color:#1a56db">BuscaCNPJ.work</a> — Dados públicos da Receita Federal.</footer>

  <script>
    function buscar() {{
      var raw = document.getElementById('cnpjInput').value.replace(/\D/g,'');
      if (raw.length === 14) {{
        window.location.href = './cnpj/' + raw + '/';
      }} else {{
        alert('Digite um CNPJ válido com 14 dígitos.');
      }}
    }}
    document.getElementById('cnpjInput').addEventListener('keydown', function(e) {{
      if (e.key === 'Enter') buscar();
    }});
  </script>
</body>
</html>"""
    with open(f"{BASE_DIR}/index.html", "w", encoding="utf-8") as f:
        f.write(content)
    log.info("index.html gerado.")

# ─── SITEMAP ──────────────────────────────────────────────────────────────────
def gerar_sitemap(processed: list):
    today = datetime.now().strftime("%Y-%m-%d")
    urls = f"  <url><loc>{DOMAIN}/</loc><changefreq>daily</changefreq></url>\n"
    urls += "\n".join([
        f"  <url><loc>{DOMAIN}/cnpj/{c}/</loc><lastmod>{today}</lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>"
        for c in processed
    ])
    xml = f"""<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{urls}
</urlset>"""
    with open(f"{BASE_DIR}/sitemap.xml", "w", encoding="utf-8") as f:
        f.write(xml)
    log.info("sitemap.xml gerado com %d URLs.", len(processed) + 1)

# ─── REQUISIÇÃO COM FALLBACK ──────────────────────────────────────────────────
def fetch_cnpj(cnpj: str, brasil_api_ativa: bool) -> tuple:
    """
    Retorna (data_dict | None, brasil_api_ativa, backoff_necessario).
    backoff_necessario = True quando ambas as APIs retornaram 429.
    """
    if brasil_api_ativa:
        try:
            r = requests.get(f"{API_BRASIL}{cnpj}", timeout=10,
                             headers={"User-Agent": "BuscaCNPJ-Bot/1.0"})
            if r.status_code == 200:
                return r.json(), True, False
            elif r.status_code == 404:
                return None, True, False
            elif r.status_code == 429:
                log.warning("BrasilAPI 429. Tentando Minha Receita para %s…", cnpj)
            else:
                log.debug("BrasilAPI %s para %s", r.status_code, cnpj)
        except requests.RequestException as e:
            log.debug("BrasilAPI erro: %s", e)

    # Fallback: Minha Receita
    try:
        r2 = requests.get(f"{API_MINHA_RECEITA}{cnpj}", timeout=10,
                          headers={"User-Agent": "BuscaCNPJ-Bot/1.0"})
        if r2.status_code == 200:
            return r2.json(), brasil_api_ativa, False
        elif r2.status_code == 404:
            return None, brasil_api_ativa, False
        elif r2.status_code == 429:
            log.warning("Minha Receita também bloqueou. Backoff ativado.")
            return None, brasil_api_ativa, True
    except requests.RequestException as e:
        log.debug("Minha Receita erro: %s", e)

    return None, brasil_api_ativa, False

# ─── MAIN ─────────────────────────────────────────────────────────────────────
def main():
    os.makedirs(f"{BASE_DIR}/cnpj", exist_ok=True)

    prog       = load_progress()
    processed  = prog["processed"]
    cur_int    = prog["last_int"]
    index_links = prog.get("index_links", [])

    log.info("=" * 60)
    log.info("BuscaCNPJ.work — Gerador de Site Estático")
    log.info("Páginas já geradas : %d / %d", len(processed), MAX_PAGES)
    log.info("=" * 60)

    if len(processed) >= MAX_PAGES:
        log.info("Meta já atingida. Aumente MAX_PAGES para continuar.")
        gerar_index(index_links)
        gerar_sitemap(processed)
        return

    brasil_api_ativa = True
    erros_brasil     = 0
    backoff_time     = BACKOFF_BASE
    seed_index       = 0

    while len(processed) < MAX_PAGES:
        # Seleciona próximo CNPJ
        if seed_index < len(SEED_CNPJS):
            target = SEED_CNPJS[seed_index]
            seed_index += 1
        else:
            cur_int += 1
            target   = str(cur_int).zfill(14)

        # Filtra duplicatas e CNPJs inválidos sem chamar a API
        if target in processed:
            continue
        if not validar_cnpj(target):
            continue

        data, brasil_api_ativa, need_backoff = fetch_cnpj(target, brasil_api_ativa)

        if need_backoff:
            log.warning("Backoff de %ds…", backoff_time)
            time.sleep(backoff_time)
            backoff_time = min(backoff_time * 2, BACKOFF_MAX)
            continue

        if data is None:
            time.sleep(SLEEP_404)
            continue

        # Gera a página HTML
        try:
            d = normalizar(data)
            html = gerar_html_empresa(data)
            path = f"{BASE_DIR}/cnpj/{d['cnpj']}"
            os.makedirs(path, exist_ok=True)
            with open(f"{path}/index.html", "w", encoding="utf-8") as f:
                f.write(html)

            processed.append(d["cnpj"])
            if len(index_links) < 60:
                nome = d["nome_fantasia"] if d["nome_fantasia"] else d["razao_social"]
                index_links.append((d["cnpj"], nome))

            save_progress(processed, cur_int, index_links)
            log.info("[%d/%d] ✅ %s — %s", len(processed), MAX_PAGES, d["cnpj"],
                     (d["nome_fantasia"] or d["razao_social"])[:50])

            backoff_time = BACKOFF_BASE   # reseta backoff após sucesso
            erros_brasil = 0
            time.sleep(SLEEP_OK)

        except Exception as e:
            log.error("Erro ao gerar página para %s: %s", target, e)

    # Finalização
    gerar_index(index_links)
    gerar_sitemap(processed)

    # Estatísticas
    total_bytes = sum(
        os.path.getsize(os.path.join(r, f))
        for r, _, files in os.walk(BASE_DIR) for f in files
    )
    log.info("=" * 60)
    log.info("✅ CONCLUÍDO!")
    log.info("Páginas geradas : %d", len(processed))
    log.info("Tamanho do site : %.2f MB", total_bytes / 1_048_576)
    log.info("=" * 60)
    log.info("")
    log.info("📦 Como publicar:")
    log.info("  1. cd site-cnpj && git init")
    log.info("  2. git remote add origin https://github.com/SEU_USUARIO/buscacnpj.git")
    log.info("  3. git add . && git commit -m 'Deploy inicial'")
    log.info("  4. git push -u origin main")
    log.info("  5. No Cloudflare Pages: Build output = /  (sem build command)")
    log.info("  6. Aponte o domínio buscacnpj.work para o Cloudflare Pages.")


if __name__ == "__main__":
    main()
