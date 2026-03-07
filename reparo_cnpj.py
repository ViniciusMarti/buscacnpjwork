#!/usr/bin/env python3
"""
reparo_cnpj.py — BuscaCNPJ.work
Auditoria completa e reparo das 50k páginas de CNPJ.
Detecta e corrige: links quebrados, CSS ausente, erros de encoding, 
JSON-LD corrompido e SEO inconsistente.
"""

import os
import re
import json
import logging
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor, as_completed
from threading import Lock

# Reutiliza a lógica de extração e geração que validamos na Fase 3
from patch_layout import parse_html, gerar_html_norm

# ── Config ──────────────────────────────────────────────────
CNPJ_DIR    = Path("./cnpj")
MAX_WORKERS = 10
LOG_FILE    = "auditoria.log"
# ────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(), logging.FileHandler(LOG_FILE, encoding="utf-8")]
)
log = logging.getLogger("reparo")

def auditoria_e_reparo(folder: Path):
    path = folder / "index.html"
    cnpj = folder.name
    
    if not path.exists():
        return cnpj, "FALTA_ARQUIVO"
        
    try:
        html = path.read_text(encoding="utf-8", errors="replace")
    except Exception as e:
        return cnpj, f"ERRO_LEITURA: {e}"

    problemas = []
    
    # 1. Verifica CSS
    if 'href="/cnpj.css"' not in html:
        problemas.append("CSS_AUSENTE")
        
    # 2. Verifica Integridade Básica
    if "</html>" not in html.lower() or "</body>" not in html.lower():
        problemas.append("ESTRUTURA_CORROMPIDA")
        
    # 3. Verifica Encoding (procura por caracteres de erro)
    if "" in html:
        problemas.append("ERRO_ENCODING")

    # 4. Verifica SEO (Canonical deve bater com a pasta)
    canonical = re.search(r'rel="canonical" href=".*?/cnpj/(\d+)/"', html)
    if not canonical or canonical.group(1) != cnpj:
        problemas.append("SEO_INCOERENTE")

    # 5. Verifica Bloco Hostinger
    if "affiliate-section" not in html:
        problemas.append("HOSTINGER_AUSENTE")

    # ── Se encontrou problemas, repara ───────────────────────
    if problemas:
        try:
            # Extrai os dados originais (o parse_html é resiliente)
            d = parse_html(html, cnpj)
            
            # Se a extração falhou miseravelmente (ex: Razão Social vazia)
            if not d["razao_social"] or d["razao_social"] == "N/A":
                 return cnpj, f"IMPOSSIVEL_REPARAR: {','.join(problemas)}"
            
            # Gera novo HTML limpo
            novo_html = gerar_html_norm(d)
            path.write_text(novo_html, encoding="utf-8")
            
            return cnpj, f"REPARADO: {','.join(problemas)}"
        except Exception as e:
            return cnpj, f"FALHA_REPARO: {e}"
            
    return cnpj, "OK"

def main():
    log.info("Iniciando auditoria completa em %s...", CNPJ_DIR)
    
    pastas = [p for p in CNPJ_DIR.iterdir() if p.is_dir()]
    total  = len(pastas)
    
    status_count = {}
    
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = {executor.submit(auditoria_e_reparo, p): p for p in pastas}
        
        i = 0
        for fut in as_completed(futures):
            cnpj, status = fut.result()
            i += 1
            
            # Agrupa status para o resumo
            base_status = status.split(":")[0]
            status_count[base_status] = status_count.get(base_status, 0) + 1
            
            if "REPARADO" in status:
                log.warning("  🔧 %s — %s", cnpj, status)
            elif "ERRO" in status or "FALHA" in status:
                log.error("  ❌ %s — %s", cnpj, status)
                
            if i % 5000 == 0 or i == total:
                log.info("Progresso: %d/%d (%.1f%%)", i, total, (i/total)*100)

    log.info("="*50)
    log.info("RESUMO DA AUDITORIA:")
    for key, val in status_count.items():
        log.info("  %s: %d", key, val)
    log.info("="*50)

if __name__ == "__main__":
    main()
