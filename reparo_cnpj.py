#!/usr/bin/env python3
"""
reparo_cnpj.py — BuscaCNPJ.work
Auditoria completa e reparo das 50k páginas de CNPJ.
Auto-contido: não depende de patch_layout.py.
Garante o link relativo ../../cnpj.css?v=1.1
"""

import os
import re
import json
import logging
import time
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor, as_completed

# ── Config ──────────────────────────────────────────────────
CNPJ_DIR    = Path("./cnpj")
MAX_WORKERS = 10
LOG_FILE    = "auditoria.log"
DOMAIN      = "https://buscacnpj.work"
# ────────────────────────────────────────────────────────────

# Recriando ícones e head para o reparo ser autossuficiente
CNPJ_HEAD = """\
  <link rel="stylesheet" href="../../cnpj.css?v=1.1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">"""

ICON_CNAE    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>'
ICON_SEC     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>'
ICON_SOCIO   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
ICON_PIN     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>'
ICON_INFO    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
ICON_COPY    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>'
ICON_PARTNER = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
ICON_CHECK   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(), logging.FileHandler(LOG_FILE, encoding="utf-8")]
)
log = logging.getLogger("reparo")

def fmt_cnpj(c):
    return f"{c[:2]}.{c[2:5]}.{c[5:8]}/{c[8:12]}-{c[12:]}"

def _get(html, label):
    m = re.search(rf'<label>{re.escape(label)}</label>\s*<p>(.*?)</p>', html, re.DOTALL | re.IGNORECASE)
    return m.group(1).strip() if m else ""

def parse_html(html, cnpj_digits):
    razao    = _get(html, "Razão Social") or _get(html, "Raz\u00e3o Social") or "N/A"
    fantasia = _get(html, "Nome Fantasia")
    situacao = _get(html, "Situação Cadastral") or _get(html, "Situação") or "N/A"
    abertura = _get(html, "Data de Abertura")
    porte    = _get(html, "Porte") or "—"
    nat_jur  = _get(html, "Natureza Jurídica") or "—"
    capital  = _get(html, "Capital Social") or "—"
    telefone = _get(html, "Telefone")
    email    = _get(html, "E-mail")

    # Endereço
    m_addr = re.search(r'<div class="addr-card">.*?<strong>(.*?)</strong><br>\s*(.*?)\s*<br>\s*(.*?)\s*/\s*(.*?)\s*<br>\s*CEP (.*?)</p>', html, re.DOTALL)
    if m_addr:
        logr_raw = m_addr.group(1).strip()
        bairro   = m_addr.group(2).strip()
        mun      = m_addr.group(3).strip()
        uf       = m_addr.group(4).strip()
        cep      = m_addr.group(5).strip()
        
        # Separa numero e complemento do logradouro se houver (ex: "RUA X, 123 — APTO")
        l_parts = logr_raw.split(", ", 1)
        logr = l_parts[0]
        num  = "S/N"
        compl = ""
        if len(l_parts) > 1:
            rest = l_parts[1].split(" — ", 1)
            num = rest[0]
            if len(rest) > 1: compl = rest[1]
    else:
        logr, num, compl, bairro, mun, uf, cep = "—","—","","—","—","—","—"

    # CNAE Principal
    m_cnae = re.search(r'Atividade Econômica Principal</h2>\s*<ul[^>]*>.*?<strong>(.*?)</strong><span>CNAE (.*?)</span>', html, re.DOTALL | re.IGNORECASE)
    cnae_p = m_cnae.group(1).strip() if m_cnae else "—"
    cnae_c = m_cnae.group(2).strip() if m_cnae else "—"

    # CNAEs Secundários
    cnaes_s = []
    m_sec_block = re.search(r'Atividades Secundárias</h2>\s*<ul[^>]*>(.*?)</ul>', html, re.DOTALL | re.IGNORECASE)
    if m_sec_block:
        for m in re.finditer(r'<li>.*?<strong>(.*?)</strong><span>CNAE (.*?) <span', m_sec_block.group(1), re.DOTALL):
            cnaes_s.append({"descricao": m.group(1).strip(), "codigo": m.group(2).strip()})

    # QSA
    qsa = []
    m_qsa_block = re.search(r'Quadro de Sócios.*?<ul[^>]*>(.*?)</ul>', html, re.DOTALL | re.IGNORECASE)
    if m_qsa_block:
        for m in re.finditer(r'<li>.*?<strong>(.*?)</strong><span>(.*?)</span>', m_qsa_block.group(1), re.DOTALL):
            qsa.append({"nome_socio": m.group(1).strip(), "qualificacao_socio": m.group(2).strip()})

    return {
        "cnpj": cnpj_digits, "razao_social": razao, "nome_fantasia": fantasia,
        "situacao": situacao, "data_abertura": abertura, "porte": porte,
        "natureza_juridica": nat_jur, "capital_social": capital,
        "telefone": telefone, "email": email,
        "logradouro": logr, "numero": num, "complemento": compl,
        "bairro": bairro, "municipio": mun, "uf": uf, "cep": cep,
        "cnae_principal": cnae_p, "cnae_codigo": cnae_c,
        "cnaes_secundarios": cnaes_s, "qsa": qsa
    }

def gerar_html_norm(d):
    razao, cnpj_f, cnpj_r = d["razao_social"], fmt_cnpj(d["cnpj"]), d["cnpj"]
    sit = d["situacao"].upper()
    if "ATIVA" in sit: b_cls, b_txt = "badge-ativa", "Ativa"
    elif any(x in sit for x in ("BAIXADA", "CANCELADA")): b_cls, b_txt = "badge-baixada", d["situacao"].title()
    elif "INAPTA" in sit: b_cls, b_txt = "badge-inapta", d["situacao"].title()
    else: b_cls, b_txt = "badge-suspensa", d["situacao"].title()

    socios_html = "".join([f'<li><div class="li-icon">{ICON_SOCIO}</div><div class="li-body"><strong>{s["nome_socio"]}</strong><span>{s["qualificacao_socio"]}</span></div></li>' for s in d["qsa"]]) or '<li><div class="li-body"><span>Não disponível</span></div></li>'
    cnaes_html = "".join([f'<li><div class="li-icon">{ICON_SEC}</div><div class="li-body"><strong>{c["descricao"]}</strong><span>CNAE {c["codigo"]} <span class="tag">Secundária</span></span></div></li>' for c in d["cnaes_secundarios"]]) or '<li><div class="li-body"><span>—</span></div></li>'
    
    end_str = f"{d['logradouro']}, {d['numero']}" + (f" — {d['complemento']}" if d["complemento"] else "")
    tel_f = f'<div class="field"><label>Telefone</label><p>{d["telefone"]}</p></div>' if d["telefone"] else ""
    email_f = f'<div class="field"><label>E-mail</label><p>{d["email"]}</p></div>' if d["email"] else ""

    schema = json.dumps({"@context": "https://schema.org", "@type": "Organization", "name": d["nome_fantasia"] or razao, "legalName": razao, "taxID": cnpj_f, "url": f"{DOMAIN}/cnpj/{cnpj_r}/"}, ensure_ascii=False)

    return f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{razao} — CNPJ {cnpj_f} | BuscaCNPJ.work</title>
<meta name="description" content="Dados do CNPJ {cnpj_f} — {razao}. Situação: {d['situacao']}. Consulta gratuita.">
<link rel="canonical" href="{DOMAIN}/cnpj/{cnpj_r}/">
<script type="application/ld+json">{schema}</script>
{CNPJ_HEAD}
</head>
<body>
<div id="copy-toast"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg><span id="copy-toast-text">Copiado!</span></div>
<header><div class="header-inner"><a class="logo" href="/">Busca<span>CNPJ</span>.work</a><form class="header-search" action="/" onsubmit="var v=this.qs.value.replace(/\\D/g,'');if(v.length===14){{window.location='/cnpj/'+v+'/';return false;}}alert('CNPJ inválido.');return false;"><input type="text" name="qs" maxlength="18" placeholder="Consultar outro CNPJ…" oninput="var v=this.value.replace(/\\D/g,'').slice(0,14);if(v.length>12)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5,8)+'/'+v.slice(8,12)+'-'+v.slice(12);else if(v.length>8)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5,8)+'/'+v.slice(8);else if(v.length>5)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5);else if(v.length>2)v=v.slice(0,2)+'.'+v.slice(2);this.value=v;"><button type="submit">Consultar</button></form><nav><a href="/">Início</a><a href="/sobre/">Sobre</a></nav></div></header>
<div class="page-wrap">
  <nav class="bc"><a href="/">Início</a><span class="bc-sep">›</span><span>{cnpj_f}</span></nav>
  <div class="company-hero">
    <div class="badge {b_cls}"><span class="badge-dot"></span>{b_txt}</div>
    <div class="copy-row-name"><h1 class="company-name">{razao}</h1><button class="copy-btn" onclick="copyData('{razao}', 'Razão social copiada!', this)">{ICON_COPY} Copiar nome</button></div>
    <div class="copy-row"><p class="cnpj-display">CNPJ {cnpj_f}</p><button class="copy-btn" onclick="copyData('{cnpj_r}', 'CNPJ copiado!', this)">{ICON_COPY} CP</button><button class="copy-btn" onclick="copyData('{cnpj_f}', 'Formatado!', this)">{ICON_COPY} CP Fmt</button></div>
  </div>
  <p class="sec-label">Dados Gerais</p>
  <div class="fields">
    <div class="field"><label>Razão Social</label><p>{razao}</p></div>
    <div class="field"><label>Nome Fantasia</label><p>{d['nome_fantasia'] or '—'}</p></div>
    <div class="field"><label>CNPJ</label><p>{cnpj_f}</p></div>
    <div class="field"><label>Situação Cadastral</label><p>{d['situacao']}</p></div>
    <div class="field"><label>Data de Abertura</label><p>{d['data_abertura']}</p></div>
    <div class="field"><label>Porte</label><p>{d['porte']}</p></div>
    <div class="field"><label>Natureza Jurídica</label><p>{d['natureza_juridica']}</p></div>
    <div class="field"><label>Capital Social</label><p>{d['capital_social']}</p></div>
    {tel_f}{email_f}
  </div>
  <p class="sec-label">Endereço</p>
  <div class="addr-card"><div class="addr-icon">{ICON_PIN}</div><div><p><strong>{end_str}</strong><br>{d['bairro']}<br>{d['municipio']} / {d['uf']}<br>CEP {d['cep']}</p></div></div>
  <p class="sec-label">Atividade Econômica Principal</p><ul class="clean-list"><li><div class="li-icon">{ICON_CNAE}</div><div class="li-body"><strong>{d['cnae_principal']}</strong><span>CNAE {d['cnae_codigo']}</span></div></li></ul>
  <p class="sec-label">Atividades Secundárias</p><ul class="clean-list">{cnaes_html}</ul>
  <p class="sec-label">Quadro de Sócios e Administradores</p><ul class="clean-list">{socios_html}</ul>
  <div class="fonte"><span class="fonte-icon">{ICON_INFO}</span><p>Dados públicos da Receita Federal.</p></div>
  <div class="affiliate-section">
    <div class="affiliate-header"><div class="partner-label">{ICON_PARTNER} Parceiro</div><img src="/hostinger_logo.png" alt="Hostinger" class="partner-logo"><h2>Sites profissionais</h2><p>Hospedagem Hostinger com performance premium.</p></div>
    <div class="offers"><div class="offer-card"><div class="offer-image" style="background-image:url('https://images.unsplash.com/photo-1573164713988-8665fc963095?w=600');"></div><div class="offer-content"><span class="offer-badge">Hospedagem</span><h3 class="offer-title">Business</h3><p class="offer-desc">Performance ultra-rápida.</p><div class="offer-price">R$ 19,99<span>/mês</span></div><ul class="offer-features"><li>{ICON_CHECK} 50 websites</li><li>{ICON_CHECK} NVMe</li></ul><a href="https://www.hostinger.com/br?REFERRALCODE=1VINICIUS74" target="_blank" class="btn-offer">Ver Plano</a></div></div></div>
    <p class="disclaimer">* Valores Hostinger.</p>
  </div>
</div>
<footer><nav class="fn"><a href="/">Início</a><a href="/sobre/">Sobre</a></nav><p>© 2026 BuscaCNPJ.work</p></footer>
<script>
function copyData(text, message, btn) {{
  navigator.clipboard.writeText(text).then(function() {{
    var orig = btn.innerHTML; btn.classList.add('copied'); btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><polyline points="20 6 9 17 4 12"></polyline></svg>!';
    setTimeout(function() {{ btn.classList.remove('copied'); btn.innerHTML = orig; }}, 2000);
    var toast = document.getElementById('copy-toast'); toast.classList.add('show');
    setTimeout(function() {{ toast.classList.remove('show'); }}, 2500);
  }});
}}
</script>
</body>
</html>"""

def audit(folder: Path):
    path, cnpj = folder / "index.html", folder.name
    if not path.exists(): return cnpj, "FALTA"
    try:
        html = path.read_text(encoding="utf-8", errors="replace")
        if 'href="../../cnpj.css?v=1.1"' in html and "affiliate-section" in html: return cnpj, "OK"
        # Reparo
        d = parse_html(html, cnpj)
        if not d["razao_social"] or d["razao_social"] == "N/A": return cnpj, "ERRO_DADOS"
        path.write_text(gerar_html_norm(d), encoding="utf-8")
        return cnpj, "REPARADO"
    except Exception as e: return cnpj, f"ERRO: {e}"

def main():
    folders = [p for p in CNPJ_DIR.iterdir() if p.is_dir()]
    total = len(folders)
    log.info("Auditoria manual: %d páginas", total)
    counts = {"OK":0, "REPARADO":0, "ERRO":0}
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as ex:
        futs = {ex.submit(audit, f): f for f in folders}
        for i, fut in enumerate(as_completed(futs), 1):
            c, st = fut.result()
            base = st.split(":")[0]
            counts[base if base in counts else "ERRO"] = counts.get(base if base in counts else "ERRO", 0) + 1
            if i % 5000 == 0 or i == total: log.info("Progresso: %d/%d - OK:%d REP:%d ERR:%d", i, total, counts["OK"], counts["REPARADO"], counts["ERRO"])
    log.info("CONCLUÍDO: %s", counts)

if __name__ == "__main__": main()
