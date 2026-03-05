#!/usr/bin/env python3
"""
remover_cambly.py — BuscaCNPJ.work
Remove TUDO que foi injetado pelos scripts de banner Cambly (v1, v2, v3).
Não altera absolutamente nada do conteúdo original das páginas.

Cobre todas as variações injetadas:
  - <style id="cambly-ad-injected">
  - <style id="cambly-banner">
  - <style id="cbly">
  - <div class="cambly-h/i/v/...">
  - <div class="cbly-wrap/h/i/v/...">
  - <a class="cbly-...">
  - <div class="cbly-wrap">...</div></div>
"""

import re
import logging
from pathlib import Path

# ── Config ────────────────────────────────────────────────────────────────────
CNPJ_DIR = Path(r"C:\Users\marti\Documents\Projetos\buscacnpjwork\cnpj")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%H:%M:%S",
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler("remover_cambly.log", encoding="utf-8"),
    ]
)
log = logging.getLogger(__name__)

# ── Padrões de remoção ────────────────────────────────────────────────────────
PATTERNS = [
    # Blocos CSS injetados (todas as versões dos scripts)
    re.compile(
        r'\r?\n?<style\s+id="(?:cambly-ad-injected|cambly-banner|cbly)"[^>]*>.*?</style>',
        re.DOTALL | re.IGNORECASE
    ),
    # Divs de banner com classes cambly-* ou cbly-*
    re.compile(
        r'<div\s+class="(?:cambly|cbly)-[^"]*"[^>]*>.*?</div>\s*(?:</div>\s*)?',
        re.DOTALL | re.IGNORECASE
    ),
    # Links <a> de banner
    re.compile(
        r'<a\s+class="(?:cambly|cbly)-[^"]*"[^>]*>.*?</a>',
        re.DOTALL | re.IGNORECASE
    ),
]

# Limpeza de linhas em branco extras deixadas pela remoção
RE_BLANK_CRLF = re.compile(r'(\r\n){3,}')
RE_BLANK_LF   = re.compile(r'\n{3,}')


def limpar(html: str) -> str:
    for pat in PATTERNS:
        html = pat.sub('', html)
    html = RE_BLANK_CRLF.sub('\r\n\r\n', html)
    html = RE_BLANK_LF.sub('\n\n', html)
    return html


def tem_injecao(html: str) -> bool:
    markers = [
        'cambly-ad-injected', 'cambly-banner', '<style id="cbly">',
        'class="cambly-', 'class="cbly-',
    ]
    return any(m in html for m in markers)


def processar(path: Path) -> str:
    """Retorna 'limpo', 'skip' ou 'err'."""
    try:
        html = path.read_text(encoding='utf-8', errors='replace')

        if not tem_injecao(html):
            return 'skip'

        tamanho_antes = len(html)
        html_limpo    = limpar(html)
        tamanho_depois = len(html_limpo)

        # Segurança: não salva se o arquivo encolheu mais de 60%
        # (evita apagar conteúdo real por acidente)
        reducao = (tamanho_antes - tamanho_depois) / tamanho_antes
        if reducao > 0.60:
            log.warning('⚠️  Reducao suspeita (%.0f%%) em %s — pulando', reducao * 100, path)
            return 'err'

        path.write_text(html_limpo, encoding='utf-8')
        log.debug('  ✓ %s  %d → %d chars', path.name, tamanho_antes, tamanho_depois)
        return 'limpo'

    except Exception as e:
        log.error('Erro em %s: %s', path, e)
        return 'err'


def main():
    if not CNPJ_DIR.exists():
        log.error('Pasta nao encontrada: %s', CNPJ_DIR)
        return

    pages = list(CNPJ_DIR.rglob('index.html'))
    total = len(pages)
    limpos = skip = err = 0

    log.info('=' * 55)
    log.info('Remover Cambly — BuscaCNPJ.work')
    log.info('Paginas encontradas: %d', total)
    log.info('Pasta: %s', CNPJ_DIR)
    log.info('=' * 55)

    for i, page in enumerate(pages, 1):
        resultado = processar(page)
        if resultado == 'limpo':
            limpos += 1
            if limpos % 500 == 0:
                log.info('[%d/%d] %d paginas limpas...', i, total, limpos)
        elif resultado == 'skip':
            skip += 1
        else:
            err += 1

    log.info('=' * 55)
    log.info('CONCLUIDO')
    log.info('  Limpas         : %d', limpos)
    log.info('  Sem injecao    : %d', skip)
    log.info('  Erros/suspeitas: %d', err)
    log.info('=' * 55)

    if err > 0:
        log.warning('Arquivos com erro estao listados em remover_cambly.log')

    log.info('Proximo: git add . && git commit -m "Remove banners Cambly" && git push')


if __name__ == '__main__':
    main()
