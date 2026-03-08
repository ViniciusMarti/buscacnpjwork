import sqlite3
import os

DB_PATH = r"c:\Users\marti\Documents\repositorio\buscacnpjgratis\database\dados.db"

def check():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='dados_cnpj'")
    indexes = [r[0] for r in c.fetchall()]
    print(f"Indices found: {indexes}")
    conn.close()

if __name__ == "__main__":
    check()
