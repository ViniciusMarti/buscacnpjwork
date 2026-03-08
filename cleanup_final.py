import os
import shutil

files_to_delete = [
    "add_ga.py", "add_ga_fast.py", "add_ga_fast_v2.py", "ampliar_seeds.py", 
    "check_empty.py", "check_json.py", "debug_audit.py", "final_audit.py", 
    "gerador_v4_b.py", "gerar_csv_modelo.py", "gerar_sitemaps.py", 
    "injetar_cambly.py", "injetar_cambly_v3.py", "injetar_cambly_v4.py", 
    "limpar_estatico.py", "old_gerador.py", "old_premium_gerador.py", 
    "remover_cambly.py", "reparo_api.py", "reparo_cnpj.py", "supervisor.py", 
    "test_apis.py", "test_regex.py", "test_regex_v4.py", "teste_gerador.py", 
    "update_cache.py", "update_index.py", "verificar_cnpjs.py",
    "404.html", "preview_gerador_novo.html", "teste_injetado_v2.html", "teste_v3.html",
    "progresso.json", "lista_cnpjs_atualizada-v1.csv", "lista_cnpjs_para_atualizar.csv",
    "bquxjob_3ef1be4e_19ccdb78d49.json", "_headers", "_redirects",
    "ampliar.log", "auditoria.log", "gerador.log", "injetar_cambly.log", 
    "injetar_cambly_v3.log", "injetar_cambly_v4.log", "regenerar.log", 
    "remover_cambly.log", "reparo_api.log", "supervisor.log"
]

dirs_to_delete = ["__pycache__", "teste_output"]

base_path = r"c:\Users\marti\Documents\Repositório\buscacnpjwork"

def clean():
    for f in files_to_delete:
        path = os.path.join(base_path, f)
        if os.path.exists(path):
            try:
                os.remove(path)
                print(f"Deletado: {f}")
            except Exception as e:
                print(f"Erro ao deletar {f}: {e}")

    for d in dirs_to_delete:
        path = os.path.join(base_path, d)
        if os.path.exists(path):
            try:
                shutil.rmtree(path)
                print(f"Diretório deletado: {d}")
            except Exception as e:
                print(f"Erro ao deletar diretório {d}: {e}")

    # Remove xml sitemaps antigos para que os novos gerados pelo db sejam usados
    for f in os.listdir(base_path):
        if f.startswith("sitemap-cnpj-") and f.endswith(".xml"):
            try:
                os.remove(os.path.join(base_path, f))
                print(f"Deletado sitemap antigo: {f}")
            except: pass

    print("\nLimpando arquivos .pyc compilados...")
    for root, dirs, files in os.walk(base_path):
        for f in files:
            if f.endswith('.pyc'):
                try:
                    os.remove(os.path.join(root, f))
                except: pass

clean()
