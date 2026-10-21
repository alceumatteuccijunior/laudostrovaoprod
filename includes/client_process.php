<?php
// Ficheiro: includes/client_process.php

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    die("Acesso negado.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/clients_list.php');
    exit();
}

// --- Recolha e Limpeza dos Dados ---
$id = $_POST['id'] ?? null;
$nome = trim($_POST['nome_razao_social'] ?? '');
$cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$email = trim($_POST['email'] ?? '');
$endereco = trim($_POST['endereco'] ?? '');

// Validação simples
if (empty($nome)) {
    header('Location: ../pages/client_form.php?error=nome_obrigatorio');
    exit();
}

try {
    if ($id) {
        // --- Lógica de ATUALIZAÇÃO (UPDATE) ---
        $sql = "UPDATE clientes SET 
                    nome_razao_social = :nome, 
                    cpf_cnpj = :cpf_cnpj, 
                    telefone = :telefone, 
                    email = :email, 
                    endereco = :endereco 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    } else {
        // --- Lógica de INSERÇÃO (INSERT) ---
        $sql = "INSERT INTO clientes (nome_razao_social, cpf_cnpj, telefone, email, endereco) 
                VALUES (:nome, :cpf_cnpj, :telefone, :email, :endereco)";
        
        $stmt = $pdo->prepare($sql);
    }

    // Associa os parâmetros comuns
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':cpf_cnpj', $cpf_cnpj);
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':endereco', $endereco);

    // Executa a instrução
    $stmt->execute();

    // Redireciona para a lista de clientes com uma mensagem de sucesso
    header('Location: ../pages/clients_list.php?success=1');
    exit();

} catch (PDOException $e) {
    // Em caso de erro, redireciona de volta com uma mensagem
    // Para depuração: die("Erro na operação com o cliente: " . $e->getMessage());
    header('Location: ../pages/client_form.php?id=' . $id . '&error=db_error');
    exit();
}
?>
