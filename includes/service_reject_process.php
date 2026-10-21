<?php
// Ficheiro: includes/service_reject_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

// Verifica se o usuário está logado
if (!is_user_logged_in()) {
    die("Acesso negado.");
}

// Valida o ID do serviço e o motivo
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$motivo = trim(filter_input(INPUT_GET, 'motivo', FILTER_SANITIZE_STRING));

if (!$id || empty($motivo)) {
    header('Location: ../pages/services_list.php?error=invalid_data');
    exit();
}

try {
    // Atualiza o laudo para reprovado e adiciona a justificativa e data
    $sql = "UPDATE ordens_servico SET reprovado = 1, motivo_reprova = :motivo, data_reprova = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':motivo', $motivo);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Redireciona de volta para os detalhes do serviço com sucesso
    header('Location: ../pages/service_details.php?id=' . $id . '&success=1');
    exit();

} catch (PDOException $e) {
    // Em caso de erro, redireciona de volta com uma mensagem de erro
    header('Location: ../pages/service_details.php?id=' . $id . '&error=db_error');
    exit();
}
?>
