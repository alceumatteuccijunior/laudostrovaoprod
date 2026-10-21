<?php
// Ficheiro: api/bi_dataset.php
// Objetivo: Retornar o Data Warehouse (base bruta) para a Engine de BI no frontend.

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado.']);
    exit();
}

header('Content-Type: application/json');

try {
    // Busca dados do último ano para não sobrecarregar
    $sql = "
        SELECT 
            os.id,
            os.data_lavagem,
            DATE_FORMAT(os.data_lavagem, '%Y-%m') AS mes_ano,
            os.status,
            os.metodo_pagamento,
            CAST(os.valor_final AS DECIMAL(10,2)) AS valor_final,
            CAST(os.subtotal AS DECIMAL(10,2)) AS subtotal,
            CAST(os.desconto AS DECIMAL(10,2)) AS desconto,
            os.placa_carreta,
            os.carga_ultima,
            os.turno,
            c.nome_razao_social AS cliente,
            p.nome AS profissional
        FROM ordens_servico os
        LEFT JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN profissionais p ON os.profissional_id = p.id
        WHERE os.data_lavagem >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
          AND os.status = 'Ativo'
    ";
    
    $stmt = $pdo->query($sql);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tipagem forte para garantir cálculos corretos no JS
    $dataset = array_map(function($row) {
        $row['valor_final'] = (float) $row['valor_final'];
        $row['subtotal'] = (float) $row['subtotal'];
        $row['desconto'] = (float) $row['desconto'];
        return $row;
    }, $resultados);

    echo json_encode($dataset);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao extrair dataset de BI.']);
}
