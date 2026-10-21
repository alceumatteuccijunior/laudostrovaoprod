<?php
// Ficheiro: includes/service_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    die("Acesso negado. Precisa de estar autenticado para realizar esta ação.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/service_register.php');
    exit();
}

// --- Recolha dos Dados ---
$cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
$profissional_id = filter_input(INPUT_POST, 'profissional_id', FILTER_VALIDATE_INT);
$profissional_id_2 = filter_input(INPUT_POST, 'profissional_id_2', FILTER_VALIDATE_INT);
$motorista = trim($_POST['motorista'] ?? '');
$doc_motorista = trim($_POST['doc_motorista'] ?? '');
$placa_carreta = trim($_POST['placa_carreta'] ?? '');
$placa_cavalo = trim($_POST['placa_cavalo'] ?? '');
$turno = trim($_POST['turno'] ?? '');
$hora_chegada = trim($_POST['hora_chegada'] ?? '');
$hora_saida = trim($_POST['hora_saida'] ?? '');
$carga_ultima = trim($_POST['carga_ultima'] ?? '');
$carga_penultima = trim($_POST['carga_penultima'] ?? '');
$carga_antepenultima = trim($_POST['carga_antepenultima'] ?? '');
$servicos_prestados = $_POST['servicos'] ?? [];
$procedimentos_realizados = $_POST['procedimentos'] ?? [];
$data_lavagem = trim($_POST['data_lavagem'] ?? '');
$lacres_post = $_POST['lacres'] ?? [];
$observacoes = trim($_POST['observacoes'] ?? '');
$desconto = filter_input(INPUT_POST, 'desconto', FILTER_VALIDATE_FLOAT) ?: 0.00;
$metodo_pagamento = trim($_POST['metodo_pagamento'] ?? 'Pix');
$id_utilizador_registo = $_SESSION['user_id'];

if (empty($profissional_id_2)) {
    $profissional_id_2 = null;
}

// Validação
if (empty($cliente_id) || empty($profissional_id) || empty($motorista) || empty($placa_carreta) || empty($servicos_prestados) || empty($data_lavagem)) {
    header('Location: ../pages/service_register.php?error=campos_obrigatorios');
    exit();
}

// --- NOVO: Cálculo da Data de Vencimento ---
try {
    $data_lavagem_obj = new DateTime($data_lavagem);
    $data_vencimento_obj = clone $data_lavagem_obj;
    $data_vencimento_obj->modify('+5 days');
    $data_vencimento = $data_vencimento_obj->format('Y-m-d');
} catch (Exception $e) {
    // Se a data for inválida, retorna um erro
    header('Location: ../pages/service_register.php?error=invalid_date');
    exit();
}
// --- FIM DO NOVO CÁLCULO ---


$lacres_info_json = json_encode(array_filter($lacres_post));
$procedimentos_json = json_encode($procedimentos_realizados);

$subtotal = 0;
foreach ($servicos_prestados as $servico) {
    $subtotal += floatval($servico['preco']);
}
$valor_final = $subtotal - $desconto;

try {
    $pdo->beginTransaction();

    // --- QUERY ATUALIZADA para incluir a data_vencimento ---
    $sql_ordem = "INSERT INTO ordens_servico (
                    cliente_id, profissional_id, profissional_id_2, motorista_nome, doc_motorista, placa_carreta, placa_cavalo,
                    turno, carga_ultima, carga_penultima, carga_antepenultima, observacoes, 
                    lacres_info, procedimentos_json, subtotal, desconto, valor_final, metodo_pagamento, 
                    data_lavagem, data_vencimento, hora_chegada, hora_saida, id_utilizador
                  ) VALUES (
                    :cliente_id, :profissional_id, :profissional_id_2, :motorista, :doc_motorista, :placa_carreta, :placa_cavalo,
                    :turno, :carga_ultima, :carga_penultima, :carga_antepenultima, :observacoes, 
                    :lacres_info, :procedimentos, :subtotal, :desconto, :valor_final, :metodo_pagamento, 
                    :data_lavagem, :data_vencimento, :hora_chegada, :hora_saida, :id_utilizador
                  )";

    $stmt_ordem = $pdo->prepare($sql_ordem);
    $stmt_ordem->execute([
        ':cliente_id' => $cliente_id,
        ':profissional_id' => $profissional_id,
        ':profissional_id_2' => $profissional_id_2,
        ':motorista' => $motorista,
        ':doc_motorista' => $doc_motorista,
        ':placa_carreta' => $placa_carreta,
        ':placa_cavalo' => $placa_cavalo,
        ':turno' => $turno,
        ':carga_ultima' => $carga_ultima,
        ':carga_penultima' => $carga_penultima,
        ':carga_antepenultima' => $carga_antepenultima,
        ':observacoes' => $observacoes,
        ':lacres_info' => $lacres_info_json,
        ':procedimentos' => $procedimentos_json,
        ':subtotal' => $subtotal,
        ':desconto' => $desconto,
        ':valor_final' => $valor_final,
        ':metodo_pagamento' => $metodo_pagamento,
        ':data_lavagem' => $data_lavagem,
        ':data_vencimento' => $data_vencimento, // Campo adicionado
        ':hora_chegada' => $hora_chegada,
        ':hora_saida' => $hora_saida,
        ':id_utilizador' => $id_utilizador_registo
    ]);

    $last_service_id = $pdo->lastInsertId();

    $sql_itens = "INSERT INTO ordens_servico_itens (ordem_servico_id, servico_id, preco_cobrado) VALUES (:ordem_id, :servico_id, :preco)";
    $stmt_itens = $pdo->prepare($sql_itens);

    foreach ($servicos_prestados as $servico) {
        $stmt_itens->execute([
            ':ordem_id' => $last_service_id,
            ':servico_id' => $servico['id'],
            ':preco' => $servico['preco']
        ]);
    }

    // --- Auto-cadastro de Veículos (Teia de Aranha) ---
    $stmt_veiculo = $pdo->prepare("INSERT IGNORE INTO veiculos (placa, tipo, cliente_id) VALUES (:placa, :tipo, :cliente_id)");
    if (!empty($placa_carreta)) {
        $stmt_veiculo->execute([
            ':placa' => strtoupper(trim($placa_carreta)),
            ':tipo' => 'Carreta/Tanque',
            ':cliente_id' => $cliente_id
        ]);
    }
    if (!empty($placa_cavalo)) {
        $stmt_veiculo->execute([
            ':placa' => strtoupper(trim($placa_cavalo)),
            ':tipo' => 'Cavalo',
            ':cliente_id' => $cliente_id
        ]);
    }

    $pdo->commit();
    $_SESSION['last_service_id'] = $last_service_id;
    header('Location: ../pages/service_success.php');
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    // Para depuração: die("Erro ao registar o serviço: " . $e->getMessage());
    header('Location: ../pages/service_register.php?error=db_error');
    exit();
}
?>
