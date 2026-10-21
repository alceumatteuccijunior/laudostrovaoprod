<?php
// Ficheiro: api/dashboard_data.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Apenas para utilizadores autenticados
secure_session_start();
if (!is_user_logged_in()) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

header('Content-Type: application/json');

// --- Lógica para Definir o Período de Tempo ---
$periodo = $_GET['periodo'] ?? 'mes_atual';
$start_date = '';
$end_date = date('Y-m-d 23:59:59');

switch ($periodo) {
    case 'ultimos_7_dias':
        $start_date = date('Y-m-d 00:00:00', strtotime('-6 days'));
        break;
    case 'mes_anterior':
        $start_date = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $end_date = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        break;
    case 'ano_corrente':
        $start_date = date('Y-01-01 00:00:00');
        break;
    case 'mes_atual':
    default:
        $start_date = date('Y-m-01 00:00:00');
        break;
}

try {
    // --- Cálculo dos KPIs ---

    // Faturação no período
    $sql_faturamento = "SELECT SUM(valor_servico) as total FROM ordens_servico WHERE data_registo BETWEEN :start_date AND :end_date";
    $stmt = $pdo->prepare($sql_faturamento);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $faturamento = $stmt->fetchColumn() ?: 0;

    // Serviços realizados no período
    $sql_servicos = "SELECT COUNT(id) FROM ordens_servico WHERE data_registo BETWEEN :start_date AND :end_date";
    $stmt = $pdo->prepare($sql_servicos);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $servicosRealizados = $stmt->fetchColumn() ?: 0;

    // Ticket Médio
    $ticketMedio = ($servicosRealizados > 0) ? ($faturamento / $servicosRealizados) : 0;

    // Novos Clientes no período
    $sql_clientes = "SELECT COUNT(id) FROM clientes WHERE data_cadastro BETWEEN :start_date AND :end_date";
    $stmt = $pdo->prepare($sql_clientes);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $novosClientes = $stmt->fetchColumn() ?: 0;

    // --- Dados para o Gráfico de Faturação Anual ---
    $faturamento_mensal = [];
    $labels_mensais = [];
    for ($i = 11; $i >= 0; $i--) {
        $mes = date('Y-m', strtotime("-$i months"));
        $labels_mensais[] = date('M/y', strtotime($mes));
        $faturamento_mensal[$mes] = 0;
    }

    $sql_grafico = "SELECT SUM(valor_servico) as total, DATE_FORMAT(data_registo, '%Y-%m') as mes 
                    FROM ordens_servico 
                    WHERE data_registo >= :start_date_grafico
                    GROUP BY mes";
    $stmt_grafico = $pdo->prepare($sql_grafico);
    $stmt_grafico->execute(['start_date_grafico' => date('Y-m-01', strtotime('-11 months'))]);
    $resultados_grafico = $stmt_grafico->fetchAll();

    foreach ($resultados_grafico as $resultado) {
        $faturamento_mensal[$resultado['mes']] = (float)$resultado['total'];
    }

    // --- Montagem da Resposta JSON ---
    $data = [
        'kpis' => [
            'faturamento' => 'R$ ' . number_format($faturamento, 2, ',', '.'),
            'servicosRealizados' => $servicosRealizados,
            'ticketMedio' => 'R$ ' . number_format($ticketMedio, 2, ',', '.'),
            'novosClientes' => $novosClientes
        ],
        'faturamentoAnual' => [
            'labels' => $labels_mensais,
            'data' => array_values($faturamento_mensal)
        ]
    ];

    // Converte o array PHP numa string JSON e imprime-a
    echo json_encode($data);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erro na base de dados: ' . $e->getMessage()]);
}
?>
