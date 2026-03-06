#!/usr/bin/env python3
"""
supervisor.py — BuscaCNPJ.work
Roda ampliar_seeds.py e gerador_v4_b.py em loop infinito durante a noite.
- Reinicia automaticamente se qualquer um travar ou der erro
- Alterna entre os dois: ampliar (busca CNPJs) -> gerar (cria HTML) -> repete
- Para automaticamente quando o total de arquivos atingir LIMITE_ARQUIVOS
- Log completo em supervisor.log
- Para parar: Ctrl+C
"""

import subprocess
import time
import logging
import sys
import os
from datetime import datetime

# ── Config ────────────────────────────────────────────────────────────────────
PYTHON             = sys.executable
SCRIPT_SEED        = "ampliar_seeds.py"
SCRIPT_GERAR       = "gerador_v4_b.py"
LOG_FILE           = "supervisor.log"
PAUSA_ENTRE_CICLOS = 10
TIMEOUT_SCRIPT     = 3600  # 1 hora por script

# Pasta monitorada e limite de arquivos
PASTA_PROJETO  = os.path.dirname(os.path.abspath(__file__))
LIMITE_ARQUIVOS = 100000

# ── Logging ───────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%H:%M:%S",
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
    ],
)
log = logging.getLogger(__name__)


# ── Contagem de arquivos ───────────────────────────────────────────────────────
def contar_arquivos(pasta: str) -> int:
    """Conta todos os arquivos recursivamente dentro da pasta."""
    total = 0
    for _, _, files in os.walk(pasta):
        total += len(files)
    return total


def limite_atingido() -> bool:
    total = contar_arquivos(PASTA_PROJETO)
    log.info("   📂 Arquivos na pasta: %d / %d", total, LIMITE_ARQUIVOS)
    return total >= LIMITE_ARQUIVOS


# ── Executor ──────────────────────────────────────────────────────────────────
def rodar(script: str, ciclo: int) -> bool:
    """Roda um script e retorna True se terminou com sucesso."""
    if not os.path.exists(script):
        log.error("❌ Script nao encontrado: %s", script)
        return False

    log.info("▶️ [Ciclo %d] Iniciando: %s", ciclo, script)
    inicio = time.time()

    try:
        proc = subprocess.Popen(
            [PYTHON, script],
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            encoding="utf-8",
            errors="replace",
        )

        while True:
            line = proc.stdout.readline()
            if line:
                log.info("  [%s] %s", script, line.rstrip())
            elif proc.poll() is not None:
                break

            if time.time() - inicio > TIMEOUT_SCRIPT:
                log.warning("⏰ Timeout! Matando %s apos %.0f min...",
                            script, TIMEOUT_SCRIPT / 60)
                proc.kill()
                return False

        rc      = proc.returncode
        elapsed = (time.time() - inicio) / 60
        if rc == 0:
            log.info("✅ [%s] Concluido em %.1f min", script, elapsed)
            return True
        else:
            log.warning("⚠️ [%s] Saiu com codigo %d apos %.1f min", script, rc, elapsed)
            return False

    except Exception as e:
        log.error("💥 Erro ao rodar %s: %s", script, e)
        return False


# ── Git auto-push ─────────────────────────────────────────────────────────────
def git_push(ciclo: int):
    """Faz commit e push automatico apos cada ciclo completo."""
    try:
        log.info("📤 [Ciclo %d] Fazendo git push...", ciclo)
        ts = datetime.now().strftime("%d/%m/%Y %H:%M")

        subprocess.run(["git", "add", "."], check=True, capture_output=True)
        result = subprocess.run(
            ["git", "commit", "-m", f"Auto-update {ts} — ciclo {ciclo}"],
            capture_output=True, text=True
        )

        if "nothing to commit" in result.stdout:
            log.info("   ℹ️ Nada novo para commitar.")
            return

        subprocess.run(["git", "push"], check=True, capture_output=True)
        log.info("   ✅ Push feito — ciclo %d", ciclo)
    except subprocess.CalledProcessError as e:
        log.warning("   ⚠️ Git push falhou: %s", e)
    except Exception as e:
        log.error("   💥 Erro no git push: %s", e)


# ── Main loop ─────────────────────────────────────────────────────────────────
def main():
    log.info("=" * 60)
    log.info("🚀 Supervisor iniciado — BuscaCNPJ.work")
    log.info("  Scripts : %s -> %s", SCRIPT_SEED, SCRIPT_GERAR)
    log.info("  Timeout : %d min por script", TIMEOUT_SCRIPT // 60)
    log.info("  Limite  : %d arquivos em %s", LIMITE_ARQUIVOS, PASTA_PROJETO)
    log.info("  Para parar: Ctrl+C")
    log.info("=" * 60)

    ciclo              = 1
    erros_consecutivos = 0

    while True:
        try:
            # ── Verifica limite ANTES de cada ciclo ───────────────────────────
            if limite_atingido():
                log.info("")
                log.info("🛑 LIMITE ATINGIDO — %d arquivos na pasta.", LIMITE_ARQUIVOS)
                log.info("  Fazendo push final e encerrando...")
                git_push(ciclo)
                log.info("✅ Supervisor encerrado com sucesso apos %d ciclos.", ciclo - 1)
                break

            log.info("")
            log.info("━" * 55)
            log.info("🔄 CICLO %d — %s", ciclo, datetime.now().strftime("%d/%m/%Y %H:%M"))
            log.info("━" * 55)

            # 1. Busca novos CNPJs
            ok1 = rodar(SCRIPT_SEED, ciclo)

            time.sleep(PAUSA_ENTRE_CICLOS)

            # Verifica limite entre os dois scripts
            if limite_atingido():
                log.info("🛑 Limite atingido apos ampliar_seeds. Pulando geracao e encerrando.")
                git_push(ciclo)
                break

            # 2. Gera paginas HTML
            ok2 = rodar(SCRIPT_GERAR, ciclo)

            # 3. Git push
            git_push(ciclo)

            if ok1 and ok2:
                erros_consecutivos = 0
                log.info("🎉 Ciclo %d completo com sucesso!", ciclo)
            else:
                erros_consecutivos += 1
                log.warning("⚠️ Ciclo %d teve falhas (%d consecutivos)",
                            ciclo, erros_consecutivos)

            if erros_consecutivos >= 5:
                espera = 300
                log.warning("😴 Muitos erros — aguardando %d seg antes de continuar...", espera)
                time.sleep(espera)
                erros_consecutivos = 0
            else:
                time.sleep(PAUSA_ENTRE_CICLOS)

            ciclo += 1

        except KeyboardInterrupt:
            log.info("")
            log.info("🛑 Supervisor encerrado pelo usuario apos %d ciclos.", ciclo - 1)
            log.info("  Fazendo push final...")
            git_push(ciclo)
            break

        except Exception as e:
            log.error("💥 Erro inesperado no supervisor: %s", e)
            log.info("  Aguardando 60s antes de retomar...")
            time.sleep(60)


if __name__ == "__main__":
    main()
