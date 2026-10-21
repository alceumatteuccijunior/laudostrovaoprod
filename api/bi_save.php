<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado.']);
    exit();
}

header('Content-Type: application/json');

// Criar tabela se não existir
try {
    $sql_create = "CREATE TABLE IF NOT EXISTS `bi_dashboards` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nome` varchar(100) NOT NULL,
      `config_json` longtext NOT NULL,
      `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_create);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao criar tabela do BI.']);
    exit();
}

// Receber os dados
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['nome']) || empty($data['config_json'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'Dados incompletos.']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO bi_dashboards (nome, config_json) VALUES (:nome, :config_json)");
    $stmt->execute([
        ':nome' => $data['nome'],
        ':config_json' => $data['config_json']
    ]);
    
    echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao salvar o dashboard.']);
}
