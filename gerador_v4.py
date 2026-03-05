#!/usr/bin/env python3
"""
gerador_v4.py — BuscaCNPJ.work
Versão rápida: requisições paralelas com ThreadPoolExecutor.
~1000 páginas em ~10-15 minutos.
"""

import requests, os, time, json, logging
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed
from threading import Lock

# ─── CONFIG ───────────────────────────────────────────────────────────────────
BASE_DIR      = "site-cnpj"
DOMAIN        = "https://buscacnpj.work"
PROGRESS_FILE = "progresso.json"
MAX_PAGES     = 5000
MAX_WORKERS   = 8      # requisições simultâneas (seguro para as APIs)
SLEEP_WORKER  = 0.5    # delay entre cada worker individual
SAVE_EVERY    = 20     # salva progresso a cada N páginas

API_BRASIL        = "https://brasilapi.com.br/api/cnpj/v1/"
API_MINHA_RECEITA = "https://minhareceita.org/"

SEED_CNPJS = [
    # Grandes empresas
    "33000167000101","33592510000154","00000000000191","00360305000104",
    "06066228000121","64170450000105","19131243000197","02429144000193",
    "05486851000115","33683111000280","33372251000101","60746948000112",
    "90400888000142","76535764000143","07526557000100","02012862000160",
    "04206050000180","33530486000129","33200056000147","00394460000141",
    # Varejo
    "47508411000156","03235738000190","33041260065290","07195279000102",
    "61412110000116","33009911000125","75315333000109","45543915000155",
    "06057223000171","53113791000122",
    # Saúde
    "29978814000106","61486891000100","44649812000138","00394586000145",
    "63025530000104",
    # Agro
    "81223973000100","02916265000160","03853896000140","04922555000100",
    "28954059000136","61065298000191","92887505000120",
    # Construção
    "60840055000131","17327099000106","02351144000188","33256439000139",
    # Tecnologia
    "07882978000100","01149953000175","02386257000168","04862600000108",
    "07613619000111","14380200000121","18970291000100","21435900000114",
    # Educação
    "03179837000182","09529699000175","62173620000180","00796865000102",
    # Energia
    "00108786000165","04821041000108","02351137000177","04902979000144",
    # Logística
    "02543816000100","09054714000186","07752121000170",
    # Fintechs
    "30723886000162","18236120000158","13140088000199","17772370000180",
    "02332886000104","31872495000172","10573521000191","16501555000157",
    # Mídia
    "27865757000102","61855045000205","33317876000100",
    # PMEs por estado (UFs diversas)
    "11222862000173","08902091000180","10599357000150","12345678000195",
    "09502335000100","11308488000194","14616875000130","15436448000180",
    "16571664000180","17213052000130","18112719000130","19397706000130",
    "20359890000189","21021983000170","22151510000130","23337501000130",
    "24338822000152","25189562000109","26180908000100","27147482000188",
]

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%H:%M:%S",
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler("gerador.log", encoding="utf-8"),
    ],
)
log = logging.getLogger(__name__)
lock = Lock()

# ─── UTILS ────────────────────────────────────────────────────────────────────
def fmt_cnpj(c):
    c = c.zfill(14)
    return f"{c[:2]}.{c[2:5]}.{c[5:8]}/{c[8:12]}-{c[12:]}"

def fmt_brl(v):
    try:
        return f"R$\u00a0{float(v):,.2f}".replace(",","X").replace(".",",").replace("X",".")
    except: return "R$\u00a00,00"

def fmt_date(d):
    try: return datetime.strptime(d, "%Y-%m-%d").strftime("%d/%m/%Y")
    except: return d or "—"

def norm(data):
    cnpj = "".join(x for x in data.get("cnpj","") if x.isdigit())
    return {
        "cnpj": cnpj,
        "razao_social":      data.get("razao_social") or data.get("razão_social") or "N/A",
        "nome_fantasia":     data.get("nome_fantasia") or data.get("nome_comercial") or "",
        "situacao":          data.get("descricao_situacao_cadastral") or data.get("descrição_situação_cadastral") or "N/A",
        "data_abertura":     fmt_date(data.get("data_inicio_atividade","")),
        "porte":             data.get("porte") or "—",
        "natureza_juridica": data.get("natureza_juridica") or "—",
        "capital_social":    fmt_brl(data.get("capital_social",0)),
        "email":             data.get("email") or "",
        "telefone":          data.get("ddd_telefone_1") or "",
        "logradouro":        data.get("logradouro") or "—",
        "numero":            data.get("numero") or "S/N",
        "complemento":       data.get("complemento") or "",
        "bairro":            data.get("bairro") or "—",
        "municipio":         data.get("municipio") or data.get("município") or "—",
        "uf":                data.get("uf") or "—",
        "cep":               data.get("cep") or "—",
        "cnae_principal":    data.get("cnae_fiscal_descricao") or data.get("cnae_fiscal_descrição") or "—",
        "cnae_codigo":       str(data.get("cnae_fiscal","") or ""),
        "cnaes_secundarios": data.get("cnaes_secundarios",[]),
        "qsa":               data.get("qsa",[]),
    }

CSS = """
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --text:#333; --muted:#888; --border:#ebebeb; --bg:#fff; --dark:#1a1a1a; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
         line-height: 1.7; color: var(--text); background: var(--bg); font-size: 1rem; }
  header { padding: 14px 24px; border-bottom: 1px solid var(--border); position: sticky; top: 0;
           background: rgba(255,255,255,0.97); backdrop-filter: blur(8px); z-index: 999; }
  .header-inner { max-width: 1100px; margin: 0 auto; display: flex; align-items: center; gap: 24px; }
  .logo { font-weight: 800; font-size: 1rem; text-decoration: none; color: var(--dark); }
  header nav { display: flex; gap: 22px; }
  header nav a { text-decoration: none; color: #555; font-size: 0.95rem; }
  header nav a:hover { color: var(--dark); }
  .container { max-width: 800px; margin: 0 auto; padding: 40px 20px 60px; }
  .breadcrumb { font-size: 0.82rem; color: var(--muted); margin-bottom: 24px; }
  .breadcrumb a { color: var(--muted); text-decoration: none; }
  .breadcrumb span { margin: 0 6px; }
  h1 { font-size: 1.9rem; color: #111; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 8px; }
  .cnpj-fmt { font-size: 0.95rem; color: var(--muted); margin-bottom: 20px; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem;
           font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
           margin-bottom: 32px; border: 1px solid transparent; }
  .badge-ativa   { background:#f0fdf4; color:#166534; border-color:#bbf7d0; }
  .badge-baixada { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
  .badge-outros  { background:#fffbeb; color:#92400e; border-color:#fde68a; }
  .section { margin-bottom: 36px; }
  h2 { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
       color: var(--muted); border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 20px; }
  .fields { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 32px; }
  @media (max-width:600px) { .fields { grid-template-columns: 1fr; } }
  .field label { display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
                 letter-spacing: 0.5px; color: var(--muted); margin-bottom: 3px; }
  .field p { font-size: 0.97rem; color: #222; }
  ul.data-list { list-style: none; }
  ul.data-list li { padding: 12px 0; border-bottom: 1px solid var(--border); font-size: 0.95rem; }
  ul.data-list li:last-child { border-bottom: none; }
  ul.data-list li strong { display: block; color: #111; font-weight: 600; }
  ul.data-list li span { color: var(--muted); font-size: 0.85rem; }
  hr { border: none; border-top: 1px solid var(--border); margin: 32px 0; }
  footer { border-top: 1px solid var(--border); padding: 32px 20px; text-align: center;
           color: var(--muted); font-size: 0.85rem; }
  footer nav { display: flex; justify-content: center; gap: 20px; margin-bottom: 12px; flex-wrap: wrap; }
  footer a { color: var(--muted); text-decoration: none; }
  footer a:hover { color: var(--dark); }
  a { color: var(--dark); text-decoration: underline; text-underline-offset: 3px; text-decoration-color: #ccc; }
  @media (prefers-color-scheme: dark) {
    :root { --text:#e8e8e8; --muted:#777; --border:#2f2f2f; --bg:#191919; --dark:#f0f0f0; }
    header { background: rgba(25,25,25,0.97) !important; }
    h1 { color:#f0f0f0; } .field p { color:#c9c9c9; }
    ul.data-list li { color:#c9c9c9; } ul.data-list li strong { color:#f0f0f0; }
    .badge-ativa   { background:#052e16; color:#86efac; border-color:#166534; }
    .badge-baixada { background:#450a0a; color:#fca5a5; border-color:#991b1b; }
    .badge-outros  { background:#451a03; color:#fcd34d; border-color:#92400e; }
  }
</style>"""

def schema_org(d):
    nome = d["nome_fantasia"] or d["razao_social"]
    obj = {
        "@context":"https://schema.org","@type":"Organization",
        "name": nome, "legalName": d["razao_social"],
        "taxID": fmt_cnpj(d["cnpj"]), "foundingDate": d["data_abertura"],
        "address": {"@type":"PostalAddress","streetAddress":f"{d['logradouro']}, {d['numero']}",
                    "addressLocality":d["municipio"],"addressRegion":d["uf"],
                    "postalCode":d["cep"],"addressCountry":"BR"},
        "url": f"{DOMAIN}/cnpj/{d['cnpj']}/"
    }
    if d["email"]:    obj["email"]     = d["email"]
    if d["telefone"]: obj["telephone"] = d["telefone"]
    return f'<script type="application/ld+json">{json.dumps(obj,ensure_ascii=False)}</script>'

def gerar_html(data):
    d = norm(data)
    nome = d["nome_fantasia"] or d["razao_social"]
    cnpj_f = fmt_cnpj(d["cnpj"])
    sit = d["situacao"].upper()
    if "ATIVA" in sit:       bc, bt = "badge-ativa",   "Ativa"
    elif any(x in sit for x in ("BAIXADA","INAPTA","CANCELADA")):
                              bc, bt = "badge-baixada", d["situacao"].title()
    else:                     bc, bt = "badge-outros",  d["situacao"].title()

    socios = "".join([
        f'<li><strong>{s.get("nome_socio","—")}</strong>'
        f'<span>{s.get("qualificacao_socio","Sócio")}</span></li>'
        for s in d["qsa"]
    ]) or "<li><span>Não disponível</span></li>"

    cnaes = "".join([
        f'<li><strong>{c.get("codigo","")}</strong> — {c.get("descricao","")}</li>'
        for c in d["cnaes_secundarios"]
    ]) or "<li><span>Sem atividades secundárias</span></li>"

    contato = ""
    if d["telefone"] or d["email"]:
        flds = ""
        if d["telefone"]: flds += f'<div class="field"><label>Telefone</label><p>{d["telefone"]}</p></div>'
        if d["email"]:    flds += f'<div class="field"><label>E-mail</label><p>{d["email"]}</p></div>'
        contato = f'<div class="section"><h2>Contato</h2><div class="fields">{flds}</div></div><hr>'

    title = f"{nome} — CNPJ {cnpj_f} | BuscaCNPJ.work"
    desc  = f"Consulta CNPJ {cnpj_f}: {d['razao_social']}. Situação {d['situacao']}, em {d['municipio']}/{d['uf']}."

    return f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{title}</title>
  <meta name="description" content="{desc}">
  <link rel="canonical" href="{DOMAIN}/cnpj/{d['cnpj']}/">
  <meta property="og:title" content="{title}">
  <meta property="og:description" content="{desc}">
  <meta property="og:url" content="{DOMAIN}/cnpj/{d['cnpj']}/">
  {schema_org(d)}{CSS}
</head>
<body>
<header><div class="header-inner">
  <a class="logo" href="{DOMAIN}">buscacnpj.work</a>
  <nav><a href="{DOMAIN}">consultar cnpj</a></nav>
</div></header>
<div class="container">
  <div class="breadcrumb"><a href="{DOMAIN}">início</a><span>/</span>
    <a href="{DOMAIN}/cnpj/">cnpj</a><span>/</span>{cnpj_f}</div>
  <h1>{nome}</h1><p class="cnpj-fmt">CNPJ {cnpj_f}</p>
  <span class="badge {bc}">{bt}</span>
  <div class="section"><h2>Informações de Registro</h2><div class="fields">
    <div class="field"><label>Razão Social</label><p>{d["razao_social"]}</p></div>
    <div class="field"><label>Nome Fantasia</label><p>{d["nome_fantasia"] or "—"}</p></div>
    <div class="field"><label>Data de Abertura</label><p>{d["data_abertura"]}</p></div>
    <div class="field"><label>Situação</label><p>{d["situacao"]}</p></div>
    <div class="field"><label>Porte</label><p>{d["porte"]}</p></div>
    <div class="field"><label>Natureza Jurídica</label><p>{d["natureza_juridica"]}</p></div>
    <div class="field"><label>Capital Social</label><p>{d["capital_social"]}</p></div>
    <div class="field"><label>CNPJ</label><p>{cnpj_f}</p></div>
  </div></div><hr>
  <div class="section"><h2>Localização</h2><div class="fields">
    <div class="field"><label>Logradouro</label>
      <p>{d["logradouro"]}, {d["numero"]}{(" — "+d["complemento"]) if d["complemento"] else ""}</p></div>
    <div class="field"><label>Bairro</label><p>{d["bairro"]}</p></div>
    <div class="field"><label>Município</label><p>{d["municipio"]} — {d["uf"]}</p></div>
    <div class="field"><label>CEP</label><p>{d["cep"]}</p></div>
  </div></div><hr>
  {contato}
  <div class="section"><h2>Atividade Principal</h2><ul class="data-list">
    <li><strong>{d["cnae_principal"]}</strong>
    {("<span>CNAE "+d["cnae_codigo"]+"</span>") if d["cnae_codigo"] else ""}</li>
  </ul></div>
  <div class="section"><h2>Atividades Secundárias</h2>
    <ul class="data-list">{cnaes}</ul></div><hr>
  <div class="section"><h2>Quadro de Sócios e Administradores</h2>
    <ul class="data-list">{socios}</ul></div>
</div>
<footer>
  <nav><a href="{DOMAIN}/">Início</a><a href="{DOMAIN}/sobre/">Sobre</a>
  <a href="{DOMAIN}/privacidade/">Privacidade</a><a href="{DOMAIN}/contato/">Contato</a></nav>
  <p>© 2026 <a href="{DOMAIN}">BuscaCNPJ.work</a> — Dados públicos da Receita Federal do Brasil.</p>
</footer></body></html>"""

def gerar_index(index_links, total):
    cards = "".join([
        f'<a class="card-link" href="cnpj/{c}/"><strong>{n[:38]+("…" if len(n)>38 else "")}</strong>'
        f'<span>{fmt_cnpj(c)}</span></a>'
        for c, n in index_links
    ])
    html = f"""<!DOCTYPE html><html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>BuscaCNPJ.work — Consulta Gratuita de CNPJ</title>
<meta name="description" content="Consulte dados públicos de qualquer empresa brasileira pelo CNPJ. Gratuito.">
<link rel="canonical" href="{DOMAIN}/">{CSS}</head>
<body>
<header><div class="header-inner"><a class="logo" href="{DOMAIN}">buscacnpj.work</a>
<nav><a href="{DOMAIN}/sobre/">sobre</a></nav></div></header>
<div style="padding:64px 24px;text-align:center;border-bottom:1px solid #ebebeb;margin-bottom:48px">
  <h1 style="font-size:2.2rem;font-weight:800;letter-spacing:-.5px;margin-bottom:10px">Consulta de CNPJ</h1>
  <p style="color:#888;font-size:1.05rem;margin-bottom:28px">Dados públicos de empresas brasileiras da Receita Federal.</p>
  <div class="search-row" style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap">
    <input type="text" id="cnpjInput" maxlength="18" placeholder="00.000.000/0000-00"
           style="padding:12px 16px;font-size:1rem;border:1px solid #ebebeb;border-radius:6px;
                  width:320px;max-width:100%;outline:none;font-family:inherit;">
    <button onclick="buscar()" style="padding:12px 22px;background:#1a1a1a;color:#fff;
            font-size:.95rem;font-weight:600;border:none;border-radius:6px;cursor:pointer;
            font-family:inherit;">Consultar</button>
  </div>
</div>
<div class="container" style="padding-top:0">
  <h2 style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;
             color:#888;border-bottom:1px solid #ebebeb;padding-bottom:8px;margin-bottom:16px">
    Empresas cadastradas ({total:,})
  </h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:16px">
    {cards}
  </div>
</div>
<footer><nav><a href="{DOMAIN}/sobre/">Sobre</a><a href="{DOMAIN}/privacidade/">Privacidade</a>
<a href="{DOMAIN}/contato/">Contato</a></nav>
<p>© 2026 <a href="{DOMAIN}">BuscaCNPJ.work</a></p></footer>
<script>
function buscar(){{var r=document.getElementById('cnpjInput').value.replace(/\D/g,'');
if(r.length===14)window.location.href='./cnpj/'+r+'/';
else alert('CNPJ inválido.');}}
document.getElementById('cnpjInput').addEventListener('keydown',function(e){{if(e.key==='Enter')buscar();}});
</script></body></html>"""
    with open(f"{BASE_DIR}/index.html","w",encoding="utf-8") as f: f.write(html)

def gerar_sitemap(processed):
    today = datetime.now().strftime("%Y-%m-%d")
    fixas = [(f"{DOMAIN}/","daily","1.0"),(f"{DOMAIN}/sobre/","monthly","0.5"),
             (f"{DOMAIN}/privacidade/","yearly","0.3"),(f"{DOMAIN}/contato/","monthly","0.4")]
    urls = "".join(f"  <url><loc>{l}</loc><changefreq>{f}</changefreq><priority>{p}</priority></url>\n"
                   for l,f,p in fixas)
    urls += "".join(f"  <url><loc>{DOMAIN}/cnpj/{c}/</loc><lastmod>{today}</lastmod>"
                    f"<changefreq>monthly</changefreq><priority>0.8</priority></url>\n"
                    for c in processed)
    with open(f"{BASE_DIR}/sitemap.xml","w",encoding="utf-8") as f:
        f.write(f'<?xml version="1.0" encoding="UTF-8"?>\n'
                f'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n{urls}</urlset>')

def fetch_cnpj(cnpj):
    """Tenta BrasilAPI, depois Minha Receita como fallback."""
    for url in [f"{API_BRASIL}{cnpj}", f"{API_MINHA_RECEITA}{cnpj}"]:
        try:
            r = requests.get(url, timeout=12, headers={"User-Agent":"BuscaCNPJ-Bot/1.0"})
            if r.status_code == 200: return r.json()
            if r.status_code == 404: return None
            if r.status_code == 429:
                time.sleep(30)
                continue
        except Exception:
            continue
    return None

def processar_cnpj(cnpj):
    time.sleep(SLEEP_WORKER)
    data = fetch_cnpj(cnpj)
    if data is None:
        return None, cnpj
    try:
        d = norm(data)
        html = gerar_html(data)
        path = f"{BASE_DIR}/cnpj/{d['cnpj']}"
        os.makedirs(path, exist_ok=True)
        with open(f"{path}/index.html","w",encoding="utf-8") as f:
            f.write(html)
        nome = d["nome_fantasia"] or d["razao_social"]
        return (d["cnpj"], nome, d["municipio"], d["uf"]), None
    except Exception as e:
        log.error("Erro %s: %s", cnpj, e)
        return None, cnpj

def load_progress():
    if os.path.exists(PROGRESS_FILE):
        try:
            with open(PROGRESS_FILE,"r") as f: return json.load(f)
        except: pass
    return {"processed":[],"index_links":[]}

def save_progress(processed, index_links):
    with open(PROGRESS_FILE,"w") as f:
        json.dump({"processed":processed,"index_links":index_links},f)

def main():
    os.makedirs(f"{BASE_DIR}/cnpj", exist_ok=True)
    prog        = load_progress()
    processed   = prog["processed"]
    index_links = prog.get("index_links",[])
    done_set    = set(processed)

    pendentes = [c for c in SEED_CNPJS if c not in done_set]

    log.info("="*55)
    log.info("BuscaCNPJ.work — Gerador v4 (paralelo)")
    log.info("Já geradas : %d", len(processed))
    log.info("Pendentes  : %d seeds", len(pendentes))
    log.info("Workers    : %d simultâneos", MAX_WORKERS)
    log.info("="*55)

    if not pendentes:
        log.info("Todos os seeds já processados.")
        gerar_index(index_links, len(processed))
        gerar_sitemap(processed)
        return

    count = 0
    t0 = time.time()

    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = {executor.submit(processar_cnpj, c): c for c in pendentes}
        for future in as_completed(futures):
            result, erro = future.result()
            if result:
                cnpj, nome, mun, uf = result
                with lock:
                    processed.append(cnpj)
                    done_set.add(cnpj)
                    if len(index_links) < 500:
                        index_links.append((cnpj, nome))
                    count += 1
                    elapsed = time.time() - t0
                    pps = count / elapsed if elapsed > 0 else 0
                    log.info("[%d] ✅  %s — %s — %s/%s  (%.1f pág/min)",
                             len(processed), cnpj, nome[:35], mun, uf, pps*60)
                    if count % SAVE_EVERY == 0:
                        save_progress(processed, index_links)
                        gerar_index(index_links, len(processed))
                        gerar_sitemap(processed)
                        log.info("    💾 Progresso salvo — %d páginas", len(processed))
            else:
                if erro:
                    log.debug("  ⚠️  Sem dados: %s", erro)

    save_progress(processed, index_links)
    gerar_index(index_links, len(processed))
    gerar_sitemap(processed)

    elapsed = time.time() - t0
    log.info("="*55)
    log.info("✅  CONCLUÍDO em %.1f min", elapsed/60)
    log.info("Total gerado : %d páginas", len(processed))
    log.info("Próximo passo: git add . && git commit -m 'Update' && git push")
    log.info("="*55)

if __name__ == "__main__":
    main()
