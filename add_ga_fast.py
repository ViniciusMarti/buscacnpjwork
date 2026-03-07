import os
import re
from concurrent.futures import ProcessPoolExecutor, as_completed

GA_TAG = """<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-BR2RRQXGCB"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-BR2RRQXGCB');
</script>
"""

PROJECT_DIR = r"c:\Users\marti\Documents\Repositório\buscacnpjwork"
GA_ID = "G-BR2RRQXGCB"

def process_file(file_path):
    try:
        with open(file_path, "r", encoding="utf-8") as f:
            content = f.read()

        if GA_ID in content:
            return False # Already has GA

        new_content = re.sub(r"(<head.*?>)", r"\1\n" + GA_TAG, content, flags=re.IGNORECASE)

        if new_content != content:
            with open(file_path, "w", encoding="utf-8") as f:
                f.write(new_content)
            return True # Modified
        return False
    except Exception as e:
        # print(f"Error processing {file_path}: {e}")
        return False

def main():
    modified_count = 0
    skipped_count = 0
    
    print(f"Listing all files in {PROJECT_DIR}...")
    all_files = []
    for root, dirs, files in os.walk(PROJECT_DIR):
        if ".git" in dirs:
            dirs.remove(".git")
        for file in files:
            if file.endswith(".html"):
                all_files.append(os.path.join(root, file))
    
    total_files = len(all_files)
    print(f"Found {total_files} HTML files. Starting processing with multiprocessing...")
    
    chunk_size = 100
    processed = 0
    with ProcessPoolExecutor() as executor:
        futures = {executor.submit(process_file, f): f for f in all_files}
        for future in as_completed(futures):
            res = future.result()
            if res:
                modified_count += 1
            else:
                skipped_count += 1
            processed += 1
            if processed % 1000 == 0:
                print(f"Progress: {processed}/{total_files} files processed... ({modified_count} modified)")

    print(f"Finished! Total modified: {modified_count}, Total skipped/already had GA: {skipped_count}")

if __name__ == "__main__":
    main()
