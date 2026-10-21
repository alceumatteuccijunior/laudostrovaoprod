<?php
// Ficheiro: pages/reports.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

// --- Lógica dos Filtros ---
// Captura os filtros da URL (GET) com validação rigorosa
$data_inicio = !empty($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = !empty($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-t');
$status = !empty($_GET['status']) ? $_GET['status'] : 'todos';
$turno = !empty($_GET['turno']) ? $_GET['turno'] : 'todos';
$cliente_id = !empty($_GET['cliente_id']) ? $_GET['cliente_id'] : '';
$cliente_nome_display = !empty($_GET['cliente_nome_display']) ? $_GET['cliente_nome_display'] : '';

// Parâmetros base para as consultas SQL
$params = [
    ':data_inicio' => $data_inicio . ' 00:00:00',
    ':data_fim' => $data_fim . ' 23:59:59'
];

// Monta as condições WHERE alteradas para data_registo
$conds = ["data_registo BETWEEN :data_inicio AND :data_fim"];
$conds_os = ["os.data_registo BETWEEN :data_inicio AND :data_fim"];

if ($status !== 'todos') {
    $conds[] = "status = :status";
    $conds_os[] = "os.status = :status";
    $params[':status'] = $status;
}
if ($turno !== 'todos') {
    $conds[] = "turno = :turno";
    $conds_os[] = "os.turno = :turno";
    $params[':turno'] = $turno;
}
if (!empty($cliente_id)) {
    $conds[] = "cliente_id = :cliente_id";
    $conds_os[] = "os.cliente_id = :cliente_id";
    $params[':cliente_id'] = $cliente_id;
}

$sql_where = " WHERE " . implode(" AND ", $conds);
$sql_where_os = " WHERE " . implode(" AND ", $conds_os);

try {
    // 1. KPIs SUPERIORES
    $sql_kpi = "SELECT 
                    SUM(CASE WHEN status = 'Ativo' THEN valor_final ELSE 0 END) as faturado,
                    SUM(desconto) as descontos,
                    COUNT(id) as total_pedidos,
                    COUNT(CASE WHEN status = 'Cancelado' THEN 1 END) as cancelados
                FROM ordens_servico" . $sql_where;
    $stmt_kpi = $pdo->prepare($sql_kpi);
    $stmt_kpi->execute($params);
    $kpi = $stmt_kpi->fetch();

    // 2. EVOLUÇÃO DO FATURAMENTO (Preenchimento inteligente com 0)
    $periodo_grafico_faturamento = $_GET['periodo_grafico'] ?? 'mes';
    
    $labels_raw = [];
    $valores_dict = [];
    
    if ($periodo_grafico_faturamento == 'dia') {
        $date_format_sql = '%Y-%m-%d'; 
        $date_interval_sql = '30 DAY';
        // Gera os últimos 30 dias
        for ($i = 29; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-$i days"));
            $labels_raw[] = $dia;
            $valores_dict[$dia] = 0;
        }
    } else {
        $date_format_sql = '%Y-%m'; 
        $date_interval_sql = '12 MONTH';
        // Gera os últimos 12 meses de forma segura
        for ($i = 11; $i >= 0; $i--) {
            $mes = date('Y-m', strtotime("first day of -$i months"));
            $labels_raw[] = $mes;
            $valores_dict[$mes] = 0;
        }
    }
    
    $sql_chart = "SELECT DATE_FORMAT(data_registo, '{$date_format_sql}') as periodo, SUM(valor_final) as total 
                  FROM ordens_servico 
                  WHERE status = 'Ativo' AND data_registo >= DATE_SUB(CURDATE(), INTERVAL {$date_interval_sql})";
    
    $chart_params = [];
    if (!empty($cliente_id)) {
        $sql_chart .= " AND cliente_id = :c_id";
        $chart_params[':c_id'] = $cliente_id;
    }
    $sql_chart .= " GROUP BY periodo ORDER BY periodo ASC";
    
    $stmt_chart = $pdo->prepare($sql_chart);
    $stmt_chart->execute($chart_params);
    $chart_data = $stmt_chart->fetchAll();

    // Dicionário de meses em Português
    $meses_pt_extenso = [
        '01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril', 
        '05'=>'Maio', '06'=>'Junho', '07'=>'Julho', '08'=>'Agosto', 
        '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro'
    ];

    // Popula o dicionário com os valores reais vindos do banco
    foreach($chart_data as $d) {
        if (isset($valores_dict[$d['periodo']])) {
            $valores_dict[$d['periodo']] = (float)$d['total'];
        }
    }

    $labels = [];
    $values = [];
    foreach($labels_raw as $raw) {
        if ($periodo_grafico_faturamento == 'dia') {
            $labels[] = date('d/m', strtotime($raw));
        } else {
            $partes = explode('-', $raw);
            if (count($partes) == 2) {
                // Transforma "2026-03" em "Março/2026"
                $labels[] = $meses_pt_extenso[$partes[1]] . '/' . $partes[0];
            } else {
                $labels[] = $raw;
            }
        }
        $values[] = $valores_dict[$raw];
    }

    // 3. ANÁLISE DE CANCELAMENTOS POR MOTIVO
    $sql_cancelamentos = "SELECT motivo_cancelamento, COUNT(id) as total FROM ordens_servico" . $sql_where . " AND status = 'Cancelado' GROUP BY motivo_cancelamento";
    $stmt_canc = $pdo->prepare($sql_cancelamentos);
    $stmt_canc->execute($params);
    $cancelamentos_data = $stmt_canc->fetchAll();
    
    $cancel_labels = [];
    $cancel_values = [];
    foreach($cancelamentos_data as $c) {
        $cancel_labels[] = !empty($c['motivo_cancelamento']) ? $c['motivo_cancelamento'] : 'Não especificado';
        $cancel_values[] = (int)$c['total'];
    }

    // 3.1 FATURAMENTO NO PERÍODO (Respeita 100% o filtro de data)
    $sql_faturamento_filtro = "SELECT DATE_FORMAT(data_registo, '%Y-%m-%d') as dia, SUM(valor_final) as total 
                               FROM ordens_servico" . $sql_where . " AND status = 'Ativo' 
                               GROUP BY dia ORDER BY dia ASC";
    $stmt_fat_filtro = $pdo->prepare($sql_faturamento_filtro);
    $stmt_fat_filtro->execute($params);
    $fat_filtro_data = $stmt_fat_filtro->fetchAll();
    
    // Preenche com 0 os dias vazios no intervalo do filtro
    $data_atual_iter = strtotime(substr($data_inicio, 0, 10));
    $data_fim_iter = strtotime(substr($data_fim, 0, 10));
    $fat_filtro_dict = [];
    // Evita loop infinito se o filtro de data for gigante
    $dias_limite = 0;
    while ($data_atual_iter <= $data_fim_iter && $dias_limite < 366) {
        $dia_str = date('Y-m-d', $data_atual_iter);
        $fat_filtro_dict[$dia_str] = 0;
        $data_atual_iter = strtotime("+1 day", $data_atual_iter);
        $dias_limite++;
    }
    
    foreach ($fat_filtro_data as $d) {
        if (isset($fat_filtro_dict[$d['dia']])) {
            $fat_filtro_dict[$d['dia']] = (float)$d['total'];
        }
    }
    
    $fat_filtro_labels = [];
    $fat_filtro_values = [];
    foreach ($fat_filtro_dict as $dia => $val) {
        $fat_filtro_labels[] = date('d/m', strtotime($dia));
        $fat_filtro_values[] = $val;
    }

    // 3.2 ANÁLISE DE REPROVAS POR MOTIVO
    $sql_reprovas = "SELECT motivo_reprova, COUNT(id) as total FROM ordens_servico" . $sql_where . " AND reprovado = 1 GROUP BY motivo_reprova";
    $stmt_repr = $pdo->prepare($sql_reprovas);
    $stmt_repr->execute($params);
    $reprovas_data = $stmt_repr->fetchAll();
    
    $repr_labels = [];
    $repr_values = [];
    foreach($reprovas_data as $r) {
        $repr_labels[] = !empty($r['motivo_reprova']) ? $r['motivo_reprova'] : 'Não especificado';
        $repr_values[] = (int)$r['total'];
    }

    // 4. RANKING DE PROFISSIONAIS (1º Responsável)
    $sql_prof = "SELECT p.nome, COUNT(os.id) as total_servicos 
                 FROM ordens_servico os 
                 JOIN profissionais p ON os.profissional_id = p.id 
                 " . $sql_where_os . " AND os.status = 'Ativo' 
                 GROUP BY p.id ORDER BY total_servicos DESC LIMIT 10";
    $stmt_prof = $pdo->prepare($sql_prof);
    $stmt_prof->execute($params);
    $prof_data = $stmt_prof->fetchAll();
    
    $prof_labels = [];
    $prof_values = [];
    foreach($prof_data as $p) {
        $prof_labels[] = $p['nome'];
        $prof_values[] = (int)$p['total_servicos'];
    }

    // 5. RANKING TOP CLIENTES (Faturamento)
    $sql_top_clientes = "SELECT c.nome_razao_social, SUM(os.valor_final) as total_faturado 
                         FROM ordens_servico os 
                         JOIN clientes c ON os.cliente_id = c.id 
                         " . $sql_where_os . " AND os.status = 'Ativo' 
                         GROUP BY c.id ORDER BY total_faturado DESC LIMIT 10";
    $stmt_top_clientes = $pdo->prepare($sql_top_clientes);
    $stmt_top_clientes->execute($params);
    $top_clientes_data = $stmt_top_clientes->fetchAll();

    $clientes_labels = [];
    $clientes_values = [];
    foreach($top_clientes_data as $c) {
        $clientes_labels[] = $c['nome_razao_social'];
        $clientes_values[] = (float)$c['total_faturado'];
    }

    // 6. RANKING DE SERVIÇOS MAIS REALIZADOS (NOVO)
    $sql_top_servicos = "SELECT cs.nome_servico, COUNT(osi.id) as total_executado 
                         FROM ordens_servico os 
                         JOIN ordens_servico_itens osi ON os.id = osi.ordem_servico_id 
                         JOIN catalogo_servicos cs ON osi.servico_id = cs.id 
                         " . $sql_where_os . " AND os.status = 'Ativo' 
                         GROUP BY cs.id ORDER BY total_executado DESC LIMIT 10";
    $stmt_top_servicos = $pdo->prepare($sql_top_servicos);
    $stmt_top_servicos->execute($params);
    $top_servicos_data = $stmt_top_servicos->fetchAll();

    $servicos_labels = [];
    $servicos_values = [];
    foreach($top_servicos_data as $s) {
        $servicos_labels[] = $s['nome_servico'];
        $servicos_values[] = (int)$s['total_executado'];
    }

    // 7. EVOLUÇÃO DE DESEMPENHO DA EQUIPE (Top 5 com histórico obrigatório de 6 meses)
    $sql_top5 = "SELECT p.id, p.nome FROM ordens_servico os JOIN profissionais p ON os.profissional_id = p.id " . $sql_where_os . " AND os.status = 'Ativo' GROUP BY p.id ORDER BY COUNT(os.id) DESC LIMIT 5";
    $stmt_top5 = $pdo->prepare($sql_top5);
    $stmt_top5->execute($params);
    $top5_prof = $stmt_top5->fetchAll(PDO::FETCH_ASSOC);
    
    $evolucao_labels = [];
    $evolucao_series = [];
    
    if(!empty($top5_prof)) {
        $prof_ids = array_column($top5_prof, 'id');
        $prof_ids_str = implode(',', $prof_ids); 
        
        $sql_evolucao = "SELECT p.nome, DATE_FORMAT(os.data_registo, '%Y-%m') as mes, COUNT(os.id) as total 
                         FROM ordens_servico os 
                         JOIN profissionais p ON os.profissional_id = p.id 
                         " . $sql_where_os . " AND os.status = 'Ativo' AND p.id IN ($prof_ids_str)
                         GROUP BY p.id, mes ORDER BY mes ASC";
        $stmt_ev = $pdo->prepare($sql_evolucao);
        $stmt_ev->execute($params);
        $ev_data = $stmt_ev->fetchAll(PDO::FETCH_ASSOC);
        
        // Garante os últimos 6 meses preenchidos
        $meses_unicos = [];
        for ($i = 5; $i >= 0; $i--) {
            $meses_unicos[] = date('Y-m', strtotime("first day of -$i months"));
        }
        
        $datasets_evolucao = [];
        foreach($top5_prof as $prof) {
            $datasets_evolucao[$prof['nome']] = array_fill_keys($meses_unicos, 0);
        }
        
        foreach($ev_data as $row) {
            if(isset($datasets_evolucao[$row['nome']][$row['mes']])) {
                $datasets_evolucao[$row['nome']][$row['mes']] = (int)$row['total'];
            }
        }
        
        // Formata os meses
        $meses_formatados = array_map(function($m) use ($meses_pt_extenso) {
            $partes = explode('-', $m);
            return $meses_pt_extenso[$partes[1]] . '/' . $partes[0];
        }, $meses_unicos);
        
        $evolucao_labels = $meses_formatados;
        $colors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0'];
        $color_idx = 0;
        
        foreach($datasets_evolucao as $nome => $dados_mes) {
            $evolucao_series[] = [
                'label' => $nome,
                'data' => array_values($dados_mes),
                'borderColor' => $colors[$color_idx % 5],
                'backgroundColor' => $colors[$color_idx % 5] . '33',
                'borderWidth' => 2,
                'fill' => false,
                'tension' => 0.3
            ];
            $color_idx++;
        }
    }

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<h1 class="mt-4 page-title-main"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Relatórios e Dashboard de Inteligência</h1>
<p class="text-muted">Gestão financeira, desempenho da equipe e exportação para contabilidade.</p>

<!-- ==========================================
     1. ÁREA DE FILTROS GLOBAIS
     ========================================== -->
<div class="card bg-dark border-secondary shadow my-4">
    <div class="card-body p-3">
        <form method="GET" action="reports.php" id="form-filtros">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-light">Filtrar Volume por Cliente</label>
                    <div class="autocomplete-container">
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="cliente_search" name="cliente_nome_display" placeholder="Todos os clientes..." value="<?php echo htmlspecialchars($cliente_nome_display); ?>">
                        <input type="hidden" id="cliente_id" name="cliente_id" value="<?php echo htmlspecialchars($cliente_id); ?>">
                        <div class="autocomplete-results" id="autocomplete-results"></div>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-light">De (Registro)</label>
                    <input type="date" name="data_inicio" id="filtro_data_inicio" class="form-control bg-dark text-light border-secondary" value="<?php echo $data_inicio; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-light">Até</label>
                    <input type="date" name="data_fim" id="filtro_data_fim" class="form-control bg-dark text-light border-secondary" value="<?php echo $data_fim; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-light">Status</label>
                    <select name="status" id="filtro_status" class="form-select bg-dark text-light border-secondary">
                        <option value="todos" <?php if($status=='todos') echo 'selected';?>>Todos</option>
                        <option value="Ativo" <?php if($status=='Ativo') echo 'selected';?>>Ativo</option>
                        <option value="Cancelado" <?php if($status=='Cancelado') echo 'selected';?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-light">Turno</label>
                    <select name="turno" id="filtro_turno" class="form-select bg-dark text-light border-secondary">
                        <option value="todos" <?php if($turno=='todos') echo 'selected';?>>Todos</option>
                        <option value="Manha" <?php if($turno=='Manha') echo 'selected';?>>Manhã</option>
                        <option value="Tarde" <?php if($turno=='Tarde') echo 'selected';?>>Tarde</option>
                        <option value="Noite" <?php if($turno=='Noite') echo 'selected';?>>Noite</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100" title="Aplicar Filtros"><i class="bi bi-filter"></i></button>
                    <a href="reports.php" class="btn btn-outline-secondary w-100" title="Limpar Filtros"><i class="bi bi-eraser"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     2. BOTÃO DE EXPORTAÇÃO OMIE
     ========================================== -->
<div class="card border-warning mb-4 shadow" style="background-color: #1a1a1a;">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center p-4">
        <div class="mb-3 mb-md-0">
            <h4 class="text-warning mb-1"><i class="bi bi-file-earmark-spreadsheet-fill me-2"></i>Exportação Padrão Omie</h4>
            <p class="text-muted mb-2 small">
                O sistema irá exportar os registos <strong>desmembrando cada serviço numa linha</strong>. Cancelados são ignorados automaticamente.
            </p>
            <div class="mt-3 bg-dark p-2 rounded d-inline-block border border-secondary">
                <label for="filtro_os_min" class="form-label text-warning me-2 mb-0 fw-bold" style="font-size: 0.9rem;">A partir da OS Nº:</label>
                <input type="number" id="filtro_os_min" class="form-control form-control-sm bg-dark text-light border-secondary d-inline-block" style="width: 100px;" placeholder="Ex: 2552">
                <small class="text-muted ms-2">(Opcional)</small>
            </div>
        </div>
        <button type="button" onclick="exportarOmie()" class="btn btn-warning btn-lg fw-bold text-dark px-4 py-3 shadow">
            <i class="bi bi-download me-2"></i>Baixar Planilha Preenchida
        </button>
    </div>
</div>

<!-- ==========================================
     3. BALANCETE E KPIS GERAIS
     ========================================== -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-dark border-secondary text-center p-3 h-100 shadow">
            <small class="text-muted text-uppercase fw-bold">Faturamento (Ativos)</small>
            <h3 class="text-success fw-bold mt-2 mb-0">R$ <?php echo number_format($kpi['faturado'] ?? 0, 2, ',', '.'); ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark border-secondary text-center p-3 h-100 shadow">
            <small class="text-muted text-uppercase fw-bold">Descontos Concedidos</small>
            <h3 class="text-warning fw-bold mt-2 mb-0">R$ <?php echo number_format($kpi['descontos'] ?? 0, 2, ',', '.'); ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark border-secondary text-center p-3 h-100 shadow">
            <small class="text-muted text-uppercase fw-bold">Total de Pedidos</small>
            <h3 class="text-white fw-bold mt-2 mb-0"><?php echo $kpi['total_pedidos'] ?? 0; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark border-secondary text-center p-3 h-100 shadow">
            <small class="text-muted text-uppercase fw-bold">Cancelados</small>
            <h3 class="text-danger fw-bold mt-2 mb-0"><?php echo $kpi['cancelados'] ?? 0; ?></h3>
        </div>
    </div>
</div>

<!-- ==========================================
     4. PAINÉIS DE GRÁFICOS (DASHBOARD BI)
     ========================================== -->
<div class="row">
    <!-- GRÁFICO 1: FATURAMENTO EM COLUNAS -->
    <div class="col-lg-8 mb-4">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center bg-dark">
                <span class="text-light fw-bold">
                    <i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Evolução de Faturamento e Volume
                    <small class="text-info fw-normal ms-2" style="font-size: 0.75rem;"><i class="bi bi-info-circle me-1"></i>Fixo em 30 Dias/12 Meses (Obedece ao filtro de Cliente)</small>
                </span>
                <div class="btn-group btn-group-sm">
                    <?php 
                        $q = $_GET; 
                        $q['periodo_grafico']='dia'; $ld='reports.php?'.http_build_query($q); 
                        $q['periodo_grafico']='mes'; $lm='reports.php?'.http_build_query($q); 
                    ?>
                    <a href="<?php echo $ld; ?>" class="btn <?php echo ($periodo_grafico_faturamento=='dia')?'btn-primary':'btn-outline-secondary text-light'; ?>">Dia</a>
                    <a href="<?php echo $lm; ?>" class="btn <?php echo ($periodo_grafico_faturamento=='mes')?'btn-primary':'btn-outline-secondary text-light'; ?>">Mês</a>
                </div>
            </div>
            <div class="card-body">
                <canvas id="faturamentoChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- EXPORTAÇÃO CUSTOMIZADA -->
    <div class="col-lg-4 mb-4">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center bg-dark">
                <span class="text-light fw-bold"><i class="bi bi-gear-fill me-2 text-info"></i>Exportação Customizada</span>
                <button type="button" class="btn btn-sm btn-outline-secondary text-light" onclick="toggleExportConfig()">Abrir/Fechar</button>
            </div>
            <div class="card-body" id="export-config-section" style="display: none;">
                <p class="small text-muted mb-3 border-bottom border-secondary pb-2">Selecione os campos para baixar uma planilha gerencial livre (sem o padrão Omie):</p>
                <form action="../includes/generate_report_excel.php" method="GET" onsubmit="syncFiltrosExportacao(this)">
                    
                    <input type="hidden" name="data_inicio" value="">
                    <input type="hidden" name="data_fim" value="">
                    <input type="hidden" name="status" value="">
                    <input type="hidden" name="turno" value="">
                    <input type="hidden" name="cliente_id" value="">
                    <input type="hidden" name="_t" value="" class="nocache-input">
                    
                    <div class="row g-1 mb-2" style="max-height: 180px; overflow-y: auto;">
                        <?php
                        $colunas_possiveis = [
                            'id' => 'Nº Pedido',
                            'data_registo' => 'Data Registo',
                            'data_lavagem' => 'Data Lavagem',
                            'nome_razao_social' => 'Cliente',
                            'cpf_cnpj' => 'CNPJ/CPF',
                            'nome_profissional' => 'Profissional',
                            'nome_segundo_responsavel' => '2º Responsável',
                            'motorista_nome' => 'Motorista',
                            'placa_carreta' => 'Pl. Carreta',
                            'placa_cavalo' => 'Pl. Cavalo',
                            'carga_ultima' => 'Últ. Carga',
                            'servicos_realizados' => 'Serviços',
                            'subtotal' => 'Subtotal',
                            'desconto' => 'Desconto',
                            'valor_final' => 'Valor Final',
                            'metodo_pagamento' => 'Pgto',
                            'turno' => 'Turno',
                            'status' => 'Status',
                            'reprovado' => 'Reprovado?',
                            'motivo_reprova' => 'Motivo Reprova'
                        ];
                        foreach ($colunas_possiveis as $key => $label): ?>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input cols-checkbox bg-dark border-secondary" type="checkbox" name="cols[]" value="<?php echo $key; ?>" id="col_<?php echo $key; ?>" checked>
                                    <label class="form-check-label small text-light" for="col_<?php echo $key; ?>" style="font-size: 0.8rem;"><?php echo $label; ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-between mb-3 border-top border-secondary pt-2">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-info" onclick="checkAllCols(true)">Marcar Todos</button>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-danger" onclick="checkAllCols(false)">Desmarcar Todos</button>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-sm fw-bold"><i class="bi bi-file-earmark-excel-fill me-1"></i>Gerar Planilha Simples</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- GRÁFICO NOVO: FATURAMENTO NO PERÍODO (OBEDECE AO FILTRO) -->
    <div class="col-lg-8 mb-4">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center bg-dark">
                <span class="text-light fw-bold">
                    <i class="bi bi-calendar-check-fill me-2 text-success"></i>Faturamento no Período Selecionado
                    <small class="text-info fw-normal ms-2 d-none d-md-inline" style="font-size: 0.75rem;">(Obedece 100% ao filtro de data atual)</small>
                </span>
            </div>
            <div class="card-body">
                <canvas id="fatFiltroChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>

    <!-- GRÁFICO NOVO: MOTIVOS DE REPROVA -->
    <div class="col-lg-4 mb-4">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary bg-dark">
                <span class="text-light fw-bold">
                    <i class="bi bi-shield-x me-2 text-warning"></i>Motivos de Reprova
                    <br><small class="text-muted fw-normal ms-4" style="font-size: 0.75rem;">(Filtros da página)</small>
                </span>
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                <?php if(empty($repr_labels)): ?>
                    <div class="text-center">
                        <i class="bi bi-patch-check-fill text-success" style="font-size: 4rem;"></i>
                        <h5 class="text-success mt-3 fw-bold">Excelente</h5>
                        <p class="text-muted small">Nenhum serviço foi reprovado neste período.</p>
                    </div>
                <?php else: ?>
                    <canvas id="reprovaChart" style="max-height: 250px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- GRÁFICO 2: CANCELAMENTOS (DOUGHNUT) -->
    <div class="col-lg-4 mb-4">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary bg-dark">
                <span class="text-light fw-bold">
                    <i class="bi bi-x-circle-fill me-2 text-danger"></i>Motivos de Cancelamento
                    <br><small class="text-muted fw-normal ms-4" style="font-size: 0.75rem;">(Filtros de data, turno e cliente)</small>
                </span>
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                <?php if(empty($cancel_labels)): ?>
                    <div class="text-center">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        <h5 class="text-success mt-3 fw-bold">100% de Aproveitamento</h5>
                        <p class="text-muted small">Nenhuma ordem de serviço foi cancelada neste período com os filtros atuais.</p>
                    </div>
                <?php else: ?>
                    <canvas id="cancelChart" style="max-height: 250px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- GRÁFICO 3: RANKING PROFISSIONAIS (BARRAS HORIZONTAIS) -->
    <div class="col-lg-8 mb-4">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary bg-dark d-flex justify-content-between align-items-center">
                <span class="text-light fw-bold">
                    <i class="bi bi-trophy-fill me-2 text-warning"></i>Ranking: Volume de Serviços (1º Responsável)
                    <small class="text-muted fw-normal ms-2 d-none d-md-inline" style="font-size: 0.75rem;">(Filtros de data, turno e cliente)</small>
                </span>
                <span class="badge bg-secondary">Top 10</span>
            </div>
            <div class="card-body">
                <?php if(empty($prof_labels)): ?>
                    <p class="text-center text-muted py-5">Nenhum serviço registrado neste período.</p>
                <?php else: ?>
                    <canvas id="profChart" style="max-height: 250px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- GRÁFICO 5: RANKING TOP CLIENTES (BARRAS HORIZONTAIS) -->
    <div class="col-lg-6 mb-4">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary bg-dark d-flex justify-content-between align-items-center">
                <span class="text-light fw-bold">
                    <i class="bi bi-building me-2" style="color: #0dcaf0;"></i>Ranking: Top Clientes
                    <small class="text-muted fw-normal ms-2 d-none d-md-inline" style="font-size: 0.75rem;">(Filtros de data e turno)</small>
                </span>
                <span class="badge bg-secondary">Top 10</span>
            </div>
            <div class="card-body">
                <?php if(empty($clientes_labels)): ?>
                    <p class="text-center text-muted py-5">Nenhum faturamento registrado.</p>
                <?php else: ?>
                    <canvas id="clientesChart" style="max-height: 300px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- GRÁFICO 6: RANKING SERVIÇOS MAIS REALIZADOS (BARRAS HORIZONTAIS) -->
    <div class="col-lg-6 mb-4">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary bg-dark d-flex justify-content-between align-items-center">
                <span class="text-light fw-bold">
                    <i class="bi bi-list-stars me-2" style="color: #6f42c1;"></i>Ranking: Serviços Executados
                    <small class="text-muted fw-normal ms-2 d-none d-md-inline" style="font-size: 0.75rem;">(Filtros de data, turno e cliente)</small>
                </span>
                <span class="badge bg-secondary">Top 10</span>
            </div>
            <div class="card-body">
                <?php if(empty($servicos_labels)): ?>
                    <p class="text-center text-muted py-5">Nenhum serviço registrado.</p>
                <?php else: ?>
                    <canvas id="servicosChart" style="max-height: 300px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- GRÁFICO 4: EVOLUÇÃO DA EQUIPE MÊS A MÊS (LINHA) -->
    <div class="col-12 mb-5">
        <div class="card bg-dark border-secondary shadow h-100">
            <div class="card-header border-secondary bg-dark d-flex justify-content-between align-items-center">
                <span class="text-light fw-bold">
                    <i class="bi bi-activity me-2 text-info"></i>Evolução de Desempenho da Equipe
                    <small class="text-muted fw-normal ms-2 d-none d-md-inline" style="font-size: 0.75rem;">(Calcula os Top 5 sujeitos aos filtros de data, turno e cliente)</small>
                </span>
                <span class="badge bg-secondary">Top 5 Profissionais</span>
            </div>
            <div class="card-body">
                <?php if(empty($evolucao_labels)): ?>
                    <p class="text-center text-muted py-5">Não há histórico de meses suficientes ou serviços para gerar o gráfico.</p>
                <?php else: ?>
                    <canvas id="evolucaoChart" style="max-height: 350px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Lógica dos Botões (Exportação Omie e Customizada)
function exportarOmie() {
    const dataInicio = document.getElementById('filtro_data_inicio').value;
    const dataFim = document.getElementById('filtro_data_fim').value;
    const status = document.getElementById('filtro_status').value;
    const turno = document.getElementById('filtro_turno').value;
    const clienteId = document.getElementById('cliente_id').value;
    const osMin = document.getElementById('filtro_os_min').value; 
    
    const params = new URLSearchParams({
        data_inicio: dataInicio,
        data_fim: dataFim,
        status: status,
        turno: turno,
        cliente_id: clienteId,
        os_min: osMin, 
        _t: Date.now()
    });
    
    window.location.href = `../includes/generate_report_omie.php?${params.toString()}`;
}

function syncFiltrosExportacao(form) {
    form.querySelector('input[name="data_inicio"]').value = document.getElementById('filtro_data_inicio').value;
    form.querySelector('input[name="data_fim"]').value = document.getElementById('filtro_data_fim').value;
    form.querySelector('input[name="status"]').value = document.getElementById('filtro_status').value;
    form.querySelector('input[name="turno"]').value = document.getElementById('filtro_turno').value;
    form.querySelector('input[name="cliente_id"]').value = document.getElementById('cliente_id').value;
    form.querySelector('.nocache-input').value = Date.now();
}

function toggleExportConfig() {
    const el = document.getElementById('export-config-section');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function checkAllCols(val) {
    document.querySelectorAll('.cols-checkbox').forEach(i => i.checked = val);
}

// Inicialização dos Gráficos (Chart.js)
document.addEventListener('DOMContentLoaded', function () {
    
    Chart.defaults.color = '#bbb';
    Chart.defaults.borderColor = '#333';

    // Formatação de Moeda no Tooltip (Para Gráficos de Faturamento)
    const currencyTooltip = {
        callbacks: {
            label: function(context) {
                let label = context.dataset.label || '';
                if (label) {
                    label += ': ';
                }
                if (context.parsed.x !== null && context.chart.options.indexAxis === 'y') {
                     label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.x);
                } else if (context.parsed.y !== null) {
                     label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.y);
                }
                return label;
            }
        }
    };

    // 1. Gráfico de Faturamento (BARRAS / COLUNAS)
    const ctxFat = document.getElementById('faturamentoChart');
    if (ctxFat) {
        new Chart(ctxFat, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Faturamento',
                    data: <?php echo json_encode($values); ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)', // Azul Moderno
                    borderColor: '#0d6efd',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true },
                    x: { grid: { display: false } }
                },
                plugins: { 
                    legend: { display: false },
                    tooltip: currencyTooltip
                }
            }
        });
    }
    // Gráfico de Faturamento no Período (Barras)
    const ctxFatFiltro = document.getElementById('fatFiltroChart');
    if (ctxFatFiltro) {
        new Chart(ctxFatFiltro, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($fat_filtro_labels); ?>,
                datasets: [{
                    label: 'Faturamento',
                    data: <?php echo json_encode($fat_filtro_values); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)', // Verde success
                    borderColor: '#198754',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true },
                    x: { grid: { display: false } }
                },
                plugins: { 
                    legend: { display: false },
                    tooltip: currencyTooltip
                }
            }
        });
    }

    // Gráfico de Reprovas (Doughnut)
    const ctxReprova = document.getElementById('reprovaChart');
    if (ctxReprova) {
        new Chart(ctxReprova, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($repr_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($repr_values); ?>,
                    backgroundColor: ['#ffc107', '#fd7e14', '#dc3545', '#6f42c1', '#0dcaf0'],
                    borderWidth: 2,
                    borderColor: '#1a1a1a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'right' } 
                },
                cutout: '65%'
            }
        });
    }

    // 2. Gráfico de Cancelamentos (Doughnut)
    const ctxCancel = document.getElementById('cancelChart');
    if (ctxCancel) {
        new Chart(ctxCancel, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($cancel_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($cancel_values); ?>,
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#6c757d', '#0dcaf0'],
                    borderWidth: 2,
                    borderColor: '#1a1a1a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'right' } 
                },
                cutout: '65%'
            }
        });
    }

    // 3. Gráfico de Ranking de Profissionais (Barras Horizontais)
    const ctxProf = document.getElementById('profChart');
    if (ctxProf) {
        new Chart(ctxProf, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($prof_labels); ?>,
                datasets: [{
                    label: 'Serviços Realizados',
                    data: <?php echo json_encode($prof_values); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)', // Verde
                    borderColor: '#198754',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // 5. Gráfico de Ranking Top Clientes (Barras Horizontais)
    const ctxClientes = document.getElementById('clientesChart');
    if (ctxClientes) {
        new Chart(ctxClientes, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($clientes_labels); ?>,
                datasets: [{
                    label: 'Faturamento',
                    data: <?php echo json_encode($clientes_values); ?>,
                    backgroundColor: 'rgba(13, 202, 240, 0.7)', // Cyan
                    borderColor: '#0dcaf0',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: { 
                    legend: { display: false },
                    tooltip: currencyTooltip
                }
            }
        });
    }

    // 6. NOVO: Gráfico de Ranking Serviços Mais Realizados (Barras Horizontais)
    const ctxServicos = document.getElementById('servicosChart');
    if (ctxServicos) {
        new Chart(ctxServicos, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($servicos_labels); ?>,
                datasets: [{
                    label: 'Vezes Executado',
                    data: <?php echo json_encode($servicos_values); ?>,
                    backgroundColor: 'rgba(111, 66, 193, 0.7)', // Roxo
                    borderColor: '#6f42c1',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: { 
                    legend: { display: false }
                }
            }
        });
    }

    // 4. Gráfico de Evolução da Equipe (Linhas)
    const ctxEv = document.getElementById('evolucaoChart');
    if (ctxEv) {
        new Chart(ctxEv, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($evolucao_labels); ?>,
                datasets: <?php echo json_encode($evolucao_series); ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: { 
                    legend: { position: 'top' } 
                }
            }
        });
    }
});
</script>

<?php include '../templates/footer.php'; ?>