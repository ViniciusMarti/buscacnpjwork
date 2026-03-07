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

def process_chunk(file_paths):
    modified = 0
    skipped = 0
    pattern = re.compile(r"(<head.*?>)", re.IGNORECASE)
    
    for file_path in file_paths:
        try:
            with open(file_path, "r", encoding="utf-8") as f:
                content = f.read()

            if GA_ID in content:
                skipped += 1
                continue

            new_content = pattern.sub(r"\1\n" + GA_TAG, content)

            if new_content != content:
                with open(file_path, "w", encoding="utf-8") as f:
                    f.write(new_content)
                modified += 1
            else:
                skipped += 1
        except Exception:
            skipped += 1
    return modified, skipped

def main():
    print(f"Listing files in {PROJECT_DIR}...")
    all_files = []
    for root, dirs, files in os.walk(PROJECT_DIR):
        if ".git" in dirs:
            dirs.remove(".git")
        for file in files:
            if file.endswith(".html"):
                all_files.append(os.path.join(root, file))
    
    total_files = len(all_files)
    print(f"Found {total_files} files. Using chunks of 500 files...")
    
    chunk_size = 500
    chunks = [all_files[i:i + chunk_size] for i in range(0, len(all_files), chunk_size)]
    
    modified_total = 0
    skipped_total = 0
    processed_total = 0
    
    with ProcessPoolExecutor() as executor:
        futures = [executor.submit(process_chunk, ch) for ch in chunks]
        for future in as_completed(futures):
            mod, skip = future.result()
            modified_total += mod
            skipped_total += skip
            processed_total += (mod + skip)
            if processed_total % 2500 <= 500: # Simple threshold for printing
                print(f"Progress: {processed_total}/{total_files} processed... ({modified_total} modified)")

    print(f"Completed! Modified: {modified_total}, Skipped: {skipped_total}")

if __name__ == "__main__":
    main()
