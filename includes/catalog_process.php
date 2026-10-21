<?php
// Ficheiro: includes/catalog_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/catalog_list.php');
    exit();
}

$id = $_POST['id'] ?? null;
$nome_servico = trim($_POST['nome_servico'] ?? '');
$preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT);

if (empty($nome_servico) || $preco === null) {
    header('Location: ../pages/catalog_form.php?error=campos_obrigatorios');
    exit();
}

try {
    if ($id) {
        $sql = "UPDATE catalogo_servicos SET nome_servico = :nome, preco = :preco WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    } else {
        $sql = "INSERT INTO catalogo_servicos (nome_servico, preco) VALUES (:nome, :preco)";
        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':nome', $nome_servico);
    $stmt->bindParam(':preco', $preco);
    $stmt->execute();

    header('Location: ../pages/catalog_list.php?success=1');
    exit();

} catch (PDOException $e) {
    header('Location: ../pages/catalog_form.php?id=' . $id . '&error=db_error');
    exit();
}
?>
