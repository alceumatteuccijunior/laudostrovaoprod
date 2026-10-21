<?php
require_once 'includes/db_connect.php';

try {
    // 1. Adicionar coluna recebe_alertas_crm em profissionais se não existir
    $check_col = $pdo->query("SHOW COLUMNS FROM profissionais LIKE 'recebe_alertas_crm'")->fetch();
    if (!$check_col) {
        $pdo->exec("ALTER TABLE profissionais ADD COLUMN recebe_alertas_crm TINYINT(1) NOT NULL DEFAULT 0");
        echo "Coluna recebe_alertas_crm adicionada com sucesso.\n";
    } else {
        echo "Coluna recebe_alertas_crm já existe.\n";
    }

    // 2. Criar tabela configuracoes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(50) PRIMARY KEY,
            valor VARCHAR(255) NOT NULL,
            descricao TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "Tabela configuracoes criada/verificada.\n";

    // Inserir configuração padrão do alerta de queda
    $check_cfg = $pdo->query("SELECT chave FROM configuracoes WHERE chave = 'limite_alerta_queda_crm'")->fetch();
    if (!$check_cfg) {
        $pdo->exec("INSERT INTO configuracoes (chave, valor, descricao) VALUES ('limite_alerta_queda_crm', '20', 'Porcentagem de queda de serviços de um cliente nos últimos 30 dias para disparar alerta vermelho.')");
        echo "Configuração limite_alerta_queda_crm adicionada (20%).\n";
    }

    // 3. Criar tabela crm_alertas_log para não repetir email
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crm_alertas_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT NOT NULL,
            data_alerta DATE NOT NULL,
            variacao_percentual DECIMAL(5,2) NOT NULL,
            UNIQUE KEY (cliente_id, data_alerta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "Tabela crm_alertas_log criada/verificada.\n";

    echo "Atualização do banco de dados concluída com sucesso!";

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
