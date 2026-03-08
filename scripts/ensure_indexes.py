import sqlite3
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, "database", "dados.db")

def add_indexes():
    if not os.path.exists(DB_PATH):
        print(f"Erro: Banco de dados {DB_PATH} não encontrado.")
        return

    print(f"Conectando ao banco de dados: {DB_PATH}")
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    indexes = [
        ("idx_uf", "dados_cnpj(uf)"),
        ("idx_municipio", "dados_cnpj(municipio)"),
        ("idx_capital", "dados_cnpj(capital_social DESC)"),
        ("idx_razao", "dados_cnpj(razao_social)"),
        ("idx_cnpj", "dados_cnpj(cnpj)")
    ]

    for name, target in indexes:
        print(f"Garantindo índice {name} em {target}...")
        try:
            cursor.execute(f"CREATE INDEX IF NOT EXISTS {name} ON {target}")
            print(f"Índice {name} pronto.")
        except Exception as e:
            print(f"Erro ao criar índice {name}: {e}")

    conn.commit()
    conn.close()
    print("Otimização concluída!")

if __name__ == "__main__":
    add_indexes()
