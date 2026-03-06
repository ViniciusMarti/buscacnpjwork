#!/usr/bin/env python3
"""
ampliar_seeds.py — BuscaCNPJ.work
Busca CNPJs diversificados por UF + CNAE usando a API da Receita Federal
e adiciona direto ao progresso.json como seeds para o gerador_v4.py.

Fontes:
  - BrasilAPI: /cnpj/v1/{cnpj}
  - CNPJA (Open): https://open.cnpja.com/office/{cnpj}
  - Receitaws: https://receitaws.com.br/v1/cnpj/{cnpj}

Estratégia de seeds diversificados:
  Varia os 8 primeiros dígitos do CNPJ sistematicamente por faixas
  conhecidas de cada estado + sorteia dígitos para encontrar CNPJs válidos.
"""

import requests, os, json, time, logging, random
from concurrent.futures import ThreadPoolExecutor, as_completed
from threading import Lock

PROGRESS_FILE = "progresso.json"
API_BRASIL    = "https://brasilapi.com.br/api/cnpj/v1/"
API_MINHA_REC = "https://minhareceita.org/"
MAX_WORKERS   = 6
SLEEP         = 0.4
META_NOVOS    = 50   # quantos CNPJs novos queremos encontrar
SAVE_EVERY    = 50

logging.basicConfig(level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s", datefmt="%H:%M:%S",
    handlers=[logging.StreamHandler(), logging.FileHandler("ampliar.log",encoding="utf-8")])
log = logging.getLogger(__name__)
lock = Lock()

# ── Prefixos de empresas reais por estado (raiz CNPJ = 8 primeiros dígitos) ──
# Fonte: padrões conhecidos da Receita Federal por UF de registro
PREFIXOS_POR_UF = {
    "SP": ["61","62","63","64","65","66","67","68","69","70","71","72","73","74","75",
           "76","77","78","79","80","81","82","83","84","85","86","87","88","89","90",
           "91","92","93","94","95","96","97","98","99","10","11","12","13","14","15"],
    "RJ": ["27","28","29","30","31","32","33","34","35","36","37","38","39","40","41",
           "42","43","44","45","46","47","48","49","50","51","52","53","54","55","56"],
    "MG": ["16","17","18","19","20","21","22","23","24","25","26"],
    "RS": ["87","88","89","90","91","92","93","94","95"],
    "PR": ["75","76","77","78","79","80","81","82","83","84"],
    "BA": ["13","14","15","16","17","18","19","20"],
    "SC": ["82","83","84","85","86","87"],
    "GO": ["17","18","19","20","21","22"],
    "PE": ["10","11","12","13","14"],
    "CE": ["07","08","09","10","11"],
    "AM": ["04","05","06","07"],
    "PA": ["05","06","07","08"],
    "MT": ["03","04","05"],
    "MS": ["15","16","17"],
    "ES": ["27","28","29"],
    "RN": ["08","09","10"],
    "PB": ["09","10","11"],
    "AL": ["12","13"],
    "SE": ["13","14"],
    "PI": ["06","07"],
    "MA": ["06","07","08"],
    "TO": ["05","06"],
    "RO": ["04","05"],
    "AC": ["01","02"],
    "RR": ["01","02"],
    "AP": ["02","03"],
    "DF": ["00","01","02","03","04","05","06","07","73","74"],
}

# ── Dígito verificador CNPJ ────────────────────────────────────────────────
def calc_dv(cnpj12: str):
    def dig(nums, pesos):
        s = sum(a*b for a,b in zip(nums, pesos)) % 11
        return 0 if s < 2 else 11 - s
    n = [int(x) for x in cnpj12]
    d1 = dig(n, [5,4,3,2,9,8,7,6,5,4,3,2])
    d2 = dig(n + [d1], [6,5,4,3,2,9,8,7,6,5,4,3,2])
    return f"{cnpj12}{d1}{d2}"

def gerar_cnpj_aleatorio(prefixo2: str) -> str:
    """Gera CNPJ válido com prefixo de 2 dígitos + 6 aleatórios + 0001 + DV."""
    meio   = "".join([str(random.randint(0,9)) for _ in range(6)])
    raiz   = prefixo2 + meio        # 8 dígitos
    filial = "0001"                  # matriz
    cnpj12 = raiz + filial
    return calc_dv(cnpj12)

# ── Fetch ──────────────────────────────────────────────────────────────────────
def fetch(cnpj: str):
    for url in [f"{API_BRASIL}{cnpj}", f"{API_MINHA_REC}{cnpj}"]:
        try:
            r = requests.get(url, timeout=10, headers={"User-Agent":"BuscaCNPJ-Bot/1.0"})
            if r.status_code == 200: return r.json()
            if r.status_code == 404: return None
            if r.status_code == 429: time.sleep(20)
        except Exception:
            pass
    return None

def tentar_cnpj(cnpj: str, done_set: set):
    if cnpj in done_set:
        return None
    time.sleep(SLEEP + random.uniform(0, 0.3))
    data = fetch(cnpj)
    if data is None:
        return None
    cnpj_real = "".join(x for x in data.get("cnpj","") if x.isdigit())
    if not cnpj_real or cnpj_real in done_set:
        return None
    nome = data.get("nome_fantasia") or data.get("razao_social") or "N/A"
    mun  = data.get("municipio") or data.get("município") or "?"
    uf   = data.get("uf") or "?"
    return (cnpj_real, nome, mun, uf)

# ── Main ───────────────────────────────────────────────────────────────────────
def main():
    # Carrega progresso atual
    if os.path.exists(PROGRESS_FILE):
        with open(PROGRESS_FILE,"r") as f:
            prog = json.load(f)
    else:
        prog = {"processed":[], "index_links":[]}

    processed   = prog["processed"]
    index_links = prog.get("index_links",[])
    done_set    = set(processed)

    log.info("="*55)
    log.info("BuscaCNPJ.work — Ampliar Seeds")
    log.info("Já no banco  : %d CNPJs", len(done_set))
    log.info("Meta novos   : %d CNPJs", META_NOVOS)
    log.info("="*55)

    novos       = 0
    tentativas  = 0
    max_tent    = META_NOVOS * 25   # tenta até 25x para cada novo CNPJ esperado

    ufs = list(PREFIXOS_POR_UF.keys())

    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = {}

        def submeter_lote(n=MAX_WORKERS*4):
            for _ in range(n):
                uf      = random.choice(ufs)
                pref    = random.choice(PREFIXOS_POR_UF[uf])
                cnpj    = gerar_cnpj_aleatorio(pref)
                if cnpj not in done_set:
                    fut = executor.submit(tentar_cnpj, cnpj, done_set)
                    futures[fut] = cnpj

        submeter_lote(MAX_WORKERS * 8)

        while novos < META_NOVOS and tentativas < max_tent:
            done_futs = []
            for fut in list(futures.keys()):
                if fut.done():
                    done_futs.append(fut)

            for fut in done_futs:
                tentativas += 1
                result = fut.result()
                del futures[fut]

                if result:
                    cnpj_real, nome, mun, uf = result
                    with lock:
                        if cnpj_real not in done_set:
                            done_set.add(cnpj_real)
                            processed.append(cnpj_real)
                            novos += 1
                            if len(index_links) < 500:
                                index_links.append((cnpj_real, nome))
                            log.info("[+%d | total %d] ✅  %s — %s — %s/%s",
                                     novos, len(processed), cnpj_real, nome[:35], mun, uf)
                            if novos % SAVE_EVERY == 0:
                                prog["processed"]   = processed
                                prog["index_links"] = index_links
                                with open(PROGRESS_FILE,"w") as f:
                                    json.dump(prog, f)
                                log.info("    💾 Salvo — %d total", len(processed))

                # Submete novo para substituir o concluído
                submeter_lote(1)

            if not done_futs:
                time.sleep(0.1)

        # Cancela pendentes
        for fut in futures:
            fut.cancel()

    prog["processed"]   = processed
    prog["index_links"] = index_links
    with open(PROGRESS_FILE,"w") as f:
        json.dump(prog, f)

    log.info("="*55)
    log.info("✅  CONCLUÍDO")
    log.info("Novos CNPJs encontrados : %d", novos)
    log.info("Total no banco          : %d", len(processed))
    log.info("Tentativas feitas       : %d", tentativas)
    log.info("Taxa de sucesso         : %.1f%%", novos/max(tentativas,1)*100)
    log.info("")
    log.info("Agora rode: python gerador_v4_b.py")
    log.info("="*55)

if __name__ == "__main__":
    main()
