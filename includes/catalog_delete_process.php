<?php
// Ficheiro: includes/catalog_delete_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ../pages/catalog_list.php?error=invalid_id');
    exit();
}

try {
    // Em vez de apagar, vamos apenas desativar o serviço
    $sql = "UPDATE catalogo_servicos SET ativo = 0 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    header('Location: ../pages/catalog_list.php?deleted=1');
    exit();

} catch (PDOException $e) {
    header('Location: ../pages/catalog_list.php?error=db_error');
    exit();
}
?>
