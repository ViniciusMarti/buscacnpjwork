<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Painel Importação CNPJ</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            padding: 20px;
            margin: 0;
        }

        .container { max-width: 1200px; margin: 0 auto; }

        h1 { color: #38bdf8; font-weight: 300; }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #1e293b;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #334155;
        }

        .stat-value { font-size: 20px; font-weight: bold; color: #f1f5f9; }
        .stat-label { color: #94a3b8; font-size: 12px; text-transform: uppercase; }

        .controls { margin-bottom: 20px; display: flex; gap: 10px; }

        button {
            padding: 10px 20px;
            font-size: 14px;
            background: #0284c7;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
        }

        button.secondary { background: #475569; }
        button:hover { opacity: 0.8; }

        .progress-bar {
            height: 10px;
            background: #334155;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0ea5e9, #22d3ee);
            width: 0%;
            transition: width 0.5s;
        }

        .status-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            background: #1e293b;
        }

        .running { color: #4ade80; border: 1px solid #4ade80; }
        .stopped { color: #f87171; border: 1px solid #f87171; }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #1e293b;
            font-size: 13px;
        }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #334155; color: #cbd5e1; }
        tr:hover { background: #334155; }

        .chart-container { height: 250px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="container">
        <h1>Painel Importação CNPJ</h1>

        <div class="controls">
            <button onclick="start(false)">Continuar Importação</button>
            <button class="secondary" onclick="start(true)">Zerar e Recomeçar</button>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Status</div>
                <div id="status-tag" class="status-tag">Carregando...</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Linhas Processadas</div>
                <div id="linhas" class="stat-value">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Velocidade Atual</div>
                <div class="stat-value"><span id="vel">0</span> <small>l/s</small></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Fase / Última Atualização</div>
                <div class="stat-value" id="fase">-</div>
                <div id="last-update" style="font-size: 10px; color: #94a3b8;">-</div>
            </div>
        </div>

        <div class="progress-bar">
            <div id="prog" class="progress-fill"></div>
        </div>

        <div class="chart-container">
            <canvas id="chart"></canvas>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Banco de Dados (Shard)</th>
                    <th>Empresas</th>
                    <th>Estabelecimento</th>
                    <th>Sócio</th>
                    <th>Tamanho (Size MB)</th>
                </tr>
            </thead>
            <tbody id="dbs"></tbody>
        </table>
    </div>

    <script>
        let chart = new Chart(document.getElementById("chart"), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Linhas/s',
                    data: [],
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { grid: { color: '#334155' } },
                    x: { display: false }
                }
            }
        });

        function start(reset) {
            let msg = reset ? "Deseja ZERAR tudo e recomeçar do zero?" : "Deseja continuar de onde parou?";
            if (!confirm(msg)) return;
            
            fetch("../import/engine.php" + (reset ? "?reset=1" : ""))
                .then(r => r.text())
                .then(t => alert(t));
        }

        function load() {
            fetch("api.php")
                .then(r => r.json())
                .then(d => {
                    if (d.error) return;

                    const statusTag = document.getElementById("status-tag");
                    statusTag.className = "status-tag " + (d.running ? "running" : "stopped");
                    statusTag.innerText = d.running ? "RODANDO" : "PARADO";

                    document.getElementById("linhas").innerText = d.linhas.toLocaleString();
                    document.getElementById("vel").innerText = d.velocidade.toLocaleString();
                    document.getElementById("fase").innerText = d.fase;
                    
                    const lastUpdate = new Date(d.last_update * 1000).toLocaleTimeString();
                    document.getElementById("last-update").innerText = "Sincronizado às: " + lastUpdate;

                    let tbody = "";
                    for (let db in d.db) {
                        let x = d.db[db];
                        tbody += `<tr>
                            <td><b>${db}</b></td>
                            <td>${x.empresas.toLocaleString()}</td>
                            <td>${x.estabelecimento.toLocaleString()}</td>
                            <td>${x.socio.toLocaleString()}</td>
                            <td>${x.size} MB</td>
                        </tr>`;
                    }
                    document.getElementById("dbs").innerHTML = tbody;

                    // Fake progress visual
                    let p = d.running ? 50 : (d.linhas > 0 ? 100 : 0); 
                    document.getElementById("prog").style.width = p + "%";

                    chart.data.labels.push("");
                    chart.data.datasets[0].data.push(d.velocidade);
                    if (chart.data.labels.length > 50) {
                        chart.data.labels.shift();
                        chart.data.datasets[0].data.shift();
                    }
                    chart.update();
                });
        }

        setInterval(load, 2000);
        load();
    </script>
</body>
</html>
