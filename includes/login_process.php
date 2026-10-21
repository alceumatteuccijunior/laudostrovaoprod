<?php
// Ficheiro: includes/login_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cpf = $_POST['cpf'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (empty($cpf) || empty($senha)) {
        header('Location: ../index.php?error=empty');
        exit();
    }

    try {
        $sql = "SELECT id, nome, senha, perfil FROM profissionais WHERE cpf = :cpf AND ativo = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':cpf', $cpf);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            // Login bem-sucedido
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nome'];
            $_SESSION['user_perfil'] = $user['perfil']; // Guarda o perfil na sessão
            $_SESSION['login_string'] = hash('sha512', $user['senha'] . $_SERVER['HTTP_USER_AGENT']);
            
            session_regenerate_id(true);
            
            header('Location: ../pages/dashboard.php');
            exit();
        }

        header('Location: ../index.php?error=1');
        exit();

    } catch (PDOException $e) {
        header('Location: ../index.php?error=db');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}
?>
