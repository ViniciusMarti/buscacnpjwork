import os
import re

# GA Tag to be added
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

        # Check if GA ID is already present
        if GA_ID in content:
            return False # Skipped

        # Find <head> or <HEAD> and insert right after it
        new_content = re.sub(r"(<head.*?>)", r"\1\n" + GA_TAG, content, flags=re.IGNORECASE)

        if new_content != content:
            with open(file_path, "w", encoding="utf-8") as f:
                f.write(new_content)
            return True # Modified
        return False
    except Exception as e:
        print(f"Error processing {file_path}: {e}")
        return False

def main():
    modified_count = 0
    skipped_count = 0
    
    print(f"Starting to process files in {PROJECT_DIR}...")
    
    for root, dirs, files in os.walk(PROJECT_DIR):
        # Optional: skip node_modules or other giant dirs if any
        if "node_modules" in dirs:
            dirs.remove("node_modules")
            
        for file in files:
            if file.endswith(".html"):
                file_path = os.path.join(root, file)
                if process_file(file_path):
                    modified_count += 1
                else:
                    skipped_count += 1
                
                if (modified_count + skipped_count) % 1000 == 0:
                    print(f"Processed {modified_count + skipped_count} files... ({modified_count} modified)")

    print(f"Finished! Total modified: {modified_count}, Total skipped/already had GA: {skipped_count}")

if __name__ == "__main__":
    main()
