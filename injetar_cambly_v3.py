#!/usr/bin/env python3
"""
injetar_cambly_v3.py — BuscaCNPJ.work
Remove qualquer injeção anterior e insere banners Cambly corretamente.

Posições testadas e confirmadas:
  1. Após o título "Atividade Principal" (depois de Localização, antes dos CNAEs)
  2. Inline antes do <footer>
"""

import re
import random
import logging
from pathlib import Path

# ── Config ────────────────────────────────────────────────────────────────────
CNPJ_DIR      = Path(r"C:\Users\marti\Documents\Projetos\buscacnpjwork\cnpj")
AFFILIATE_URL = "https://www.cambly.com/invite/VINICIUSCODES?st=030526&sc=4"
LOGO_PATH     = "/cambly-logo.png"

# Âncoras exatas (verificadas nos arquivos reais)
ANCHOR_MID    = '</div></div><hr>\r\n\r\n<div class="s"><h2>Atividade Principal</h2>'
ANCHOR_FOOTER = '<footer>'

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%H:%M:%S",
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler("injetar_cambly_v3.log", encoding="utf-8")
    ]
)
log = logging.getLogger(__name__)

# ── CSS ───────────────────────────────────────────────────────────────────────
CSS = (
'\n<style id="cbly">'
'.cbly-wrap{margin:20px 0}'
'.cbly-h{background:#FFCB3C;border-radius:14px;padding:18px 24px;display:flex;'
'align-items:center;gap:18px;box-shadow:0 4px 20px rgba(255,180,0,.2);'
'position:relative;overflow:hidden;text-decoration:none}'
'.cbly-h::after{content:"";position:absolute;right:-44px;top:-44px;'
'width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.13);pointer-events:none}'
'.cbly-h img{height:18px;filter:brightness(0);flex-shrink:0}'
'.cbly-h .dv{width:1px;height:36px;background:rgba(0,0,0,.13);flex-shrink:0}'
'.cbly-h .cp{flex:1;min-width:0}'
'.cbly-h .cp strong{display:block;font-size:.88rem;font-weight:800;color:#1a1a1a;margin-bottom:1px}'
'.cbly-h .cp span{font-size:.76rem;color:#5a4200}'
'.cbly-h .pr{flex-shrink:0;text-align:center;line-height:1}'
'.cbly-h .pr s{font-size:.62rem;color:#9a7a00;display:block}'
'.cbly-h .pr big{font-size:1.55rem;font-weight:900;color:#1a1a1a;letter-spacing:-.5px}'
'.cbly-h .pr big sup{font-size:.6rem;vertical-align:top;margin-top:3px}'
'.cbly-h .pr big sub{font-size:.6rem;font-weight:500;color:#7a6000}'
'.cbly-h .bt{flex-shrink:0;background:#1a1a1a;color:#FFCB3C;font-weight:800;'
'font-size:.78rem;text-decoration:none;padding:10px 16px;border-radius:50px;'
'white-space:nowrap;position:relative;z-index:1;transition:transform .15s}'
'.cbly-h .bt:hover{transform:translateY(-2px)}'
'.cbly-i{background:#fff;border:1.5px solid #FFCB3C;border-radius:10px;'
'padding:13px 16px;display:flex;align-items:center;gap:13px;'
'box-shadow:0 2px 8px rgba(255,180,0,.1);text-decoration:none}'
'.cbly-i img{height:15px;flex-shrink:0}'
'.cbly-i .cp{flex:1;min-width:0}'
'.cbly-i .cp strong{font-size:.83rem;font-weight:700;color:#1a1a1a;display:block;margin-bottom:1px}'
'.cbly-i .cp span{font-size:.72rem;color:#888}'
'.cbly-i .pr{flex-shrink:0;text-align:center;line-height:1}'
'.cbly-i .pr s{font-size:.58rem;color:#ccc;display:block}'
'.cbly-i .pr big{font-size:1.05rem;font-weight:900;color:#1a1a1a}'
'.cbly-i .pr big sup{font-size:.5rem;vertical-align:top;margin-top:2px}'
'.cbly-i .pr big sub{font-size:.54rem;color:#888;font-weight:500}'
'.cbly-i .bt{flex-shrink:0;border:2px solid #1a1a1a;color:#1a1a1a;font-weight:700;'
'font-size:.75rem;text-decoration:none;padding:7px 13px;border-radius:7px;'
'white-space:nowrap;transition:all .2s}'
'.cbly-i .bt:hover{background:#1a1a1a;color:#FFCB3C}'
'.cbly-v{background:#FFCB3C;border-radius:14px;padding:22px 18px;text-align:center;'
'box-shadow:0 6px 24px rgba(255,180,0,.22);position:relative;overflow:hidden}'
'.cbly-v::before{content:"";position:absolute;top:-35px;right:-35px;'
'width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.14);pointer-events:none}'
'.cbly-v img{height:17px;filter:brightness(0);margin-bottom:8px;position:relative;z-index:1;display:block;margin-left:auto;margin-right:auto}'
'.cbly-v strong{display:block;font-size:1rem;font-weight:800;color:#1a1a1a;'
'margin-bottom:4px;line-height:1.25;position:relative;z-index:1}'
'.cbly-v span{display:block;font-size:.76rem;color:#5a4200;margin-bottom:12px;position:relative;z-index:1}'
'.cbly-v .pv{background:#fff;border-radius:9px;padding:10px 14px;margin-bottom:13px;position:relative;z-index:1}'
'.cbly-v .pv s{font-size:.65rem;color:#bbb;display:block}'
'.cbly-v .pv big{font-size:1.75rem;font-weight:900;color:#1a1a1a;line-height:1;letter-spacing:-1px}'
'.cbly-v .pv big sup{font-size:.72rem;vertical-align:top;margin-top:3px}'
'.cbly-v .pv big sub{font-size:.68rem;font-weight:500;color:#888}'
'.cbly-v .pv small{display:block;font-size:.6rem;color:#aaa;margin-top:2px}'
'.cbly-v .bt{display:block;background:#1a1a1a;color:#FFCB3C;font-weight:800;'
'font-size:.8rem;text-decoration:none;padding:11px 16px;border-radius:50px;'
'position:relative;z-index:1;transition:transform .15s}'
'.cbly-v .bt:hover{transform:translateY(-2px)}'
'.cbly-v em{display:block;font-size:.64rem;color:#7a6000;margin-top:8px;'
'font-style:normal;position:relative;z-index:1}'
'@media(max-width:600px){'
'.cbly-h{flex-direction:column;text-align:center;padding:16px 14px;gap:10px}'
'.cbly-h .dv{width:100%;height:1px}'
'.cbly-i{flex-direction:column;text-align:center}'
'}'
'@media(prefers-color-scheme:dark){'
'.cbly-i{background:#1e1e1e}'
'.cbly-i .cp strong,.cbly-i .pr big{color:#f0f0f0}'
'.cbly-i .bt{border-color:#FFCB3C;color:#FFCB3C}'
'.cbly-i .bt:hover{background:#FFCB3C;color:#1a1a1a}'
'}'
'</style>'
)

# ── Banners HTML ──────────────────────────────────────────────────────────────
def b_horizontal(url, logo):
    return (
        '<div class="cbly-wrap">'
        f'<a class="cbly-h" href="{url}" target="_blank" rel="noopener sponsored">'
        f'<img src="{logo}" alt="Cambly">'
        '<div class="dv"></div>'
        '<div class="cp">'
        '<strong>Aprenda ingl\u00eas com tutores 100% nativos</strong>'
        '<span>Conversas reais \u00b7 Todos os n\u00edveis \u00b7 24h por dia, 7 dias por semana</span>'
        '</div>'
        '<div class="pr">'
        '<s>R$93/m\u00eas</s>'
        '<big><sup>R$</sup>52<sub>/m\u00eas</sub></big>'
        '</div>'
        '<span class="bt">Come\u00e7ar agora \u2192</span>'
        '</a>'
        '</div>'
    )

def b_inline(url, logo):
    return (
        '<div class="cbly-wrap">'
        f'<a class="cbly-i" href="{url}" target="_blank" rel="noopener sponsored">'
        f'<img src="{logo}" alt="Cambly">'
        '<div class="cp">'
        '<strong>Aprenda ingl\u00eas com o Cambly \u2014 tutores nativos 24/7</strong>'
        '<span>Conversas reais \u00b7 A partir de R$52/m\u00eas \u00b7 Cancele quando quiser</span>'
        '</div>'
        '<div class="pr">'
        '<s>R$93</s>'
        '<big><sup>R$</sup>52<sub>/m\u00eas</sub></big>'
        '</div>'
        '<span class="bt">Ver planos</span>'
        '</a>'
        '</div>'
    )

def b_card(url, logo):
    return (
        '<div class="cbly-wrap">'
        '<div class="cbly-v">'
        f'<img src="{logo}" alt="Cambly">'
        '<strong>Aprenda ingl\u00eas de verdade. Evolua de verdade.</strong>'
        '<span>Tutores nativos \u00b7 Todos os n\u00edveis \u00b7 24/7</span>'
        '<div class="pv">'
        '<s>De R$93/m\u00eas</s>'
        '<big><sup>R$</sup>52<sub>/m\u00eas</sub></big>'
        '<small>44% de desconto \u00b7 Pequenos Grupos</small>'
        '</div>'
        f'<a class="bt" href="{url}" target="_blank" rel="noopener sponsored">Come\u00e7ar agora \u2192</a>'
        '<em>Sem compromisso \u00b7 Cancele quando quiser</em>'
        '</div>'
        '</div>'
    )

BANNERS = [b_horizontal, b_inline, b_card]

# ── Remove injeções antigas ───────────────────────────────────────────────────
RE_CSS  = re.compile(r'\n?<style id="cbly">.*?</style>', re.DOTALL)
RE_WRAP = re.compile(r'<div class="cbly-wrap">.*?</div>\s*</div>', re.DOTALL)

def limpar(html):
    html = RE_CSS.sub('', html)
    html = RE_WRAP.sub('', html)
    return html

# ── Injetor ───────────────────────────────────────────────────────────────────
def injetar(path: Path) -> bool:
    try:
        html = path.read_text(encoding='utf-8', errors='replace')

        # Remove injeção anterior
        html = limpar(html)

        # CSS no </head>
        if '<style id="cbly">' not in html:
            html = html.replace('</head>', CSS + '</head>', 1)

        # Banner principal: APÓS âncora (inserido depois dela, não antes)
        fn = random.choice(BANNERS)
        banner_mid = '\n' + fn(AFFILIATE_URL, LOGO_PATH) + '\n'

        if ANCHOR_MID in html:
            html = html.replace(ANCHOR_MID, ANCHOR_MID + banner_mid, 1)

        # Banner inline fixo antes do footer
        banner_foot = '\n' + b_inline(AFFILIATE_URL, LOGO_PATH) + '\n'
        html = html.replace(ANCHOR_FOOTER, banner_foot + ANCHOR_FOOTER, 1)

        path.write_text(html, encoding='utf-8')
        return True

    except Exception as e:
        log.error('Erro em %s: %s', path, e)
        return False

# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    if not CNPJ_DIR.exists():
        log.error('Pasta nao encontrada: %s', CNPJ_DIR)
        return

    pages = list(CNPJ_DIR.rglob('index.html'))
    total = len(pages)
    ok = err = 0

    log.info('=' * 55)
    log.info('Injetor Cambly v3 — BuscaCNPJ.work')
    log.info('Paginas : %d', total)
    log.info('=' * 55)

    for i, page in enumerate(pages, 1):
        if injetar(page):
            ok += 1
            if ok % 500 == 0:
                log.info('[%d/%d] %d processadas...', i, total, ok)
        else:
            err += 1

    log.info('=' * 55)
    log.info('CONCLUIDO — OK: %d  Erros: %d', ok, err)
    log.info('=' * 55)
    log.info('Proximo: git add . && git commit -m "Banners Cambly v3" && git push')

if __name__ == '__main__':
    main()
