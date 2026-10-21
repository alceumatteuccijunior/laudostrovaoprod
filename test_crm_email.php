<?php
/**
 * Ficheiro: test_crm_email.php
 * Descrição: Script temporário para forçar e testar o envio de e-mails do CRM pelo navegador.
 */

require_once 'includes/db_connect.php';

echo "<h1>Teste de Envio de E-mail do CRM</h1>";
echo "Iniciando varredura...<br><br>";

$limite_alerta = 20; // padrão
try {
    $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'limite_alerta_queda_crm'");
    if ($row = $stmt->fetch()) {
        $limite_alerta = (float) $row['valor'];
    }
} catch (PDOException $e) {
    die("Erro ao ler configurações.");
}

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
            if ($variacao <= -$limite_alerta) {
                // NOTA: Diferente do cron_alertas_crm.php real, não checamos a tabela de log aqui
                // para garantir que o e-mail SEMPRE será disparado ao testar.
                $c['variacao'] = number_format($variacao, 1, ',', '.');
                $clientes_em_risco[] = $c;
            }
        }
    }
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

if (empty($clientes_em_risco)) {
    echo "<strong style='color:green;'>No momento não há nenhum cliente na zona de risco (Queda de {$limite_alerta}% ou mais).<br>O e-mail não será enviado.</strong>";
    exit();
}

echo "<strong>Clientes em risco encontrados:</strong> " . count($clientes_em_risco) . "<br>";
foreach ($clientes_em_risco as $c) {
    echo "- {$c['nome_razao_social']} (Variação: {$c['variacao']}%)<br>";
}
echo "<br>";

// Buscar admins
try {
    $stmt_admins = $pdo->query("SELECT nome, email FROM profissionais WHERE perfil = 'admin' AND ativo = 1 AND recebe_alertas_crm = 1 AND email != ''");
    $admins = $stmt_admins->fetchAll();
    
    if (empty($admins)) {
        echo "<strong style='color:red;'>Nenhum administrador configurou para receber os alertas. Vá na tela de edição do seu usuário e marque a caixinha de alertas de CRM e adicione seu e-mail!</strong>";
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao buscar admins: " . $e->getMessage());
}

echo "<strong>Administradores inscritos para receber o alerta:</strong><br>";

// Montar o e-mail
$assunto = "🚨 [TESTE] Alerta CRM: " . count($clientes_em_risco) . " cliente(s) reduziram os servicos!";
$corpo = "<html><body style='font-family: Arial, sans-serif;'>";
$corpo .= "<h2 style='color: #dc3545;'>Radar CRM Trovão: Alerta de Queda de Lavagens</h2>";
$corpo .= "<p>Este é um E-MAIL DE TESTE.</p>";
$corpo .= "<p>Os seguintes clientes apresentaram uma redução drástica (maior que {$limite_alerta}%) no número de serviços nos últimos 30 dias em comparação aos 30 dias anteriores.</p>";
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
$headers .= "From: CRM Trovao <alertas@trovaolaudos.com.br>\r\n";
$headers .= "Reply-To: alertas@trovaolaudos.com.br\r\n";

// Disparar
$enviados = 0;
foreach ($admins as $admin) {
    echo "- Enviando para: {$admin['nome']} ({$admin['email']})... ";
    if (mail($admin['email'], $assunto, $corpo, $headers)) {
        echo "<span style='color:green;'>OK!</span><br>";
        $enviados++;
    } else {
        echo "<span style='color:red;'>FALHOU!</span> O servidor bloqueou ou a função mail() está desabilitada.<br>";
    }
}

echo "<br><strong>Processo finalizado. E-mails enviados: {$enviados}</strong><br>";
echo "<p>Nota: Pode demorar alguns minutos para chegar na sua caixa de entrada ou cair na pasta de Spam (Lixo Eletrônico).</p>";
?>
