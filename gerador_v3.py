#!/usr/bin/env python3
"""
gerador_v3.py — BuscaCNPJ.work
Usa uma lista curada e diversificada de CNPJs como seeds,
cobrindo diferentes setores, portes e estados do Brasil.
Nunca incrementa sequencialmente — sempre pula para uma nova empresa diferente.
"""

import requests
import os
import time
import json
import logging
from datetime import datetime

# ─── CONFIGURAÇÕES ────────────────────────────────────────────────────────────
BASE_DIR        = "site-cnpj"
DOMAIN          = "https://buscacnpj.work"
PROGRESS_FILE   = "progresso.json"
MAX_PAGES       = 1000
SLEEP_OK        = 3.5
SLEEP_404       = 0.3
BACKOFF_BASE    = 60
BACKOFF_MAX     = 900

API_BRASIL        = "https://brasilapi.com.br/api/cnpj/v1/"
API_MINHA_RECEITA = "https://minhareceita.org/"

# ─── LISTA CURADA — diversificada por setor, porte e estado ──────────────────
# Apenas CNPJs da sede (matriz) de empresas conhecidas e variadas.
# Nunca incrementar sequencialmente a partir daqui.
SEED_CNPJS = [
    # ── GRANDES EMPRESAS NACIONAIS ──────────────────────────────────────
    "33000167000101",  # Petrobras (matriz)
    "33592510000154",  # Eletrobras
    "00000000000191",  # Banco do Brasil
    "00360305000104",  # Caixa Econômica Federal
    "06066228000121",  # Itaú Unibanco
    "64170450000105",  # Bradesco
    "19131243000197",  # Ambev
    "02429144000193",  # Embraer
    "05486851000115",  # Nubank
    "33683111000280",  # SERPRO
    "33372251000101",  # Vale S.A.
    "60746948000112",  # Banco Santander Brasil
    "90400888000142",  # Banrisul
    "76535764000143",  # Copel
    "07526557000100",  # OI S.A.
    "02012862000160",  # Claro Brasil
    "04206050000180",  # TIM S.A.
    "33530486000129",  # Vivo / Telefônica
    "33200056000147",  # Correios
    "00394460000141",  # BNDES

    # ── VAREJO E CONSUMO ─────────────────────────────────────────────────
    "47508411000156",  # Magazine Luiza
    "03235738000190",  # Via Varejo (Casas Bahia)
    "33041260065290",  # Americanas
    "07195279000102",  # Lojas Renner
    "61412110000116",  # Centauro
    "33009911000125",  # Grupo Pão de Açúcar (GPA)
    "75315333000109",  # Atacadão (Carrefour)
    "45543915000155",  # Grupo Mateus
    "06057223000171",  # Raia Drogasil
    "53113791000122",  # Drogasil

    # ── SAÚDE ────────────────────────────────────────────────────────────
    "29978814000106",  # Hapvida
    "61486891000100",  # Unimed Paulistana
    "44649812000138",  # Amil
    "00394586000145",  # Fiocruz
    "63025530000104",  # Albert Einstein

    # ── AGRONEGÓCIO ──────────────────────────────────────────────────────
    "81223973000100",  # JBS
    "02916265000160",  # BRF S.A.
    "03853896000140",  # Marfrig
    "04922555000100",  # Minerva Foods
    "28954059000136",  # Cargill
    "61065298000191",  # Raízen (Cosan + Shell)
    "07526557000100",  # SLC Agrícola
    "92887505000120",  # Tramontina

    # ── CONSTRUÇÃO E INFRAESTRUTURA ──────────────────────────────────────
    "60840055000131",  # MRV Engenharia
    "17327099000106",  # Cyrela
    "02351144000188",  # EZTec
    "06948969000175",  # CCDI (Camargo Corrêa)
    "33256439000139",  # Odebrecht (Novonor)
    "15616212000137",  # Tegma

    # ── TECNOLOGIA E STARTUPS ────────────────────────────────────────────
    "07882978000100",  # TOTVS
    "01149953000175",  # Stefanini
    "02386257000168",  # CI&T
    "04862600000108",  # Locaweb
    "28122765000198",  # Linx
    "07613619000111",  # Movile (iFood)
    "14380200000121",  # QuintoAndar
    "18970291000100",  # Creditas
    "32402502000135",  # Loft
    "21435900000114",  # Loggi

    # ── EDUCAÇÃO ─────────────────────────────────────────────────────────
    "03179837000182",  # Kroton (Cogna)
    "40268892000178",  # Anhanguera
    "09529699000175",  # Estácio
    "62173620000180",  # Uninove
    "00796865000102",  # USP (Universidade de São Paulo)
    "03657916000137",  # PUCRS

    # ── ENERGIA ──────────────────────────────────────────────────────────
    "00108786000165",  # EDP Brasil
    "02429144000193",  # ENEL Brasil
    "04821041000108",  # Eneva
    "02351137000177",  # Energisa
    "08631541000100",  # Omega Energia
    "04902979000144",  # Cemig

    # ── LOGÍSTICA E TRANSPORTE ───────────────────────────────────────────
    "02543816000100",  # JSL Logística
    "07945686000169",  # Movida
    "09054714000186",  # Localiza
    "06164220000157",  # Unidas
    "11760553000100",  # Total Express
    "07752121000170",  # Sequoia Logística

    # ── FINANCEIRO E FINTECHS ────────────────────────────────────────────
    "30723886000162",  # Inter (Banco Inter)
    "18236120000158",  # C6 Bank
    "13140088000199",  # PicPay
    "17772370000180",  # XP Inc.
    "02332886000104",  # BTG Pactual
    "31872495000172",  # Mercado Pago Brasil
    "10573521000191",  # Stone
    "16501555000157",  # Cielo

    # ── MÍDIA E ENTRETENIMENTO ───────────────────────────────────────────
    "27865757000102",  # Globo Comunicações
    "83474922000170",  # RBS (Grupo)
    "61855045000205",  # SBT
    "33317876000100",  # Band
    "04671413000181",  # Spotify Brasil
    "08808841000180",  # Netflix Brasil

    # ── PEQUENAS E MÉDIAS EMPRESAS — variadas ────────────────────────────
    "11222862000173",  # Exemplo PME SP
    "08902091000180",  # Exemplo PME RJ
    "10599357000150",  # Exemplo PME MG
    "12345678000195",  # Exemplo PME RS
    "09502335000100",  # Exemplo PME BA
    "11308488000194",  # Exemplo PME PR
    "14616875000130",  # Exemplo PME CE
    "15436448000180",  # Exemplo PME PE
    "16571664000180",  # Exemplo PME GO
    "17213052000130",  # Exemplo PME SC
    "18112719000130",  # Exemplo PME DF
    "19397706000130",  # Exemplo PME AM
    "20359890000189",  # Exemplo PME PA
    "21021983000170",  # Exemplo PME MT
    "22151510000130",  # Exemplo PME ES
    "23337501000130",  # Exemplo PME RN
    "24338822000152",  # Exemplo PME PB
    "25189562000109",  # Exemplo PME PI
    "26180908000100",  # Exemplo PME MA
    "27147482000188",  # Exemplo PME RO
]

# ─── LOGGING ──────────────────────────────────────────────────────────────────
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

# ─── VALIDADOR ────────────────────────────────────────────────────────────────
def validar_cnpj(cnpj: str) -> bool:
    cnpj = "".join(d for d in cnpj if d.isdigit())
    if len(cnpj) != 14 or len(set(cnpj)) == 1:
        return False
    def dig(nums, pesos):
        s = sum(a * b for a, b in zip(nums, pesos)) % 11
        return 0 if s < 2 else 11 - s
    n = [int(x) for x in cnpj]
    return n[12] == dig(n[:12], [5,4,3,2,9,8,7,6,5,4,3,2]) and            n[13] == dig(n[:13], [6,5,4,3,2,9,8,7,6,5,4,3,2])

# ─── PROGRESSO ────────────────────────────────────────────────────────────────
def load_progress() -> dict:
    if os.path.exists(PROGRESS_FILE):
        try:
            with open(PROGRESS_FILE, "r") as f:
                return json.load(f)
        except Exception:
            pass
    return {"processed": [], "index_links": []}

def save_progress(processed, index_links):
    with open(PROGRESS_FILE, "w") as f:
        json.dump({"processed": processed, "index_links": index_links}, f)

# ─── FORMATADORES ─────────────────────────────────────────────────────────────
def fmt_cnpj(cnpj: str) -> str:
    c = cnpj.zfill(14)
    return f"{c[:2]}.{c[2:5]}.{c[5:8]}/{c[8:12]}-{c[12:]}"

def fmt_brl(valor) -> str:
    try:
        v = float(valor)
        return f"R$\u00a0{v:,.2f}".replace(",","X").replace(".",",").replace("X",".")
    except Exception:
        return "R$\u00a00,00"

def fmt_date(d: str) -> str:
    try:
        return datetime.strptime(d, "%Y-%m-%d").strftime("%d/%m/%Y")
    except Exception:
        return d or "—"

# ─── NORMALIZAÇÃO ─────────────────────────────────────────────────────────────
def norm(data: dict) -> dict:
    cnpj = "".join(x for x in data.get("cnpj","") if x.isdigit())
    return {
        "cnpj":              cnpj,
        "razao_social":      data.get("razao_social") or data.get("razão_social") or "N/A",
        "nome_fantasia":     data.get("nome_fantasia") or data.get("nome_comercial") or "",
        "situacao":          (data.get("descricao_situacao_cadastral")
                              or data.get("descrição_situação_cadastral") or "N/A"),
        "data_abertura":     fmt_date(data.get("data_inicio_atividade","")),
        "porte":             data.get("porte") or "—",
        "natureza_juridica": data.get("natureza_juridica") or "—",
        "capital_social":    fmt_brl(data.get("capital_social", 0)),
        "email":             data.get("email") or "",
        "telefone":          data.get("ddd_telefone_1") or "",
        "logradouro":        data.get("logradouro") or "—",
        "numero":            data.get("numero") or "S/N",
        "complemento":       data.get("complemento") or "",
        "bairro":            data.get("bairro") or "—",
        "municipio":         data.get("municipio") or data.get("município") or "—",
        "uf":                data.get("uf") or "—",
        "cep":               data.get("cep") or "—",
        "cnae_principal":    (data.get("cnae_fiscal_descricao")
                              or data.get("cnae_fiscal_descrição") or "—"),
        "cnae_codigo":       str(data.get("cnae_fiscal","") or ""),
        "cnaes_secundarios": data.get("cnaes_secundarios", []),
        "qsa":               data.get("qsa", []),
    }

# ─── CSS ──────────────────────────────────────────────────────────────────────
CSS = """
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --text:#333; --muted:#888; --border:#ebebeb; --bg:#fff; --dark:#1a1a1a; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
         Helvetica, Arial, sans-serif; line-height: 1.7; color: var(--text);
         background: var(--bg); font-size: 1rem; }
  header { padding: 14px 24px; border-bottom: 1px solid var(--border);
           position: sticky; top: 0; background: rgba(255,255,255,0.97);
           backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 999; }
  .header-inner { max-width: 1100px; margin: 0 auto;
                  display: flex; align-items: center; gap: 24px; }
  .logo { font-weight: 800; font-size: 1rem; text-decoration: none;
          color: var(--dark); letter-spacing: -0.3px; }
  header nav { display: flex; gap: 22px; align-items: center; flex: 1; }
  header nav a { text-decoration: none; color: #555; font-size: 0.95rem; transition: color 0.15s; }
  header nav a:hover { color: var(--dark); }
  .container { max-width: 800px; margin: 0 auto; padding: 40px 20px 60px; }
  .breadcrumb { font-size: 0.82rem; color: var(--muted); margin-bottom: 24px; }
  .breadcrumb a { color: var(--muted); text-decoration: none; }
  .breadcrumb a:hover { color: var(--dark); text-decoration: underline; }
  .breadcrumb span { margin: 0 6px; }
  h1 { font-size: 1.9rem; color: #111; line-height: 1.25; font-weight: 800;
       letter-spacing: -0.5px; margin-bottom: 8px; }
  .cnpj-fmt { font-size: 0.95rem; color: var(--muted); margin-bottom: 20px; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 20px;
           font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;
           text-transform: uppercase; margin-bottom: 32px; border: 1px solid transparent; }
  .badge-ativa   { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
  .badge-baixada { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
  .badge-outros  { background: #fffbeb; color: #92400e; border-color: #fde68a; }
  .section { margin-bottom: 36px; }
  h2 { font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
       letter-spacing: 1px; color: var(--muted); border-bottom: 1px solid var(--border);
       padding-bottom: 8px; margin-bottom: 20px; }
  .fields { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 32px; }
  @media (max-width: 600px) { .fields { grid-template-columns: 1fr; } }
  .field label { display: block; font-size: 0.72rem; font-weight: 700;
                 text-transform: uppercase; letter-spacing: 0.5px;
                 color: var(--muted); margin-bottom: 3px; }
  .field p { font-size: 0.97rem; color: #222; }
  ul.data-list { list-style: none; }
  ul.data-list li { padding: 12px 0; border-bottom: 1px solid var(--border);
                    font-size: 0.95rem; color: #333; }
  ul.data-list li:last-child { border-bottom: none; }
  ul.data-list li strong { display: block; color: #111; font-weight: 600; }
  ul.data-list li span { color: var(--muted); font-size: 0.85rem; }
  .hero { padding: 64px 24px; text-align: center;
          border-bottom: 1px solid var(--border); margin-bottom: 48px; }
  .hero h1 { font-size: 2.2rem; margin-bottom: 10px; }
  .hero p  { color: var(--muted); font-size: 1.05rem; margin-bottom: 28px; }
  .search-row { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
  .search-row input { padding: 12px 16px; font-size: 1rem; border: 1px solid var(--border);
                      border-radius: 6px; width: 320px; max-width: 100%;
                      outline: none; font-family: inherit; color: #111; }
  .search-row input:focus { border-color: #aaa; }
  .search-row button { padding: 12px 22px; background: var(--dark); color: #fff;
                       font-size: 0.95rem; font-weight: 600; border: none;
                       border-radius: 6px; cursor: pointer; font-family: inherit; }
  .search-row button:hover { background: #333; }
  .grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr));
                gap: 16px; margin-top: 16px; }
  .card-link { display: block; padding: 16px; border: 1px solid var(--border);
               border-radius: 8px; text-decoration: none; color: var(--dark);
               transition: border-color 0.15s; }
  .card-link:hover { border-color: #aaa; }
  .card-link strong { display: block; font-size: 0.92rem; margin-bottom: 4px; }
  .card-link span { font-size: 0.78rem; color: var(--muted); }
  hr { border: none; border-top: 1px solid var(--border); margin: 32px 0; }
  footer { border-top: 1px solid var(--border); padding: 32px 20px;
           text-align: center; color: var(--muted); font-size: 0.85rem; }
  footer a { color: var(--muted); text-decoration: none; }
  footer a:hover { color: var(--dark); text-decoration: underline; }
  footer nav { display: flex; justify-content: center; gap: 20px;
               margin-bottom: 12px; flex-wrap: wrap; }
  a { color: var(--dark); text-decoration: underline;
      text-underline-offset: 3px; text-decoration-color: #ccc; }
  a:hover { text-decoration-color: var(--dark); }
  @media (prefers-color-scheme: dark) {
    :root { --text:#e8e8e8; --muted:#777; --border:#2f2f2f; --bg:#191919; --dark:#f0f0f0; }
    header { background: rgba(25,25,25,0.97) !important; }
    h1, .hero h1 { color: #f0f0f0; } .field p { color: #c9c9c9; }
    ul.data-list li { color: #c9c9c9; } ul.data-list li strong { color: #f0f0f0; }
    .search-row input { background: #2a2a2a; color: #e8e8e8; border-color: #3a3a3a; }
    .badge-ativa   { background: #052e16; color: #86efac; border-color: #166534; }
    .badge-baixada { background: #450a0a; color: #fca5a5; border-color: #991b1b; }
    .badge-outros  { background: #451a03; color: #fcd34d; border-color: #92400e; }
    .search-row button { background: #e8e8e8; color: #111; }
    .card-link { background: #222; }
  }
</style>
"""

# ─── SCHEMA.ORG ───────────────────────────────────────────────────────────────
def schema_org(d: dict) -> str:
    nome = d["nome_fantasia"] if d["nome_fantasia"] else d["razao_social"]
    obj = {
        "@context": "https://schema.org", "@type": "Organization",
        "name": nome, "legalName": d["razao_social"],
        "taxID": fmt_cnpj(d["cnpj"]), "foundingDate": d["data_abertura"],
        "address": {
            "@type": "PostalAddress",
            "streetAddress": f"{d['logradouro']}, {d['numero']}",
            "addressLocality": d["municipio"], "addressRegion": d["uf"],
            "postalCode": d["cep"], "addressCountry": "BR"
        },
        "url": f"{DOMAIN}/cnpj/{d['cnpj']}/"
    }
    if d["email"]:    obj["email"]     = d["email"]
    if d["telefone"]: obj["telephone"] = d["telefone"]
    return f'<script type="application/ld+json">{json.dumps(obj, ensure_ascii=False)}</script>'

# ─── HTML DA EMPRESA ──────────────────────────────────────────────────────────
def gerar_html_empresa(data: dict) -> str:
    d      = norm(data)
    nome   = d["nome_fantasia"] if d["nome_fantasia"] else d["razao_social"]
    cnpj_f = fmt_cnpj(d["cnpj"])
    sit    = d["situacao"].upper()
    if "ATIVA" in sit:
        badge_cls, badge_txt = "badge-ativa",   "Ativa"
    elif any(x in sit for x in ("BAIXADA","INAPTA","CANCELADA")):
        badge_cls, badge_txt = "badge-baixada", d["situacao"].title()
    else:
        badge_cls, badge_txt = "badge-outros",  d["situacao"].title()

    socios = "".join([
        f'<li><strong>{s.get("nome_socio","—")}</strong>'
        f'<span>{s.get("qualificacao_socio","Sócio")}'
        f'{(" · desde " + fmt_date(s.get("data_entrada_sociedade",""))) if s.get("data_entrada_sociedade") else ""}'
        f'</span></li>'
        for s in d["qsa"]
    ]) or "<li><span>Informação não disponível</span></li>"

    cnaes_sec = "".join([
        f'<li><strong>{c.get("codigo","")}</strong> — {c.get("descricao","")}</li>'
        for c in d["cnaes_secundarios"]
    ]) or "<li><span>Sem atividades secundárias</span></li>"

    contato_bloco = ""
    if d["telefone"] or d["email"]:
        flds = ""
        if d["telefone"]: flds += f'<div class="field"><label>Telefone</label><p>{d["telefone"]}</p></div>'
        if d["email"]:    flds += f'<div class="field"><label>E-mail</label><p>{d["email"]}</p></div>'
        contato_bloco = f'<div class="section"><h2>Contato</h2><div class="fields">{flds}</div></div><hr>'

    title = f"{nome} — CNPJ {cnpj_f} | BuscaCNPJ.work"
    desc  = (f"Consulta CNPJ {cnpj_f}: {d['razao_social']}. "
             f"Situação {d['situacao']}, localizada em {d['municipio']}/{d['uf']}. "
             f"Dados públicos da Receita Federal.")

    return f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{title}</title>
  <meta name="description" content="{desc}">
  <link rel="canonical" href="{DOMAIN}/cnpj/{d['cnpj']}/">
  <meta property="og:title" content="{title}">
  <meta property="og:description" content="{desc}">
  <meta property="og:type" content="website">
  <meta property="og:url" content="{DOMAIN}/cnpj/{d['cnpj']}/">
  {schema_org(d)}
  {CSS}
</head>
<body>
<header><div class="header-inner">
  <a class="logo" href="{DOMAIN}">buscacnpj.work</a>
  <nav><a href="{DOMAIN}">consultar cnpj</a></nav>
</div></header>
<div class="container">
  <div class="breadcrumb">
    <a href="{DOMAIN}">início</a><span>/</span>
    <a href="{DOMAIN}/cnpj/">cnpj</a><span>/</span>{cnpj_f}
  </div>
  <h1>{nome}</h1>
  <p class="cnpj-fmt">CNPJ {cnpj_f}</p>
  <span class="badge {badge_cls}">{badge_txt}</span>
  <div class="section"><h2>Informações de Registro</h2>
    <div class="fields">
      <div class="field"><label>Razão Social</label><p>{d["razao_social"]}</p></div>
      <div class="field"><label>Nome Fantasia</label><p>{d["nome_fantasia"] or "—"}</p></div>
      <div class="field"><label>Data de Abertura</label><p>{d["data_abertura"]}</p></div>
      <div class="field"><label>Situação Cadastral</label><p>{d["situacao"]}</p></div>
      <div class="field"><label>Porte</label><p>{d["porte"]}</p></div>
      <div class="field"><label>Natureza Jurídica</label><p>{d["natureza_juridica"]}</p></div>
      <div class="field"><label>Capital Social</label><p>{d["capital_social"]}</p></div>
      <div class="field"><label>CNPJ</label><p>{cnpj_f}</p></div>
    </div>
  </div>
  <hr>
  <div class="section"><h2>Localização</h2>
    <div class="fields">
      <div class="field"><label>Logradouro</label>
        <p>{d["logradouro"]}, {d["numero"]}{(" — " + d["complemento"]) if d["complemento"] else ""}</p>
      </div>
      <div class="field"><label>Bairro</label><p>{d["bairro"]}</p></div>
      <div class="field"><label>Município</label><p>{d["municipio"]} — {d["uf"]}</p></div>
      <div class="field"><label>CEP</label><p>{d["cep"]}</p></div>
    </div>
  </div>
  <hr>
  {contato_bloco}
  <div class="section"><h2>Atividade Principal</h2>
    <ul class="data-list">
      <li><strong>{d["cnae_principal"]}</strong>
      {("<span>CNAE " + d["cnae_codigo"] + "</span>") if d["cnae_codigo"] else ""}</li>
    </ul>
  </div>
  <div class="section"><h2>Atividades Secundárias</h2>
    <ul class="data-list">{cnaes_sec}</ul>
  </div>
  <hr>
  <div class="section"><h2>Quadro de Sócios e Administradores</h2>
    <ul class="data-list">{socios}</ul>
  </div>
</div>
<footer>
  <nav>
    <a href="{DOMAIN}/">Início</a>
    <a href="{DOMAIN}/sobre/">Sobre</a>
    <a href="{DOMAIN}/privacidade/">Privacidade</a>
    <a href="{DOMAIN}/contato/">Contato</a>
  </nav>
  <p>© 2026 <a href="{DOMAIN}">BuscaCNPJ.work</a> — Dados públicos da Receita Federal do Brasil.</p>
</footer>
</body></html>"""

# ─── INDEX ────────────────────────────────────────────────────────────────────
def gerar_index(index_links: list, total: int):
    cards = "".join([
        f'<a class="card-link" href="cnpj/{c}/"><strong>{n[:38]+("…" if len(n)>38 else "")}</strong>'
        f'<span>{fmt_cnpj(c)}</span></a>'
        for c, n in index_links
    ])
    schema = json.dumps({
        "@context": "https://schema.org", "@type": "WebSite",
        "name": "BuscaCNPJ.work", "url": DOMAIN,
        "description": "Consulta gratuita de CNPJ de empresas brasileiras",
        "potentialAction": {"@type": "SearchAction",
            "target": f"{DOMAIN}/cnpj/{{cnpj}}/", "query-input": "required name=cnpj"}
    }, ensure_ascii=False)
    html = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BuscaCNPJ.work — Consulta Gratuita de CNPJ</title>
  <meta name="description" content="Consulte dados públicos de qualquer empresa brasileira pelo CNPJ. Situação cadastral, endereço, sócios e atividades direto da Receita Federal. Gratuito.">
  <link rel="canonical" href="{DOMAIN}/">
  <script type="application/ld+json">{schema}</script>
  {CSS}
</head>
<body>
<header><div class="header-inner">
  <a class="logo" href="{DOMAIN}">buscacnpj.work</a>
  <nav><a href="{DOMAIN}/sobre/">sobre</a></nav>
</div></header>
<div class="hero">
  <h1>Consulta de CNPJ</h1>
  <p>Dados públicos de empresas brasileiras direto da Receita Federal.</p>
  <div class="search-row">
    <input type="text" id="cnpjInput" maxlength="18"
           placeholder="00.000.000/0000-00" autocomplete="off">
    <button onclick="buscar()">Consultar</button>
  </div>
  <p style="font-size:0.8rem;color:#aaa;margin-top:12px">Digite apenas os números ou o CNPJ formatado</p>
</div>
<div class="container" style="padding-top:0">
  <div class="section">
    <h2>Empresas cadastradas ({total:,})</h2>
    <div class="grid-cards">{cards}</div>
  </div>
</div>
<footer>
  <nav>
    <a href="{DOMAIN}/sobre/">Sobre</a>
    <a href="{DOMAIN}/privacidade/">Privacidade</a>
    <a href="{DOMAIN}/contato/">Contato</a>
  </nav>
  <p>© 2026 <a href="{DOMAIN}">BuscaCNPJ.work</a> — Dados públicos da Receita Federal do Brasil.</p>
</footer>
<script>
  function buscar() {{
    var raw = document.getElementById('cnpjInput').value.replace(/\D/g,'');
    if (raw.length === 14) {{ window.location.href = './cnpj/' + raw + '/'; }}
    else {{ alert('Digite um CNPJ válido com 14 dígitos.'); }}
  }}
  document.getElementById('cnpjInput').addEventListener('keydown',function(e){{if(e.key==='Enter')buscar();}});
</script>
</body></html>"""
    with open(f"{BASE_DIR}/index.html", "w", encoding="utf-8") as f:
        f.write(html)
    log.info("index.html gerado.")

# ─── SITEMAP ──────────────────────────────────────────────────────────────────
def gerar_sitemap(processed: list):
    today = datetime.now().strftime("%Y-%m-%d")
    fixas = [
        (f"{DOMAIN}/",             "daily",   "1.0"),
        (f"{DOMAIN}/sobre/",       "monthly", "0.5"),
        (f"{DOMAIN}/privacidade/", "yearly",  "0.3"),
        (f"{DOMAIN}/contato/",     "monthly", "0.4"),
    ]
    urls = ""
    for loc, freq, pri in fixas:
        urls += f"  <url><loc>{loc}</loc><changefreq>{freq}</changefreq><priority>{pri}</priority></url>\n"
    for c in processed:
        urls += (f"  <url><loc>{DOMAIN}/cnpj/{c}/</loc>"
                 f"<lastmod>{today}</lastmod><changefreq>monthly</changefreq>"
                 f"<priority>0.8</priority></url>\n")
    xml = f'<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n{urls}</urlset>'
    with open(f"{BASE_DIR}/sitemap.xml", "w", encoding="utf-8") as f:
        f.write(xml)
    log.info("sitemap.xml atualizado — %d URLs.", len(processed) + 4)

# ─── FETCH ────────────────────────────────────────────────────────────────────
def fetch_cnpj(cnpj: str, brasil_ativa: bool):
    if brasil_ativa:
        try:
            r = requests.get(f"{API_BRASIL}{cnpj}", timeout=10,
                             headers={"User-Agent": "BuscaCNPJ-Bot/1.0"})
            if r.status_code == 200:  return r.json(), True, False
            if r.status_code == 404:  return None, True, False
            if r.status_code == 429:  log.warning("BrasilAPI 429 → Minha Receita…")
        except Exception as e:
            log.debug("BrasilAPI: %s", e)
    try:
        r2 = requests.get(f"{API_MINHA_RECEITA}{cnpj}", timeout=10,
                          headers={"User-Agent": "BuscaCNPJ-Bot/1.0"})
        if r2.status_code == 200:  return r2.json(), brasil_ativa, False
        if r2.status_code == 404:  return None, brasil_ativa, False
        if r2.status_code == 429:
            log.warning("Minha Receita 429 também. Backoff.")
            return None, brasil_ativa, True
    except Exception as e:
        log.debug("Minha Receita: %s", e)
    return None, brasil_ativa, False

# ─── MAIN ─────────────────────────────────────────────────────────────────────
def main():
    os.makedirs(f"{BASE_DIR}/cnpj", exist_ok=True)

    prog        = load_progress()
    processed   = prog["processed"]
    index_links = prog.get("index_links", [])

    log.info("=" * 60)
    log.info("BuscaCNPJ.work — Gerador v3 (lista curada)")
    log.info("Já geradas: %d / %d", len(processed), MAX_PAGES)
    log.info("Seeds disponíveis: %d CNPJs únicos", len(SEED_CNPJS))
    log.info("=" * 60)

    if len(processed) >= MAX_PAGES:
        log.info("Meta atingida. Aumente MAX_PAGES para continuar.")
        gerar_index(index_links, len(processed))
        gerar_sitemap(processed)
        return

    brasil_ativa = True
    backoff_time = BACKOFF_BASE

    for cnpj in SEED_CNPJS:
        if len(processed) >= MAX_PAGES:
            break
        if not validar_cnpj(cnpj):
            log.debug("CNPJ inválido ignorado: %s", cnpj)
            continue
        if cnpj in processed:
            log.debug("Já processado: %s", cnpj)
            continue

        data, brasil_ativa, need_backoff = fetch_cnpj(cnpj, brasil_ativa)

        if need_backoff:
            log.warning("Backoff de %ds…", backoff_time)
            time.sleep(backoff_time)
            backoff_time = min(backoff_time * 2, BACKOFF_MAX)
            # Tenta o mesmo CNPJ novamente
            data, brasil_ativa, need_backoff = fetch_cnpj(cnpj, brasil_ativa)
            if not data:
                continue

        if data is None:
            log.info("  ⚠️  Não encontrado: %s", cnpj)
            time.sleep(SLEEP_404)
            continue

        try:
            d    = norm(data)
            html = gerar_html_empresa(data)
            path = f"{BASE_DIR}/cnpj/{d['cnpj']}"
            os.makedirs(path, exist_ok=True)
            with open(f"{path}/index.html", "w", encoding="utf-8") as f:
                f.write(html)

            processed.append(d["cnpj"])
            if len(index_links) < 200:
                index_links.append((d["cnpj"], d["nome_fantasia"] or d["razao_social"]))

            save_progress(processed, index_links)
            backoff_time = BACKOFF_BASE
            log.info("[%d/%d] ✅  %s — %s — %s/%s",
                     len(processed), MAX_PAGES, d["cnpj"],
                     (d["nome_fantasia"] or d["razao_social"])[:40],
                     d["municipio"], d["uf"])
            time.sleep(SLEEP_OK)

        except Exception as e:
            log.error("Erro ao gerar %s: %s", cnpj, e)

    gerar_index(index_links, len(processed))
    gerar_sitemap(processed)

    total_bytes = sum(
        os.path.getsize(os.path.join(r, f))
        for r, _, files in os.walk(BASE_DIR) for f in files
    )
    log.info("=" * 60)
    log.info("✅  CONCLUÍDO!")
    log.info("Páginas geradas : %d", len(processed))
    log.info("Tamanho do site : %.2f MB", total_bytes / 1_048_576)
    log.info("Próximo passo   : git add . && git commit -m 'Update' && git push")
    log.info("=" * 60)


if __name__ == "__main__":
    main()
