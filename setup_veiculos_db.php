<?php
/**
 * Ficheiro: setup_veiculos_db.php
 * Script de migração para criar a estrutura da Teia de Aranha de Veículos.
 */
require_once 'includes/db_connect.php';

try {
    // 1. Criar a tabela veiculos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS veiculos (
            placa VARCHAR(20) PRIMARY KEY,
            tipo VARCHAR(50) NULL,
            cliente_id INT NULL,
            observacoes TEXT NULL,
            data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "Tabela 'veiculos' criada com sucesso.<br>";

    // 2. Extrair Placas Únicas (Carretas e Cavalos) do histórico
    // Vamos pegar o cliente mais recente que lavou aquele caminhão para definir o "dono" inicial
    $sql = "
        SELECT placa, tipo, cliente_id FROM (
            SELECT placa_carreta as placa, 'Carreta/Tanque' as tipo, cliente_id, data_lavagem
            FROM ordens_servico WHERE placa_carreta != '' AND placa_carreta IS NOT NULL
            UNION
            SELECT placa_cavalo as placa, 'Cavalo' as tipo, cliente_id, data_lavagem
            FROM ordens_servico WHERE placa_cavalo != '' AND placa_cavalo IS NOT NULL
        ) as sub
        ORDER BY data_lavagem DESC
    ";
    
    $stmt = $pdo->query($sql);
    $placas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $inseridas = 0;
    
    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO veiculos (placa, tipo, cliente_id) VALUES (:placa, :tipo, :cliente_id)");
    
    foreach ($placas as $p) {
        $placa_limpa = trim(strtoupper($p['placa']));
        if (!empty($placa_limpa)) {
            $stmt_insert->execute([
                ':placa' => $placa_limpa,
                ':tipo' => $p['tipo'],
                ':cliente_id' => $p['cliente_id']
            ]);
            if ($stmt_insert->rowCount() > 0) {
                $inseridas++;
            }
        }
    }
    
    echo "Migração concluída! Foram cadastradas {$inseridas} placas exclusivas no sistema.<br>";
    echo "Você pode deletar este arquivo agora.";

} catch (PDOException $e) {
    echo "Erro durante o setup: " . $e->getMessage();
}
?>
