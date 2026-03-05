#!/usr/bin/env python3
"""
adicionar_favicon.py — BuscaCNPJ.work
Adiciona as tags de favicon em todos os index.html do site.
Roda uma vez, idempotente (não duplica se já existir).
"""

import os
import glob

BASE_DIR = "."  # raiz do projeto (onde estão index.html, cnpj/, etc.)

FAVICON_TAGS = """  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">"""

ANCHOR = "</title>"  # insere logo após o </title>

def processar(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()

    # Idempotente: pula se já tiver favicon
    if "favicon.ico" in content:
        return False

    if ANCHOR not in content:
        return False

    novo = content.replace(ANCHOR, ANCHOR + "\n" + FAVICON_TAGS, 1)

    with open(filepath, "w", encoding="utf-8") as f:
        f.write(novo)

    return True

def main():
    # Encontra todos os index.html recursivamente + 404.html
    arquivos = glob.glob(os.path.join(BASE_DIR, "**", "index.html"), recursive=True)
    arquivos += glob.glob(os.path.join(BASE_DIR, "404.html"))

    total     = len(arquivos)
    atualizados = 0
    pulados     = 0

    print()
    print("=" * 55)
    print("  Adicionando favicon em todas as páginas...")
    print(f"  Arquivos encontrados: {total:,}")
    print("=" * 55)

    for path in sorted(arquivos):
        ok = processar(path)
        if ok:
            atualizados += 1
            print(f"  ✅  {path}")
        else:
            pulados += 1

    print()
    print("=" * 55)
    print(f"  ✅  Atualizados : {atualizados:,}")
    print(f"  ⏭️   Já tinham   : {pulados:,}")
    print()
    print("  Próximo:")
    print("  git add . && git commit -m 'Favicon adicionado' && git push")
    print("=" * 55)

if __name__ == "__main__":
    main()
