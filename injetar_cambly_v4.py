#!/usr/bin/env python3
"""
injetar_cambly_v4.py — BuscaCNPJ.work
Substitui permanentemente os banners da Hostinger pelos novos banners premium do Cambly.
Inclui 4 variações de imagem e texto.
"""

import re, random, logging, os
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor

# Configurações
CNPJ_DIR      = Path("./cnpj")
LOG_FILE      = "injetar_cambly_v4.log"
MAX_WORKERS   = 60
AFFILIATE_URL = "https://www.cambly.com/invite/VINICIUSCODES?st=030526&sc=4"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
    handlers=[logging.StreamHandler(), logging.FileHandler(LOG_FILE, encoding="utf-8")]
)
log = logging.getLogger(__name__)

# Variações
VARIATIONS = [
    {
        "title": "Domine o Inglês de Negócios",
        "text": "Consultar CNPJs é o primeiro passo para parcerias. Falar inglês nativo é o passo definitivo para o sucesso global.",
        "img": "../../cambly_assets/var1.png",
        "badge": "Opção mais vendida"
    },
    {
        "title": "Explore o Mundo sem Barreiras",
        "text": "Sua jornada como nômade digital ou viajante profissional exige fluência. Aprenda com nativos 24h por dia.",
        "img": "../../cambly_assets/var2.png",
        "badge": "Destaque"
    },
    {
        "title": "Conexão Global em Grupo",
        "text": "Pratique conversação com alunos de todo o mundo em pequenos grupos acolhedores e com tutores nativos.",
        "img": "../../cambly_assets/var3.png",
        "badge": "Melhor custo-benefício"
    },
    {
        "title": "Inglês Fluente no seu Ritmo",
        "text": "Sem horários fixos. Abra o aplicativo e comece a falar em segundos. Flexibilidade total para sua rotina.",
        "img": "../../cambly_assets/var4.png",
        "badge": "Mais flexível"
    }
]

def get_cambly_html(v):
    return f"""
        <div style="margin-bottom:2rem; opacity:0.6; font-weight:800; letter-spacing:2px; text-transform:uppercase; font-size:0.75rem;">Sugestão para seu negócio</div>
        <div class="cambly-banner-card">
            <div class="cambly-content">
                <img src="../../cambly-logo.png" alt="Cambly" class="cambly-logo">
                <h2 class="cambly-title">{v['title']}</h2>
                <p class="cambly-text">{v['text']}</p>
                <div class="cambly-price-row">
                    <div class="cambly-price">R$ 52<span>/mês</span></div>
                    <div class="cambly-badge">{v['badge']}</div>
                </div>
                <a href="{AFFILIATE_URL}" class="cambly-cta" target="_blank">Ativar Oferta Exclusiva →</a>
            </div>
            <div class="cambly-image-side" style="background-image: url('{v['img']}');"></div>
        </div>"""

# Regex para encontrar a seção Hostinger antiga ou o erro da rodada anterior
# Captura do início da partner-section até o div que precede o footer
RE_PARTNER = re.compile(r'<div class="partner-section">.*?(?=</div>\s*<footer>)', re.DOTALL)

def process_file(path: Path):
    try:
        html = path.read_text(encoding="utf-8", errors="replace")
        
        # Se não tem partner-section, ignora
        if '<div class="partner-section">' not in html:
            return "SKIP"
            
        # Escolhe variação aleatória
        v = random.choice(VARIATIONS)
        cambly_block = f'<div class="partner-section">{get_cambly_html(v)}\n    </div>'
        
        # Substitui
        new_html = RE_PARTNER.sub(cambly_block, html)
        
        # Garante que o CSS linkado seja v=1.5 ou superior para pegar os novos estilos
        new_html = new_html.replace('cnpj.css?v=1.3', 'cnpj.css?v=1.5')
        new_html = new_html.replace('cnpj.css?v=1.4', 'cnpj.css?v=1.5')
        
        if new_html != html:
            path.write_text(new_html, encoding="utf-8")
            return "FIXED"
        return "NO_CHANGE"
        
    except Exception as e:
        return f"ERROR:{e}"

def main():
    pages = list(CNPJ_DIR.glob("**/index.html"))
    total = len(pages)
    log.info("Iniciando injeção Cambly v4 em %d páginas...", total)
    
    counts = {"FIXED": 0, "SKIP": 0, "NO_CHANGE": 0, "ERROR": 0}
    
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = {executor.submit(process_file, p): p for p in pages}
        for i, fut in enumerate(futures, 1):
            res = fut.result()
            if res.startswith("ERROR"): counts["ERROR"] += 1
            else: counts[res] += 1
            
            if i % 1000 == 0 or i == total:
                log.info("Progresso: %d/%d | FIXED:%d SKIP:%d ERR:%d", 
                         i, total, counts["FIXED"], counts["SKIP"], counts["ERROR"])

    log.info("Finalizado: %s", counts)

if __name__ == "__main__":
    main()
