import os
import shutil

CNPJ_DIR = "cnpj"

def cleanup():
    if not os.path.exists(CNPJ_DIR):
        print(f"Diretório {CNPJ_DIR} não encontrado.")
        return

    print(f"Limpando as pastas estáticas dentro de '{CNPJ_DIR}'...")
    try:
        count = 0
        for item in os.listdir(CNPJ_DIR):
            item_path = os.path.join(CNPJ_DIR, item)
            # Deleta apenas as pastas (que são os CNPJs)
            if os.path.isdir(item_path):
                shutil.rmtree(item_path)
                count += 1
        print(f"Limpeza concluída! {count} pastas removidas com sucesso.")
    except Exception as e:
        print(f"Erro durante a limpeza: {e}")

if __name__ == "__main__":
    cleanup()
