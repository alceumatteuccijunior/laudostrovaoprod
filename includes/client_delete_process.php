<?php
// Ficheiro: includes/client_delete_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    die("Acesso negado.");
}

// Apenas o método GET é permitido para esta operação simples
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: ../pages/clients_list.php');
    exit();
}

// Valida o ID do cliente
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ../pages/clients_list.php?error=invalid_id');
    exit();
}

try {
    // --- Lógica de EXCLUSÃO (DELETE) ---
    // Futuramente, pode adicionar uma verificação para não excluir clientes com serviços associados.
    $sql = "DELETE FROM clientes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Redireciona para a lista de clientes com uma mensagem de sucesso
    header('Location: ../pages/clients_list.php?deleted=1');
    exit();

} catch (PDOException $e) {
    // Em caso de erro, redireciona de volta com uma mensagem
    // Para depuração: die("Erro ao excluir cliente: " . $e->getMessage());
    header('Location: ../pages/clients_list.php?error=db_error');
    exit();
}
?>
