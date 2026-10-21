<?php
require_once 'includes/db_connect.php';
try {
    $sql = "CREATE TABLE IF NOT EXISTS `bi_dashboards` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nome` varchar(100) NOT NULL,
      `config_json` longtext NOT NULL,
      `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    echo "Tabela bi_dashboards criada com sucesso.\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
