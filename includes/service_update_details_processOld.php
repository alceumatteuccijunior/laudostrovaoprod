<?php
// Ficheiro: includes/service_update_details_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    die("Acesso negado.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/dashboard.php');
    exit();
}

// --- Recolha e Validação dos Dados ---
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
$profissional_id = filter_input(INPUT_POST, 'profissional_id', FILTER_VALIDATE_INT);
$profissional_id_2 = filter_input(INPUT_POST, 'profissional_id_2', FILTER_VALIDATE_INT);

$motorista = trim($_POST['motorista'] ?? '');
$doc_motorista = trim($_POST['doc_motorista'] ?? '');
$placa_carreta = trim($_POST['placa_carreta'] ?? '');
$placa_cavalo = trim($_POST['placa_cavalo'] ?? '');
$hora_chegada = trim($_POST['hora_chegada'] ?? '');
$hora_saida = trim($_POST['hora_saida'] ?? '');
$turno = trim($_POST['turno'] ?? '');

$carga_ultima = trim($_POST['carga_ultima'] ?? '');
$carga_penultima = trim($_POST['carga_penultima'] ?? '');
$carga_antepenultima = trim($_POST['carga_antepenultima'] ?? '');

$data_lavagem_str = trim($_POST['data_lavagem'] ?? '');
$observacoes = trim($_POST['observacoes'] ?? '');
$lacres_post = $_POST['lacres'] ?? [];

if (empty($profissional_id_2)) {
    $profissional_id_2 = null;
}

if (!$service_id || !$cliente_id || !$profissional_id || empty($data_lavagem_str) || empty($motorista)) {
    header('Location: ../pages/service_details.php?id=' . $service_id . '&error=invalid_data');
    exit();
}

try {
    $pdo->beginTransaction();

    // Calcula a nova data de vencimento
    $data_lavagem_obj = new DateTime($data_lavagem_str);
    $data_vencimento_obj = clone $data_lavagem_obj;
    $data_vencimento_obj->modify('+5 days');
    $data_vencimento_str = $data_vencimento_obj->format('Y-m-d');

    // Prepara os dados JSON dos lacres
    $lacres_info_json = json_encode(array_filter($lacres_post));

    // --- Inicia a construção da query de UPDATE ---
    $sql_fields = "cliente_id = :cliente_id,
                   profissional_id = :profissional_id,
                   profissional_id_2 = :profissional_id_2,
                   motorista_nome = :motorista,
                   doc_motorista = :doc_motorista,
                   placa_carreta = :placa_carreta,
                   placa_cavalo = :placa_cavalo,
                   hora_chegada = :hora_chegada,
                   hora_saida = :hora_saida,
                   turno = :turno,
                   carga_ultima = :carga_ultima,
                   carga_penultima = :carga_penultima,
                   carga_antepenultima = :carga_antepenultima,
                   data_lavagem = :data_lavagem, 
                   data_vencimento = :data_vencimento,
                   observacoes = :observacoes,
                   lacres_info = :lacres_info";
                   
    $params = [
        ':cliente_id' => $cliente_id,
        ':profissional_id' => $profissional_id,
        ':profissional_id_2' => $profissional_id_2,
        ':motorista' => $motorista,
        ':doc_motorista' => $doc_motorista,
        ':placa_carreta' => $placa_carreta,
        ':placa_cavalo' => $placa_cavalo,
        ':hora_chegada' => $hora_chegada,
        ':hora_saida' => $hora_saida,
        ':turno' => $turno,
        ':carga_ultima' => $carga_ultima,
        ':carga_penultima' => $carga_penultima,
        ':carga_antepenultima' => $carga_antepenultima,
        ':data_lavagem' => $data_lavagem_str,
        ':data_vencimento' => $data_vencimento_str,
        ':observacoes' => $observacoes,
        ':lacres_info' => $lacres_info_json,
        ':id' => $service_id
    ];

    // --- SÓ ATUALIZA DADOS FINANCEIROS SE FOR ADMIN ---
    if ($_SESSION['user_perfil'] === 'admin') {
        $servicos_prestados = $_POST['servicos'] ?? [];
        $desconto = filter_input(INPUT_POST, 'desconto', FILTER_VALIDATE_FLOAT) ?: 0.00;
        $metodo_pagamento = trim($_POST['metodo_pagamento'] ?? 'Pix');
        
        $subtotal = 0;
        foreach ($servicos_prestados as $servico) {
            $subtotal += floatval($servico['preco']);
        }
        $valor_final = $subtotal - $desconto;

        // Adiciona campos financeiros ao SQL e aos parâmetros
        $sql_fields .= ", subtotal = :subtotal,
                        desconto = :desconto,
                        valor_final = :valor_final,
                        metodo_pagamento = :metodo_pagamento";
        
        $params[':subtotal'] = $subtotal;
        $params[':desconto'] = $desconto;
        $params[':valor_final'] = $valor_final;
        $params[':metodo_pagamento'] = $metodo_pagamento;

        // Apaga os itens de serviço antigos
        $sql_delete_itens = "DELETE FROM ordens_servico_itens WHERE ordem_servico_id = :service_id";
        $stmt_delete_itens = $pdo->prepare($sql_delete_itens);
        $stmt_delete_itens->execute([':service_id' => $service_id]);

        // Insere os novos itens de serviço
        if (!empty($servicos_prestados)) {
            $sql_insert_itens = "INSERT INTO ordens_servico_itens (ordem_servico_id, servico_id, preco_cobrado) VALUES (:ordem_id, :servico_id, :preco)";
            $stmt_insert_itens = $pdo->prepare($sql_insert_itens);

            foreach ($servicos_prestados as $servico) {
                $stmt_insert_itens->execute([
                    ':ordem_id' => $service_id,
                    ':servico_id' => $servico['id'],
                    ':preco' => $servico['preco']
                ]);
            }
        }
    }

    $sql_update_ordem = "UPDATE ordens_servico SET " . $sql_fields . " WHERE id = :id";
            
    $stmt_update_ordem = $pdo->prepare($sql_update_ordem);
    $stmt_update_ordem->execute($params);

    $pdo->commit();

    header('Location: ../pages/service_details.php?id=' . $service_id . '&success=updated');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    // Para depuração: die("Erro: " . $e->getMessage());
    header('Location: ../pages/service_details.php?id=' . $service_id . '&error=db_error');
    exit();
}
?>