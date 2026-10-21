<?php
// Ficheiro: pages/ai_dashboard.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<style>
    .ai-view-container {
        min-height: 70vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    
    .search-section {
        width: 100%;
        max-width: 700px;
        text-align: center;
        transition: all 0.4s ease-in-out;
    }

    .ai-icon-large {
        font-size: 4rem;
        color: #ffc107;
        margin-bottom: 15px;
        animation: float 3s ease-in-out infinite;
    }

    .loading-section {
        width: 100%;
        text-align: center;
        display: none;
    }

    .result-section {
        width: 100%;
        max-width: 900px;
        display: none;
        animation: fadeIn 0.5s;
    }

    .chart-wrapper {
        background: #fff;
        padding: 15px;
        border-radius: 8px;
        margin-top: 25px;
        height: 350px;
        display: none; /* Escondido por padrão, mostrado apenas se a IA gerar gráfico */
    }

    .ai-text {
        font-size: 1.1rem;
        line-height: 1.7;
        color: #e0e0e0;
    }

    /* Animações */
    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
        100% { transform: translateY(0px); }
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="ai-view-container">

    <!-- 1. ESTADO INICIAL (Campo de Pesquisa Centralizado) -->
    <div id="search-section" class="search-section">
        <i class="bi bi-robot ai-icon-large"></i>
        <h2 class="text-light fw-bold">Consultor IA Trovão</h2>
        <p class="text-muted mb-4 fs-5">Faça perguntas sobre o faturamento, clientes ou serviços e receba insights gerados por Inteligência Artificial.</p>
        
        <form id="ai-form" onsubmit="enviarPergunta(event)">
            <div class="input-group input-group-lg shadow">
                <input type="text" id="user-input" class="form-control bg-dark text-light border-secondary" placeholder="Ex: Qual cliente me deu mais lucro este ano?" required autocomplete="off">
                <button class="btn btn-warning fw-bold px-4" type="submit" id="btn-send">
                    <i class="bi bi-stars me-2"></i>Analisar
                </button>
            </div>
        </form>
        
        <div class="mt-4 text-muted small">
            Dicas: 
            <span class="badge bg-secondary me-1">Quais os serviços mais vendidos?</span>
            <span class="badge bg-secondary me-1">Faça um resumo dos cancelamentos</span>
            <span class="badge bg-secondary">Evolução do faturamento</span>
        </div>
    </div>

    <!-- 2. ESTADO DE CARREGAMENTO (Analisando) -->
    <div id="loading-section" class="loading-section">
        <div class="spinner-grow text-warning" role="status" style="width: 4rem; height: 4rem;">
            <span class="visually-hidden">Carregando...</span>
        </div>
        <h4 class="mt-4 text-light fw-bold">Analisando os dados da TrovãoBrasil...</h4>
        <p class="text-muted">Nossa Inteligência Artificial está processando seu banco de dados para gerar a melhor resposta.</p>
    </div>

    <!-- 3. ESTADO DE RESULTADO (Resposta da IA + Gráfico) -->
    <div id="result-section" class="result-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="text-warning m-0"><i class="bi bi-lightbulb-fill me-2"></i>Insights Gerados</h4>
            <button type="button" class="btn btn-outline-light btn-sm" onclick="novaPergunta()">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Fazer Nova Pergunta
            </button>
        </div>
        
        <div class="card bg-dark border-secondary shadow-lg">
            <div class="card-body p-4">
                <!-- Mostra a pergunta que o usuário fez -->
                <div class="fst-italic text-muted mb-4 pb-3 border-bottom border-secondary">
                    <i class="bi bi-person-circle me-2"></i> <span id="display-user-question"></span>
                </div>
                
                <!-- Resposta em Texto da IA -->
                <div id="ai-text-response" class="ai-text mb-2"></div>
                
                <!-- Gráfico Gerado pela IA (Se houver) -->
                <div id="chart-wrapper" class="chart-wrapper">
                    <canvas id="aiChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentChart = null; // Guarda a instância do gráfico para poder destruir e recriar

async function enviarPergunta(e) {
    e.preventDefault();
    
    const inputField = document.getElementById('user-input');
    const question = inputField.value.trim();
    if (!question) return;

    // Elementos da Interface
    const searchSection = document.getElementById('search-section');
    const loadingSection = document.getElementById('loading-section');
    const resultSection = document.getElementById('result-section');
    
    // Configura a tela de resultado com a pergunta atual
    document.getElementById('display-user-question').textContent = question;
    
    // Transição: Esconde pesquisa, mostra loading
    searchSection.style.display = 'none';
    loadingSection.style.display = 'block';

    try {
        const response = await fetch('../api/ai_consultant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: question })
        });

        const data = await response.json();
        
        // Transição: Esconde loading, mostra resultado
        loadingSection.style.display = 'none';
        resultSection.style.display = 'block';

        if (data.erro) {
            document.getElementById('ai-text-response').innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Erro: ${data.erro}</span>`;
            document.getElementById('chart-wrapper').style.display = 'none';
        } else {
            renderizarResultado(data);
        }

    } catch (error) {
        loadingSection.style.display = 'none';
        resultSection.style.display = 'block';
        document.getElementById('ai-text-response').innerHTML = `<span class="text-danger"><i class="bi bi-wifi-off me-2"></i> Ocorreu um erro de conexão com a IA. Tente novamente mais tarde.</span>`;
        document.getElementById('chart-wrapper').style.display = 'none';
    }
}

function renderizarResultado(iaData) {
    // 1. Renderiza o texto
    document.getElementById('ai-text-response').innerHTML = iaData.resposta_texto;

    // 2. Controla o Gráfico
    const chartWrapper = document.getElementById('chart-wrapper');
    
    if (iaData.tem_grafico && iaData.grafico_dados && iaData.grafico_labels) {
        chartWrapper.style.display = 'block'; // Mostra a div do gráfico
        
        // Se já existir um gráfico de uma pergunta anterior, nós o destruímos
        if (currentChart) {
            currentChart.destroy();
        }
        
        const ctx = document.getElementById('aiChart').getContext('2d');
        
        // Define as cores (usamos paleta baseada no logo/sistema)
        const bgColors = ['#ffc107', '#0d6efd', '#198754', '#dc3545', '#0dcaf0', '#6f42c1', '#fd7e14'];
        
        currentChart = new Chart(ctx, {
            type: iaData.grafico_tipo || 'bar',
            data: {
                labels: iaData.grafico_labels,
                datasets: [{
                    label: iaData.grafico_titulo || 'Dados Analisados',
                    data: iaData.grafico_dados,
                    backgroundColor: iaData.grafico_tipo === 'line' ? 'rgba(255, 193, 7, 0.2)' : bgColors,
                    borderColor: iaData.grafico_tipo === 'line' ? '#ffc107' : bgColors,
                    borderWidth: 1,
                    fill: iaData.grafico_tipo === 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        display: iaData.grafico_tipo === 'pie' || iaData.grafico_tipo === 'doughnut' 
                    } 
                },
                scales: iaData.grafico_tipo === 'pie' || iaData.grafico_tipo === 'doughnut' ? {} : {
                    y: { beginAtZero: true }
                }
            }
        });
    } else {
        // Se a IA decidiu que a pergunta não precisa de gráfico
        chartWrapper.style.display = 'none';
    }
}

function novaPergunta() {
    // Reseta o input e volta para a tela inicial
    document.getElementById('user-input').value = '';
    document.getElementById('result-section').style.display = 'none';
    document.getElementById('search-section').style.display = 'block';
    
    // Foca automaticamente no campo de pesquisa
    setTimeout(() => document.getElementById('user-input').focus(), 100);
}
</script>

<?php include '../templates/footer.php'; ?>