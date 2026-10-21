<?php
// Ficheiro: includes/generate_report_omie.php

// 1. TRAVAS DE SEGURANÇA CONTRA TRAVAMENTO (Aumenta memória e tempo do servidor)
ini_set('memory_limit', '512M');
set_time_limit(300);

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

require_once 'db_connect.php';
require_once 'functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

// 2. Recebe parâmetros com validação rigorosa
$data_inicio = !empty($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim    = !empty($_GET['data_fim'])    ? $_GET['data_fim']    : date('Y-m-t');
$status      = !empty($_GET['status'])      ? $_GET['status']      : 'todos';
$turno       = !empty($_GET['turno'])       ? $_GET['turno']       : 'todos';
$cliente_id  = !empty($_GET['cliente_id'])  ? $_GET['cliente_id']  : '';

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim'   => $data_fim
];

// Uso da função DATE() garante que a hora do registro não quebre o filtro
$sql_where = " WHERE DATE(os.data_lavagem) BETWEEN :data_inicio AND :data_fim";

if ($status !== 'todos') {
    $sql_where .= " AND os.status = :status";
    $params[':status'] = $status;
}
if ($turno !== 'todos') {
    $sql_where .= " AND os.turno = :turno";
    $params[':turno'] = $turno;
}
if (!empty($cliente_id)) {
    $sql_where .= " AND os.cliente_id = :cliente_id";
    $params[':cliente_id'] = $cliente_id;
}

try {
    // Busca os dados filtrados
    $sql = "SELECT os.*, c.nome_razao_social, 
            GROUP_CONCAT(cs.nome_servico SEPARATOR ' + ') as servicos_realizados
            FROM ordens_servico AS os
            JOIN clientes AS c ON os.cliente_id = c.id
            LEFT JOIN ordens_servico_itens AS osi ON os.id = osi.ordem_servico_id
            LEFT JOIN catalogo_servicos AS cs ON osi.servico_id = cs.id
            " . $sql_where . " 
            GROUP BY os.id
            ORDER BY os.data_lavagem ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

    $templatePath = 'template_omie.xlsx'; 
    
    if (!file_exists($templatePath)) {
        die("Erro: O arquivo modelo 'template_omie.xlsx' não foi encontrado na pasta includes.");
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();

    // ----------------------------------------------------------------------
    // SOLUÇÃO OTIMIZADA: Pega apenas a última linha com DADOS REAIS
    // Isso evita o travamento por excesso de células vazias formatadas
    // ----------------------------------------------------------------------
    $highestRow = $sheet->getHighestDataRow(); 
    
    if ($highestRow >= 6) {
        $qtdLinhasParaApagar = $highestRow - 5;
        
        // Trava de segurança extra: Só apaga se for menos de 5000 linhas para não explodir a memória
        if ($qtdLinhasParaApagar > 0 && $qtdLinhasParaApagar <= 5000) {
            $sheet->removeRow(6, $qtdLinhasParaApagar);
        }
    }
    // ----------------------------------------------------------------------

    $rowNum = 6;
    $data_hoje = date('d/m/Y');

    foreach ($pedidos as $row) {
        $lacres = json_decode($row['lacres_info'], true) ?? [];
        $lacres_str = implode(" | ", array_filter($lacres));
        $data_lavagem = date('d/m/Y', strtotime($row['data_lavagem']));
        
        $valor_unitario = $row['subtotal']; 

        // Extrai apenas o primeiro nome do motorista e limita a 15 caracteres
        $nome_bruto = $row['motorista_nome'] ?? '';
        $motorista_partes = explode(' ', trim($nome_bruto));
        $motorista_primeiro_nome = !empty($motorista_partes[0]) ? substr($motorista_partes[0], 0, 15) : '';

        // Mapeamento exato conforme solicitado para importação Omie
        $sheet->setCellValue('B' . $rowNum, $row['id']); // Código de Integração
        $sheet->setCellValue('C' . $rowNum, $row['nome_razao_social']); // Cliente
        $sheet->setCellValue('D' . $rowNum, $data_hoje); // Previsão de Faturamento
        $sheet->setCellValue('E' . $rowNum, 'A VISTA'); // Número de Parcelas
        $sheet->setCellValue('F' . $rowNum, 'ALESSANDRA'); // Vendedor
        $sheet->setCellValue('G' . $rowNum, 'PADRAO'); // Projeto
        $sheet->setCellValue('H' . $rowNum, 'Lavagem Cam. Tanque Carreta'); // Categoria
        $sheet->setCellValue('I' . $rowNum, 'Omie.CASH'); // Conta Corrente
        $sheet->setCellValue('J' . $rowNum, $row['id']); // Nº do Pedido do Cliente
        $sheet->setCellValue('K' . $rowNum, $row['placa_cavalo']); // Nº do Contrato de Venda
        $sheet->setCellValue('L' . $rowNum, $data_lavagem); // Data RPS
        $sheet->setCellValue('M' . $rowNum, 'SÃO ROQUE (SP)'); // Cidade da Prestação do Serviço
        $sheet->setCellValue('N' . $rowNum, $row['placa_carreta']); // Código da Obra
        $sheet->setCellValue('O' . $rowNum, $motorista_primeiro_nome); // Código ART
        $sheet->setCellValue('P' . $rowNum, $lacres_str); // Contato
        
        // Pulos de colunas Q, R, S, T, etc...
        $sheet->setCellValue('U' . $rowNum, 'Não'); // Enviar o Link da NFS-e
        $sheet->setCellValue('V' . $rowNum, 'Não'); // Enviar também o Pix de Cobrança
        $sheet->setCellValue('X' . $rowNum, '1180330'); // Observações
        
        // Dados Fiscais e Financeiros
        $sheet->setCellValue('AC' . $rowNum, 'Imune'); // Tributação de Serviços
        $sheet->setCellValue('AD' . $rowNum, '14.01'); // Código do Serviço Municipal
        $sheet->setCellValue('AE' . $rowNum, '14.01'); // Código do Serviço LC116
        $sheet->setCellValue('AF' . $rowNum, '118033000'); // Código NBS
        $sheet->setCellValue('AG' . $rowNum, 1); // Quantidade
        $sheet->setCellValue('AH' . $rowNum, $valor_unitario); // Valor Unitário
        $sheet->setCellValue('AI' . $rowNum, 'Não'); // Não gerar conta a receber para este item?
        $sheet->setCellValue('AJ' . $rowNum, '0.00'); // Valor das Deduções
        $sheet->setCellValue('AK' . $rowNum, $row['servicos_realizados']); // Descrição do Serviço

        $rowNum++;
    }

    // Headers anticache obrigatórios do PHP
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Importacao_Omie_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1
    header('Pragma: no-cache'); // HTTP 1.0
    header('Expires: 0'); // Proxies

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die("Erro ao processar planilha: " . $e->getMessage());
}