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

// Criar tabela caso ela seja requisitada antes de salvar
try {
    $sql_create = "CREATE TABLE IF NOT EXISTS `bi_dashboards` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nome` varchar(100) NOT NULL,
      `config_json` longtext NOT NULL,
      `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql_create);
} catch (PDOException $e) {}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

try {
    if ($id) {
        // Carrega um específico
        $stmt = $pdo->prepare("SELECT * FROM bi_dashboards WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $dash = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($dash ?: ['erro' => 'Não encontrado']);
    } else {
        // Lista todos
        $stmt = $pdo->query("SELECT id, nome, data_criacao FROM bi_dashboards ORDER BY data_criacao DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao carregar dashboards.']);
}
