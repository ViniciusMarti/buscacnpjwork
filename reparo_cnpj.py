#!/usr/bin/env python3
"""
reparo_cnpj.py — BuscaCNPJ.work
Versão DEFINITIVA — 2026-03-07
Garante a extração de dados (mesmo com divergência de encoding/dash) e o design v1.3.
"""

import os
import re
import logging
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor, as_completed

# ── Config ──────────────────────────────────────────────────
CNPJ_DIR    = Path("./cnpj")
MAX_WORKERS = 60
LOG_FILE    = "auditoria.log"
DOMAIN      = "https://buscacnpj.work"
VERSION     = "1.4"
# ────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(), logging.FileHandler(LOG_FILE, encoding="utf-8")]
)
log = logging.getLogger(__name__)

def fmt_cnpj(c):
    c = c.zfill(14)
    return f"{c[:2]}.{c[2:5]}.{c[5:8]}/{c[8:12]}-{c[12:]}"

def _extract(html, label):
    # Tenta padrão <label>X</label><p>Y</p>
    patterns = [
        rf'<(?:label|span|div)[^>]*>{re.escape(label)}</(?:label|span|div)>\s*<p>(.*?)</p>',
        rf'<div[^>]*class=["\']label["\'][^>]*>{re.escape(label)}</div>\s*<div[^>]*class=["\']value["\'][^>]*>(.*?)</div>',
        rf'<b>{re.escape(label)}:?</b>\s*(.*?)<br>'
    ]
    for p in patterns:
        m = re.search(p, html, re.I | re.S)
        if m: return m.group(1).strip()
    return ""

def parse_html(html, cnpj_digits):
    # Razão Social com fallbacks robustos
    razao = _extract(html, "Razão Social") or _extract(html, "Raz\u00e3o Social")
    if not razao:
        m_title = re.search(r'<title>(.*?)\s+[-—–|]\s+CNPJ', html, re.I)
        if not m_title:
             m_title = re.search(r'<title>(.*?)\s+[-—–|]', html, re.I)
        razao = m_title.group(1).strip() if m_title else "N/A"

    d = {
        "cnpj": cnpj_digits,
        "razao_social": razao,
        "nome_fantasia": _extract(html, "Nome Fantasia") or "",
        "situacao": _extract(html, "Situação Cadastral") or _extract(html, "Situa\u00e7\u00e3o Cadastral") or _extract(html, "Situação") or "N/A",
        "data_abertura": _extract(html, "Data de Abertura") or "—",
        "porte": _extract(html, "Porte") or "—",
        "natureza_juridica": _extract(html, "Natureza Jurídica") or _extract(html, "Natureza Jur\u00eddica") or "—",
        "capital_social": _extract(html, "Capital Social") or "—",
        "telefone": _extract(html, "Telefone") or "",
        "email": _extract(html, "E-mail") or _extract(html, "Email") or "",
        "logradouro": _extract(html, "Logradouro") or _extract(html, "Endereço") or _extract(html, "Endere\u00e7o") or "—",
        "bairro": _extract(html, "Bairro") or "—",
        "municipio": "—", "uf": "—", "cnae_principal": "—", "cnae_codigo": "—"
    }
    
    # Município/UF
    m_loc = re.search(r'<(?:div|span|label)[^>]*(?:municipio|uf|cidade)[^>]*>(?:Cidade/UF|Município)</(?:label|div|span)>\s*<p>(.*?)</p>', html, re.I | re.S)
    if not m_loc:
        m_loc = re.search(r'<label>Cidade/UF</label><p>(.*?)</p>', html, re.I)
    
    if m_loc:
        val = m_loc.group(1)
        parts = val.split(" — ") if " — " in val else val.split(" / ") if " / " in val else [val]
        d["municipio"] = parts[0].strip()
        if len(parts) > 1: d["uf"] = parts[1].strip()
    
    m_cnae = re.search(r'(?:Atividade Principal|CNAE).*?<strong>(.*?)</strong>(?:.*?CNAE (.*?)(?:</span>| |<|/))?', html, re.S | re.I)
    if m_cnae:
        d["cnae_principal"] = m_cnae.group(1).strip()
        if m_cnae.group(2): d["cnae_codigo"] = m_cnae.group(2).strip().replace("(", "").replace(")", "").strip()

    return d

def gerar_html_premium(d):
    razao = d["razao_social"]
    nome = razao.upper() # H1 agora é Razão Social
    nome_fantasia = (d["nome_fantasia"] or "").upper()
    cnpj_f = fmt_cnpj(d["cnpj"])
    cnpj_r = d["cnpj"]
    sit = d["situacao"].upper()
    b_cls = "ba" if "ATIVA" in sit else "bb" if any(x in sit for x in ("BAIXADA", "INAPTA")) else "bo"

    return f"""<!DOCTYPE html><html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>{nome} — CNPJ {cnpj_f} | BuscaCNPJ.work</title>
    <meta name="description" content="Dados do CNPJ {cnpj_f}: {razao}. Situação {d['situacao']}.">
    <link rel="canonical" href="{DOMAIN}/cnpj/{cnpj_r}/">
    <link rel="stylesheet" href="../../cnpj.css?v={VERSION}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<header><div class="header-inner"><a class="logo" href="/">Busca<span>CNPJ</span>.work</a><nav><a href="/">Início</a><a href="/sobre/">Sobre</a></nav></div></header>
<div class="page-wrap fade-up">
    <div class="bc"><a href="/">Início</a> / <a href="/cnpj/">CNPJ</a> / {cnpj_f}</div>
    <div class="company-hero">
        <div class="badge {b_cls}">{d['situacao']}</div>
        <h1 class="company-title">{nome}</h1>
        {f'<p style="color:var(--text-muted); font-size: 0.9rem; margin-top:-10px; margin-bottom:10px;">{nome_fantasia}</p>' if nome_fantasia and nome_fantasia != nome else ''}
        <p style="color:var(--text-muted); font-weight:600; margin-bottom: 20px;">CNPJ {cnpj_f}</p>
        <div class="copy-group">
            <button class="btn-copy" onclick="copyText('{razao}', this)">Copiar Nome</button>
            <button class="btn-copy" onclick="copyText('{cnpj_r}', this)">Copiar CNPJ</button>
            <button class="btn-copy" onclick="copyText('{cnpj_f}', this)">Formatado</button>
        </div>
    </div>
    <h2 class="sec-title">Dados de Registro</h2>
    <div class="info-grid">
        <div class="info-box"><label>Razão Social</label><p>{razao}</p></div>
        <div class="info-box"><label>Nome Fantasia</label><p>{d['nome_fantasia'] or '—'}</p></div>
        <div class="info-box"><label>Data de Abertura</label><p>{d['data_abertura']}</p></div>
        <div class="info-box"><label>Situação</label><p>{d['situacao']}</p></div>
        <div class="info-box"><label>Porte</label><p>{d['porte']}</p></div>
        <div class="info-box"><label>Capital Social</label><p>{d['capital_social']}</p></div>
    </div>
    <h2 class="sec-title">Localização & Contato</h2>
    <div class="info-grid">
        <div class="info-box" style="grid-column: span 2;"><label>Endereço</label><p>{d['logradouro'] or '—'}</p></div>
        <div class="info-box"><label>Bairro</label><p>{d['bairro']}</p></div>
        <div class="info-box"><label>Cidade/UF</label><p>{d['municipio']} — {d['uf']}</p></div>
        <div class="info-box"><label>Telefone</label><p>{d['telefone'] or '—'}</p></div>
        <div class="info-box"><label>Email</label><p>{d['email'] or '—'}</p></div>
    </div>
    <h2 class="sec-title">Atividade Principal</h2>
    <div class="info-box"><label>CNAE</label><p>{d['cnae_principal']} (CNAE {d['cnae_codigo']})</p></div>
    <div class="partner-section">
        <div style="margin-bottom:2rem; opacity:0.6; font-weight:800; letter-spacing:2px; text-transform:uppercase; font-size:0.75rem;">Sugestão para seu negócio</div>
        <h2 style="font-size:3rem; margin-bottom:1rem; color:#fff;">Hostinger Brasil</h2>
        <div class="partner-grid">
            <div class="partner-card">
                <span class="badge ba" style="margin-bottom:1rem;">Hospedagem</span>
                <h3>Sites Profissionais</h3>
                <div class="partner-price">R$ 19,99<span>/mês</span></div>
                <a href="https://www.hostinger.com/br?REFERRALCODE=1VINICIUS74" class="btn-cta" target="_blank">Ativar Oferta</a>
            </div>
            <div class="partner-card">
                <span class="badge bo" style="margin-bottom:1rem;">Email Business</span>
                <h3>Email Corporativo</h3>
                <div class="partner-price">R$ 9,95<span>/mês</span></div>
                <a href="https://www.hostinger.com/br?REFERRALCODE=1VINICIUS74" class="btn-cta" target="_blank">Criar Email</a>
            </div>
        </div>
    </div>
</div>
<footer><p>© 2026 BuscaCNPJ.work — Dados Oficiais.</p></footer>
<script>
function copyText(txt, btn) {{
    navigator.clipboard.writeText(txt).then(() => {{
        const originalText = btn.innerHTML;
        btn.innerHTML = "Copiado!";
        setTimeout(() => {{ btn.innerHTML = originalText; }}, 2000);
    }}).catch(() => {{}});
}}
</script>
</body></html>"""

def audit(folder: Path):
    path, cnpj = folder / "index.html", folder.name
    if not path.exists(): return cnpj, "FALTA"
    try:
        html = path.read_text(encoding="utf-8", errors="replace")
        if f'v={VERSION}' in html and 'margin-top:-10px' in html:
            return cnpj, "OK"
        
        d = parse_html(html, cnpj)
        if d["razao_social"] == "N/A": 
            return cnpj, "ERRO_DADOS"
        
        new_html = gerar_html_premium(d)
        path.write_text(new_html, encoding="utf-8")
        return cnpj, "REPARADO"
    except Exception as e:
        return cnpj, f"ERRO:{e}"

def main():
    folders = [p for p in CNPJ_DIR.iterdir() if p.is_dir()]
    total = len(folders)
    log.info("Iniciando REPARO DEFINITIVO v%s em %d pastas...", VERSION, total)
    counts = {"OK": 0, "REPARADO": 0, "ERRO": 0}
    
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as ex:
        futs = {ex.submit(audit, f): f for f in folders}
        for i, fut in enumerate(as_completed(futs), 1):
            cnpj, st = fut.result()
            if st == "OK": counts["OK"] += 1
            elif st == "REPARADO": counts["REPARADO"] += 1
            else:
                counts["ERRO"] += 1
                if counts["ERRO"] <= 10:
                    log.warning("Falha em %s: %s", cnpj, st)
            
            if i % 1000 == 0 or i == total:
                log.info("Progresso: %d/%d - OK:%d REP:%d ERR:%d", i, total, counts["OK"], counts["REPARADO"], counts["ERRO"])

if __name__ == "__main__":
    main()
