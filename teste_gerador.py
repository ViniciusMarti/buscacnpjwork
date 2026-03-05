# teste_gerador.py
import json, os

# Importa as funções do gerador sem rodar o main()
from gerador_v4_b import fetch, gerar_html, norm, fmt_cnpj

CNPJ_TESTE = "11222333000181"  # troca por qualquer CNPJ válido

print("1. Buscando na API...")
data = fetch(CNPJ_TESTE)

if not data:
    print("❌ CNPJ não encontrado na API")
else:
    print("✅ API retornou dados")
    d = norm(data)
    print(f"   Razão Social : {d['razao_social']}")
    print(f"   Situação     : {d['situacao']}")
    print(f"   Município    : {d['municipio']}/{d['uf']}")
    print(f"   CNAEs sec.   : {len(d['cnaes_secundarios'])}")
    print(f"   Sócios       : {len(d['qsa'])}")

    print("\n2. Gerando HTML...")
    html = gerar_html(data)

    # Salva em pasta de teste, não em site-cnpj/
    os.makedirs("teste_output", exist_ok=True)
    with open("teste_output/index.html", "w", encoding="utf-8") as f:
        f.write(html)

    print(f"✅ HTML gerado — {len(html)} chars")
    print("   Arquivo: teste_output/index.html")

    # Verificações básicas
    checks = [
        ("CSS presente",       "<style>" in html),
        ("Dark mode",          "prefers-color-scheme:dark" in html),
        ("Schema.org",         "application/ld+json" in html),
        ("Badge situação",     'class="badge' in html),
        ("Sócios",             "Quadro de Sócios" in html),
        ("CNAEs secundários",  "Atividades Secundárias" in html),
        ("Encoding OK",        "├" not in html and "ÔÇö" not in html),
    ]

    print("\n3. Checklist:")
    all_ok = True
    for nome, ok in checks:
        status = "✅" if ok else "❌"
        print(f"   {status} {nome}")
        if not ok: all_ok = False

    print(f"\n{'🎉 Tudo certo!' if all_ok else '⚠️  Há problemas — revise acima'}")
