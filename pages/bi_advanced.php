<?php
// Ficheiro: pages/bi_advanced.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado. Apenas administradores podem aceder a esta página.");
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Dependências -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/gridstack.js/10.0.1/gridstack.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
    /* Estilo Ultra-Moderno (Glassmorphism + Dark Mode Avançado) */
    body {
        font-family: 'Inter', sans-serif !important;
        background: #0f111a !important; 
    }

    .bi-wrapper {
        display: flex;
        height: calc(100vh - 130px);
        gap: 20px;
        padding: 10px;
    }

    .glass-panel {
        background: rgba(30, 33, 44, 0.7);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        color: #fff;
        padding: 20px;
    }

    .sidebar-visuals {
        width: 250px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        gap: 15px;
        overflow-y: auto;
    }

    .visual-item {
        background: rgba(45, 49, 66, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        cursor: grab;
        transition: all 0.3s ease;
    }

    .visual-item:hover {
        background: rgba(65, 70, 92, 0.8);
        transform: translateY(-2px);
        border-color: rgba(255, 255, 255, 0.2);
    }
    .visual-item i { font-size: 2rem; margin-bottom: 5px; display: inline-block;}

    .canvas-container {
        flex-grow: 1;
        position: relative;
        overflow-y: auto;
    }

    .inspector-panel {
        width: 320px;
        flex-shrink: 0;
        display: none; 
        flex-direction: column;
        overflow-y: auto;
    }

    .form-label { font-size: 0.85rem; color: #a9b0c0; text-transform: uppercase; letter-spacing: 1px; margin-top: 15px;}
    .form-select, .form-control {
        background: rgba(15, 17, 26, 0.8) !important;
        border: 1px solid rgba(255,255,255,0.1) !important;
        color: #fff !important;
        border-radius: 6px;
    }
    .form-select:focus, .form-control:focus { box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.5) !important; }

    .grid-stack-item-content {
        background: rgba(30, 33, 44, 0.6) !important;
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 12px;
        color: #fff;
        padding: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
    }

    .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        font-weight: 600;
        color: #e2e8f0;
    }

    .widget-tools i { cursor: pointer; color: #94a3b8; margin-left: 8px; transition: 0.2s; }
    .widget-tools i:hover { color: #fff; }

    #loader-overlay {
        position: absolute; top:0; left:0; right:0; bottom:0;
        background: rgba(15, 17, 26, 0.9);
        z-index: 1000;
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        color: #fff; border-radius: 12px;
    }

    .grid-stack {
        min-height: 600px !important;
        height: 100%;
        background: rgba(0,0,0,0.1);
        border-radius: 8px;
    }

    .selected-widget { border: 2px solid #3b82f6 !important; }

    /* Cross Filter Banner */
    #cross-filter-banner {
        display: none;
        background: #3b82f6;
        color: white;
        padding: 5px 15px;
        border-radius: 5px;
        font-size: 0.9rem;
        margin-left: 10px;
    }

    /* Minimal Table Styles */
    .bi-table { font-size: 0.85rem; }
    .bi-table th { background: rgba(255,255,255,0.05); color: #94a3b8; position: sticky; top: 0; }
    .bi-table td, .bi-table th { border-bottom: 1px solid rgba(255,255,255,0.05); padding: 5px; }

</style>

<!-- TOP BAR CONTROLS -->
<div class="d-flex justify-content-between align-items-center px-3 mt-2 mb-2 flex-wrap gap-2">
    <div class="d-flex align-items-center">
        <h4 class="text-white fw-bold mb-0" style="font-family: 'Inter';">
            <i class="bi bi-bar-chart-fill text-primary me-2"></i>Analytics <span class="badge bg-primary fs-6 ms-2">Pro</span>
        </h4>
        <div id="cross-filter-banner" class="d-flex align-items-center">
            <span>Filtro Ativo: <strong id="cross-filter-label"></strong></span>
            <i class="bi bi-x-circle ms-2" style="cursor:pointer" onclick="limparCrossFilter()"></i>
        </div>
    </div>
    
    <!-- Filtros Globais -->
    <div class="d-flex align-items-center gap-2 bg-dark rounded p-1 border border-secondary">
        <span class="text-light ms-2"><i class="bi bi-calendar-range"></i> Período:</span>
        <input type="date" id="filtro-data-ini" class="form-control form-control-sm bg-dark text-light border-0" onchange="aplicarFiltros()">
        <span class="text-light">até</span>
        <input type="date" id="filtro-data-fim" class="form-control form-control-sm bg-dark text-light border-0" onchange="aplicarFiltros()">
        <button class="btn btn-sm btn-outline-secondary" onclick="limparDatas()" title="Limpar Datas"><i class="bi bi-x"></i></button>
    </div>

    <!-- Workspace Controls -->
    <div class="d-flex align-items-center gap-2">
        <select id="load-dashboard" class="form-select form-select-sm bg-dark text-light border-secondary" style="width:180px" onchange="carregarPainel(this.value)">
            <option value="">Carregar Painel...</option>
        </select>
        <input type="text" id="dash-nome" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Nome do Painel" style="width:180px">
        <button class="btn btn-sm btn-success" onclick="salvarPainel()"><i class="bi bi-floppy"></i> Salvar</button>
        <button class="btn btn-outline-light btn-sm" onclick="exportarPDF()"><i class="bi bi-file-pdf"></i></button>
    </div>
</div>

<div class="bi-wrapper">
    
    <!-- 1. BARRA ESQUERDA (VISUAIS) -->
    <div class="sidebar-visuals glass-panel">
        <h6 class="text-muted text-uppercase fw-bold mb-3" style="font-size: 0.8rem;">Gráficos</h6>
        
        <div class="visual-type grid-stack-item" gs-w="4" gs-h="3" data-vtype="bar" draggable="true">
            <div class="grid-stack-item-content visual-item text-primary"><i class="bi bi-bar-chart-line-fill"></i><br>Barras</div>
        </div>
        <div class="visual-type grid-stack-item" gs-w="3" gs-h="3" data-vtype="pie" draggable="true">
            <div class="grid-stack-item-content visual-item text-warning"><i class="bi bi-pie-chart-fill"></i><br>Pizza</div>
        </div>
        <div class="visual-type grid-stack-item" gs-w="3" gs-h="3" data-vtype="doughnut" draggable="true">
            <div class="grid-stack-item-content visual-item text-danger"><i class="bi bi-record-circle-fill"></i><br>Rosca</div>
        </div>
        <div class="visual-type grid-stack-item" gs-w="5" gs-h="3" data-vtype="line" draggable="true">
            <div class="grid-stack-item-content visual-item text-success"><i class="bi bi-graph-up"></i><br>Linha</div>
        </div>
        <div class="visual-type grid-stack-item" gs-w="3" gs-h="2" data-vtype="kpi" draggable="true">
            <div class="grid-stack-item-content visual-item text-info"><i class="bi bi-123"></i><br>Cartão KPI</div>
        </div>
        <div class="visual-type grid-stack-item" gs-w="4" gs-h="4" data-vtype="table" draggable="true">
            <div class="grid-stack-item-content visual-item text-light"><i class="bi bi-table"></i><br>Tabela</div>
        </div>
    </div>

    <!-- 2. CANVAS CENTRAL -->
    <div class="canvas-container glass-panel" id="canvas-pdf">
        <div id="loader-overlay">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
            <h5 class="mt-3 fw-bold">Processando Data Warehouse...</h5>
        </div>
        <div class="grid-stack"></div>
    </div>

    <!-- 3. INSPETOR DIREITO -->
    <div class="inspector-panel glass-panel" id="inspector">
        <h5 class="text-white fw-bold border-bottom border-secondary pb-2">Propriedades</h5>
        <div class="text-muted small mb-3">Configure os eixos do visual selecionado.</div>

        <label class="form-label">Título</label>
        <input type="text" id="prop-title" class="form-control" onchange="atualizarWidgetAtual()">

        <label class="form-label text-primary"><i class="bi bi-list-ul me-1"></i>Eixo X (Agrupamento)</label>
        <select id="prop-dim" class="form-select" onchange="atualizarWidgetAtual()">
            <option value="">Nenhum...</option>
            <option value="cliente">Cliente</option>
            <option value="profissional">Profissional</option>
            <option value="mes_ano">Mês/Ano</option>
            <option value="placa_carreta">Placa do Veículo</option>
            <option value="turno">Turno</option>
            <option value="metodo_pagamento">Método Pagamento</option>
            <option value="carga_ultima">Tipo de Carga</option>
            <option value="status">Status da Ordem</option>
        </select>

        <label class="form-label text-success"><i class="bi bi-hash me-1"></i>Eixo Y (Métrica)</label>
        <select id="prop-measure" class="form-select" onchange="atualizarWidgetAtual()">
            <option value="contagem">Contagem (Qtd)</option>
            <option value="valor_final">Faturamento Bruto (R$)</option>
            <option value="subtotal">Subtotal (R$)</option>
            <option value="desconto">Descontos Concedidos (R$)</option>
        </select>

        <label class="form-label"><i class="bi bi-calculator me-1"></i>Operação</label>
        <select id="prop-op" class="form-select" onchange="atualizarWidgetAtual()">
            <option value="soma">Soma / Total</option>
            <option value="media">Média</option>
        </select>
        
        <label class="form-label"><i class="bi bi-palette me-1"></i>Cor Base</label>
        <input type="color" id="prop-color" class="form-control form-control-color w-100 bg-dark border-secondary" value="#3b82f6" onchange="atualizarWidgetAtual()">

        <button class="btn btn-outline-success btn-sm mt-4 w-100" onclick="exportarCSV()"><i class="bi bi-file-earmark-excel"></i> Baixar Dados (CSV)</button>

        <div class="mt-4 p-3 bg-dark rounded border border-danger text-center" id="kpi-warning" style="display:none;">
            <small class="text-danger">Visuais de Cartão não usam o Eixo X.</small>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gridstack.js/10.0.1/gridstack-all.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    let globalDataset = []; 
    let widgetsConfig = {}; 
    let activeWidgetId = null; 
    let chartsInstances = {}; 
    let currentCrossFilter = null; // { widgetId, dim, val }

    const defaultColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'];

    // Inicializar Gridstack
    var grid = GridStack.init({
        acceptWidgets: true,
        cellHeight: 90,
        margin: 15,
        float: true,
        minRow: 1
    });

    GridStack.setupDragIn('.visual-type', { appendTo: 'body', helper: 'clone' });

    // Startup
    async function carregarTudo() {
        await carregarListaPaineis();
        await carregarDataset();
    }
    carregarTudo();

    async function carregarDataset() {
        try {
            const res = await fetch('../api/bi_dataset.php');
            globalDataset = await res.json();
            document.getElementById('loader-overlay').style.display = 'none';
        } catch(e) {
            alert('Erro ao carregar os dados analíticos.');
        }
    }

    async function carregarListaPaineis() {
        try {
            const res = await fetch('../api/bi_load.php');
            const data = await res.json();
            const select = document.getElementById('load-dashboard');
            select.innerHTML = '<option value="">Carregar Painel...</option>';
            data.forEach(d => {
                select.innerHTML += `<option value="${d.id}">${d.nome} (${d.data_criacao.split(' ')[0]})</option>`;
            });
        } catch(e) {}
    }

    grid.on('added', function(e, items) {
        items.forEach(function(item) {
            let vtype = item.el.getAttribute('data-vtype');
            if(vtype) {
                if (!item.id) {
                    item.id = 'w_' + Math.random().toString(36).substr(2, 9);
                    grid.update(item.el, {id: item.id});
                }
                
                if(!widgetsConfig[item.id]) {
                    widgetsConfig[item.id] = {
                        type: vtype,
                        title: vtype === 'kpi' ? 'Indicador Chave' : 'Gráfico Personalizado',
                        dim: vtype === 'kpi' ? null : 'cliente',
                        measure: 'contagem',
                        op: 'soma',
                        color: '#3b82f6'
                    };
                }

                renderizarCasca(item);
                processarGrafico(item.id);
                selecionarWidget(item.id);
            }
        });
    });

    function renderizarCasca(item) {
        let contentEl = item.el.querySelector('.grid-stack-item-content');
        if(!contentEl) {
            contentEl = document.createElement('div');
            contentEl.className = 'grid-stack-item-content';
            item.el.innerHTML = '';
            item.el.appendChild(contentEl);
        }
        
        contentEl.setAttribute('onclick', `selecionarWidget('${item.id}')`);
        contentEl.style.cursor = 'pointer';

        contentEl.innerHTML = `
            <div class="widget-header">
                <span class="w-title">${widgetsConfig[item.id].title}</span>
                <div class="widget-tools">
                    <i class="bi bi-gear-fill" onclick="selecionarWidget('${item.id}')"></i>
                    <i class="bi bi-trash-fill text-danger" onclick="removerWidget('${item.id}', event)"></i>
                </div>
            </div>
            <div class="w-body" style="flex-grow:1; position:relative; width:100%; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                <canvas id="canvas_${item.id}"></canvas>
                <div id="table_container_${item.id}" style="display:none; width:100%; height:100%; overflow:auto;"></div>
                <div id="kpi_${item.id}" style="display:none; text-align:center;">
                    <h1 class="text-white fw-bold mb-0" style="font-size:3rem;" id="kpi_val_${item.id}">-</h1>
                    <small class="text-muted text-uppercase" id="kpi_label_${item.id}">-</small>
                </div>
            </div>
        `;
    }

    // Engine: Aplica Filtros Globais + Cross Filtering
    function aplicarFiltros() {
        document.querySelectorAll('.grid-stack-item').forEach(el => {
            let id = el.getAttribute('gs-id');
            if(id) processarGrafico(id);
        });
    }

    function limparDatas() {
        document.getElementById('filtro-data-ini').value = "";
        document.getElementById('filtro-data-fim').value = "";
        aplicarFiltros();
    }

    function limparCrossFilter() {
        currentCrossFilter = null;
        document.getElementById('cross-filter-banner').style.display = 'none';
        aplicarFiltros();
    }

    // Processa lógica e Plota
    function processarGrafico(widgetId) {
        const conf = widgetsConfig[widgetId];
        if(!conf) return;

        // Dataset local filtrado
        let dadosFiltrados = globalDataset.filter(row => {
            let dtIni = document.getElementById('filtro-data-ini').value;
            let dtFim = document.getElementById('filtro-data-fim').value;
            if(dtIni && row.data_lavagem < dtIni) return false;
            if(dtFim && row.data_lavagem > dtFim) return false;
            
            // Filtro Cruzado (exceto para o próprio gráfico que o gerou, para manter o contexto)
            if(currentCrossFilter && currentCrossFilter.widgetId !== widgetId) {
                let v = row[currentCrossFilter.dim] || 'Não Informado';
                if(v !== currentCrossFilter.val) return false;
            }
            return true;
        });

        if(conf.type === 'kpi') {
            document.getElementById(`canvas_${widgetId}`).style.display = 'none';
            document.getElementById(`table_container_${widgetId}`).style.display = 'none';
            document.getElementById(`kpi_${widgetId}`).style.display = 'block';
            
            let total = 0;
            let count = 0;
            dadosFiltrados.forEach(row => {
                let val = conf.measure === 'contagem' ? 1 : (row[conf.measure] || 0);
                total += val;
                count++;
            });

            if(conf.op === 'media' && count > 0) total = total / count;
            
            let formatado = conf.measure !== 'contagem' 
                ? total.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}) 
                : total;

            document.getElementById(`kpi_val_${widgetId}`).innerText = formatado;
            document.getElementById(`kpi_val_${widgetId}`).style.color = conf.color || '#fff';
            document.getElementById(`kpi_label_${widgetId}`).innerText = conf.measure === 'contagem' ? 'Serviços' : 'Reais (R$)';
            return;
        }

        if(!conf.dim) return; 
        
        let agrupado = {};
        
        dadosFiltrados.forEach(row => {
            let xVal = row[conf.dim] || 'Não Informado';
            let yVal = conf.measure === 'contagem' ? 1 : (row[conf.measure] || 0);

            if(!agrupado[xVal]) { agrupado[xVal] = { sum: 0, count: 0 }; }
            agrupado[xVal].sum += yVal;
            agrupado[xVal].count++;
        });

        let labels = Object.keys(agrupado);
        let dados = labels.map(l => conf.op === 'media' ? (agrupado[l].sum / agrupado[l].count) : agrupado[l].sum);

        // Sort desc
        let combinados = labels.map((l, i) => ({l, d: dados[i]})).sort((a,b) => b.d - a.d);
        labels = combinados.map(i => i.l);
        dados = combinados.map(i => i.d);

        // Salva os dados processados na config para Exportação CSV
        conf.lastLabels = labels;
        conf.lastDados = dados;

        // Limita Top N
        if(labels.length > 20 && conf.type !== 'line') {
            labels = labels.slice(0, 20);
            dados = dados.slice(0, 20);
        }

        if(conf.type === 'table') {
            document.getElementById(`canvas_${widgetId}`).style.display = 'none';
            document.getElementById(`kpi_${widgetId}`).style.display = 'none';
            let tc = document.getElementById(`table_container_${widgetId}`);
            tc.style.display = 'block';
            
            let html = `<table class="table bi-table table-dark table-hover w-100">
                <thead><tr><th>${conf.dim.toUpperCase()}</th><th class="text-end">${conf.measure.toUpperCase()}</th></tr></thead><tbody>`;
            labels.forEach((l, i) => {
                let formatted = conf.measure !== 'contagem' ? dados[i].toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}) : dados[i];
                html += `<tr><td>${l}</td><td class="text-end">${formatted}</td></tr>`;
            });
            html += `</tbody></table>`;
            tc.innerHTML = html;
            return;
        }

        desenharChart(widgetId, labels, dados, conf);
    }

    function desenharChart(widgetId, labels, data, conf) {
        document.getElementById(`canvas_${widgetId}`).style.display = 'block';
        document.getElementById(`table_container_${widgetId}`).style.display = 'none';
        document.getElementById(`kpi_${widgetId}`).style.display = 'none';

        const ctx = document.getElementById(`canvas_${widgetId}`).getContext('2d');
        
        if(chartsInstances[widgetId]) chartsInstances[widgetId].destroy();

        if(conf.type === 'line' && conf.dim === 'mes_ano') {
            let combinados = labels.map((l, i) => ({l, d: data[i]})).sort((a,b) => a.l.localeCompare(b.l));
            labels = combinados.map(i => i.l);
            data = combinados.map(i => i.d);
        }

        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = 'Inter';

        // Gera paleta dinâmica caso seja pie/doughnut baseada na cor escolhida
        let bgColors = defaultColors;
        if(conf.type === 'pie' || conf.type === 'doughnut') {
            bgColors = labels.map((_, i) => defaultColors[i % defaultColors.length]);
        } else {
            bgColors = conf.color || '#3b82f6';
            if(conf.type === 'line') bgColors = (conf.color || '#3b82f6') + '33'; // 20% opacity
        }

        chartsInstances[widgetId] = new Chart(ctx, {
            type: conf.type,
            data: {
                labels: labels,
                datasets: [{
                    label: conf.measure === 'contagem' ? 'Qtd' : 'Valor',
                    data: data,
                    backgroundColor: bgColors,
                    borderColor: conf.type === 'line' ? (conf.color || '#3b82f6') : 'transparent',
                    borderWidth: 2,
                    fill: conf.type === 'line',
                    tension: 0.4 
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: (e, elements) => {
                    // Cross Filtering Trigger
                    if (elements.length > 0) {
                        let idx = elements[0].index;
                        let clickedLabel = labels[idx];
                        
                        if (currentCrossFilter && currentCrossFilter.val === clickedLabel) {
                            limparCrossFilter();
                        } else {
                            currentCrossFilter = { widgetId: widgetId, dim: conf.dim, val: clickedLabel };
                            document.getElementById('cross-filter-banner').style.display = 'flex';
                            document.getElementById('cross-filter-label').innerText = `${conf.dim} = ${clickedLabel}`;
                            aplicarFiltros();
                        }
                    }
                },
                plugins: {
                    legend: { display: (conf.type === 'pie' || conf.type === 'doughnut'), position: 'right', labels: {color: '#fff'} }
                },
                scales: (conf.type === 'pie' || conf.type === 'doughnut') ? {} : {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function selecionarWidget(id) {
        activeWidgetId = id;
        
        document.querySelectorAll('.grid-stack-item-content').forEach(el => el.classList.remove('selected-widget'));
        let canvasEl = document.getElementById(`canvas_${id}`);
        if(canvasEl) {
            let widgetEl = canvasEl.closest('.grid-stack-item-content');
            if(widgetEl) widgetEl.classList.add('selected-widget');
        }

        let conf = widgetsConfig[id];
        document.getElementById('prop-title').value = conf.title;
        document.getElementById('prop-measure').value = conf.measure;
        document.getElementById('prop-op').value = conf.op;
        document.getElementById('prop-color').value = conf.color || '#3b82f6';
        
        if(conf.type === 'kpi') {
            document.getElementById('prop-dim').disabled = true;
            document.getElementById('prop-dim').value = "";
            document.getElementById('kpi-warning').style.display = 'block';
        } else {
            document.getElementById('prop-dim').disabled = false;
            document.getElementById('prop-dim').value = conf.dim || "";
            document.getElementById('kpi-warning').style.display = 'none';
        }

        document.getElementById('inspector').style.display = 'flex';
    }

    function atualizarWidgetAtual() {
        if(!activeWidgetId) return;

        widgetsConfig[activeWidgetId].title = document.getElementById('prop-title').value;
        widgetsConfig[activeWidgetId].dim = document.getElementById('prop-dim').value;
        widgetsConfig[activeWidgetId].measure = document.getElementById('prop-measure').value;
        widgetsConfig[activeWidgetId].op = document.getElementById('prop-op').value;
        widgetsConfig[activeWidgetId].color = document.getElementById('prop-color').value;

        let canvasEl = document.getElementById(`canvas_${activeWidgetId}`);
        if(canvasEl) {
            let wTitle = canvasEl.closest('.grid-stack-item-content').querySelector('.w-title');
            if (wTitle) wTitle.innerText = widgetsConfig[activeWidgetId].title;
        }

        processarGrafico(activeWidgetId);
    }

    function removerWidget(id, event) {
        event.stopPropagation();
        let canvasEl = document.getElementById(`canvas_${id}`);
        if (canvasEl) {
            let el = canvasEl.closest('.grid-stack-item');
            if (el) {
                if(chartsInstances[id]) chartsInstances[id].destroy();
                delete chartsInstances[id];
                delete widgetsConfig[id];
                grid.removeWidget(el);
                
                if(activeWidgetId === id) {
                    document.getElementById('inspector').style.display = 'none';
                    activeWidgetId = null;
                }
            }
        }
    }

    grid.on('resizestop', function(e, item) {
        if(chartsInstances[item.id]) chartsInstances[item.id].resize();
    });

    // Funcionalidades Enterprise (Salvar, Carregar, Exportar)
    async function salvarPainel() {
        let nome = document.getElementById('dash-nome').value;
        if(!nome) { alert('Digite um nome para o painel antes de salvar!'); return; }
        
        let layout = grid.save();
        let payload = {
            nome: nome,
            config_json: JSON.stringify({ layout: layout, widgets: widgetsConfig })
        };

        let res = await fetch('../api/bi_save.php', {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json' }
        });
        
        let json = await res.json();
        if(json.sucesso) {
            alert('Dashboard Salvo com Sucesso!');
            carregarListaPaineis();
        } else {
            alert('Erro: ' + json.erro);
        }
    }

    async function carregarPainel(id) {
        if(!id) return;
        let res = await fetch(`../api/bi_load.php?id=${id}`);
        let data = await res.json();
        
        if(data.erro) { alert(data.erro); return; }

        let config = JSON.parse(data.config_json);
        
        document.getElementById('dash-nome').value = data.nome;
        
        // Limpa tudo
        grid.removeAll();
        widgetsConfig = config.widgets;
        chartsInstances = {};
        
        // Remove trigger do drop temporariamente para evitar conflito com load
        let layout = config.layout;
        layout.forEach(item => {
            let n = grid.addWidget({
                id: item.id,
                x: item.x, y: item.y, w: item.w, h: item.h,
                content: '' // Vazio, renderizarCasca fará o html
            });
            n.setAttribute('data-vtype', widgetsConfig[item.id].type);
            renderizarCasca({el: n, id: item.id});
            processarGrafico(item.id);
        });
    }

    function exportarCSV() {
        if(!activeWidgetId || !widgetsConfig[activeWidgetId]) return;
        let conf = widgetsConfig[activeWidgetId];
        
        if(!conf.lastLabels) { alert("Nenhum dado para exportar."); return; }

        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += `${conf.dim || 'Categoria'},${conf.measure}\n`;

        for(let i=0; i<conf.lastLabels.length; i++){
            let l = conf.lastLabels[i].toString().replace(/,/g, ''); // limpa virgulas
            csvContent += `${l},${conf.lastDados[i]}\n`;
        }

        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `export_${conf.title}.csv`);
        document.body.appendChild(link);
        link.click();
    }

    function exportarPDF() {
        document.querySelectorAll('.grid-stack-item-content').forEach(el => el.classList.remove('selected-widget'));
        document.getElementById('inspector').style.display = 'none';

        const elemento = document.getElementById('canvas-pdf');
        const opt = {
            margin:       10,
            filename:     'TricaoAnalytics.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#0f111a' }, 
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
        };

        html2pdf().set(opt).from(elemento).save();
    }

</script>

<?php include '../templates/footer.php'; ?>
