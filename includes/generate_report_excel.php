<?php
// Ficheiro: includes/generate_report_excel.php

set_time_limit(600);
require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');
$status = $_GET['status'] ?? 'todos';
$turno = $_GET['turno'] ?? 'todos';
$cliente_id = $_GET['cliente_id'] ?? '';

// Colunas Selecionadas pelo Usuário
$cols = $_GET['cols'] ?? [];
if (empty($cols)) {
    // Fallback para todas se nada for enviado (embora a UI garanta)
    $cols = ['id', 'data_lavagem', 'nome_razao_social', 'valor_final', 'status'];
}

// Mapeamento de Cabeçalhos (Amigável Omie)
$header_map = [
    'id' => 'ID Pedido',
    'data_lavagem' => 'Data Faturamento',
    'nome_razao_social' => 'Cliente/Razao Social',
    'cpf_cnpj' => 'CNPJ/CPF',
    'nome_profissional' => 'Responsavel',
    'motorista_nome' => 'Motorista',
    'placa_carreta' => 'Placa Unidade',
    'placa_cavalo' => 'Placa Tracao',
    'carga_ultima' => 'Ultimo Produto',
    'servicos_realizados' => 'Descricao Servicos',
    'subtotal' => 'Valor Bruto',
    'desconto' => 'Valor Desconto',
    'valor_final' => 'Valor Liquido',
    'metodo_pagamento' => 'Forma Pagamento',
    'turno' => 'Periodo',
    'status' => 'Status',
    'motivo_cancelamento' => 'Justificativa (Cancelamento)',
    'reprovado' => 'Reprovado?',
    'motivo_reprova' => 'Motivo da Reprova'
];

$params = [
    ':data_inicio' => $data_inicio . ' 00:00:00',
    ':data_fim' => $data_fim . ' 23:59:59'
];

$sql_where = " WHERE os.data_registo BETWEEN :data_inicio AND :data_fim";
if ($status !== 'todos') {
    $sql_where .= " AND os.status = :status";
    $params[':status'] = $status;
}
if ($turno !== 'todos') {
    $sql_where .= " AND os.turno = :turno";
    $params[':turno'] = $turno;
}
if (!empty($cliente_id)) {
    $sql_where .= " AND os.cliente_id = :cliente_id";
    $params[':cliente_id'] = $cliente_id;
}

try {
    $sql = "SELECT 
                os.id, 
                os.data_registo,
                os.data_lavagem, 
                c.nome_razao_social, 
                c.cpf_cnpj,
                p1.nome AS nome_profissional, 
                p2.nome AS nome_segundo_responsavel,
                os.motorista_nome,
                os.placa_carreta,
                os.placa_cavalo,
                os.carga_ultima,
                os.subtotal,
                os.desconto,
                os.valor_final,
                os.metodo_pagamento,
                os.turno,
                os.status,
                os.motivo_cancelamento,
                os.reprovado,
                os.motivo_reprova,
                GROUP_CONCAT(cs.nome_servico SEPARATOR ' + ') as servicos_realizados
              FROM ordens_servico AS os
              JOIN clientes AS c ON os.cliente_id = c.id
              JOIN profissionais AS p1 ON os.profissional_id = p1.id
              LEFT JOIN profissionais AS p2 ON os.profissional_id_2 = p2.id
              LEFT JOIN ordens_servico_itens AS osi ON os.id = osi.ordem_servico_id
              LEFT JOIN catalogo_servicos AS cs ON osi.servico_id = cs.id
              " . $sql_where . " 
              GROUP BY os.id
              ORDER BY os.data_lavagem DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}

// Saída Excel
$filename = "exportacao_customizada_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta charset="utf-8"></head><body><table border="1"><thead><tr>';

// Escreve cabeçalhos baseados na seleção do usuário
foreach ($cols as $col) {
    echo '<th style="background-color: #ffc107;">' . ($header_map[$col] ?? $col) . '</th>';
}
echo '</tr></thead><tbody>';

if (!empty($pedidos)) {
    foreach ($pedidos as $row) {
        echo '<tr>';
        foreach ($cols as $col) {
            $val = $row[$col] ?? '';
            // Formatação especial para datas e números
            if (($col === 'data_lavagem' || $col === 'data_registo') && !empty($val))
                $val = date('d/m/Y', strtotime($val));
            if (in_array($col, ['subtotal', 'desconto', 'valor_final']))
                $val = number_format($val, 2, ',', '');
            if ($col === 'reprovado')
                $val = ($val == 1) ? 'Sim' : 'Não';

            echo '<td>' . htmlspecialchars($val) . '</td>';
        }
        echo '</tr>';
    }
}

echo '</tbody></table></body></html>';
exit();
?>