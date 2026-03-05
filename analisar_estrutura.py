import os

PASTA = r"C:\Users\marti\Documents\meu-projeto-cnpj"

def mostrar_estrutura(caminho, prefixo=""):
    try:
        itens = sorted(os.listdir(caminho))
    except PermissionError:
        print(prefixo + "  [sem permissão]")
        return

    for i, item in enumerate(itens):
        ultimo = (i == len(itens) - 1)
        conector = "└── " if ultimo else "├── "
        caminho_completo = os.path.join(caminho, item)

        if os.path.isdir(caminho_completo):
            print(prefixo + conector + f"📁 {item}/")
            extensao = "    " if ultimo else "│   "
            # Limita subpastas do tipo cnpj (evita listar 1000 pastas)
            sub_itens = os.listdir(caminho_completo)
            if len(sub_itens) > 20:
                mostrar_estrutura(caminho_completo, prefixo + extensao, limite=5)
            else:
                mostrar_estrutura(caminho_completo, prefixo + extensao)
        else:
            tamanho = os.path.getsize(caminho_completo)
            tamanho_fmt = f"{tamanho:,} bytes" if tamanho < 1024 else f"{tamanho/1024:.1f} KB"
            print(prefixo + conector + f"📄 {item}  ({tamanho_fmt})")

def mostrar_estrutura(caminho, prefixo="", limite=None):
    try:
        itens = sorted(os.listdir(caminho))
    except PermissionError:
        print(prefixo + "  [sem permissão]")
        return

    if limite and len(itens) > limite:
        amostra = itens[:limite]
        ocultos = len(itens) - limite
    else:
        amostra = itens
        ocultos = 0

    for i, item in enumerate(amostra):
        ultimo = (i == len(amostra) - 1) and ocultos == 0
        conector = "└── " if ultimo else "├── "
        caminho_completo = os.path.join(caminho, item)

        if os.path.isdir(caminho_completo):
            print(prefixo + conector + f"📁 {item}/")
            extensao = "    " if ultimo else "│   "
            sub_itens = os.listdir(caminho_completo)
            sub_limite = 5 if len(sub_itens) > 20 else None
            mostrar_estrutura(caminho_completo, prefixo + extensao, limite=sub_limite)
        else:
            tamanho = os.path.getsize(caminho_completo)
            tamanho_fmt = f"{tamanho:,} bytes" if tamanho < 1024 else f"{tamanho/1024:.1f} KB"
            print(prefixo + conector + f"📄 {item}  ({tamanho_fmt})")

    if ocultos > 0:
        print(prefixo + f"└── ... e mais {ocultos} itens ocultos")

# ── Estatísticas gerais ──────────────────────────────────────────────────────
def estatisticas(caminho):
    total_arquivos = 0
    total_pastas   = 0
    total_bytes    = 0
    ext_count      = {}

    for root, dirs, files in os.walk(caminho):
        total_pastas += len(dirs)
        for f in files:
            total_arquivos += 1
            fp = os.path.join(root, f)
            try:
                sz = os.path.getsize(fp)
                total_bytes += sz
            except Exception:
                pass
            ext = os.path.splitext(f)[1].lower() or "(sem extensão)"
            ext_count[ext] = ext_count.get(ext, 0) + 1

    print()
    print("═" * 50)
    print("  ESTATÍSTICAS")
    print("═" * 50)
    print(f"  Pastas      : {total_pastas:,}")
    print(f"  Arquivos    : {total_arquivos:,}")
    print(f"  Tamanho     : {total_bytes / 1_048_576:.2f} MB")
    print()
    print("  Arquivos por extensão:")
    for ext, qtd in sorted(ext_count.items(), key=lambda x: -x[1]):
        print(f"    {ext:<15} {qtd:>6} arquivo(s)")
    print("═" * 50)

# ── Main ──────────────────────────────────────────────────────────────────────
print(f"📂 {PASTA}")
print()
mostrar_estrutura(PASTA)
estatisticas(PASTA)
