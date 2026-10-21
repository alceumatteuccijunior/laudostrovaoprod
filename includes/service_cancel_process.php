<?php
// Ficheiro: includes/service_cancel_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

// Apenas administradores podem cancelar um pedido
if (!is_user_logged_in()) {
    die("Acesso negado.");
}

// Valida o ID do serviço
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$motivo = trim(filter_input(INPUT_GET, 'motivo', FILTER_SANITIZE_STRING));

if (!$id || empty($motivo)) {
    header('Location: ../pages/services_list.php?error=invalid_data');
    exit();
}

try {
    // Altera o estado do pedido para 'Cancelado' e guarda o motivo
    $sql = "UPDATE ordens_servico SET status = 'Cancelado', motivo_cancelamento = :motivo WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':motivo', $motivo);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Redireciona para a lista de serviços com uma mensagem de sucesso
    header('Location: ../pages/services_list.php?canceled=1');
    exit();

} catch (PDOException $e) {
    // Em caso de erro, redireciona de volta com uma mensagem
    header('Location: ../pages/services_list.php?error=db_error');
    exit();
}
?>
