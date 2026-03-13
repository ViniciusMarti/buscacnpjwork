<?php
/**
 * Importador de CNPJ - Painel Administrativo
 * Local: /public_html/importador/index.php
 */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador de CNPJ | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --border: rgba(255, 255, 255, 0.1);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(59, 130, 246, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.05) 0%, transparent 40%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            width: 100%;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--primary);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-online { color: var(--success); }
        .status-idle { color: var(--text-muted); }
        .status-running { color: var(--warning); animation: pulse 2s infinite; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.03);
            padding: 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .progress-section {
            margin-bottom: 2rem;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .progress-bar-container {
            height: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 0.5rem;
            border: 1px solid var(--border);
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #60a5fa);
            width: 0%;
            transition: width 0.3s ease;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
        }

        .controls {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        button {
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            outline: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:disabled {
            background: #475569;
            cursor: not-allowed;
            transform: none;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .log-container {
            background: #000;
            border-radius: 0.75rem;
            padding: 1rem;
            height: 400px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8125rem;
            border: 1px solid var(--border);
            margin-top: 1rem;
        }

        .log-entry {
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }

        .log-time { color: var(--text-muted); }
        .log-info { color: var(--primary); }
        .log-success { color: var(--success); }
        .log-warning { color: var(--warning); }
        .log-error { color: var(--danger); font-weight: bold; }

        .shard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .shard-box {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            transition: all 0.2s;
            cursor: default;
        }

        .shard-box.pending { color: var(--text-muted); }
        .shard-box.processing { background: var(--warning); color: #000; border-color: var(--warning); }
        .shard-box.done { background: var(--success); color: #000; border-color: var(--success); }
        .shard-box.error { background: var(--danger); color: white; border-color: var(--danger); }

        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-database"></i>
                CNPJ Import System
            </div>
            <div id="connection-status" class="status-badge status-idle">
                <i class="fas fa-circle"></i>
                <span>Pronto</span>
            </div>
        </header>

        <div class="main-grid">
            <div class="left-col">
                <div class="card">
                    <div class="progress-section">
                        <div class="progress-info">
                            <span id="label-current-action">Aguardando início...</span>
                            <span id="percent-current">0%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div id="bar-current" class="progress-bar" style="width: 0%;"></div>
                        </div>
                        
                        <div class="progress-info" style="margin-top: 1rem;">
                            <span>Progresso Total (32 Shards)</span>
                            <span id="percent-total">0%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div id="bar-total" class="progress-bar" style="width: 0%; background: linear-gradient(90deg, var(--success), #34d399);"></div>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-label">Registros Importados</div>
                            <div id="stat-count" class="stat-value">0</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Taxa (Linhas/seg)</div>
                            <div id="stat-rate" class="stat-value">0</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Tempo Decorrido</div>
                            <div id="stat-time" class="stat-value">00:00:00</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Erros Críticos</div>
                            <div id="stat-errors" class="stat-value" style="color: var(--danger);">0</div>
                        </div>
                    </div>

                    <div class="controls">
                        <button id="btn-start" class="btn-primary">
                            <i class="fas fa-play"></i> Iniciar Importação
                        </button>
                        <button id="btn-pause" class="btn-outline" disabled>
                            <i class="fas fa-pause"></i> Pausar
                        </button>
                        <button id="btn-upload-toggle" class="btn-outline">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Files
                        </button>
                        <button id="btn-reset" class="btn-outline" style="margin-left: auto; color: var(--danger); border-color: var(--danger);">
                            <i class="fas fa-undo"></i> Resetar Progresso
                        </button>
                    </div>

                    <!-- Seção de Upload (Oculta por padrão) -->
                    <div id="upload-section" class="card" style="margin-top: 1.5rem; display: none; background: rgba(255,255,255,0.02);">
                        <h4 style="margin-bottom: 1rem;"><i class="fas fa-upload"></i> Central de Upload</h4>
                        <form id="form-upload" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 0.5rem; align-items: end;">
                            <div>
                                <label class="stat-label">Shard</label>
                                <select name="shard" style="width: 100%; padding: 0.5rem; border-radius: 4px; background: #1e293b; color: white; border: 1px solid var(--border);">
                                    <?php for($i=1; $i<=32; $i++) echo "<option value='$i'>Banco $i</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="stat-label">Tipo</label>
                                <select name="type" style="width: 100%; padding: 0.5rem; border-radius: 4px; background: #1e293b; color: white; border: 1px solid var(--border);">
                                    <option value="empresas">Empresas</option>
                                    <option value="estabelecimentos">Estabelecimentos</option>
                                    <option value="socios">Sócios</option>
                                </select>
                            </div>
                            <div>
                                <label class="stat-label">Arquivo (.csv.gz)</label>
                                <input type="file" name="file" accept=".gz" style="width: 100%; font-size: 0.75rem; color: var(--text-muted);">
                            </div>
                            <button type="submit" class="btn-primary" style="padding: 0.5rem 1rem;">Enviar</button>
                        </form>
                        <div id="upload-status" style="margin-top: 0.5rem; font-size: 0.8rem;"></div>
                        <div id="upload-progress-container" style="display:none; height: 4px; background: #334155; margin-top: 10px; border-radius: 2px;">
                            <div id="upload-progress-bar" style="width: 0%; height: 100%; background: var(--primary); transition: width 0.3s;"></div>
                        </div>
                    </div>

                    <div id="log-console" class="log-container">
                        <div class="log-entry"><span class="log-time">[00:00:00]</span> <span class="log-info">Sistema pronto. Clique em iniciar para começar a importação das 32 bases.</span></div>
                    </div>
                </div>
            </div>

            <div class="right-col">
                <div class="card" style="height: 100%;">
                    <h3>Shards Status (1-32)</h3>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem;">Progresso individual por banco de dados</p>
                    
                    <div id="shard-grid" class="shard-grid">
                        <!-- Shard boxes added via JS -->
                    </div>

                    <div style="margin-top: 2rem;">
                        <div class="stat-label">Info do Servidor</div>
                        <div style="font-size: 0.8125rem; line-height: 1.6;">
                            <div><i class="fas fa-server"></i> Host: u582732852</div>
                            <div><i class="fas fa-microchip"></i> PHP 8.1+</div>
                            <div><i class="fas fa-hdd"></i> Gzip Streaming Enabled</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const btnStart = document.getElementById('btn-start');
        const btnPause = document.getElementById('btn-pause');
        const btnReset = document.getElementById('btn-reset');
        const logConsole = document.getElementById('log-console');
        const shardGrid = document.getElementById('shard-grid');
        const statusBadge = document.getElementById('connection-status');
        
        let isRunning = false;
        let eventSource = null;
        let pollInterval = null;

        // Initialize shard grid
        for (let i = 1; i <= 32; i++) {
            const box = document.createElement('div');
            box.id = `shard-${i}`;
            box.className = 'shard-box pending';
            box.textContent = i;
            shardGrid.appendChild(box);
        }

        function addLog(msg, type = 'info') {
            const time = new Date().toLocaleTimeString('pt-BR');
            const entry = document.createElement('div');
            entry.className = `log-entry`;
            entry.innerHTML = `<span class="log-time">[${time}]</span> <span class="log-${type}">${msg}</span>`;
            logConsole.appendChild(entry);
            logConsole.scrollTop = logConsole.scrollHeight;
        }

        function updateUI(data) {
            if (!data) return;

            document.getElementById('stat-count').textContent = data.total_rows.toLocaleString();
            document.getElementById('stat-rate').textContent = data.rate.toLocaleString();
            document.getElementById('stat-time').textContent = data.elapsed_time;
            document.getElementById('stat-errors').textContent = data.error_count;

            document.getElementById('percent-current').textContent = data.file_percent + '%';
            document.getElementById('bar-current').style.width = data.file_percent + '%';
            
            document.getElementById('percent-total').textContent = data.total_percent + '%';
            document.getElementById('bar-total').style.width = data.total_percent + '%';

            document.getElementById('label-current-action').textContent = `Processando: ${data.current_shard} > ${data.current_file}`;

            // Update shard boxes
            if (data.shard_status) {
                for (const [id, status] of Object.entries(data.shard_status)) {
                    const box = document.getElementById(`shard-${id}`);
                    if (box) box.className = `shard-box ${status}`;
                }
            }

            if (data.last_log && data.last_log !== window.lastLogMsg) {
                addLog(data.last_log, data.last_log_type || 'info');
                window.lastLogMsg = data.last_log;
            }
        }

        async function fetchProgress() {
            try {
                const res = await fetch('progress.php?t=' + Date.now());
                const data = await res.json();
                updateUI(data);
                
                if (data.status === 'completed' && isRunning) {
                    stopAll();
                    addLog('IMPORTAÇÃO CONCLUÍDA COM SUCESSO!', 'success');
                    statusBadge.className = 'status-badge status-online';
                    statusBadge.querySelector('span').textContent = 'Concluído';
                }
            } catch (e) {
                console.error('Error fetching progress:', e);
            }
        }

        function startImport() {
            isRunning = true;
            btnStart.disabled = true;
            btnPause.disabled = false;
            statusBadge.className = 'status-badge status-running';
            statusBadge.querySelector('span').textContent = 'Importando...';
            
            addLog('Iniciando processo de importação...', 'info');

            // Start the worker (async)
            fetch('import_worker.php?action=start').catch(e => console.error(e));

            // Start polling progress
            pollInterval = setInterval(fetchProgress, 1000);
        }

        function stopAll() {
            isRunning = false;
            btnStart.disabled = false;
            btnPause.disabled = true;
            clearInterval(pollInterval);
            statusBadge.className = 'status-badge status-idle';
            statusBadge.querySelector('span').textContent = 'Pausado';
        }

        btnStart.addEventListener('click', startImport);
        
        btnPause.addEventListener('click', () => {
            fetch('import_worker.php?action=pause');
            stopAll();
            addLog('Importação pausada pelo usuário.', 'warning');
        });

        btnReset.addEventListener('click', () => {
            if (confirm('Tem certeza que deseja resetar todo o progresso? Isso não apagará os dados do banco, apenas o marcador de onde o script parou.')) {
                fetch('import_worker.php?action=reset').then(() => {
                    location.reload();
                });
            }
        });

        // Toggle Seção de Upload
        const btnUploadToggle = document.getElementById('btn-upload-toggle');
        const uploadSection = document.getElementById('upload-section');
        btnUploadToggle.addEventListener('click', () => {
            uploadSection.style.display = uploadSection.style.display === 'none' ? 'block' : 'none';
        });

        // Handler de Upload
        const formUpload = document.getElementById('form-upload');
        const uploadStatus = document.getElementById('upload-status');
        const uploadProgressBar = document.getElementById('upload-progress-bar');
        const uploadProgressContainer = document.getElementById('upload-progress-container');

        formUpload.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(formUpload);
            
            uploadStatus.innerHTML = '<span style="color: var(--warning)">Enviando arquivo... aguarde.</span>';
            uploadProgressContainer.style.display = 'block';
            uploadProgressBar.style.width = '0%';

            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload_handler.php', true);

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        uploadProgressBar.style.width = percent + '%';
                    }
                };

                xhr.onload = function() {
                    const result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        uploadStatus.innerHTML = `<span style="color: var(--success)"><i class="fas fa-check"></i> ${result.message}</span>`;
                        addLog(result.message, 'success');
                        formUpload.reset();
                    } else {
                        uploadStatus.innerHTML = `<span style="color: var(--danger)"><i class="fas fa-times"></i> Erro: ${result.message}</span>`;
                        addLog('Erro no upload: ' + result.message, 'error');
                    }
                    setTimeout(() => { uploadProgressContainer.style.display = 'none'; }, 3000);
                };

                xhr.onerror = function() {
                    uploadStatus.innerHTML = '<span style="color: var(--danger)">Erro de conexão. Verifique o limite de upload do servidor.</span>';
                };

                xhr.send(formData);
            } catch (err) {
                uploadStatus.innerHTML = '<span style="color: var(--danger)">Erro inesperado.</span>';
            }
        });

        // Initial fetch to show progress if already running or partially done
        fetchProgress();
    </script>
</body>
</html>
