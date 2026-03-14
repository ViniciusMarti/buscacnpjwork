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
            padding: 40px;
            margin: 0;
        }

        h1 {
            color: #38bdf8;
            font-weight: 300;
            margin-bottom: 30px;
        }

        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #1e293b;
            padding: 20px;
            border-radius: 12px;
            flex: 1;
            border: 1px solid #334155;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #f1f5f9;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 14px;
            text-transform: uppercase;
        }

        button {
            padding: 12px 24px;
            font-size: 16px;
            background: #0284c7;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
            margin-bottom: 30px;
        }

        button:hover {
            background: #0369a1;
        }

        .bar-container {
            margin-bottom: 30px;
        }

        .bar {
            height: 12px;
            background: #334155;
            border-radius: 6px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: linear-gradient(90deg, #0ea5e9, #22d3ee);
            width: 0%;
            transition: width 0.5s ease-out;
        }

        canvas {
            background: #1e293b;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #334155;
            max-height: 300px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #1e293b;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #334155;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }

        th {
            background: #334155;
            color: #cbd5e1;
            font-weight: 600;
        }

        tr:hover {
            background: #334155;
        }
    </style>
</head>
<body>

    <h1>Painel Importação CNPJ</h1>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-label">Total Linhas</div>
            <div id="linhas" class="stat-value">0</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Velocidade</div>
            <div class="stat-value"><span id="vel">0</span> <small style="font-size: 14px;">linhas/s</small></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Fase Atual</div>
            <div id="fase" class="stat-value">-</div>
        </div>
    </div>

    <button onclick="start()">Iniciar Importação</button>

    <div class="bar-container">
        <div class="stat-label" style="margin-bottom: 5px;">Progresso Geral</div>
        <div class="bar">
            <div id="prog" class="progress"></div>
        </div>
    </div>

    <canvas id="chart"></canvas>

    <table>
        <thead>
            <tr>
                <th>DB</th>
                <th>Empresas</th>
                <th>Estabelecimento</th>
                <th>Socio</th>
                <th>Size MB</th>
            </tr>
        </thead>
        <tbody id="dbs"></tbody>
    </table>

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
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: '#334155' } },
                    x: { grid: { display: false } }
                }
            }
        });

        function start() {
            if (!confirm("Deseja realmente iniciar a importação?")) return;
            fetch("../import/engine.php")
                .then(r => r.text())
                .then(t => alert(t))
                .catch(e => alert("Erro ao iniciar: " + e));
        }

        function load() {
            fetch("api.php")
                .then(r => r.json())
                .then(d => {
                    if (d.error) return;

                    document.getElementById("linhas").innerText = d.linhas.toLocaleString();
                    document.getElementById("vel").innerText = d.velocidade.toLocaleString();
                    document.getElementById("fase").innerText = d.fase;

                    let tbody = "";
                    let totalL = 0;
                    
                    // Simple progress calculation based on phase? 
                    // Or maybe just based on the fact that we have 3 phases.
                    // For now, let's just make it look "alive"
                    let progress = d.running ? (d.linhas % 100) : (d.linhas > 0 ? 100 : 0);
                    document.getElementById("prog").style.width = progress + "%";

                    for (let db in d.db) {
                        let x = d.db[db];
                        tbody += `
                        <tr>
                            <td>${db}</td>
                            <td>${x.empresas.toLocaleString()}</td>
                            <td>${x.estabelecimento.toLocaleString()}</td>
                            <td>${x.socio.toLocaleString()}</td>
                            <td>${x.size}</td>
                        </tr>
                        `;
                    }

                    document.getElementById("dbs").innerHTML = tbody;

                    const now = new Date().toLocaleTimeString();
                    chart.data.labels.push(now);
                    chart.data.datasets[0].data.push(d.velocidade);
                    
                    if (chart.data.labels.length > 20) {
                        chart.data.labels.shift();
                        chart.data.datasets[0].data.shift();
                    }
                    chart.update();
                })
                .catch(err => console.error("Erro na API:", err));
        }

        setInterval(load, 2000);
        load();
    </script>

</body>
</html>
