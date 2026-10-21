<?php
// Ficheiro: api/ai_consultant.php
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    echo json_encode(['erro' => 'Acesso negado.']); exit;
}

// ==========================================================
// CHAVE DE API DO GOOGLE GEMINI
// ==========================================================
$GEMINI_API_KEY = 'AIzaSyDdbSdWTC1_dVHF_LO9Ve2Wq-fAnP31zb4'; 

$input = json_decode(file_get_contents('php://input'), true);
$pergunta_usuario = $input['prompt'] ?? '';

if (empty($pergunta_usuario)) {
    echo json_encode(['erro' => 'Nenhuma pergunta enviada.']); exit;
}

try {
    // ==========================================================
    // 1. EXTRAÇÃO DE DADOS AGREGADOS (O CONHECIMENTO DA IA)
    // ==========================================================
    
    // Top Clientes
    $stmt1 = $pdo->query("SELECT c.nome_razao_social as cliente, SUM(os.valor_final) as total_faturado, COUNT(os.id) as qtd_pedidos FROM ordens_servico os JOIN clientes c ON os.cliente_id = c.id WHERE os.status = 'Ativo' GROUP BY c.id ORDER BY total_faturado DESC LIMIT 30");
    $top_clientes = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // Faturamento Mensal
    $stmt2 = $pdo->query("SELECT DATE_FORMAT(data_registo, '%Y-%m') as mes, SUM(valor_final) as faturado FROM ordens_servico WHERE status = 'Ativo' GROUP BY mes ORDER BY mes DESC LIMIT 12");
    $faturamento_mensal = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Top Serviços
    $stmt3 = $pdo->query("SELECT cs.nome_servico, COUNT(osi.id) as quantidade FROM ordens_servico_itens osi JOIN catalogo_servicos cs ON osi.servico_id = cs.id JOIN ordens_servico os ON osi.ordem_servico_id = os.id WHERE os.status = 'Ativo' GROUP BY cs.id ORDER BY quantidade DESC LIMIT 20");
    $top_servicos = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    // Produtividade por Turno
    $stmt4 = $pdo->query("SELECT turno, COUNT(id) as total_pedidos, SUM(valor_final) as faturado FROM ordens_servico WHERE status = 'Ativo' GROUP BY turno");
    $turnos = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    // Status e Motivos de Cancelamento
    $stmt5 = $pdo->query("SELECT status, motivo_cancelamento, COUNT(id) as total FROM ordens_servico GROUP BY status, motivo_cancelamento");
    $status_pedidos = $stmt5->fetchAll(PDO::FETCH_ASSOC);

    // Ranking de Motoristas e Frota (NOVO)
    $stmt6 = $pdo->query("SELECT motorista_nome, COUNT(id) as viagens, SUM(valor_final) as valor_gerado FROM ordens_servico WHERE status = 'Ativo' AND motorista_nome != '' GROUP BY motorista_nome ORDER BY valor_gerado DESC LIMIT 15");
    $top_motoristas = $stmt6->fetchAll(PDO::FETCH_ASSOC);

    // Formas de Pagamento (NOVO)
    $stmt7 = $pdo->query("SELECT metodo_pagamento, COUNT(id) as qtd, SUM(valor_final) as total FROM ordens_servico WHERE status = 'Ativo' GROUP BY metodo_pagamento");
    $pagamentos = $stmt7->fetchAll(PDO::FETCH_ASSOC);

    // Pacote de dados JSON Seguro
    $dados_negocio = json_encode([
        'top_clientes_lucro' => $top_clientes,
        'faturamento_ultimos_12_meses' => $faturamento_mensal,
        'servicos_mais_realizados' => $top_servicos,
        'produtividade_por_turno' => $turnos,
        'status_e_cancelamentos' => $status_pedidos,
        'top_motoristas' => $top_motoristas,
        'metodos_pagamento' => $pagamentos
    ], JSON_UNESCAPED_UNICODE);

    // ==========================================================
    // 2. O CÉREBRO: INSTRUÇÃO DO SISTEMA E MAPA DO BANCO DE DADOS
    // ==========================================================
    $system_instruction = "Você é um Consultor de Negócios Sênior e Cientista de Dados da empresa TrovãoBrasil (especializada em lavagem de caminhões, carretas e tanques). 

    --- SEU CONHECIMENTO SOBRE A ESTRUTURA DO BANCO DE DADOS ---
    Para que você entenda a operação da empresa, aqui está como o nosso sistema funciona e as relações das tabelas:
    1. Tabela `clientes`: Cadastro de transportadoras e motoristas independentes (razao_social, cnpj, contatos).
    2. Tabela `profissionais`: Nossa equipe de lavadores que executam o serviço.
    3. Tabela `catalogo_servicos`: Os tipos de lavagem que vendemos (Lavagem Completa, Interna, Tanque, etc) e preços.
    4. Tabela `ordens_servico` (A mais importante): Registra cada lavagem. Ela se liga ao `cliente` (cliente_id), aos `profissionais` (quem lavou), e guarda dados operacionais como: `motorista_nome` (quem trouxe o caminhão), `placa_carreta`, `placa_cavalo`, `turno` (Manhã, Tarde, Noite), `valor_final`, `data_registo` e `metodo_pagamento`.
    5. Tabela `ordens_servico_itens`: Liga a Ordem de Serviço aos vários Serviços do Catálogo que foram realizados nela.

    --- SUA MISSÃO ---
    Vou te passar os dados estatísticos reais gerados a partir dessas tabelas no formato JSON.
    Responda as perguntas dos administradores agindo como um consultor especialista.
    Cruze os dados! Se perguntarem 'Qual cliente dá mais lucro e por quê?', analise o 'top_clientes_lucro', associe aos serviços mais vendidos, aos métodos de pagamento ou aos motoristas para criar uma resposta inteligente e analítica.
    - Elogie bons desempenhos operacionais.
    - Destaque anomalias (ex: muitos cancelamentos).
    
    --- FORMATO DE RESPOSTA OBRIGATÓRIO ---
    Você DEVE retornar EXCLUSIVAMENTE um objeto JSON válido. NÃO USE blocos markdown de código (como ```json).
    {
        \"resposta_texto\": \"Sua explicação textual detalhada, profissional e analítica. Use tags HTML (como <b>, <br>, <ul>, <li>). Mantenha uma formatação limpa e executiva.\",
        \"tem_grafico\": true ou false (true se os dados puderem ser mostrados visualmente),
        \"grafico_tipo\": \"bar\" (rankings), \"line\" (evolução no tempo), \"pie\" ou \"doughnut\" (proporções),
        \"grafico_titulo\": \"Título sugestivo para o gráfico\",
        \"grafico_labels\": [\"Rótulo 1\", \"Rótulo 2\"],
        \"grafico_dados\": [10.5, 20.0]
    }";

    $prompt_completo = "DADOS ESTATÍSTICOS ATUAIS DA TROVÃOBRASIL:\n" . $dados_negocio . "\n\nPERGUNTA DO DIRETOR:\n" . $pergunta_usuario;

    // ==========================================================
    // 3. COMUNICAÇÃO COM A API DO GOOGLE GEMINI 2.5 FLASH
    // ==========================================================
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $GEMINI_API_KEY;

    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt_completo]]]
        ],
        "systemInstruction" => [
            "parts" => [["text" => $system_instruction]]
        ],
        "generationConfig" => [
            "responseMimeType" => "application/json"
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        echo json_encode(['erro' => "Erro de conexão do servidor (cURL): " . $curlError]); exit;
    }

    if ($httpCode != 200) {
        $error_msg = json_decode($response, true);
        $detalhe = $error_msg['error']['message'] ?? $response; 
        echo json_encode(['erro' => "Erro do Google (HTTP $httpCode): " . $detalhe]); exit;
    }

    $responseData = json_decode($response, true);
    $ia_json_string = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    echo $ia_json_string;

} catch (Exception $e) {
    echo json_encode(['erro' => 'Erro interno ao processar dados: ' . $e->getMessage()]);
}