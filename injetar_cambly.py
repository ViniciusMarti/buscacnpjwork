#!/usr/bin/env python3
"""
injetar_cambly.py — BuscaCNPJ.work
Percorre todas as páginas em site-cnpj/cnpj/*/index.html e injeta
um banner Cambly aleatório (3 variações) de forma estratégica:
  - Após a seção de Localização (antes de Atividade Principal)
  - Antes do <footer>
Páginas que já têm o banner são ignoradas (idempotente).
"""

import os
import random
import logging
from pathlib import Path

# ── Config ────────────────────────────────────────────────────────────────────
CNPJ_DIR      = Path(r"C:\Users\marti\Documents\Projetos\buscacnpjwork\cnpj")
AFFILIATE_URL = "https://www.cambly.com/invite/VINICIUSCODES?st=030526&sc=4"
LOGO_PATH     = "/cambly-logo.png"
MARKER        = "cambly-ad-injected"   # marca para não reinjetar

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%H:%M:%S",
    handlers=[logging.StreamHandler(), logging.FileHandler("injetar_cambly.log", encoding="utf-8")]
)
log = logging.getLogger(__name__)

# ── CSS (injetado 1x no <head> de cada página) ────────────────────────────────
CSS = """
<style id="cambly-ad-injected">
  .cambly-h{background:#FFCB3C;border-radius:14px;padding:20px 28px;display:flex;
             align-items:center;gap:20px;box-shadow:0 6px 28px rgba(255,180,0,.22);
             margin:28px 0;position:relative;overflow:hidden;text-decoration:none}
  .cambly-h::after{content:"";position:absolute;right:-50px;top:-50px;width:180px;height:180px;
                    border-radius:50%;background:rgba(255,255,255,.12);pointer-events:none}
  .cambly-h .ch-logo img{height:20px;filter:brightness(0);flex-shrink:0}
  .cambly-h .ch-div{width:1px;height:40px;background:rgba(0,0,0,.12);flex-shrink:0}
  .cambly-h .ch-copy{flex:1}
  .cambly-h .ch-copy strong{display:block;font-size:.92rem;font-weight:800;color:#1a1a1a;margin-bottom:2px}
  .cambly-h .ch-copy span{font-size:.78rem;color:#5a4200}
  .cambly-h .ch-price{flex-shrink:0;text-align:center}
  .cambly-h .ch-price .de{font-size:.65rem;color:#9a7a00;text-decoration:line-through;display:block}
  .cambly-h .ch-price .por{font-size:1.65rem;font-weight:900;color:#1a1a1a;line-height:1;letter-spacing:-.5px}
  .cambly-h .ch-price .por sup{font-size:.65rem;vertical-align:top;margin-top:3px}
  .cambly-h .ch-price sub{font-size:.65rem;font-weight:500;color:#7a6000}
  .cambly-h .ch-btn{flex-shrink:0;background:#1a1a1a;color:#FFCB3C;font-weight:800;font-size:.82rem;
                     text-decoration:none;padding:11px 20px;border-radius:50px;white-space:nowrap;
                     position:relative;z-index:1;transition:transform .15s,box-shadow .15s}
  .cambly-h .ch-btn:hover{transform:translateY(-2px);box-shadow:0 5px 14px rgba(0,0,0,.2)}
  @media(max-width:640px){.cambly-h{flex-direction:column;text-align:center;padding:20px 18px;gap:12px}
    .cambly-h .ch-div{width:100%;height:1px}.cambly-h .ch-price{display:flex;align-items:center;gap:8px}}

  .cambly-i{background:#fff;border:1.5px solid #FFCB3C;border-radius:10px;padding:15px 18px;
             display:flex;align-items:center;gap:14px;box-shadow:0 2px 10px rgba(255,180,0,.1);
             margin:24px 0;text-decoration:none}
  .cambly-i .ci-logo img{height:16px;flex-shrink:0}
  .cambly-i .ci-copy{flex:1}
  .cambly-i .ci-copy strong{font-size:.85rem;font-weight:700;color:#1a1a1a;display:block;margin-bottom:1px}
  .cambly-i .ci-copy span{font-size:.74rem;color:#888}
  .cambly-i .ci-price{flex-shrink:0;text-align:center}
  .cambly-i .ci-price .de{font-size:.62rem;color:#ccc;text-decoration:line-through;display:block}
  .cambly-i .ci-price .por{font-size:1.15rem;font-weight:900;color:#1a1a1a;line-height:1}
  .cambly-i .ci-price .por sup{font-size:.55rem;vertical-align:top;margin-top:2px}
  .cambly-i .ci-price sub{font-size:.58rem;color:#888;font-weight:500}
  .cambly-i .ci-btn{flex-shrink:0;border:2px solid #1a1a1a;color:#1a1a1a;font-weight:700;
                     font-size:.78rem;text-decoration:none;padding:7px 14px;border-radius:7px;
                     white-space:nowrap;transition:all .2s}
  .cambly-i .ci-btn:hover{background:#1a1a1a;color:#FFCB3C}
  @media(max-width:560px){.cambly-i{flex-direction:column;text-align:center}
    .cambly-i .ci-price{display:flex;align-items:center;gap:8px}}

  .cambly-v{background:#FFCB3C;border-radius:16px;padding:28px 24px;text-align:center;
             box-shadow:0 8px 32px rgba(255,180,0,.28);margin:28px auto;max-width:360px;
             position:relative;overflow:hidden}
  .cambly-v::before{content:"";position:absolute;top:-40px;right:-40px;width:160px;height:160px;
                     border-radius:50%;background:rgba(255,255,255,.15);pointer-events:none}
  .cambly-v .cv-logo img{height:20px;filter:brightness(0);margin-bottom:10px}
  .cambly-v h3{font-size:1.15rem;font-weight:800;color:#1a1a1a;margin-bottom:6px;line-height:1.25}
  .cambly-v p{font-size:.8rem;color:#5a4200;margin-bottom:14px}
  .cambly-v .cv-price{background:#fff;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:inline-block;width:100%}
  .cambly-v .cv-price .de{font-size:.72rem;color:#bbb;text-decoration:line-through;display:block}
  .cambly-v .cv-price .por{font-size:2rem;font-weight:900;color:#1a1a1a;line-height:1;letter-spacing:-1px}
  .cambly-v .cv-price .por sup{font-size:.8rem;vertical-align:top;margin-top:4px}
  .cambly-v .cv-price sub{font-size:.78rem;font-weight:500;color:#888}
  .cambly-v .cv-price small{display:block;font-size:.65rem;color:#aaa;margin-top:2px}
  .cambly-v .cv-btn{display:block;background:#1a1a1a;color:#FFCB3C;font-weight:800;font-size:.85rem;
                     text-decoration:none;padding:13px 20px;border-radius:50px;
                     transition:transform .15s,box-shadow .15s;position:relative;z-index:1}
  .cambly-v .cv-btn:hover{transform:translateY(-2px);box-shadow:0 5px 16px rgba(0,0,0,.2)}
  .cambly-v .cv-sub{font-size:.68rem;color:#7a6000;margin-top:10px;position:relative;z-index:1}

  @media(prefers-color-scheme:dark){
    .cambly-i{background:#1c1c1c}
    .cambly-i .ci-copy strong,.cambly-i .ci-price .por{color:#f0f0f0}
    .cambly-i .ci-btn{border-color:#FFCB3C;color:#FFCB3C}
    .cambly-i .ci-btn:hover{background:#FFCB3C;color:#1a1a1a}
  }
</style>
"""

# ── 3 variações de banner ─────────────────────────────────────────────────────
def banner_horizontal(url, logo):
    return f"""
<div class="cambly-h">
  <div class="ch-logo"><img src="{logo}" alt="Cambly"></div>
  <div class="ch-div"></div>
  <div class="ch-copy">
    <strong>Aprenda inglês com tutores 100% nativos</strong>
    <span>Conversas reais · Todos os níveis · 24h por dia, 7 dias por semana</span>
  </div>
  <div class="ch-price">
    <span class="de">R$93/mês</span>
    <div class="por"><sup>R$</sup>52<sub>/mês</sub></div>
  </div>
  <a class="ch-btn" href="{url}" target="_blank" rel="noopener sponsored">Começar agora →</a>
</div>"""

def banner_inline(url, logo):
    return f"""
<div class="cambly-i">
  <div class="ci-logo"><img src="{logo}" alt="Cambly"></div>
  <div class="ci-copy">
    <strong>Aprenda inglês com o Cambly — tutores nativos 24/7</strong>
    <span>Conversas reais · A partir de R$52/mês · Cancele quando quiser</span>
  </div>
  <div class="ci-price">
    <span class="de">R$93</span>
    <div class="por"><sup>R$</sup>52<sub>/mês</sub></div>
  </div>
  <a class="ci-btn" href="{url}" target="_blank" rel="noopener sponsored">Ver planos</a>
</div>"""

def banner_card(url, logo):
    return f"""
<div class="cambly-v">
  <div class="cv-logo"><img src="{logo}" alt="Cambly"></div>
  <h3>Aprenda inglês de verdade.<br>Evolua de verdade.</h3>
  <p>Tutores nativos · Todos os níveis · 24/7</p>
  <div class="cv-price">
    <span class="de">De R$93/mês</span>
    <div class="por"><sup>R$</sup>52<sub>/mês</sub></div>
    <small>44% de desconto · Pequenos Grupos</small>
  </div>
  <a class="cv-btn" href="{url}" target="_blank" rel="noopener sponsored">Começar agora →</a>
  <p class="cv-sub">Sem compromisso · Cancele quando quiser</p>
</div>"""

BANNERS = [banner_horizontal, banner_inline, banner_card]

# ── Injetor principal ─────────────────────────────────────────────────────────
def injetar(path: Path) -> bool:
    try:
        html = path.read_text(encoding="utf-8", errors="replace")

        # Ignora páginas já injetadas
        if MARKER in html:
            return False

        # Escolhe banner aleatório
        fn     = random.choice(BANNERS)
        banner = fn(AFFILIATE_URL, LOGO_PATH)

        # 1. Injeta CSS no </head>
        if CSS not in html:
            html = html.replace("</head>", CSS + "</head>", 1)

        # 2. Posição estratégica: após o bloco de Localização (<hr> após endereço)
        #    Estrutura típica: ...CEP</div></div></div><hr> → insere banner aqui
        #    Antes da seção de Atividade Principal
        anchor_mid = "</div></div><hr>\n<div"
        if anchor_mid in html:
            # Conta ocorrências — queremos após a 2ª <hr> (após Localização)
            parts = html.split(anchor_mid)
            if len(parts) >= 3:
                # Insere após a 2ª ocorrência (após Localização)
                html = anchor_mid.join(parts[:2]) + anchor_mid + banner + anchor_mid.join(parts[2:])
            else:
                html = html.replace(anchor_mid, anchor_mid + banner, 1)
        else:
            # Fallback: insere antes do footer
            html = html.replace("<footer>", banner + "\n<footer>", 1)

        # 3. Banner inline fixo antes do footer (toda página ganha 2 pontos de contato)
        #    Só se o banner principal não for o inline (evita repetir o mesmo estilo)
        if fn != banner_inline:
            footer_banner = banner_inline(AFFILIATE_URL, LOGO_PATH)
            html = html.replace("<footer>", footer_banner + "\n<footer>", 1)

        path.write_text(html, encoding="utf-8")
        return True

    except Exception as e:
        log.error("Erro em %s: %s", path, e)
        return False


# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    if not CNPJ_DIR.exists():
        log.error("Pasta não encontrada: %s", CNPJ_DIR)
        return

    pages   = list(CNPJ_DIR.rglob("index.html"))
    total   = len(pages)
    injetadas = 0
    puladas   = 0

    log.info("=" * 55)
    log.info("Injetor Cambly — BuscaCNPJ.work")
    log.info("Páginas encontradas : %d", total)
    log.info("Pasta               : %s", CNPJ_DIR)
    log.info("=" * 55)

    for i, page in enumerate(pages, 1):
        ok = injetar(page)
        if ok:
            injetadas += 1
            if injetadas % 200 == 0:
                log.info("[%d/%d] ✅ %d injetadas até agora...", i, total, injetadas)
        else:
            puladas += 1

    log.info("=" * 55)
    log.info("✅ CONCLUÍDO")
    log.info("   Injetadas : %d", injetadas)
    log.info("   Puladas   : %d (já tinham banner)", puladas)
    log.info("=" * 55)
    log.info("Próximo: git add . && git commit -m 'Banners Cambly injetados' && git push")


if __name__ == "__main__":
    main()
