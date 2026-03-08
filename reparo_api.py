#!/usr/bin/env python3
"""
reparo_api.py — BuscaCNPJ.work
Script para identificar CNPJs com dados faltantes (marcados como —)
e consultar novamente as APIs para completar as informações.
"""

import requests, os, json, time, logging, re
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

# Configurações
BASE_DIR      = "."
CNPJ_DIR      = Path("./cnpj")
LOG_FILE      = "reparo_api.log"
MAX_WORKERS   = 2 # Mais lento para evitar blocks
SLEEP_FETCH   = 1.0
API_BRASIL    = "https://brasilapi.com.br/api/cnpj/v1/"
API_MINHA_REC = "https://minhareceita.org/"
DOMAIN        = "https://buscacnpj.work"
VERSION       = "1.3"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
    handlers=[logging.StreamHandler(), logging.FileHandler(LOG_FILE, encoding="utf-8")]
)
log = logging.getLogger(__name__)

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
        if not d or d == "—": return "—"
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

def gerar_html_v1_3(data):
    d = norm(data)
    razao = d["razao_social"]
    nome = razao.upper()
    nome_fantasia = (d["nome_fantasia"] or "").upper()
    cnpj_f = fmt_cnpj(d["cnpj"])
    cnpj_r = d["cnpj"]
    
    sit = d["situacao"].upper()
    badge_cls = "ba" if "ATIVA" in sit else "bb" if any(x in sit for x in ("BAIXADA", "INAPTA")) else "bo"
    
    # Sócios & CNAEs
    socios = d.get("qsa")
    if not isinstance(socios, list): socios = []
    socios_html = "".join([f'<li><strong>{s.get("nome_socio","—") if isinstance(s, dict) else s}</strong><span>{s.get("qualificacao_socio","Sócio") if isinstance(s, dict) else "Sócio"}</span></li>' for s in socios]) or "<li><span>Informação não disponível</span></li>"
    
    cnaes = d.get("cnaes_secundarios")
    if not isinstance(cnaes, list): cnaes = []
    cnaes_sec = "".join([f'<li><strong>{c.get("descricao","—") if isinstance(c, dict) else c}</strong><span>CNAE {c.get("codigo","") if isinstance(c, dict) else ""}</span></li>' for c in cnaes]) or "<li><span>—</span></li>"

    title = f"{nome} — CNPJ {cnpj_f} | BuscaCNPJ.work"
    desc = f"Dados do CNPJ {cnpj_f}: {razao}. Situação {d['situacao']}."
    
    return f"""<!DOCTYPE html><html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>{title}</title>
    <meta name="description" content="{desc}">
    <link rel="canonical" href="{DOMAIN}/cnpj/{cnpj_r}/">
    <link rel="stylesheet" href="../../cnpj.css?v={VERSION}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
<header><div class="header-inner"><a class="logo" href="/">Busca<span>CNPJ</span>.work</a><nav><a href="/">Início</a><a href="/sobre/">Sobre</a></nav></div></header>
<div class="page-wrap fade-up">
    <div class="bc"><a href="/">Início</a> / <a href="/cnpj/">CNPJ</a> / {cnpj_f}</div>
    <div class="company-hero">
        <div class="badge {badge_cls}">{d['situacao']}</div>
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
        <div class="info-box" style="grid-column: span 2;"><label>Endereço</label><p>{d['logradouro']}, {d['numero']} {d['complemento']}</p></div>
        <div class="info-box"><label>Bairro</label><p>{d['bairro']}</p></div>
        <div class="info-box"><label>Cidade/UF</label><p>{d['municipio']} — {d['uf']}</p></div>
        <div class="info-box"><label>Telefone</label><p>{d['telefone'] or '—'}</p></div>
        <div class="info-box"><label>Email</label><p>{d['email'] or '—'}</p></div>
    </div>
    <h2 class="sec-title">Atividades Econômicas</h2>
    <div class="info-box" style="margin-bottom:24px;"><label>Atividade Principal</label><p>{d['cnae_principal']} (CNAE {d['cnae_codigo']})</p></div>
    <div class="info-box"><label>Atividades Secundárias</label><ul style="list-style:none; padding-top:10px;">{cnaes_sec}</ul></div>
    <h2 class="sec-title">Quadro Societário</h2>
    <div class="info-box"><ul style="list-style:none; padding-top:10px;">{socios_html}</ul></div>
    <div class="partner-section">
        <div style="margin-bottom:2rem; opacity:0.6; font-weight:800; letter-spacing:2px; text-transform:uppercase; font-size:0.75rem;">Sugestão para seu negócio</div>
        <h2 style="font-size:3rem; margin-bottom:1rem; color:#fff;">Hostinger Brasil</h2>
        <div class="partner-grid">
            <div class="partner-card">
                <span class="badge ba" style="margin-bottom:1rem;">Hospedagem Business</span>
                <h3>Sites de Alta Performance</h3>
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
<footer><nav><a href="/">Início</a><a href="/sobre/">Sobre</a><a href="/privacidade/">Privacidade</a><a href="/contato/">Contato</a></nav><p>© 2026 BuscaCNPJ.work — Todos os direitos reservados.</p></footer>
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

def fetch(cnpj):
    # Tenta BrasilAPI primeiro, depois MinhaReceita
    for url in [f"{API_BRASIL}{cnpj}", f"{API_MINHA_REC}{cnpj}"]:
        try:
            r = requests.get(url, timeout=12, headers={"User-Agent":"BuscaCNPJ-Repair/1.0"})
            if r.status_code == 200: 
                data = r.json()
                # Verifica se retornou dados úteis (não apenas CNPJ)
                if len(data.keys()) > 5:
                    return data
            if r.status_code == 429: 
                log.warning("Rate limit atingido. Dormindo 30s...")
                time.sleep(30)
        except: pass
    return None

def check_needs_repair(html):
    # Conta ocorrências de <p>—</p> ou campos vazios
    placeholders = len(re.findall(r'<p>—</p>', html))
    # Campos críticos que não devem ser —
    missing_critical = any(x in html for x in [
        '<label>Porte</label><p>—</p>',
        '<label>Data de Abertura</label><p>—</p>',
        '<label>Endereço</label><p>—, S/N </p>'
    ])
    return placeholders >= 4 or missing_critical

def repair_folder(folder: Path):
    path = folder / "index.html"
    if not path.exists(): return folder.name, "MISSING"
    
    try:
        html = path.read_text(encoding="utf-8", errors="replace")
        if not check_needs_repair(html):
            return folder.name, "SKIP"
        
        cnpj = folder.name
        log.info("Reparando CNPJ %s via API...", cnpj)
        data = fetch(cnpj)
        
        if not data:
            return cnpj, "API_FAIL"
        
        # Gera novo HTML
        new_html = gerar_html_v1_3(data)
        path.write_text(new_html, encoding="utf-8")
        return cnpj, "FIXED"
        
    except Exception as e:
        return folder.name, f"ERROR:{e}"

def main():
    folders = [p for p in CNPJ_DIR.iterdir() if p.is_dir()]
    total = len(folders)
    log.info("Iniciando REPARO VIA API em %d pastas...", total)
    
    counts = {"FIXED": 0, "SKIP": 0, "API_FAIL": 0, "ERROR": 0, "MISSING": 0}
    
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = {executor.submit(repair_folder, f): f for f in folders}
        for i, fut in enumerate(as_completed(futures), 1):
            cnpj, status = fut.result()
            if status.startswith("ERROR"): counts["ERROR"] += 1
            else: counts[status] += 1
            
            if i % 50 == 0 or i == total:
                log.info("Progresso: %d/%d | FIX:%d SKIP:%d FAIL:%d ERR:%d", 
                         i, total, counts["FIXED"], counts["SKIP"], counts["API_FAIL"], counts["ERROR"])
            
            if counts["FIXED"] >= 1000:
                log.info("Limite de 1000 reparos atingido. Parando por segurança.")
                break
            
    log.info("Finalizado: %s", counts)

if __name__ == "__main__":
    main()
