import json
import sqlite3
import os
import sys

# Caminho para o banco de dados relativo a este script
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, "database", "dados.db")

def import_json(json_file):
    if not os.path.exists(json_file):
        print(f"Erro: Arquivo {json_file} não encontrado.")
        return

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    with open(json_file, "r", encoding="utf-8") as f:
        data = json.load(f)

    print(f"Lendo {len(data)} registros do JSON...")

    count = 0
    for row in data:
        # Tratamento de campos
        cnpj = str(row.get('cnpj', '')).zfill(14)
        if not cnpj:
            continue

        razao_social = str(row.get('razao_social') or '')
        nome_fantasia = str(row.get('nome_fantasia') or '')
        situacao = str(row.get('situacao') or '')
        data_abertura = str(row.get('data_abertura') or '')
        porte = str(row.get('porte') or '')
        
        # Capital Social
        capital_str = str(row.get('capital_social') or '0').replace(',', '.')
        try:
            capital_social = float(capital_str)
        except:
            capital_social = 0.0

        logradouro = str(row.get('logradouro') or '')
        numero = str(row.get('numero') or '')
        complemento = str(row.get('complemento') or '')
        bairro = str(row.get('bairro') or '')
        cep = str(row.get('cep') or '')
        municipio = str(row.get('municipio') or '')
        uf = str(row.get('uf') or '')
        telefone = str(row.get('telefone') or '')
        email = str(row.get('email') or '')
        cnae_principal_codigo = str(row.get('cnae_principal_codigo') or '')
        cnae_principal_descricao = str(row.get('cnae_principal_descricao') or '')
        cnaes_secundarios = str(row.get('cnaes_secundarios') or '')
        quadro_societario = str(row.get('quadro_societario') or '')

        cursor.execute('''
            INSERT OR REPLACE INTO dados_cnpj (
                cnpj, razao_social, nome_fantasia, situacao, data_abertura, porte, capital_social,
                logradouro, numero, complemento, bairro, cep, municipio, uf, telefone, email,
                cnae_principal_codigo, cnae_principal_descricao, cnaes_secundarios, quadro_societario,
                ultima_atualizacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ''', (
            cnpj, razao_social, nome_fantasia, situacao, data_abertura, porte, capital_social,
            logradouro, numero, complemento, bairro, cep, municipio, uf, telefone, email,
            cnae_principal_codigo, cnae_principal_descricao, cnaes_secundarios, quadro_societario
        ))

        count += 1
        if count % 10000 == 0:
            conn.commit()
            print(f"Importados: {count}...")

    conn.commit()
    conn.close()
    print(f"Importação concluída! Total de {count} CNPJs atualizados no banco em {DB_PATH}.")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        import_json(sys.argv[1])
    else:
        print("Uso: python import_json.py caminho_do_arquivo.json")
