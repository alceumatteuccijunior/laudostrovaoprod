<?php
// Ficheiro: api/sync_receive.php
// Este script RODA NO SERVIDOR MESTRE (ONLINE). 
// Ele recebe os dados de PUSH do servidor local.

require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Segurança Básica: Em produção real, você deve validar um token enviado no Header
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['origem'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Carga de dados inválida.']);
    exit();
}

$origem = $input['origem'];
$clientes = $input['clientes'] ?? [];
$ordens = $input['ordens_servico'] ?? [];

$clientes_map = []; // [local_id => master_id]
$ordens_map = [];   // [local_id => master_id]

try {
    $pdo->beginTransaction();

    // 1. Processar Clientes
    foreach ($clientes as $cli) {
        $local_id = $cli['id'];
        
        // Verifica se o ID já existe
        $stmtCheck = $pdo->prepare("SELECT id, origem FROM clientes WHERE id = ?");
        $stmtCheck->execute([$local_id]);
        $row = $stmtCheck->fetch();

        if ($row) {
            if ($row['origem'] === $origem) {
                // É um retry do mesmo local. Apenas atualiza.
                $stmtUpd = $pdo->prepare("UPDATE clientes SET nome_razao_social=?, cnpj_cpf=?, telefone=?, email=?, endereco=? WHERE id=?");
                $stmtUpd->execute([$cli['nome_razao_social'], $cli['cnpj_cpf'], $cli['telefone'], $cli['email'], $cli['endereco'], $local_id]);
            } else {
                // CONFLITO DE ID! Inserir como novo (auto-increment)
                $stmtIns = $pdo->prepare("INSERT INTO clientes (nome_razao_social, cnpj_cpf, telefone, email, endereco, origem, sync_status, data_sync) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmtIns->execute([$cli['nome_razao_social'], $cli['cnpj_cpf'], $cli['telefone'], $cli['email'], $cli['endereco'], $origem]);
                $clientes_map[$local_id] = $pdo->lastInsertId();
            }
        } else {
            // ID livre, insere com o ID original do local
            $stmtIns = $pdo->prepare("INSERT INTO clientes (id, nome_razao_social, cnpj_cpf, telefone, email, endereco, origem, sync_status, data_sync) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmtIns->execute([$local_id, $cli['nome_razao_social'], $cli['cnpj_cpf'], $cli['telefone'], $cli['email'], $cli['endereco'], $origem]);
        }
    }

    // 2. Processar Ordens de Serviço
    foreach ($ordens as $os) {
        $local_id = $os['id'];
        
        // Corrige o cliente_id se ele foi mapeado para um novo no passo anterior
        $cliente_id_correto = $os['cliente_id'];
        if (isset($clientes_map[$cliente_id_correto])) {
            $cliente_id_correto = $clientes_map[$cliente_id_correto];
        }

        // Verifica se o ID já existe
        $stmtCheck = $pdo->prepare("SELECT id, origem FROM ordens_servico WHERE id = ?");
        $stmtCheck->execute([$local_id]);
        $row = $stmtCheck->fetch();

        // Extrai os dados para o insert/update, preenchendo com nulos caso não existam
        $profissional_id = $os['profissional_id'] ?: null;
        $profissional_id_2 = !empty($os['profissional_id_2']) ? $os['profissional_id_2'] : null;
        $servico_id = $os['servico_id'] ?: null;

        if ($row) {
            if ($row['origem'] === $origem) {
                // Retry: Update
                $stmtUpd = $pdo->prepare("UPDATE ordens_servico SET cliente_id=?, profissional_id=?, profissional_id_2=?, motorista=?, doc_motorista=?, hora_chegada=?, hora_saida=?, placa_carreta=?, placa_cavalo=?, turno=?, servico_id=?, valor_servico=?, forma_pagamento=?, status=?, data_lavagem=? WHERE id=?");
                $stmtUpd->execute([
                    $cliente_id_correto, $profissional_id, $profissional_id_2, 
                    $os['motorista'], $os['doc_motorista'], $os['hora_chegada'], $os['hora_saida'], 
                    $os['placa_carreta'], $os['placa_cavalo'], $os['turno'], $servico_id, 
                    $os['valor_servico'], $os['forma_pagamento'], $os['status'], $os['data_lavagem'], 
                    $local_id
                ]);
            } else {
                // CONFLITO! Inserir sem ID para forçar Auto Increment
                $stmtIns = $pdo->prepare("INSERT INTO ordens_servico (cliente_id, profissional_id, profissional_id_2, motorista, doc_motorista, hora_chegada, hora_saida, placa_carreta, placa_cavalo, turno, servico_id, valor_servico, forma_pagamento, status, data_lavagem, data_registo, origem, sync_status, data_sync) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmtIns->execute([
                    $cliente_id_correto, $profissional_id, $profissional_id_2, 
                    $os['motorista'], $os['doc_motorista'], $os['hora_chegada'], $os['hora_saida'], 
                    $os['placa_carreta'], $os['placa_cavalo'], $os['turno'], $servico_id, 
                    $os['valor_servico'], $os['forma_pagamento'], $os['status'], $os['data_lavagem'], 
                    $os['data_registo'], $origem
                ]);
                $ordens_map[$local_id] = $pdo->lastInsertId();
            }
        } else {
            // Inserir com o ID local
            $stmtIns = $pdo->prepare("INSERT INTO ordens_servico (id, cliente_id, profissional_id, profissional_id_2, motorista, doc_motorista, hora_chegada, hora_saida, placa_carreta, placa_cavalo, turno, servico_id, valor_servico, forma_pagamento, status, data_lavagem, data_registo, origem, sync_status, data_sync) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmtIns->execute([
                $local_id, $cliente_id_correto, $profissional_id, $profissional_id_2, 
                $os['motorista'], $os['doc_motorista'], $os['hora_chegada'], $os['hora_saida'], 
                $os['placa_carreta'], $os['placa_cavalo'], $os['turno'], $servico_id, 
                $os['valor_servico'], $os['forma_pagamento'], $os['status'], $os['data_lavagem'], 
                $os['data_registo'], $origem
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Dados processados com sucesso.',
        'clientes_map' => $clientes_map,
        'ordens_map' => $ordens_map
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados mestre: ' . $e->getMessage()]);
}
?>
