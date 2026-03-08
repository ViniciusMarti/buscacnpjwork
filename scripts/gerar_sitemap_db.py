import sqlite3
import os

# Caminho para o banco de dados relativo a este script
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(os.path.dirname(BASE_DIR), "database_cnpj.sqlite")
OUTPUT_DIR = os.path.join(BASE_DIR, "sitemaps")
INDEX_PATH = os.path.join(BASE_DIR, "sitemap.xml")

def gerar_sitemap():
    if not os.path.exists(DB_PATH):
        print(f"Erro: Banco de dados {DB_PATH} não encontrado.")
        return

    # Garante que a pasta de sitemaps existe
    if not os.path.exists(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # Busca todos os CNPJs ativos ou com razão social
    print("Buscando CNPJs no banco de dados...")
    cnpjs = [row[0] for row in cursor.execute("SELECT cnpj FROM dados_cnpj WHERE razao_social != ''").fetchall()]
    
    if not cnpjs:
        print("Nenhum CNPJ com dados encontrado no banco.")
        return

    print(f"Gerando sitemaps para {len(cnpjs)} empresas...")

    # Limite de URLs por arquivo
    URLS_PER_FILE = 5000
    sitemaps = []

    for i in range(0, len(cnpjs), URLS_PER_FILE):
        chunk = cnpjs[i:i + URLS_PER_FILE]
        file_num = (i // URLS_PER_FILE) + 1
        filename = f"sitemap-cnpj-{file_num}.xml"
        path = os.path.join(OUTPUT_DIR, filename)
        
        with open(path, "w", encoding="utf-8") as f:
            f.write('<?xml version="1.0" encoding="UTF-8"?>\n')
            f.write('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n')
            for cnpj in chunk:
                f.write(f'  <url><loc>https://buscacnpjgratis.com.br/cnpj/{cnpj}/</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>\n')
            f.write('</urlset>')
        
        sitemaps.append(filename)
        if file_num % 10 == 0:
            print(f"Gerados {file_num} sitemaps...")

    # Gera o Sitemap Index na RAIZ
    with open(INDEX_PATH, "w", encoding="utf-8") as f:
        f.write('<?xml version="1.0" encoding="UTF-8"?>\n')
        f.write('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n')
        # Adiciona o sitemap principal
        f.write('  <sitemap><loc>https://buscacnpjgratis.com.br/sitemaps/sitemap-main.xml</loc></sitemap>\n')
        for s in sitemaps:
            f.write(f'  <sitemap><loc>https://buscacnpjgratis.com.br/sitemaps/{s}</loc></sitemap>\n')
        f.write('</sitemapindex>')

    conn.close()
    print(f"Concluído! {len(sitemaps)} arquivos de sitemap criados na pasta /sitemaps/ e index na raiz.")

if __name__ == "__main__":
    gerar_sitemap()
