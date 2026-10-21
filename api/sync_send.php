<?php
// Ficheiro: api/sync_send.php
// Este script RODA NO SERVIDOR MESTRE (ONLINE). 
// Ele envia os dados base (Profissionais, Serviços) para os nós locais.

require_once '../includes/db_connect.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

$data = [];

try {
    // 1. Tabela de Profissionais Inteira
    $stmt = $pdo->query("SELECT id, nome, ativo FROM profissionais");
    $data['profissionais'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Catálogo de Serviços Inteiro
    $stmt2 = $pdo->query("SELECT id, nome_servico, preco, ativo FROM catalogo_servicos");
    $data['catalogo_servicos'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // 3. Últimos 100 Clientes ou os criados recentemente
    $stmt3 = $pdo->query("SELECT id, nome_razao_social, cnpj_cpf, telefone, email, endereco FROM clientes ORDER BY id DESC LIMIT 200");
    $data['clientes_novos'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao compilar dados: ' . $e->getMessage()]);
}
?>
