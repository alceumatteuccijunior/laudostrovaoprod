<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    echo json_encode([]);
    exit;
}

try {
    $q = $_GET['q'] ?? '';
    
    // Só pesquisa se houver pelo menos 2 caracteres digitados
    if (strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    // Busca inteligente: Procura por nome/razão social OU CNPJ/CPF em qualquer parte do texto
    $stmt = $pdo->prepare("SELECT id, nome_razao_social, cpf_cnpj 
                           FROM clientes 
                           WHERE nome_razao_social LIKE :q 
                              OR cpf_cnpj LIKE :q 
                           LIMIT 15");
                           
    $stmt->execute([':q' => '%' . $q . '%']);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retorna o JSON com os dados para o script de autocompletar do frontend
    echo json_encode($results);

} catch (PDOException $e) {
    // Em caso de erro, retorna um array vazio para não quebrar o JS
    echo json_encode([]);
}
?>