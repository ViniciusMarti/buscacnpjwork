import sqlite3
import os

# Caminho para o banco de dados relativo a este script
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, "database", "dados.db")
OUTPUT_DIR = BASE_DIR

def gerar_sitemap():
    if not os.path.exists(DB_PATH):
        print(f"Erro: Banco de dados {DB_PATH} não encontrado.")
        return

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # Busca todos os CNPJs ativos ou com razão social
    print("Buscando CNPJs no banco de dados...")
    cnpjs = [row[0] for row in cursor.execute("SELECT cnpj FROM dados_cnpj WHERE razao_social != ''").fetchall()]
    
    if not cnpjs:
        print("Nenhum CNPJ com dados encontrado no banco.")
        return

    print(f"Gerando sitemaps para {len(cnpjs)} empresas...")

    # Limite de URLs por arquivo (Sitemap padrão é 50k, mas o usuário pediu 5k)
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

    # Gera o Sitemap Index
    with open(os.path.join(OUTPUT_DIR, "sitemap.xml"), "w", encoding="utf-8") as f:
        f.write('<?xml version="1.0" encoding="UTF-8"?>\n')
        f.write('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n')
        # Adiciona a página inicial
        f.write('  <sitemap><loc>https://buscacnpjgratis.com.br/sitemap-main.xml</loc></sitemap>\n')
        for s in sitemaps:
            f.write(f'  <sitemap><loc>https://buscacnpjgratis.com.br/{s}</loc></sitemap>\n')
        f.write('</sitemapindex>')

    conn.close()
    print(f"Concluído! {len(sitemaps)} arquivos de sitemap criados.")

if __name__ == "__main__":
    gerar_sitemap()
