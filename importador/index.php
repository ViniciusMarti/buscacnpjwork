<?php
// index.php - Dashboard Administrativo do Importador

$progressFile = 'logs/import_progress.json';
$stateFile = 'state.json';

// Handle Start Request
if (isset($_GET['action']) && $_GET['action'] == 'start') {
    ob_start();
    header('Content-Type: application/json');
    try {
        $phpBinary = 'php';
        $scriptPath = __DIR__ . "/cron_import.php";
        $logPath = __DIR__ . "/logs/import_errors.log";
        
        if (!file_exists($scriptPath)) {
            throw new Exception("Script cron_import.php não encontrado.");
        }

        // Tentar capturar qualquer erro do comando exec
        $cmd = "$phpBinary $scriptPath > /dev/null 2>&1 &";
        $output = [];
        $resultCode = 0;
        @exec($cmd, $output, $resultCode);

        // Se o exec falhou e retornou algo
        if ($resultCode !== 0) {
            file_put_contents($logPath, date('[Y-m-d H:i:s] ') . "Exec failed with code $resultCode. Cmd: $cmd" . PHP_EOL, FILE_APPEND);
        }

        ob_clean();
        echo json_encode(['status' => 'started', 'msg' => 'Processo disparado']);
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        file_put_contents(__DIR__ . "/logs/import_errors.log", date('[Y-m-d H:i:s] ') . "Start Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// Handle Progress Polling
if (isset($_GET['action']) && $_GET['action'] == 'poll') {
    ob_start();
    header('Content-Type: application/json');
    $progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : ['status' => 'idle', 'records_imported' => 0, 'speed' => 0];
    $state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    ob_clean();
    echo json_encode(['progress' => $progress, 'state' => $state]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador BigQuery - Busca CNPJ Grátis</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text: #f8fafc;
            --text-dim: #94a3b8;
            --border: rgba(255, 255, 255, 0.1);
            --success: #10b981;
            --error: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.1) 0px, transparent 50%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            width: 100%;
        }

        header {
            margin-bottom: 3rem;
            text-align: center;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(90deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            background: var(--border);
            margin-top: 1rem;
        }

        .status-running {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);
        }

        .card-label {
            color: var(--text-dim);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .card-value {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .progress-container {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .progress-bar-bg {
            height: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #818cf8);
            width: 0%;
            transition: width 0.5s ease;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
        }

        .controls {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        button {
            padding: 1rem 2.5rem;
            border-radius: 0.75rem;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:disabled {
            background: #475569;
            cursor: not_allowed;
        }

        .log-container {
            margin-top: 3rem;
            background: #000;
            border-radius: 0.75rem;
            padding: 1rem;
            height: 200px;
            overflow-y: auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.875rem;
            color: #10b981;
            border: 1px solid var(--border);
        }

        .log-entry { margin-bottom: 0.25rem; }
        .log-error { color: var(--error); }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .pulse { animation: pulse 2s infinite; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Pipeline ETL BigQuery</h1>
        <p style="color: var(--text-dim)">Distribuição em 32 Bancos MySQL (Sharding)</p>
        <div id="status-badge" class="status-badge">Standby</div>
    </header>

    <div class="grid">
        <div class="card">
            <div class="card-label">Tabela Atual</div>
            <div id="val-table" class="card-value">-</div>
        </div>
        <div class="card">
            <div class="card-label">Registros Importados</div>
            <div id="val-records" class="card-value">0</div>
        </div>
        <div class="card">
            <div class="card-label">Velocidade</div>
            <div id="val-speed" class="card-value">0 reg/s</div>
        </div>
        <div class="card">
            <div class="card-label">Última Data</div>
            <div id="val-date" class="card-value">-</div>
        </div>
    </div>

    <div class="progress-container">
        <div class="progress-header">
            <span>Progresso da Tabela</span>
            <span id="val-percent">0%</span>
        </div>
        <div class="progress-bar-bg">
            <div id="progress-fill" class="progress-bar-fill"></div>
        </div>
    </div>

    <div class="controls">
        <button id="btn-start" class="btn-primary">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path d="M4.5 3.5a.5.5 0 01.5.5v12a.5.5 0 01-1 0V4a.5.5 0 01.5-.5zM16.5 3.5a.5.5 0 01.5.5v12a.5.5 0 01-1 0V4a.5.5 0 01.5-.5zM6.5 4.107l9 5.393-9 5.393V4.107z"></path></svg>
            Iniciar Importação
        </button>
    </div>

    <div class="log-container" id="logs">
        <div class="log-entry">Aguardando início...</div>
    </div>
</div>

<script>
    const btnStart = document.getElementById('btn-start');
    const logs = document.getElementById('logs');
    
    function addLog(msg, type = '') {
        const div = document.createElement('div');
        div.className = 'log-entry ' + type;
        div.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
        logs.appendChild(div);
        logs.scrollTop = logs.scrollHeight;
    }

    async function poll() {
        try {
            const res = await fetch('?action=poll');
            const data = await res.json();
            
            if (data.progress) {
                const p = data.progress;
                document.getElementById('val-table').textContent = p.table || '-';
                document.getElementById('val-records').textContent = p.records_imported.toLocaleString();
                document.getElementById('val-speed').textContent = p.speed + ' reg/s';
                
                const badge = document.getElementById('status-badge');
                if (p.status === 'running') {
                    badge.textContent = 'Processando...';
                    badge.className = 'status-badge status-running pulse';
                    btnStart.disabled = true;
                    btnStart.innerHTML = 'Executando...';
                } else if (p.status === 'completed') {
                    badge.textContent = 'Concluído';
                    badge.className = 'status-badge status-running';
                    btnStart.disabled = false;
                    btnStart.innerHTML = 'Reiniciar Importação';
                }
            }

            if (data.state) {
                document.getElementById('val-date').textContent = data.state.ultima_data_processada;
            }

        } catch (e) {
            console.error('Polling error', e);
        }
    }

    btnStart.onclick = async () => {
        if (!confirm('Deseja iniciar o processo de importação automática?')) return;
        
        addLog('Iniciando pipeline...');
        btnStart.disabled = true;
        
        try {
            const res = await fetch('?action=start');
            const text = await res.text();
            
            try {
                const data = JSON.parse(text);
                if (data.status === 'started') {
                    addLog('Processo disparado em segundo plano.');
                } else {
                    addLog('Erro: ' + (data.msg || 'Desconhecido'), 'log-error');
                    btnStart.disabled = false;
                }
            } catch (jsonErr) {
                addLog('Erro de Resposta: Resposta do servidor não é JSON.', 'log-error');
                console.error('Invalid JSON:', text);
                addLog('Dica: O servidor pode estar exibindo um erro PHP. Verifique logs/import_errors.log', 'log-error');
                btnStart.disabled = false;
            }
        } catch (e) {
            addLog('Erro de Rede: ' + e.message, 'log-error');
            btnStart.disabled = false;
        }
    };

    setInterval(poll, 2000);
    poll();
</script>

</body>
</html>
