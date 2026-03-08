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


# CSS agora é externo (/cnpj.css) — não há mais inline aqui
CNPJ_HEAD = """\
  <link rel="stylesheet" href="/cnpj.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">"""
# SVG icons para os itens das listas
ICON_CNAE    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>'
ICON_SEC     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>'
ICON_SOCIO   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
ICON_PIN     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>'
ICON_INFO    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
ICON_COPY    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>'
ICON_PARTNER = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
ICON_CHECK   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'

def gerar_html(data):
    d = norm(data)
    razao  = d["razao_social"]
    nome   = d["nome_fantasia"] or razao
    cnpj_f = fmt_cnpj(d["cnpj"])
    cnpj_r = d["cnpj"]  # só dígitos
    sit    = d["situacao"].upper()

    if "ATIVA" in sit:
        badge_cls, badge_txt = "badge-ativa", "Ativa"
    elif any(x in sit for x in ("BAIXADA", "CANCELADA")):
        badge_cls, badge_txt = "badge-baixada", d["situacao"].title()
    elif "INAPTA" in sit:
        badge_cls, badge_txt = "badge-inapta", d["situacao"].title()
    else:
        badge_cls, badge_txt = "badge-suspensa", d["situacao"].title()

    # ------ Sócios ------
    socios_html = ""
    for s in d["qsa"]:
        nm = s.get("nome_socio", "\u2014")
        ql = s.get("qualificacao_socio", "S\u00f3cio")
        socios_html += f"""
    <li>
      <div class="li-icon">{ICON_SOCIO}</div>
      <div class="li-body"><strong>{nm}</strong><span>{ql}</span></div>
    </li>"""
    if not socios_html:
        socios_html = '<li><div class="li-body"><span>N\u00e3o dispon\u00edvel</span></div></li>'

    # ------ CNAEs Secundários ------
    cnaes_html = ""
    for c in d["cnaes_secundarios"]:
        desc = c.get("descricao", "\u2014")
        cod  = c.get("codigo", "")
        cnaes_html += f"""
    <li>
      <div class="li-icon">{ICON_SEC}</div>
      <div class="li-body"><strong>{desc}</strong><span>CNAE {cod} <span class="tag">Secund\u00e1ria</span></span></div>
    </li>"""
    if not cnaes_html:
        cnaes_html = '<li><div class="li-body"><span>\u2014</span></div></li>'

    # ------ Contato (opcional) ------
    contact_fields = ""
    if d["telefone"]:
        contact_fields += f'<div class="field"><label>Telefone</label><p>{d["telefone"]}</p></div>'
    if d["email"]:
        contact_fields += f'<div class="field"><label>E-mail</label><p>{d["email"]}</p></div>'
    contact_section = f"""
  <p class="sec-label">Contato</p>
  <div class="fields">{contact_fields}</div>""" if contact_fields else ""

    # ------ Endereço ------
    end_comp = f" \u2014 {d['complemento']}" if d["complemento"] else ""
    end_str  = f"{d['logradouro']}, {d['numero']}{end_comp}"

    title = f"{razao} \u2014 CNPJ {cnpj_f} | BuscaCNPJ.work"
    desc  = (f"Dados do CNPJ {cnpj_f} \u2014 {razao}. Situa\u00e7\u00e3o: {d['situacao']}. "
             f"Endere\u00e7o: {end_str}, {d['municipio']} - {d['uf']}. Consulta gratuita da Receita Federal.")
    schema = json.dumps({
        "@context": "https://schema.org", "@type": "Organization",
        "name": nome, "legalName": razao, "taxID": cnpj_f,
        "foundingDate": d["data_abertura"],
        "address": {"@type": "PostalAddress",
                    "streetAddress": end_str,
                    "addressLocality": d["municipio"],
                    "addressRegion": d["uf"],
                    "postalCode": d["cep"], "addressCountry": "BR"},
        "url": f"{DOMAIN}/cnpj/{cnpj_r}/"
    }, ensure_ascii=False)

    return f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{title}</title>
<meta name="description" content="{desc}">
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
<link rel="canonical" href="{DOMAIN}/cnpj/{cnpj_r}/">
<meta property="og:type"        content="website">
<meta property="og:title"       content="{razao} \u2014 CNPJ {cnpj_f}">
<meta property="og:description" content="Dados p\u00fablicos do CNPJ {cnpj_f} \u2014 {razao}. Situa\u00e7\u00e3o {d['situacao']}. Receita Federal.">
<meta property="og:url"         content="{DOMAIN}/cnpj/{cnpj_r}/">
<meta property="og:site_name"   content="BuscaCNPJ.work">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<script type="application/ld+json">{schema}</script>
{CNPJ_HEAD}
</head>
<body>

<!-- Toast de c\u00f3pia -->
<div id="copy-toast">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
  <span id="copy-toast-text">Copiado!</span>
</div>

<header>
  <div class="header-inner">
    <a class="logo" href="/">Busca<span>CNPJ</span>.work</a>
    <form class="header-search" action="/"
          onsubmit="var v=this.qs.value.replace(/\\D/g,'');if(v.length===14){{window.location='/cnpj/'+v+'/';return false;}}alert('CNPJ inv\u00e1lido.');return false;">
      <input type="text" name="qs" maxlength="18" placeholder="Consultar outro CNPJ\u2026"
             autocomplete="off" inputmode="numeric"
             oninput="var v=this.value.replace(/\\D/g,'').slice(0,14);
               if(v.length>12)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5,8)+'/'+v.slice(8,12)+'-'+v.slice(12);
               else if(v.length>8)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5,8)+'/'+v.slice(8);
               else if(v.length>5)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5);
               else if(v.length>2)v=v.slice(0,2)+'.'+v.slice(2);
               this.value=v;">
      <button type="submit">Consultar</button>
    </form>
    <nav>
      <a href="/">In\u00edcio</a>
      <a href="/sobre/">Sobre</a>
    </nav>
  </div>
</header>

<div class="page-wrap">

  <nav class="bc" aria-label="Breadcrumb">
    <a href="/">In\u00edcio</a><span class="bc-sep">\u203a</span>
    <a href="/">CNPJ</a><span class="bc-sep">\u203a</span>
    <span>{cnpj_f}</span>
  </nav>

  <div class="company-hero">
    <div class="badge {badge_cls}"><span class="badge-dot"></span>{badge_txt}</div>
    <div class="copy-row-name">
      <h1 class="company-name">{razao}</h1>
      <button class="copy-btn" onclick="copyData('{razao}', 'Raz\u00e3o social copiada!', this)" title="Copiar Raz\u00e3o Social">{ICON_COPY} Copiar nome</button>
    </div>
    <div class="copy-row">
      <p class="cnpj-display">CNPJ {cnpj_f}</p>
      <button class="copy-btn" onclick="copyData('{cnpj_r}', 'CNPJ copiado!', this)" title="Copiar CNPJ">{ICON_COPY} Copiar CNPJ</button>
      <button class="copy-btn" onclick="copyData('{cnpj_f}', 'CNPJ formatado copiado!', this)" title="Copiar CNPJ formatado">{ICON_COPY} Com pontua\u00e7\u00e3o</button>
    </div>
  </div>

  <p class="sec-label">Dados Gerais</p>
  <div class="fields">
    <div class="field"><label>Raz\u00e3o Social</label><p>{razao}</p></div>
    <div class="field"><label>Nome Fantasia</label><p>{d['nome_fantasia'] or '\u2014'}</p></div>
    <div class="field"><label>CNPJ</label><p>{cnpj_f}</p></div>
    <div class="field"><label>Situa\u00e7\u00e3o Cadastral</label><p>{d['situacao']}</p></div>
    <div class="field"><label>Data de Abertura</label><p>{d['data_abertura']}</p></div>
    <div class="field"><label>Porte</label><p>{d['porte']}</p></div>
    <div class="field"><label>Natureza Jur\u00eddica</label><p>{d['natureza_juridica']}</p></div>
    <div class="field"><label>Capital Social</label><p>{d['capital_social']}</p></div>
    {f'<div class="field"><label>Telefone</label><p>{d["telefone"]}</p></div>' if d['telefone'] else ''}
    {f'<div class="field"><label>E-mail</label><p>{d["email"]}</p></div>' if d['email'] else ''}
  </div>

  <p class="sec-label">Endere\u00e7o</p>
  <div class="addr-card">
    <div class="addr-icon">{ICON_PIN}</div>
    <div>
      <p><strong>{end_str}</strong><br>
      {d['bairro']}<br>
      {d['municipio']} / {d['uf']}<br>
      CEP {d['cep']}</p>
    </div>
  </div>

  <p class="sec-label">Atividade Econ\u00f4mica Principal</p>
  <ul class="clean-list">
    <li>
      <div class="li-icon">{ICON_CNAE}</div>
      <div class="li-body"><strong>{d['cnae_principal']}</strong><span>CNAE {d['cnae_codigo']}</span></div>
    </li>
  </ul>

  <p class="sec-label">Atividades Secund\u00e1rias</p>
  <ul class="clean-list">{cnaes_html}</ul>

  <p class="sec-label">Quadro de S\u00f3cios e Administradores</p>
  <ul class="clean-list">{socios_html}</ul>

  <div class="fonte">
    <span class="fonte-icon">{ICON_INFO}</span>
    <p>Dados p\u00fablicos provenientes da <strong>Receita Federal do Brasil</strong>.
    Para corre\u00e7\u00f5es, acesse o <a href="https://www.gov.br/receitafederal" target="_blank" rel="noopener">portal da Receita Federal</a>
    ou <a href="/contato/">entre em contato</a>.</p>
  </div>

  <div class="affiliate-section">
    <div class="affiliate-header">
      <div class="partner-label">{ICON_PARTNER} Recomenda\u00e7\u00e3o de Parceiro</div>
      <img src="/hostinger_logo.png" alt="Hostinger Brasil" class="partner-logo">
      <h2>Transforme sua empresa em refer\u00eancia online</h2>
      <p>Com a <strong>Hostinger</strong>, coloque o site da sua empresa no ar com performance premium e e-mail corporativo.</p>
    </div>
    <div class="offers">
      <div class="offer-card">
        <div class="offer-image" style="background-image:url('https://images.unsplash.com/photo-1573164713988-8665fc963095?auto=format&fit=crop&q=80&w=600');"></div>
        <div class="offer-content">
          <span class="offer-badge">Hospedagem de Sites</span>
          <h3 class="offer-title">Business Web Hosting</h3>
          <p class="offer-desc">Pot\u00eancia e recursos avan\u00e7ados, ideal para empresas que buscam alta velocidade.</p>
          <div class="offer-price">R$ 19,99<span>/m\u00eas</span></div>
          <ul class="offer-features">
            <li>{ICON_CHECK} At\u00e9 50 websites na mesma conta</li>
            <li>{ICON_CHECK} 50 GB de Armazenamento NVMe</li>
            <li>{ICON_CHECK} Performance ultra-r\u00e1pida</li>
          </ul>
          <a href="https://www.hostinger.com/br?REFERRALCODE=1VINICIUS74" target="_blank" rel="noopener sponsored" class="btn-offer">Ver o Plano Business</a>
        </div>
      </div>
      <div class="offer-card">
        <div class="offer-image" style="background-image:url('https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&q=80&w=600');"></div>
        <div class="offer-content">
          <span class="offer-badge">E-mail Profissional</span>
          <h3 class="offer-title">Premium Business Email</h3>
          <p class="offer-desc">Transmita credibilidade aos seus clientes com endere\u00e7os @suaempresa.</p>
          <div class="offer-price">R$ 9,95<span>/m\u00eas</span></div>
          <ul class="offer-features">
            <li>{ICON_CHECK} 50 GB de armazenamento</li>
            <li>{ICON_CHECK} 50 regras de encaminhamento</li>
            <li>{ICON_CHECK} Dom\u00ednio gr\u00e1tis incluso</li>
          </ul>
          <a href="https://www.hostinger.com/br?REFERRALCODE=1VINICIUS74" target="_blank" rel="noopener sponsored" class="btn-offer">Criar E-mail Corporativo</a>
        </div>
      </div>
    </div>
    <p class="disclaimer">* Valores definidos pela Hostinger e sujeitos a altera\u00e7\u00f5es.</p>
  </div>

</div>

<footer>
  <nav class="fn">
    <a href="/">In\u00edcio</a>
    <a href="/sobre/">Sobre</a>
    <a href="/privacidade/">Privacidade</a>
    <a href="/contato/">Contato</a>
  </nav>
  <p>\u00a9 2026 <a href="/">BuscaCNPJ.work</a> \u2014 Dados p\u00fablicos da Receita Federal do Brasil.</p>
</footer>

<script>
function copyData(text, message, btn) {{
  navigator.clipboard.writeText(text).then(function() {{
    var orig = btn.innerHTML;
    btn.classList.add('copied');
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><polyline points="20 6 9 17 4 12"></polyline></svg> Copiado!';
    setTimeout(function() {{ btn.classList.remove('copied'); btn.innerHTML = orig; }}, 2000);
    var toast = document.getElementById('copy-toast');
    document.getElementById('copy-toast-text').textContent = message;
    toast.classList.add('show');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(function() {{ toast.classList.remove('show'); }}, 2500);
  }}).catch(function() {{
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
  }});
}}
</script>
</body>
</html>"""


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
<link rel="canonical" href="{DOMAIN}/"></head>
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
