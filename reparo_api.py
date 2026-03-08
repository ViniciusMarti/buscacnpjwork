#!/usr/bin/env python3
"""
reparo_api.py — BuscaCNPJ.work
Script para identificar CNPJs com dados faltantes (marcados como —)
e consultar novamente as APIs (agora incluindo OpenCNPJ) para completar as informações.
"""

import requests, os, json, time, logging, re
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

# Configurações
BASE_DIR      = "."
CNPJ_DIR      = Path("./cnpj")
LOG_FILE      = "reparo_api.log"
MAX_WORKERS   = 5 # Mais lento para garantir captura integral e evitar bloqueios
API_OPENCNPJ  = "https://api.opencnpj.org/"
API_BRASIL    = "https://brasilapi.com.br/api/cnpj/v1/"
API_MINHA_REC = "https://minhareceita.org/"
DOMAIN        = "https://buscacnpj.work"
VERSION       = "1.7.1" # Versão atualizada

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
    handlers=[logging.StreamHandler(), logging.FileHandler(LOG_FILE, encoding="utf-8")]
)
log = logging.getLogger(__name__)

def fmt_cnpj(c):
    c = str(c).zfill(14)
    return f"{c[:2]}.{c[2:5]}.{c[5:8]}/{c[8:12]}-{c[12:]}"

def fmt_brl(v):
    try:
        # Resolve problema com formatos de string "1000,00"
        if isinstance(v, str):
            v = v.replace(".", "").replace(",", ".")
        return f"R$\xa0{float(v):,.2f}".replace(",","X").replace(".",",").replace("X",".")
    except:
        return "R$\xa00,00"

def fmt_date(d):
    try:
        if not d or d == "—": return "—"
        if "/" in d: return d # Já formatado
        return datetime.strptime(d, "%Y-%m-%d").strftime("%d/%m/%Y")
    except:
        return d or "—"

def norm(data):
    if not data: return {}
    cnpj = "".join(x for x in str(data.get("cnpj","")) if x.isdigit())
    
    # Situação
    sit_r = (data.get("descricao_situacao_cadastral") or 
             data.get("descrição_situação_cadastral") or 
             data.get("situacao_cadastral") or 
             data.get("situacao") or "N/A")
    situacao = str(sit_r).upper()
    
    data_abertura = fmt_date(data.get("data_inicio_atividade") or data.get("data_abertura") or "")
    porte = str(data.get("porte") or data.get("porte_empresa") or "—").upper()
    
    # Contato
    tel = data.get("ddd_telefone_1") or ""
    if not tel and data.get("telefones") and isinstance(data["telefones"], list) and len(data["telefones"]) > 0:
        t = data["telefones"][0]
        if isinstance(t, dict):
            tel = f"({t.get('ddd','')}) {t.get('numero','')}".replace("None", "").strip()
            if tel == "()": tel = ""
    elif not tel and data.get("telefone"): 
        tel = str(data.get("telefone"))

    # QSA
    socios_raw = data.get("qsa") or data.get("quadro_societario") or data.get("socios") or []
    socios_clean = []
    if not isinstance(socios_raw, list): socios_raw = []
    for s in socios_raw:
        if isinstance(s, dict):
            nome = str(s.get("nome_socio") or s.get("nome") or "—").upper()
            
            # Tenta pegar código e descrição da qualificação
            q_cod = str(s.get("codigo_qualificacao_socio") or s.get("cod_qualificacao") or "")
            q_desc = str(s.get("qualificacao_socio") or s.get("qualificacao") or s.get("cargo") or "Sócio")
            
            if q_cod and q_cod not in q_desc:
                cargo = f"{q_cod} - {q_desc}"
            else:
                cargo = q_desc
            
            socios_clean.append({"nome": nome, "cargo": cargo})
        else:
            socios_clean.append({"nome": str(s).upper(), "cargo": "Sócio"})

    # Atividade Principal
    cnae_desc = (data.get("cnae_fiscal_descricao") or data.get("cnae_fiscal_descrição") or 
                 data.get("cnae_principal_descricao") or data.get("cnae_principal_desc") or "")
    
    cf = data.get("cnae_fiscal") or data.get("cnae_principal") or data.get("estabelecimento", {}).get("atividade_principal")
    if not cf and data.get("atividade_principal") and isinstance(data["atividade_principal"], list):
        cf = data["atividade_principal"][0]

    if isinstance(cf, dict):
        cnae_cod = str(cf.get("codigo") or cf.get("id") or "")
        if not cnae_desc: cnae_desc = cf.get("descricao") or cf.get("text") or ""
    else:
        cnae_cod = str(cf or "")
    
    if not cnae_desc or cnae_desc == cnae_cod:
        cnae_desc = "Atividade principal" if cnae_cod else "—"

    # Atividades Secundárias
    cnaes_sec_raw = data.get("cnaes_secundarios") or data.get("atividades_secundarias") or []
    cnaes_sec_clean = []
    if not isinstance(cnaes_sec_raw, list): cnaes_sec_raw = []
    for c in cnaes_sec_raw:
        if isinstance(c, dict):
            cod = str(c.get("codigo") or c.get("id") or "")
            txt = str(c.get("descricao") or c.get("text") or "Atividade secundária")
            cnaes_sec_clean.append({"codigo": cod, "descricao": txt})
        else:
            cnaes_sec_clean.append({"codigo": str(c), "descricao": "Atividade secundária"})

    return {
        "cnpj":             cnpj,
        "razao_social":     str(data.get("razao_social") or data.get("razão_social") or data.get("nome") or "N/A").upper(),
        "nome_fantasia":    str(data.get("nome_fantasia") or data.get("nome_comercial") or data.get("fantasia") or "").upper(),
        "situacao":         situacao,
        "data_abertura":    data_abertura,
        "porte":            porte,
        "natureza_juridica":str(data.get("natureza_juridica") or "—"),
        "capital_social":   fmt_brl(data.get("capital_social", 0)),
        "email":            str(data.get("email") or "").lower(),
        "telefone":         tel,
        "logradouro":       str(data.get("logradouro") or "—").upper(),
        "numero":           str(data.get("numero") or "S/N"),
        "complemento":      str(data.get("complemento") or "").upper(),
        "bairro":           str(data.get("bairro") or "—").upper(),
        "municipio":        str(data.get("municipio") or data.get("município") or "—").upper(),
        "uf":               str(data.get("uf") or "—").upper(),
        "cep":              str(data.get("cep") or "—"),
        "cnae_principal":   str(cnae_desc),
        "cnae_codigo":      str(cnae_cod),
        "cnaes_secundarios":cnaes_sec_clean,
        "qsa":              socios_clean,
    }

ICON_COPY = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>'

def gerar_html_v1_7(data):
    d = norm(data)
    if not d: return ""
    razao = d.get("razao_social", "N/A")
    nome = razao.upper()
    nome_fantasia = d.get("nome_fantasia", "").upper()
    cnpj_f = fmt_cnpj(d.get("cnpj", "00000000000000"))
    cnpj_r = d.get("cnpj", "")
    
    sit = d.get("situacao", "").upper()
    badge_cls = "ba" if "ATIVA" in sit else "bb" if any(x in sit for x in ("BAIXADA", "INAPTA")) else "bo"
    
    # Sócios & CNAEs
    socios_html = "".join([f'<li><strong>{s["nome"]}</strong><span>{s["cargo"]}</span></li>' for s in d.get("qsa", [])]) or "<li><span>Informação não disponível</span></li>"
    
    cnaes_sec_list = []
    for c in d.get("cnaes_secundarios", []):
        desc = c.get("descricao", "")
        cod = c.get("codigo", "")
        if desc and desc != cod and not desc.isdigit() and "secundária" not in desc.lower():
            cnaes_sec_list.append(f'<li><strong>{desc}</strong><span>CNAE {cod}</span></li>')
        elif cod:
            cnaes_sec_list.append(f'<li><strong>CNAE {cod}</strong></li>')
    cnaes_sec = "".join(cnaes_sec_list) or "<li><span>—</span></li>"
    
    # Atividade Principal
    cp_desc = d.get("cnae_principal", "")
    cp_cod = d.get("cnae_codigo", "")
    if cp_desc and cp_desc != cp_cod and not cp_desc.isdigit() and "principal" not in cp_desc.lower():
        cnae_main_display = f"{cp_desc} (CNAE {cp_cod})"
    elif cp_cod:
        cnae_main_display = f"CNAE {cp_cod}"
    else:
        cnae_main_display = "—"

    title = f"{nome} — CNPJ {cnpj_f} | BuscaCNPJ.work"
    desc = f"Dados do CNPJ {cnpj_f}: {razao}. Situação {sit}."
    schema = json.dumps({"@context":"https://schema.org","@type":"Organization","name":nome,"taxID":cnpj_f}, ensure_ascii=False)
    
    return f"""<!DOCTYPE html><html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>{title}</title>
    <meta name="description" content="{desc}">
    <link rel="canonical" href="{DOMAIN}/cnpj/{cnpj_r}/">
    <link rel="stylesheet" href="../../cnpj.css?v={VERSION}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script type="application/ld+json">{schema}</script>
</head>
<body>
<header><div class="header-inner"><a class="logo" href="/">Busca<span>CNPJ</span>.work</a><nav><a href="/">Início</a><a href="/sobre/">Sobre</a></nav></div></header>
<div class="page-wrap fade-up">
    <div class="bc"><a href="/">Início</a> / <a href="/cnpj/">CNPJ</a> / {cnpj_f}</div>
    <div class="company-hero">
        <div class="badge {badge_cls}">{d.get('situacao', 'N/A')}</div>
        <h1 class="company-title">{nome}</h1>
        {f'<p style="color:var(--text-muted); font-size: 0.9rem; margin-top:-10px; margin-bottom:10px;">{nome_fantasia}</p>' if nome_fantasia and nome_fantasia != nome else ''}
        <p style="color:var(--text-muted); font-weight:600; margin-bottom: 20px;">CNPJ {cnpj_f}</p>
        <div class="copy-group">
            <button class="btn-copy" onclick="copyText('{razao}', this)">{ICON_COPY} Copiar Nome</button>
            <button class="btn-copy" onclick="copyText('{cnpj_r}', this)">{ICON_COPY} Copiar CNPJ</button>
            <button class="btn-copy" onclick="copyText('{cnpj_f}', this)">{ICON_COPY} Formatado</button>
        </div>
    </div>
    <h2 class="sec-title">Dados de Registro</h2>
    <div class="info-grid">
        <div class="info-box"><label>Razão Social</label><p>{razao}</p></div>
        <div class="info-box"><label>Nome Fantasia</label><p>{d.get('nome_fantasia') or '—'}</p></div>
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
    <div class="info-box" style="margin-bottom:24px;"><label>Atividade Principal</label><p>{cnae_main_display}</p></div>
    <div class="info-box"><label>Atividades Secundárias</label><ul style="list-style:none; padding-top:10px;">{cnaes_sec}</ul></div>
    <h2 class="sec-title">Quadro Societário</h2>
    <div class="info-box"><ul style="list-style:none; padding-top:10px;">{socios_html}</ul></div>
    <div class="partner-section">
        <div style="margin-bottom:2rem; opacity:0.6; font-weight:800; letter-spacing:2px; text-transform:uppercase; font-size:0.75rem;">Sugestão para seu negócio</div>
        <h2 style="font-size:3rem; margin-bottom:1rem;">Hostinger Brasil</h2>
        <p style="font-size:1.2rem; margin-bottom:4rem;">Hospedagem profissional com performance de elite para sua nova empresa.</p>
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
    # Inverte ordem: BrasilAPI e MinhaReceita são mais estáveis para dados completos
    urls = [
        ("BrasilAPI", f"{API_BRASIL}{cnpj}"), 
        ("MinhaReceita", f"{API_MINHA_REC}{cnpj}"),
        ("OpenCNPJ", f"{API_OPENCNPJ}{cnpj}")
    ]
    for name, url in urls:
        try:
            r = requests.get(url, timeout=15, headers={"User-Agent":"BuscaCNPJ-Repair/1.7.1"})
            if r.status_code == 200: 
                data = r.json()
                # Verifica integridade mínima: Razão Social e pelo menos 10 campos
                if data.get("razao_social") or data.get("nome"):
                    if len(data.keys()) > 10:
                        log.info("CNPJ %s capturado via %s", cnpj, name)
                        return data
            if r.status_code == 429: 
                log.warning("Rate limit em %s. Aguardando 2s...", name)
                time.sleep(2)
        except Exception as e:
            pass
        time.sleep(0.5) # Pequeno intervalo entre APIs para não sobrecarregar
    return None

def check_needs_repair(html):
    # Valores suspeitos que indicam falha na captura anterior
    suspicious = [
        '<p>09/06/1994</p>',
        '<p>R$ 0,00</p>',
        'Informação não disponível'
    ]
    if any(x in html for x in suspicious):
        return True

    # Conta ocorrências de <p>—</p>
    placeholders = len(re.findall(r'<p>—</p>', html))
    # Campos críticos que não devem ser —
    missing_critical = any(x in html for x in [
        '<label>Porte</label><p>—</p>',
        '<label>Data de Abertura</label><p>—</p>',
        '<label>Endereço</label><p>—, S/N </p>',
        '— (CNAE —)'
    ])
    return placeholders >= 3 or missing_critical

def repair_folder(folder: Path):
    path = folder / "index.html"
    if not path.exists(): return folder.name, "MISSING"
    
    try:
        html = path.read_text(encoding="utf-8", errors="replace")
        if not check_needs_repair(html):
            return folder.name, "SKIP"
        
        cnpj = folder.name
        log.info("Reparando CNPJ %s...", cnpj)
        data = fetch(cnpj)
        
        if not data:
            return cnpj, "API_FAIL"
        
        # Gera novo HTML v1.7
        new_html = gerar_html_v1_7(data)
        path.write_text(new_html, encoding="utf-8")
        return cnpj, "FIXED"
        
    except Exception as e:
        return folder.name, f"ERROR:{e}"

def main():
    folders = [p for p in CNPJ_DIR.iterdir() if p.is_dir()]
    total = len(folders)
    log.info("Iniciando REPARO VIA API (OpenCNPJ) em %d pastas...", total)
    
    counts = {"FIXED": 0, "SKIP": 0, "API_FAIL": 0, "ERROR": 0, "MISSING": 0}
    
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = {executor.submit(repair_folder, f): f for f in folders}
        for i, fut in enumerate(as_completed(futures), 1):
            cnpj, status = fut.result()
            if status.startswith("ERROR"): counts["ERROR"] += 1
            else: counts[status] += 1
            
            if i % 5000 == 0 or i == total:
                log.info("Progresso: %d/%d | FIXED:%d SKIP:%d FAIL:%d ERR:%d", 
                         i, total, counts["FIXED"], counts["SKIP"], counts["API_FAIL"], counts["ERROR"])
            
    log.info("Finalizado: %s", counts)

if __name__ == "__main__":
    main()
