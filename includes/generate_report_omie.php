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
$os_min      = !empty($_GET['os_min'])      ? (int)$_GET['os_min'] : 0; // Filtro de ponto de partida

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim'    => $data_fim
];

// Uso da função DATE() garante que a hora do registo não quebre o filtro
$sql_where = " WHERE DATE(os.data_registo) BETWEEN :data_inicio AND :data_fim";

// REGRA RÍGIDA: Nunca exportar pedidos cancelados para o Omie
$sql_where .= " AND os.status != 'Cancelado'";

if ($status !== 'todos' && $status !== 'Cancelado') {
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
if ($os_min > 0) {
    // Adiciona o filtro para exportar apenas a partir da OS escolhida
    $sql_where .= " AND os.id >= :os_min";
    $params[':os_min'] = $os_min;
}

try {
    // ----------------------------------------------------------------------
    // CORREÇÃO APLICADA: 
    // Como a tabela ordens_servico_itens não possui a coluna 'valor', 
    // buscamos o preço base diretamente do catálogo (cs.preco).
    // ----------------------------------------------------------------------
    $sql = "SELECT os.*, c.nome_razao_social, 
            cs.nome_servico as servico_individual_nome,
            cs.preco as servico_individual_valor
            FROM ordens_servico AS os
            JOIN clientes AS c ON os.cliente_id = c.id
            JOIN ordens_servico_itens AS osi ON os.id = osi.ordem_servico_id
            JOIN catalogo_servicos AS cs ON osi.servico_id = cs.id
            " . $sql_where . " 
            ORDER BY os.data_registo ASC, os.id ASC, osi.id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

    // Usa o novo modelo atualizado do Omie
    $templatePath = 'template_omie_new.xlsx'; 
    
    if (!file_exists($templatePath)) {
        // Fallback para modelo anterior caso não encontre o novo
        $templatePath = 'template_omie.xlsx';
    }

    if (!file_exists($templatePath)) {
        die("Erro: O arquivo modelo 'template_omie_new.xlsx' não foi encontrado na pasta includes.");
    }

    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();

    // ----------------------------------------------------------------------
    // Limpeza de linhas sobressalentes
    // ----------------------------------------------------------------------
    $highestRow = $sheet->getHighestDataRow(); 
    
    if ($highestRow >= 6) {
        $qtdLinhasParaApagar = $highestRow - 5;
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
        
        // CORREÇÃO: Pega o preço base do Catálogo. Se vier zero ou nulo, usa o subtotal como redundância
        $valor_unitario = (isset($row['servico_individual_valor']) && $row['servico_individual_valor'] > 0) 
                          ? $row['servico_individual_valor'] 
                          : $row['subtotal']; 
                          
        $nome_servico = $row['servico_individual_nome'] ?? 'Serviço Padrão';

        // Extrai apenas o primeiro nome do motorista e limita a 15 caracteres
        $nome_bruto = $row['motorista_nome'] ?? '';
        $motorista_partes = explode(' ', trim($nome_bruto));
        $motorista_primeiro_nome = !empty($motorista_partes[0]) ? substr($motorista_partes[0], 0, 15) : '';

        // Mapeamento atualizado conforme novo layout de importação do Omie (template_omie_new.xlsx)
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
        $sheet->setCellValue('N' . $rowNum, 'SÃO ROQUE (SP)'); // Cidade de Incidência (Nova coluna no Omie)
        $sheet->setCellValue('O' . $rowNum, $row['placa_carreta']); // Código da Obra (deslocada N -> O)
        $sheet->setCellValue('P' . $rowNum, $motorista_primeiro_nome); // Código ART (deslocada O -> P)
        $sheet->setCellValue('Q' . $rowNum, $lacres_str); // Contato (deslocada P -> Q)
        
        // Pulos de colunas e opções de envio
        $sheet->setCellValue('T' . $rowNum, 'Não'); // Enviar o Link da NFS-e gerada na Prefeitura?
        $sheet->setCellValue('V' . $rowNum, 'Não'); // Enviar também o Link de Cobrança?
        $sheet->setCellValue('W' . $rowNum, 'Não'); // Enviar também o Pix de Cobrança?
        $sheet->setCellValue('Y' . $rowNum, '1180330'); // Observações (deslocada X -> Y)
        
        // Dados Fiscais e Financeiros
        $sheet->setCellValue('AD' . $rowNum, 'Imune'); // Tributação de Serviços (deslocada AC -> AD)
        $sheet->setCellValue('AE' . $rowNum, '14.01'); // Código do Serviço Municipal (deslocada AD -> AE)
        $sheet->setCellValue('AF' . $rowNum, '14.01'); // Código do Serviço LC116 (deslocada AE -> AF)
        $sheet->setCellValue('AG' . $rowNum, '118033000'); // Código NBS (deslocada AF -> AG)
        $sheet->setCellValue('AH' . $rowNum, 1); // Quantidade (deslocada AG -> AH)
        $sheet->setCellValue('AI' . $rowNum, $valor_unitario); // VALOR INDIVIDUAL DO SERVIÇO DESMEMBRADO (deslocada AH -> AI)
        $sheet->setCellValue('AJ' . $rowNum, 'Não'); // Não gerar conta a receber para este item? (deslocada AI -> AJ)
        $sheet->setCellValue('AK' . $rowNum, '0.00'); // Valor das Deduções (deslocada AJ -> AK)
        $sheet->setCellValue('AL' . $rowNum, $nome_servico); // NOME ESPECÍFICO DO SERVIÇO (deslocada AK -> AL)

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