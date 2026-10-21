<?php
require 'includes/db_connect.php';
$stmt = $pdo->query('SHOW COLUMNS FROM ordens_servico');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
