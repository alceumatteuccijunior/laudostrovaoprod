<?php
// Ficheiro: pages/bi_builder.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado. Apenas administradores podem aceder a esta página.");
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Gridstack CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/gridstack.js/10.0.1/gridstack.min.css" rel="stylesheet"/>

<style>
    .bi-container {
        display: flex;
        height: calc(100vh - 80px);
        gap: 20px;
        padding-top: 15px;
    }

    .widgets-sidebar {
        width: 300px;
        background: #1e1e1e;
        border-radius: 8px;
        padding: 15px;
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0,0,0,0.5);
    }

    .widget-item {
        background: #2a2a2a;
        border: 1px dashed #555;
        padding: 15px;
        margin-bottom: 10px;
        cursor: grab;
        border-radius: 6px;
        text-align: center;
        color: #fff;
        transition: 0.2s;
    }

    .widget-item:hover {
        border-color: #ffc107;
        background: #333;
    }

    .canvas-area {
        flex-grow: 1;
        background: #121212;
        border: 2px dashed #444;
        border-radius: 8px;
        position: relative;
        overflow-y: auto;
    }

    /* Gridstack overrides para modo escuro */
    .grid-stack-item-content {
        background: #252525 !important;
        border-radius: 8px;
        color: #fff;
        padding: 15px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        position: relative;
    }
    
    .remove-widget {
        position: absolute;
        top: 5px;
        right: 5px;
        color: #dc3545;
        cursor: pointer;
        display: none;
    }
    
    .grid-stack-item-content:hover .remove-widget {
        display: block;
    }

    .toolbar {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    /* Ocultar barra superior na hora do PDF */
    .print-hide { display: block; }
</style>

<div class="toolbar print-hide">
    <div>
        <h2 class="text-white"><i class="bi bi-grid-1x2-fill text-warning me-2"></i>Super BI Drag & Drop</h2>
        <p class="text-muted mb-0">Arraste os indicadores da esquerda para o quadro da direita e monte seu painel.</p>
    </div>
    <div class="d-flex align-items-center">
        <button class="btn btn-danger" onclick="limparPainel()">
            <i class="bi bi-trash me-2"></i>Limpar
        </button>
        <button class="btn btn-primary ms-2" onclick="exportarPDF()">
            <i class="bi bi-file-earmark-pdf-fill me-2"></i>Exportar PDF
        </button>
    </div>
</div>

<div class="bi-container">
    <!-- Barra Lateral de Widgets (Oculta na Exportação) -->
    <div class="widgets-sidebar print-hide">
        <h5 class="text-warning mb-3">Widgets Disponíveis</h5>
        
        <!-- Elementos arrastáveis pro Gridstack -->
        <div class="newWidget grid-stack-item" data-gs-w="3" data-gs-h="2" data-type="kpi_lavagens_mes">
            <div class="widget-item">
                <i class="bi bi-123 fs-2 text-primary"></i><br>
                Lavagens do Mês (KPI)
            </div>
        </div>

        <div class="newWidget grid-stack-item" data-gs-w="3" data-gs-h="2" data-type="kpi_clientes_ativos">
            <div class="widget-item">
                <i class="bi bi-people-fill fs-2 text-success"></i><br>
                Clientes Ativos 30d (KPI)
            </div>
        </div>

        <div class="newWidget grid-stack-item" data-gs-w="6" data-gs-h="4" data-type="grafico_evolucao_lavagens">
            <div class="widget-item">
                <i class="bi bi-graph-up fs-2 text-info"></i><br>
                Evolução (Gráfico Linha)
            </div>
        </div>

        <div class="newWidget grid-stack-item" data-gs-w="4" data-gs-h="4" data-type="grafico_top_clientes">
            <div class="widget-item">
                <i class="bi bi-trophy-fill fs-2 text-warning"></i><br>
                Top 5 Clientes (Barra)
            </div>
        </div>
    </div>

    <!-- O Canvas -->
    <div class="canvas-area" id="painel-pdf">
        <div class="grid-stack"></div>
    </div>
</div>

<!-- Scripts Necessários -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gridstack.js/10.0.1/gridstack-all.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    // Inicialização do Gridstack
    var grid = GridStack.init({
        acceptWidgets: '.newWidget', // Aceita elementos com esta classe
        float: true,
        cellHeight: 80,
        margin: 10
    });

    // Configurar o Drag and Drop da Sidebar para o Canvas
    GridStack.setupDragIn('.newWidget', { revert: 'invalid', scroll: false, appendTo: 'body', helper: 'clone' });

    // Armazena as instâncias dos gráficos para podermos redimensionar/destruir
    var chartsInstances = {};

    // Evento quando um widget é solto no grid
    grid.on('added', function(e, items) {
        items.forEach(function(item) {
            // O tipo de dado (grafico, kpi) vem do elemento arrastado
            let type = item.el.getAttribute('data-type');
            if(type) {
                renderizarConteudo(item, type);
            }
        });
    });

    // Evento para redimensionamento
    grid.on('resizestop', function(e, item) {
        // Se houver gráfico neste item, forçamos o resize
        let widgetId = item.id;
        if(chartsInstances[widgetId]) {
            chartsInstances[widgetId].resize();
        }
    });

    // Função para renderizar conteúdo dinâmico da API
    async function renderizarConteudo(item, type) {
        let contentEl = item.el.querySelector('.grid-stack-item-content');
        
        // Atribuir um ID único ao item se não tiver
        if (!item.id) {
            item.id = 'widget_' + Math.random().toString(36).substr(2, 9);
            grid.update(item.el, {id: item.id});
        }

        // Adiciona botão de remover
        contentEl.innerHTML = `
            <i class="bi bi-x-circle-fill remove-widget" onclick="removerWidget('${item.id}')"></i>
            <div class="spinner-border text-warning" role="status"><span class="visually-hidden">Carregando...</span></div>
        `;

        try {
            const res = await fetch(`../api/bi_data.php?acao=${type}`);
            const data = await res.json();
            
            contentEl.innerHTML = `<i class="bi bi-x-circle-fill remove-widget" onclick="removerWidget('${item.id}')"></i>`;

            if(type.startsWith('kpi_')) {
                // Renderiza HTML simples para KPI
                contentEl.innerHTML += `
                    <div class="text-center w-100">
                        <h6 class="text-muted text-uppercase fw-bold">${data.subtitulo}</h6>
                        <h2 class="text-white fw-bold my-2" style="font-size: 2.5rem;">${data.valor}</h2>
                        <small class="text-${data.variacao >= 0 ? 'success' : 'danger'} fw-bold">${data.variacao_texto}</small>
                    </div>
                `;
            } 
            else if(type.startsWith('grafico_')) {
                // Prepara Canvas para o Chart.js
                contentEl.innerHTML += `
                    <h6 class="text-white align-self-start w-100 mb-2">${data.titulo}</h6>
                    <div style="position: relative; height: 100%; width: 100%;">
                        <canvas id="canvas_${item.id}"></canvas>
                    </div>
                `;

                const ctx = document.getElementById(`canvas_${item.id}`).getContext('2d');
                
                let chartType = type === 'grafico_evolucao_lavagens' ? 'line' : 'bar';
                let colors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0'];

                chartsInstances[item.id] = new Chart(ctx, {
                    type: chartType,
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Total',
                            data: data.dados,
                            backgroundColor: chartType === 'line' ? 'rgba(255, 193, 7, 0.2)' : colors,
                            borderColor: chartType === 'line' ? '#ffc107' : colors,
                            borderWidth: 1,
                            fill: chartType === 'line'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }
        } catch (error) {
            contentEl.innerHTML = `<i class="bi bi-x-circle-fill remove-widget" onclick="removerWidget('${item.id}')"></i>
                                   <div class="text-danger">Erro ao carregar</div>`;
        }
    }

    function removerWidget(id) {
        let el = document.querySelector(`[gs-id="${id}"]`);
        if (el) {
            if(chartsInstances[id]) {
                chartsInstances[id].destroy();
                delete chartsInstances[id];
            }
            grid.removeWidget(el);
        }
    }

    function limparPainel() {
        if(confirm('Tem certeza que deseja limpar todo o painel?')) {
            grid.removeAll();
            chartsInstances = {};
        }
    }

    function exportarPDF() {
        const elemento = document.getElementById('painel-pdf');
        
        // Antes de exportar, ocultamos botões de remover e aplicamos fundo branco/limpo
        const opt = {
            margin:       10,
            filename:     'SuperBI_Painel.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#1e1e1e' },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
        };

        // Adiciona classe para impressão para esconder lixeiras temporariamente
        document.querySelectorAll('.remove-widget').forEach(el => el.style.display = 'none');
        
        // Troca borda tracejada
        elemento.style.border = 'none';

        html2pdf().set(opt).from(elemento).save().then(() => {
            // Restaura as visualizações
            document.querySelectorAll('.remove-widget').forEach(el => el.style.display = '');
            elemento.style.border = '2px dashed #444';
        });
    }
</script>

<?php include '../templates/footer.php'; ?>
