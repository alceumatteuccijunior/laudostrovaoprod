<?php
// Ficheiro: api/client_search.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Apenas para utilizadores autenticados
secure_session_start();
if (!is_user_logged_in()) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

if (strlen($term) < 2) {
    echo json_encode([]); // Retorna um array vazio se o termo for muito curto
    exit();
}

try {
    $sql = "SELECT id, nome_razao_social FROM clientes WHERE nome_razao_social LIKE :term LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->execute();
    $clientes = $stmt->fetchAll();

    $results = [];
    foreach ($clientes as $cliente) {
        // O formato 'value' e 'label' é comum para bibliotecas de autocompletar
        $results[] = [
            'id' => $cliente['id'],
            'label' => $cliente['nome_razao_social']
        ];
    }

    echo json_encode($results);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Erro na base de dados']);
}
?>
