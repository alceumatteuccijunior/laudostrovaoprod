<?php
// Ficheiro: includes/service_update_date_process.php

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
$data_lavagem_str = trim($_POST['data_lavagem'] ?? '');

if (!$service_id || empty($data_lavagem_str)) {
    header('Location: ../pages/service_details.php?id=' . $service_id . '&error=invalid_data');
    exit();
}

try {
    // Calcula a nova data de vencimento (5 dias após a data da lavagem)
    $data_lavagem_obj = new DateTime($data_lavagem_str);
    $data_vencimento_obj = clone $data_lavagem_obj;
    $data_vencimento_obj->modify('+5 days');
    $data_vencimento_str = $data_vencimento_obj->format('Y-m-d');

    // --- Atualiza a Base de Dados ---
    $sql = "UPDATE ordens_servico SET data_lavagem = :data_lavagem, data_vencimento = :data_vencimento WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':data_lavagem' => $data_lavagem_str,
        ':data_vencimento' => $data_vencimento_str,
        ':id' => $service_id
    ]);

    header('Location: ../pages/service_details.php?id=' . $service_id . '&success=date_updated');
    exit();

} catch (Exception $e) {
    // Em caso de erro, redireciona de volta com uma mensagem
    header('Location: ../pages/service_details.php?id=' . $service_id . '&error=db_error');
    exit();
}
?>
