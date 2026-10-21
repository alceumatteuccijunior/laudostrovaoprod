<?php
// Ficheiro: includes/professional_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/professionals_list.php');
    exit();
}

// --- Recolha e Limpeza dos Dados ---
$id = $_POST['id'] ?? null;
$nome = trim($_POST['nome'] ?? '');
$cpf = trim($_POST['cpf'] ?? '');
$senha = $_POST['senha'] ?? '';
$cargo = trim($_POST['cargo'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$email = trim($_POST['email'] ?? '');
$perfil = $_POST['perfil'] ?? 'operador';
$ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
$recebe_alertas_crm = isset($_POST['recebe_alertas_crm']) ? (int)$_POST['recebe_alertas_crm'] : 0;

if (empty($nome) || empty($cpf)) {
    header('Location: ../pages/professional_form.php?error=campos_obrigatorios');
    exit();
}

if (!$id && empty($senha)) {
    header('Location: ../pages/professional_form.php?error=senha_obrigatoria');
    exit();
}

try {
    if ($id) {
        $sql = "UPDATE profissionais SET nome = :nome, cpf = :cpf, cargo = :cargo, telefone = :telefone, email = :email, perfil = :perfil, ativo = :ativo, recebe_alertas_crm = :recebe_alertas_crm";
        
        if (!empty($senha)) {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $sql .= ", senha = :senha";
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    } else {
        $sql = "INSERT INTO profissionais (nome, cpf, senha, cargo, telefone, email, perfil, ativo, recebe_alertas_crm) 
                VALUES (:nome, :cpf, :senha, :cargo, :telefone, :email, :perfil, :ativo, :recebe_alertas_crm)";
        
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':cpf', $cpf);
    $stmt->bindParam(':cargo', $cargo);
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':perfil', $perfil);
    $stmt->bindParam(':ativo', $ativo, PDO::PARAM_INT);
    $stmt->bindParam(':recebe_alertas_crm', $recebe_alertas_crm, PDO::PARAM_INT);

    if (!empty($senha)) {
        $stmt->bindParam(':senha', $senha_hash);
    }

    $stmt->execute();
    header('Location: ../pages/professionals_list.php?success=1');
    exit();

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        header('Location: ../pages/professional_form.php?id=' . $id . '&error=cpf_duplicado');
    } else {
        header('Location: ../pages/professional_form.php?id=' . $id . '&error=db_error');
    }
    exit();
}
?>
