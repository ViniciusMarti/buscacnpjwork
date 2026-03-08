import os
import glob
from concurrent.futures import ThreadPoolExecutor

def audit_file(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    issues = []
    # Check CSS
    if 'href="/cnpj.css"' in content:
        issues.append("CSS ABSOLUTO")
    if 'cnpj.css?v=1.1' not in content:
        issues.append("VERSAO CSS AUSENTE")
    
    # Check broken patterns (like the description was truncated in user example)
    if 'description" content="' in content and '">' not in content.split('description" content="')[1].split('\n')[0]:
         if 'description" content="' in content:
             # Basic check if it ends
             meta_part = content.split('description" content="')[1]
             if '">' not in meta_part[:500]:
                 issues.append("META DESC QUEBRADO")

    if issues:
        return f"{file_path}: {', '.join(issues)}"
    return None

def main():
    files = glob.glob('cnpj/*/index.html')
    print(f"Auditando {len(files)} arquivos...")
    
    with ThreadPoolExecutor(max_workers=20) as executor:
        results = list(executor.map(audit_file, files))
    
    errors = [r for r in results if r]
    if not errors:
        print("Tudo 100% OK! Nenhuma página com erro de CSS ou Meta.")
    else:
        print(f"Encontrados {len(errors)} problemas:")
        for e in errors[:20]:
            print(e)
        if len(errors) > 20:
            print("...")

if __name__ == "__main__":
    main()
