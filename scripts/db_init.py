import sqlite3
import os

# Caminho para o banco de dados relativo a este script
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_DIR = os.path.join(BASE_DIR, "database")
DB_PATH = os.path.join(DB_DIR, "dados.db")

def init_db():
    if not os.path.exists(DB_DIR):
        os.makedirs(DB_DIR)
        print(f"Diretório {DB_DIR} criado.")

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    # Criação da tabela principal
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS dados_cnpj (
        cnpj TEXT PRIMARY KEY,
        razao_social TEXT,
        nome_fantasia TEXT,
        situacao TEXT,
        data_abertura TEXT,
        porte TEXT,
        capital_social REAL,
        logradouro TEXT,
        numero TEXT,
        complemento TEXT,
        bairro TEXT,
        cep TEXT,
        municipio TEXT,
        uf TEXT,
        telefone TEXT,
        email TEXT,
        cnae_principal_codigo TEXT,
        cnae_principal_descricao TEXT,
        cnaes_secundarios TEXT,
        quadro_societario TEXT,
        ultima_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
    )
    ''')

    # Índices para busca rápida
    cursor.execute('CREATE INDEX IF NOT EXISTS idx_cnpj ON dados_cnpj(cnpj)')
    cursor.execute('CREATE INDEX IF NOT EXISTS idx_razao ON dados_cnpj(razao_social)')

    conn.commit()
    conn.close()
    print(f"Banco de dados inicializado em: {DB_PATH}")

if __name__ == "__main__":
    init_db()
