import os, re

def update_css_version(root_dir, old_v, new_v):
    count = 0
    pattern = re.compile(f'cnpj.css\\?v={old_v}')
    replacement = f'cnpj.css?v={new_v}'
    
    print(f"Iniciando atualização de {old_v} para {new_v}...")
    
    for root, dirs, files in os.walk(root_dir):
        for file in files:
            if file == 'index.html':
                path = os.path.join(root, file)
                try:
                    with open(path, 'r', encoding='utf-8') as f:
                        content = f.read()
                    
                    if f'cnpj.css?v={old_v}' in content:
                        new_content = content.replace(f'cnpj.css?v={old_v}', replacement)
                        with open(path, 'w', encoding='utf-8') as f:
                            f.write(new_content)
                        count += 1
                        if count % 5000 == 0:
                            print(f"{count} arquivos atualizados...")
                except Exception as e:
                    print(f"Erro no arquivo {path}: {e}")
                    
    print(f"Concluído! Total de arquivos atualizados: {count}")

if __name__ == "__main__":
    # Atualizar de v=1.5 para v=1.7 (e também v=1.2 se existir algum)
    update_css_version('cnpj', '1.5', '1.7')
    update_css_version('cnpj', '1.2', '1.7')
