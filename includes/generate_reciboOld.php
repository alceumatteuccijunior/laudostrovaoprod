<?php
// Ficheiro: includes/generate_recibo.php

require_once 'db_connect.php';
require_once 'functions.php';
require_once 'tcpdf/tcpdf.php';

secure_session_start();

if (!is_user_logged_in()) {
    die("Acesso negado.");
}

// --- Lógica para buscar os dados do serviço ---
$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$service_id) {
    die("ID de serviço inválido.");
}

try {
    $sql = "SELECT os.*, c.nome_razao_social, c.cpf_cnpj, p.nome as nome_profissional
            FROM ordens_servico AS os
            JOIN clientes AS c ON os.cliente_id = c.id
            JOIN profissionais AS p ON os.profissional_id = p.id
            WHERE os.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $service_id]);
    $servico = $stmt->fetch();

    if (!$servico) { die("Serviço não encontrado."); }

    $sql_itens = "SELECT i.servico_id, c.nome_servico, i.preco_cobrado 
                  FROM ordens_servico_itens AS i
                  JOIN catalogo_servicos AS c ON i.servico_id = c.id
                  WHERE i.ordem_servico_id = :id";
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([':id' => $service_id]);
    $servicos_prestados = $stmt_itens->fetchAll();
    
    $lacres_info = json_decode($servico['lacres_info'], true) ?? [];

} catch (PDOException $e) {
    die("Erro ao buscar dados do serviço: " . $e->getMessage());
}

// --- Função para desenhar uma via do recibo ---
function desenharViaRecibo($pdf, $servico, $servicos_prestados, $lacres_info, $y_start) {
    $pdf->SetY($y_start);

    // Cabeçalho do Recibo
    $pdf->SetFont('courier', 'B', 12);
    $pdf->Cell(0, 8, 'Lavador Trovao', 0, 1, 'C');
    $pdf->Ln(2);

    // Linha 1: Pedido e Data
    $pdf->SetFont('courier', '', 10);
    $data_registo = new DateTime($servico['data_registo']);
    $pdf->Cell(90, 5, 'Pedido: ' . $servico['id'], 0, 0, 'L');
    $pdf->Cell(90, 5, $data_registo->format('d/m/Y'), 0, 1, 'R');

    // Linha 2: Cliente
    $pdf->Cell(0, 5, 'Cliente: ' . $servico['nome_razao_social'], 0, 1, 'L');

    // Linha 3: Placas
    $pdf->Cell(90, 5, 'Placa Cavalo: ' . $servico['placa_cavalo'], 0, 0, 'L');
    $pdf->Cell(90, 5, 'Carreta / Truck: ' . $servico['placa_carreta'], 0, 1, 'L');

    // Linha 4: Motorista
    $pdf->Cell(0, 5, 'Motorista..: ' . $servico['motorista_nome'], 0, 1, 'L');

    // Linha 5: Horários
    $hora_chegada = $servico['hora_chegada'] ? date('H:i', strtotime($servico['hora_chegada'])) : '';
    $hora_saida = $servico['hora_saida'] ? date('H:i', strtotime($servico['hora_saida'])) : '';
    $pdf->Cell(90, 5, 'Chegada: ' . $hora_chegada, 0, 0, 'L');
    $pdf->Cell(90, 5, 'Saida: ' . $hora_saida, 0, 1, 'L');

    // Linha 6: Carregamento e Lacres
    $pdf->Cell(90, 5, 'Carregamento: ' . $servico['carga_ultima'], 0, 0, 'L');
    $numeros_lacres = implode(', ', array_filter($lacres_info));
    $pdf->Cell(90, 5, 'Lacres: ' . $numeros_lacres, 0, 1, 'L');
    $pdf->Ln(3);

    // Tabela de Serviços
    $pdf->Cell(30, 5, 'Codigo', 0, 0, 'L');
    $pdf->Cell(80, 5, 'Descricao', 0, 0, 'L');
    $pdf->Cell(1, 5, 'Qtd.', 0, 0, 'R');
    $pdf->Cell(40, 5, 'Valor Unitario', 0, 0, 'R');
    $pdf->Cell(35, 5, 'Valor Total', 0, 1, 'R');
    $pdf->Cell(0, 2, '========================================================================', 0, 1);

    foreach ($servicos_prestados as $item) {
        $pdf->Cell(30, 5, $item['servico_id'], 0, 0, 'L');
        $pdf->Cell(80, 5, $item['nome_servico'], 0, 0, 'L');
        $pdf->Cell(1, 5, '1.00', 0, 0, 'R');
        $pdf->Cell(40, 5, number_format($item['preco_cobrado'], 2, ',', '.'), 0, 0, 'R');
        $pdf->Cell(35, 5, number_format($item['preco_cobrado'], 2, ',', '.'), 0, 1, 'R');
    }
    $pdf->Cell(0, 2, '========================================================================', 0, 1);
    $pdf->SetFont('courier', 'B', 10);
    $pdf->Cell(160, 8, 'TOTAL R$', 0, 0, 'R');
    $pdf->Cell(25, 8, number_format($servico['valor_final'], 2, ',', '.'), 0, 1, 'R');
    $pdf->Ln(5);

    // Rodapé
    $pdf->SetFont('courier', '', 10);
    $pdf->Cell(0, 5, 'Declaro ter conferido placas e numeros de lacres constantes no Certificado.', 0, 1, 'L');
    $pdf->Ln(8);

    $pdf->Cell(95, 5, '________________________________________', 0, 0, 'L');
    $pdf->Cell(95, 5, '_________________________', 0, 1, 'C');
    
    $pdf->Cell(95, 5, $servico['motorista_nome'], 0, 0, 'L');
    $pdf->Cell(95, 5, 'TROVÃO BRASIL', 0, 1, 'C');

    $pdf->Cell(95, 5, 'RG:', 0, 0, 'L');
}


// --- Geração do PDF com TCPDF ---
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// --- CONFIGURAÇÃO DA ASSINATURA DIGITAL ---
$certificate = 'file://' . realpath(__DIR__ . '/../certs/certificado.pfx');
$password = '500752';
$info = ['Name' => 'TROVAO BRASIL', 'Location' => 'Sao Roque, SP', 'Reason' => 'Recibo de Pagamento', 'ContactInfo' => 'operacao.lavador@trovaobrasil.com.br'];
$pdf->setSignature($certificate, $certificate, $password, '', 2, $info);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema TrovãoBrasil');
$pdf->SetTitle('Recibo - Pedido #' . $service_id);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(false); // Desativa a quebra de página automática

$pdf->AddPage();

// --- Desenha a primeira via (superior) ---
desenharViaRecibo($pdf, $servico, $servicos_prestados, $lacres_info, 15);

// --- Linha de corte ---
$style = ['width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => '2,2', 'color' => [150, 150, 150]];
$pdf->Line(15, 148.5, 195, 148.5, $style);

// --- Desenha a segunda via (inferior) ---
desenharViaRecibo($pdf, $servico, $servicos_prestados, $lacres_info, 152);


// Saída do PDF
$pdf->Output('Recibo_Pedido_' . $service_id . '.pdf', 'I');
?>
