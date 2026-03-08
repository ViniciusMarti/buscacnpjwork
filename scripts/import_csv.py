import csv
import sqlite3
import os
import sys

# Caminho para o banco de dados relativo a este script
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, "database", "dados.db")
CSV_DIR = os.path.join(BASE_DIR, "database", "csv_final")

def import_csv_file(csv_filename, conn):
    cursor = conn.cursor()
    count = 0
    with open(csv_filename, "r", encoding="utf-8") as f:
        # Detecta o delimitador (pode ser ; ou ,)
        sample = f.read(2048)
        f.seek(0)
        try:
            dialect = csv.Sniffer().sniff(sample)
        except:
            dialect = 'excel'
        reader = csv.DictReader(f, dialect=dialect)
        
        batch = []
        for row in reader:
            try:
                # Limpa o CNPJ (apenas números)
                cnpj = "".join(filter(str.isdigit, row["cnpj"]))
                
                # Trata capital social para float
                cap = row["capital_social"].replace("R$", "").replace(".", "").replace(",", ".").strip()
                try:
                    capital = float(cap) if cap else 0.0
                except:
                    capital = 0.0

                batch.append((
                    cnpj, row["razao_social"], row["nome_fantasia"], row["situacao"],
                    row["data_abertura"], row["porte"], capital, row["logradouro"],
                    row["numero"], row["complemento"], row["bairro"], row["cep"],
                    row["municipio"], row["uf"], row["telefone"], row["email"],
                    row["cnae_principal_codigo"], row["cnae_principal_descricao"],
                    row["cnaes_secundarios"], row["quadro_societario"]
                ))
                
                count += 1
                if count % 5000 == 0:
                    cursor.executemany('''
                        INSERT OR REPLACE INTO dados_cnpj (
                            cnpj, razao_social, nome_fantasia, situacao, data_abertura, 
                            porte, capital_social, logradouro, numero, complemento, 
                            bairro, cep, municipio, uf, telefone, email, 
                            cnae_principal_codigo, cnae_principal_descricao, 
                            cnaes_secundarios, quadro_societario, ultima_atualizacao
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ''', batch)
                    batch = []
                    print(f"[{os.path.basename(csv_filename)}] Importados: {count}...")
            except Exception as e:
                print(f"Erro no CNPJ {row.get('cnpj')} do arquivo {csv_filename}: {e}")
        
        # Insere o restante
        if batch:
            cursor.executemany('''
                INSERT OR REPLACE INTO dados_cnpj (
                    cnpj, razao_social, nome_fantasia, situacao, data_abertura, 
                    porte, capital_social, logradouro, numero, complemento, 
                    bairro, cep, municipio, uf, telefone, email, 
                    cnae_principal_codigo, cnae_principal_descricao, 
                    cnaes_secundarios, quadro_societario, ultima_atualizacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ''', batch)
            
    conn.commit()
    return count

def main():
    if not os.path.exists(CSV_DIR):
        print(f"Erro: Diretório de CSVs {CSV_DIR} não encontrado.")
        return

    csv_files = [f for f in os.listdir(CSV_DIR) if f.endswith('.csv')]
    if not csv_files:
        print(f"Nenhum arquivo CSV encontrado em {CSV_DIR}")
        return

    print(f"Encontrados {len(csv_files)} arquivos para importar.")
    
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA synchronous = OFF")
    conn.execute("PRAGMA journal_mode = WAL")
    conn.execute("PRAGMA cache_size = -100000") # 100MB cache
    
    total_imported = 0
    for idx, csv_file in enumerate(csv_files):
        csv_path = os.path.join(CSV_DIR, csv_file)
        print(f"Processando arquivo {idx+1}/{len(csv_files)}: {csv_file}")
        total_imported += import_csv_file(csv_path, conn)

    conn.commit()
    conn.close()
    print(f"Processamento concluído! Total de {total_imported} registros importados.")

if __name__ == "__main__":
    main()
