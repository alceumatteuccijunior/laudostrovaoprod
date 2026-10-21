<?php
/**
 * Ficheiro: cron_alertas_crm.php
 * Descrição: Script de Automação para envio de alertas do CRM.
 * Deve ser configurado no Cron Job do servidor (ex: rodar toda segunda-feira às 08:00)
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "Iniciando varredura do CRM...\n";

// 1. Obter o limite de alerta configurado
$limite_alerta = 20; // padrão
try {
    $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'limite_alerta_queda_crm'");
    if ($row = $stmt->fetch()) {
        $limite_alerta = (float) $row['valor'];
    }
} catch (PDOException $e) {
    die("Erro ao ler configurações. Execute o setup_crm_db.php primeiro.\n");
}

// 2. Processar os clientes (Últimos 30 vs 30 anteriores)
$clientes_em_risco = [];

try {
    $sql_crm = "
        SELECT 
            c.id, c.nome_razao_social, c.telefone, c.email,
            COUNT(CASE WHEN os.data_lavagem >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN os.id END) as qtd_atual,
            COUNT(CASE WHEN os.data_lavagem >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND os.data_lavagem < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN os.id END) as qtd_anterior
        FROM clientes c
        LEFT JOIN ordens_servico os ON os.cliente_id = c.id AND os.status != 'Cancelado'
        GROUP BY c.id
    ";
    $stmt = $pdo->query($sql_crm);
    $clientes = $stmt->fetchAll();

    foreach ($clientes as $c) {
        $atual = (int) $c['qtd_atual'];
        $anterior = (int) $c['qtd_anterior'];
        
        if ($atual == 0 && $anterior == 0) continue;
        
        if ($anterior > 0) {
            $variacao = (($atual - $anterior) / $anterior) * 100;
            // Se caiu mais ou igual ao limite configurado
            if ($variacao <= -$limite_alerta) {
                // Verificar se já enviamos e-mail nos últimos 7 dias para este cliente para não flodar
                $stmt_log = $pdo->prepare("SELECT id FROM crm_alertas_log WHERE cliente_id = :id AND data_alerta >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                $stmt_log->execute([':id' => $c['id']]);
                if (!$stmt_log->fetch()) {
                    $c['variacao'] = number_format($variacao, 1, ',', '.');
                    $clientes_em_risco[] = $c;
                }
            }
        }
    }
} catch (PDOException $e) {
    die("Erro na consulta do CRM: " . $e->getMessage() . "\n");
}

// Se não houver clientes novos em risco, parar
if (empty($clientes_em_risco)) {
    echo "Nenhum novo alerta de risco detectado ou os e-mails já foram enviados recentemente.\n";
    exit();
}

// 3. Buscar os administradores que ativaram o recebimento de alertas
try {
    $stmt_admins = $pdo->query("SELECT nome, email FROM profissionais WHERE perfil = 'admin' AND ativo = 1 AND recebe_alertas_crm = 1 AND email != ''");
    $admins = $stmt_admins->fetchAll();
    
    if (empty($admins)) {
        echo "Existem clientes em risco, mas nenhum administrador configurou o e-mail para receber alertas.\n";
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao buscar admins: " . $e->getMessage() . "\n");
}

// 4. Montar o e-mail
$assunto = "🚨 Alerta CRM: " . count($clientes_em_risco) . " cliente(s) reduziram os servicos!";
$corpo = "<html><body style='font-family: Arial, sans-serif;'>";
$corpo .= "<h2 style='color: #dc3545;'>Radar CRM Trovão: Alerta de Queda de Lavagens</h2>";
$corpo .= "<p>Os seguintes clientes apresentaram uma redução drástica (maior que {$limite_alerta}%) no número de serviços nos últimos 30 dias em comparação aos 30 dias anteriores.</p>";
$corpo .= "<p>Recomendamos um contato imediato para retenção.</p>";
$corpo .= "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 800px;'>";
$corpo .= "<tr style='background-color: #f8f9fa;'><th>Cliente</th><th>Qtd. Anterior (30d)</th><th>Qtd. Atual (30d)</th><th>Queda</th><th>Telefone</th></tr>";

foreach ($clientes_em_risco as $c) {
    $corpo .= "<tr>";
    $corpo .= "<td><b>{$c['nome_razao_social']}</b></td>";
    $corpo .= "<td style='text-align: center;'>{$c['qtd_anterior']}</td>";
    $corpo .= "<td style='text-align: center;'>{$c['qtd_atual']}</td>";
    $corpo .= "<td style='color: red; text-align: center;'><b>{$c['variacao']}%</b></td>";
    $corpo .= "<td>{$c['telefone']}</td>";
    $corpo .= "</tr>";
}

$corpo .= "</table>";
$corpo .= "<br><p>Acesse o sistema gerador de laudos para ver os detalhes e registrar os contatos!</p>";
$corpo .= "</body></html>";

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=utf-8\r\n";
$headers .= "From: CRM Trovao <nao-responda@trovaolaudos.com.br>\r\n";

// 5. Disparar os e-mails e registrar no log
$emails_enviados = 0;
foreach ($admins as $admin) {
    if (mail($admin['email'], $assunto, $corpo, $headers)) {
        echo "E-mail enviado com sucesso para: {$admin['email']}\n";
        $emails_enviados++;
    } else {
        echo "Falha ao enviar e-mail para: {$admin['email']}\n";
    }
}

// 6. Registrar os alertas disparados para não floodar
if ($emails_enviados > 0) {
    $stmt_log_insert = $pdo->prepare("INSERT IGNORE INTO crm_alertas_log (cliente_id, data_alerta, variacao_percentual) VALUES (:id, CURDATE(), :var)");
    foreach ($clientes_em_risco as $c) {
        $stmt_log_insert->execute([
            ':id' => $c['id'],
            ':var' => (float) str_replace(',', '.', $c['variacao'])
        ]);
    }
    echo "Log de alertas atualizado no banco de dados.\n";
}

echo "Processo finalizado.\n";
?>
