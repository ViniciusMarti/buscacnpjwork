#!/usr/bin/env python3
"""
gerador_v4_b.py — BuscaCNPJ.work
Gera as páginas HTML para os CNPJs que estão no progresso.json mas ainda não têm pasta em site-cnpj/cnpj/.
Versão paralela — usa os mesmos templates do v4.
"""

import requests, os, json, time, logging
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed
from threading import Lock

BASE_DIR      = "."
DOMAIN        = "https://buscacnpj.work"
PROGRESS_FILE = "progresso.json"
MAX_WORKERS   = 8
SLEEP         = 0.4
SAVE_EVERY    = 50
API_BRASIL    = "https://brasilapi.com.br/api/cnpj/v1/"
API_MINHA_REC = "https://minhareceita.org/"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%H:%M:%S",
    handlers=[logging.StreamHandler(), logging.FileHandler("gerador.log", encoding="utf-8")]
)
log  = logging.getLogger(__name__)
lock = Lock()


def fmt_cnpj(c):
    c = c.zfill(14)
    return f"{c[:2]}.{c[2:5]}.{c[5:8]}/{c[8:12]}-{c[12:]}"

def fmt_brl(v):
    try:
        return f"R$\xa0{float(v):,.2f}".replace(",","X").replace(".",",").replace("X",".")
    except:
        return "R$\xa00,00"

def fmt_date(d):
    try:
        return datetime.strptime(d, "%Y-%m-%d").strftime("%d/%m/%Y")
    except:
        return d or "—"

def norm(data):
    cnpj = "".join(x for x in data.get("cnpj","") if x.isdigit())
    return {
        "cnpj":             cnpj,
        "razao_social":     data.get("razao_social") or data.get("razão_social") or "N/A",
        "nome_fantasia":    data.get("nome_fantasia") or data.get("nome_comercial") or "",
        "situacao":         data.get("descricao_situacao_cadastral") or data.get("descrição_situação_cadastral") or "N/A",
        "data_abertura":    fmt_date(data.get("data_inicio_atividade","")),
        "porte":            data.get("porte") or "—",
        "natureza_juridica":data.get("natureza_juridica") or "—",
        "capital_social":   fmt_brl(data.get("capital_social",0)),
        "email":            data.get("email") or "",
        "telefone":         data.get("ddd_telefone_1") or "",
        "logradouro":       data.get("logradouro") or "—",
        "numero":           data.get("numero") or "S/N",
        "complemento":      data.get("complemento") or "",
        "bairro":           data.get("bairro") or "—",
        "municipio":        data.get("municipio") or data.get("município") or "—",
        "uf":               data.get("uf") or "—",
        "cep":              data.get("cep") or "—",
        "cnae_principal":   data.get("cnae_fiscal_descricao") or data.get("cnae_fiscal_descrição") or "—",
        "cnae_codigo":      str(data.get("cnae_fiscal","") or ""),
        "cnaes_secundarios":data.get("cnaes_secundarios",[]),
        "qsa":              data.get("qsa",[]),
    }


CSS = """<style>

  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--text:#333;--muted:#888;--border:#ebebeb;--bg:#fff;--dark:#1a1a1a}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
       line-height:1.7;color:var(--text);background:var(--bg);font-size:1rem}
  header{padding:14px 24px;border-bottom:1px solid var(--border);position:sticky;top:0;
         background:rgba(255,255,255,0.97);backdrop-filter:blur(8px);z-index:999}
  .hi{max-width:1100px;margin:0 auto;display:flex;align-items:center;gap:24px}
  .logo{font-weight:800;font-size:1rem;text-decoration:none;color:var(--dark)}
  nav{display:flex;gap:22px}nav a{text-decoration:none;color:#555;font-size:.95rem}
  .c{max-width:800px;margin:0 auto;padding:40px 20px 60px}
  .bc{font-size:.82rem;color:var(--muted);margin-bottom:24px}
  .bc a{color:var(--muted);text-decoration:none}.bc span{margin:0 6px}
  h1{font-size:1.9rem;color:#111;font-weight:800;letter-spacing:-.5px;margin-bottom:8px}
  .cf{font-size:.95rem;color:var(--muted);margin-bottom:20px}
  .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;
         font-weight:700;letter-spacing:.5px;text-transform:uppercase;
         margin-bottom:32px;border:1px solid transparent}
  .ba{background:#f0fdf4;color:#166534;border-color:#bbf7d0}
  .bb{background:#fef2f2;color:#991b1b;border-color:#fecaca}
  .bo{background:#fffbeb;color:#92400e;border-color:#fde68a}
  .s{margin-bottom:36px}
  h2{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;
     color:var(--muted);border-bottom:1px solid var(--border);padding-bottom:8px;margin-bottom:20px}
  .g{display:grid;grid-template-columns:1fr 1fr;gap:18px 32px}
  @media(max-width:600px){.g{grid-template-columns:1fr}}
  .f label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;
            letter-spacing:.5px;color:var(--muted);margin-bottom:3px}
  .f p{font-size:.97rem;color:#222}
  ul.dl{list-style:none}ul.dl li{padding:12px 0;border-bottom:1px solid var(--border);font-size:.95rem}
  ul.dl li:last-child{border-bottom:none}ul.dl li strong{display:block;color:#111;font-weight:600}
  ul.dl li span{color:var(--muted);font-size:.85rem}
  hr{border:none;border-top:1px solid var(--border);margin:32px 0}
  footer{border-top:1px solid var(--border);padding:32px 20px;text-align:center;
         color:var(--muted);font-size:.85rem}
  footer nav{display:flex;justify-content:center;gap:20px;margin-bottom:12px;flex-wrap:wrap}
  footer a{color:var(--muted);text-decoration:none}footer a:hover{color:var(--dark)}
  a{color:var(--dark);text-decoration:underline;text-underline-offset:3px;text-decoration-color:#ccc}
  @media(prefers-color-scheme:dark){
    :root{--text:#e8e8e8;--muted:#777;--border:#2f2f2f;--bg:#191919;--dark:#f0f0f0}
    header{background:rgba(25,25,25,0.97)!important}h1{color:#f0f0f0}.f p{color:#c9c9c9}
    ul.dl li{color:#c9c9c9}ul.dl li strong{color:#f0f0f0}
    .ba{background:#052e16;color:#86efac;border-color:#166534}
    .bb{background:#450a0a;color:#fca5a5;border-color:#991b1b}
    .bo{background:#451a03;color:#fcd34d;border-color:#92400e}
  }
</style>"""


def gerar_html(data):
    d = norm(data)
    nome   = d["nome_fantasia"] or d["razao_social"]
    cnpj_f = fmt_cnpj(d["cnpj"])
    sit    = d["situacao"].upper()
    if "ATIVA" in sit:
        bc, bt = "ba", "Ativa"
    elif any(x in sit for x in ("BAIXADA","INAPTA","CANCELADA")):
        bc, bt = "bb", d["situacao"].title()
    else:
        bc, bt = "bo", d["situacao"].title()

    socios = "".join([
        f'<li><strong>{s.get("nome_socio","—")}</strong>' +
        f'<span>{s.get("qualificacao_socio","Sócio")}</span></li>'
        for s in d["qsa"]
    ]) or "<li><span>Não disponível</span></li>"

    cnaes = "".join([
        f'<li><strong>{c.get("descricao","—")}</strong>\n<span>CNAE {c.get("codigo","")}</span></li>'
        for c in d["cnaes_secundarios"]
    ]) or "<li><span>—</span></li>"

    cont = ""
    if d["telefone"] or d["email"]:
        flds = ""
        if d["telefone"]: flds += f'<div class="f"><label>Telefone</label><p>{d["telefone"]}</p></div>'
        if d["email"]:    flds += f'<div class="f"><label>E-mail</label><p>{d["email"]}</p></div>'
        cont = f'<div class="s"><h2>Contato</h2><div class="g">{flds}</div></div><hr>'

    title  = f"{nome} — CNPJ {cnpj_f} | BuscaCNPJ.work"
    desc   = f"Consulta CNPJ {cnpj_f}: {d['razao_social']}. Situação {d['situacao']}, {d['municipio']}/{d['uf']}."
    schema = json.dumps({
        "@context":"https://schema.org","@type":"Organization",
        "name":nome,"legalName":d["razao_social"],"taxID":cnpj_f,
        "address":{"@type":"PostalAddress",
                   "streetAddress":f"{d['logradouro']}, {d['numero']}",
                   "addressLocality":d["municipio"],"addressRegion":d["uf"],
                   "postalCode":d["cep"],"addressCountry":"BR"},
        "url":f"{DOMAIN}/cnpj/{d['cnpj']}/"
    }, ensure_ascii=False)

    return f"""<!DOCTYPE html><html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{title}</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest"><meta name="description" content="{desc}">
<link rel="canonical" href="{DOMAIN}/cnpj/{d['cnpj']}/">
<script type="application/ld+json">{schema}</script>{CSS}</head>
<body>
<header><div class="hi"><a class="logo" href="{DOMAIN}">buscacnpj.work</a>
<nav><a href="{DOMAIN}">consultar cnpj</a></nav></div></header>
<div class="c">
<div class="bc"><a href="{DOMAIN}">início</a><span>/</span>
<a href="{DOMAIN}/cnpj/">cnpj</a><span>/</span>{cnpj_f}</div>
<h1>{nome}</h1><p class="cf">CNPJ {cnpj_f}</p><span class="badge {bc}">{bt}</span>
<div class="s"><h2>Informações de Registro</h2><div class="g">
<div class="f"><label>Razão Social</label><p>{d["razao_social"]}</p></div>
<div class="f"><label>Nome Fantasia</label><p>{d["nome_fantasia"] or "—"}</p></div>
<div class="f"><label>Data de Abertura</label><p>{d["data_abertura"]}</p></div>
<div class="f"><label>Situação</label><p>{d["situacao"]}</p></div>
<div class="f"><label>Porte</label><p>{d["porte"]}</p></div>
<div class="f"><label>Natureza Jurídica</label><p>{d["natureza_juridica"]}</p></div>
<div class="f"><label>Capital Social</label><p>{d["capital_social"]}</p></div>
<div class="f"><label>CNPJ</label><p>{cnpj_f}</p></div>
</div></div><hr>
<div class="s"><h2>Localização</h2><div class="g">
<div class="f"><label>Logradouro</label>
<p>{d["logradouro"]}, {d["numero"]}{(" — "+d["complemento"]) if d["complemento"] else ""}</p></div>
<div class="f"><label>Bairro</label><p>{d["bairro"]}</p></div>
<div class="f"><label>Município</label><p>{d["municipio"]} — {d["uf"]}</p></div>
<div class="f"><label>CEP</label><p>{d["cep"]}</p></div>
</div></div><hr>
{cont}
<div class="s"><h2>Atividade Principal</h2><ul class="dl">
<li><strong>{d["cnae_principal"]}</strong>
{('<span>CNAE '+d["cnae_codigo"]+'</span>') if d["cnae_codigo"] else ""}</li></ul></div>
<div class="s"><h2>Atividades Secundárias</h2><ul class="dl">{cnaes}</ul></div><hr>
<div class="s"><h2>Quadro de Sócios</h2><ul class="dl">{socios}</ul></div>
</div>
<footer><nav><a href="{DOMAIN}/">Início</a><a href="{DOMAIN}/sobre/">Sobre</a>
<a href="{DOMAIN}/privacidade/">Privacidade</a><a href="{DOMAIN}/contato/">Contato</a></nav>
<p>© 2026 <a href="{DOMAIN}">BuscaCNPJ.work</a> — Dados públicos da Receita Federal.</p>
</footer></body></html>"""


def gerar_index(index_links, total):
    cards = "".join([
        f'<a href="cnpj/{c}/" style="display:block;padding:16px;border:1px solid #ebebeb;' +
        f'border-radius:8px;text-decoration:none;color:#1a1a1a;margin-bottom:0">' +
        f'<strong style="display:block;font-size:.92rem;margin-bottom:4px">' +
        f'{n[:38]+("…" if len(n)>38 else "")}</strong>' +
        f'<span style="font-size:.78rem;color:#888">{fmt_cnpj(c)}</span></a>'
        for c, n in index_links
    ])
    html = f"""<!DOCTYPE html><html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>BuscaCNPJ.work — Consulta Gratuita de CNPJ</title>
<meta name="description" content="Consulte dados públicos de qualquer empresa pelo CNPJ. Gratuito.">
<link rel="canonical" href="{DOMAIN}/">{CSS}</head>
<body>
<header><div class="hi"><a class="logo" href="{DOMAIN}">buscacnpj.work</a>
<nav><a href="{DOMAIN}/sobre/">sobre</a></nav></div></header>
<div style="padding:64px 24px;text-align:center;border-bottom:1px solid #ebebeb;margin-bottom:48px">
<h1 style="font-size:2.2rem;font-weight:800;letter-spacing:-.5px;margin-bottom:10px">Consulta de CNPJ</h1>
<p style="color:#888;margin-bottom:28px">Dados públicos de empresas brasileiras da Receita Federal.</p>
<div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap">
<input id="q" type="text" maxlength="18" placeholder="00.000.000/0000-00"
       style="padding:12px 16px;font-size:1rem;border:1px solid #ebebeb;border-radius:6px;
              width:320px;max-width:100%;outline:none;font-family:inherit">
<button onclick="buscar()" style="padding:12px 22px;background:#1a1a1a;color:#fff;
        font-size:.95rem;font-weight:600;border:none;border-radius:6px;cursor:pointer">Consultar</button>
</div></div>
<div class="c" style="padding-top:0">
<h2 style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;
           color:#888;border-bottom:1px solid #ebebeb;padding-bottom:8px;margin-bottom:16px">
Empresas cadastradas ({total:,})</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
{cards}</div></div>
<footer><nav><a href="{DOMAIN}/sobre/">Sobre</a><a href="{DOMAIN}/privacidade/">Privacidade</a>
<a href="{DOMAIN}/contato/">Contato</a></nav>
<p>© 2026 <a href="{DOMAIN}">BuscaCNPJ.work</a></p></footer>
<script>
function buscar(){{var r=document.getElementById('q').value.replace(/\D/g,'');
if(r.length===14)window.location.href='./cnpj/'+r+'/';
else alert('CNPJ inválido.');}}
document.getElementById('q').addEventListener('keydown',function(e){{if(e.key==='Enter')buscar();}});
</script></body></html>"""
    with open(f"{BASE_DIR}/index.html","w",encoding="utf-8") as f:
        f.write(html)


def gerar_sitemap(processed):
    today = datetime.now().strftime("%Y-%m-%d")
    fixas = [
        (f"{DOMAIN}/","daily","1.0"),
        (f"{DOMAIN}/sobre/","monthly","0.5"),
        (f"{DOMAIN}/privacidade/","yearly","0.3"),
        (f"{DOMAIN}/contato/","monthly","0.4"),
    ]
    urls  = "".join(f"  <url><loc>{l}</loc><changefreq>{f}</changefreq><priority>{p}</priority></url>\n"
                    for l,f,p in fixas)
    urls += "".join(f"  <url><loc>{DOMAIN}/cnpj/{c}/</loc><lastmod>{today}</lastmod>"
                    f"<changefreq>monthly</changefreq><priority>0.8</priority></url>\n"
                    for c in processed)
    with open(f"{BASE_DIR}/sitemap.xml","w",encoding="utf-8") as f:
        f.write(f'''<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{urls}</urlset>''')


def fetch(cnpj):
    for url in [f"{API_BRASIL}{cnpj}", f"{API_MINHA_REC}{cnpj}"]:
        try:
            r = requests.get(url, timeout=12, headers={"User-Agent":"BuscaCNPJ-Bot/1.0"})
            if r.status_code == 200: return r.json()
            if r.status_code == 404: return None
            if r.status_code == 429: time.sleep(20)
        except Exception:
            pass
    return None


def processar(cnpj):
    time.sleep(SLEEP)
    data = fetch(cnpj)
    if not data: return None
    try:
        d    = norm(data)
        html = gerar_html(data)
        path = f"{BASE_DIR}/cnpj/{d['cnpj']}"
        os.makedirs(path, exist_ok=True)
        with open(f"{path}/index.html","w",encoding="utf-8") as f:
            f.write(html)
        return d["cnpj"], (d["nome_fantasia"] or d["razao_social"]), d["municipio"], d["uf"]
    except Exception as e:
        log.error("Erro %s: %s", cnpj, e)
        return None


def main():
    os.makedirs(f"{BASE_DIR}/cnpj", exist_ok=True)
    with open(PROGRESS_FILE,"r") as f:
        prog = json.load(f)
    processed   = prog["processed"]
    index_links = prog.get("index_links",[])

    pendentes = [c for c in processed
                 if not os.path.exists(f"{BASE_DIR}/cnpj/{c}/index.html")]

    log.info("="*55)
    log.info("BuscaCNPJ.work — Gerador v4-b (páginas pendentes)")
    log.info("Total no banco : %d", len(processed))
    log.info("Sem página     : %d", len(pendentes))
    log.info("="*55)

    if not pendentes:
        log.info("Tudo já gerado! Só rebuild do index.")
        gerar_index(index_links, len(processed))
        gerar_sitemap(processed)
        return

    count = 0
    t0    = time.time()

    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = {executor.submit(processar, c): c for c in pendentes}
        for fut in as_completed(futures):
            res = fut.result()
            if res:
                cnpj, nome, mun, uf = res
                count += 1
                ppm = count / max((time.time()-t0)/60, 0.01)
                log.info("[%d/%d] ✅  %s — %s — %s/%s  (%.0f pág/min)",
                         count, len(pendentes), cnpj, nome[:35], mun, uf, ppm)
                if count % SAVE_EVERY == 0:
                    with lock:
                        if (cnpj, nome) not in index_links and len(index_links) < 500:
                            index_links.append((cnpj, nome))
                        gerar_index(index_links, len(processed))
                        gerar_sitemap(processed)
                    log.info("    💾 index + sitemap atualizados")

    gerar_index(index_links, len(processed))
    gerar_sitemap(processed)
    elapsed = time.time()-t0
    log.info("="*55)
    log.info("✅  CONCLUÍDO em %.1f min — %d páginas geradas", elapsed/60, count)
    log.info("Próximo: git add . && git commit -m 'Update' && git push")
    log.info("="*55)


if __name__ == "__main__":
    main()
