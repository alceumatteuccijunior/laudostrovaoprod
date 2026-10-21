<?php
// Ficheiro: includes/professional_delete_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    die("Acesso negado.");
}

// Apenas o método GET é permitido para esta operação
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: ../pages/professionals_list.php');
    exit();
}

// Valida o ID do profissional
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ../pages/professionals_list.php?error=invalid_id');
    exit();
}

// Não permitir que o utilizador se auto-exclua
if ($id === $_SESSION['user_id']) {
    header('Location: ../pages/professionals_list.php?error=self_delete');
    exit();
}

try {
    // --- Lógica de EXCLUSÃO LÓGICA (Desligar) ---
    $sql = "UPDATE profissionais SET ativo = 0 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Redireciona para a lista de profissionais com uma mensagem de sucesso
    header('Location: ../pages/professionals_list.php?deleted=1');
    exit();

} catch (PDOException $e) {
    // Em caso de erro, redireciona de volta com uma mensagem
    // Para depuração: die("Erro ao excluir profissional: " . $e->getMessage());
    header('Location: ../pages/professionals_list.php?error=db_error');
    exit();
}
?>
