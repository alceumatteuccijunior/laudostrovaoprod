<?php
// Ficheiro: includes/generate_laudo.php

require_once 'db_connect.php';
require_once 'functions.php';
require_once 'tcpdf/tcpdf.php';

secure_session_start();

if (!is_user_logged_in()) {
    die("Acesso negado.");
}

// --- Lógica para buscar os dados do serviço ---
$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$tipo_laudo = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_STRING) ?: 'trovao';

if (!$service_id) {
    die("ID de serviço inválido.");
}

try {
    $sql = "SELECT os.*, c.nome_razao_social, c.endereco, p.nome AS nome_profissional
            FROM ordens_servico AS os
            JOIN clientes AS c ON os.cliente_id = c.id
            JOIN profissionais AS p ON os.profissional_id = p.id
            WHERE os.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $service_id]);
    $servico = $stmt->fetch();

    if (!$servico) { die("Serviço não encontrado."); }

    $sql_itens = "SELECT c.nome_servico FROM ordens_servico_itens AS i JOIN catalogo_servicos AS c ON i.servico_id = c.id WHERE i.ordem_servico_id = :id";
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([':id' => $service_id]);
    $servicos_prestados = $stmt_itens->fetchAll(PDO::FETCH_COLUMN);

    $lacres_info = json_decode($servico['lacres_info'], true) ?? [];
    $procedimentos_realizados = json_decode($servico['procedimentos_json'], true) ?? [];

} catch (PDOException $e) {
    die("Erro ao buscar detalhes do serviço: " . $e->getMessage());
}

// --- Classe PDF Personalizada ---
class PDF extends TCPDF
{
    public $tipo_laudo_atual = 'trovao';

    function Header() {
        if ($this->tipo_laudo_atual === 'trovao') {
            $logo_path = '../assets/img/logo_trovao.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 15, 15, 80, 0, 'PNG');
            }
        } elseif ($this->tipo_laudo_atual === 'aak') {
            $logo_path = '../assets/img/logo_aak.png';
            $this->SetFont('helvetica', '', 10);
            $this->SetLineWidth(0.5);
            $this->Line(15, 10, 195, 10); $this->Line(15, 25, 195, 25);
            $this->Line(15, 10, 15, 25); $this->Line(195, 10, 195, 25);
            $this->Line(55, 10, 55, 25); $this->Line(155, 10, 155, 25);
            
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 20, 12, 30, 0, 'PNG');
            }
            
            $this->SetY(12);
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(40, 10, '', 0, 0);
            $this->Cell(100, 10, 'Declaração de Higienização de Caminhões', 0, 0, 'C');

            $this->SetFont('helvetica', '', 9);
            $html = '<table border="0" cellpadding="1"><tr><td align="left"><b>Cod.:</b> FOR.01.01.0088</td></tr><tr><td align="left"><b>Vigência:</b> 19/06/2024</td></tr><tr><td align="left"><b>Revisão:</b> 03</td></tr></table>';
            $this->writeHTMLCell(40, 10, 155, 11, $html, 0, 1, 0, true, 'J', true);
        }
    }

    function Footer() {
        $this->SetY(-20);
        $this->SetFont('helvetica','',8);
        $this->Cell(0,5,'TROVAO BRASIL COMERCIO E SERVICOS AUTOMOTIVOS LTDA | CNPJ: 50.075.298/0001-77',0,1,'C');
        $this->Cell(0,5,'E-mail: operacao.lavador@trovaobrasil.com.br | Tel. (11) 4712-8839',0,1,'C');
        $this->SetFont('helvetica','I',8);
        $this->Cell(0,5,'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(),0,0,'C');
    }

    function TabelaProcedimentos($procedimentos_realizados) {
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 7, 'Servicos Executados', 1, 1, 'C', true);
        
        $lista_completa = [
            "Lavagem Interna", "Vapor", "Lavagem Com Agua Quente", "Lavagem Com Detergente Neutro",
            "Enxague Com Agua - 30 Minutos", "Vaporizacao Korsher/Halal", "Secagem Manual", "Ventilacao / Exaustao",
            "Lavagem Da Tubulacao", "Lavagem Das Valvulas", "Lavagem Dos Mangotes", "Troca De Junta Das Valvulas",
            "Desmonte Das Valvulas Dreno", "Troca Das Juntas Da Boca Visita", "Limpeza De Manometros", "PH enxague final (7,5 a 9,0)"
        ];
        
        $this->SetFont('helvetica', '', 9);
        $this->SetFillColor(255, 255, 255);
        
        for ($i = 0; $i < 8; $i++) {
            $proc_esq = $lista_completa[$i];
            $status_esq = in_array($proc_esq, $procedimentos_realizados) ? 'S' : 'N';
            $this->Cell(85, 6, $proc_esq, 'L', 0);
            $this->Cell(10, 6, $status_esq, 'R', 0, 'C');

            $proc_dir = $lista_completa[$i + 8];
            $status_dir = in_array($proc_dir, $procedimentos_realizados) ? 'S' : 'N';
            $this->Cell(85, 6, $proc_dir, 'L', 0);
            $this->Cell(10, 6, $status_dir, 'R', 1, 'C');
        }
        $this->Cell(0, 0, '', 'T', 1);
    }
}

$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->tipo_laudo_atual = $tipo_laudo;

// --- CONFIGURAÇÃO DA ASSINATURA DIGITAL ---
$certificate = 'file://' . realpath(__DIR__ . '/../certs/certificado.pfx');
$password = '500752';
$info = ['Name' => 'TROVAO BRASIL', 'Location' => 'Sao Roque, SP', 'Reason' => 'Validação de Laudo', 'ContactInfo' => 'operacao.lavador@trovaobrasil.com.br'];
$pdf->setSignature($certificate, $certificate, $password, '', 2, $info);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema TrovãoBrasil');
$pdf->SetTitle('Laudo de Higienização - Pedido #' . $service_id);
$pdf->setPrintHeader(true);
$pdf->SetMargins(15, 35, 15);
$pdf->SetAutoPageBreak(TRUE, 25);

// =============================================================================
// LÓGICA PARA O LAUDO TROVÃO (2 PÁGINAS)
// =============================================================================
if ($tipo_laudo === 'trovao') {
    // ---------- PÁGINA 1 ----------
    $pdf->AddPage();
    // Utilizando a Data de Registro como fonte da verdade para o Laudo
    // $data_registo_obj = new DateTime($servico['data_registo']);
    $data_registo_obj = new DateTime($servico['data_lavagem']);

    $pdf->SetY(15); $pdf->SetX(-75);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(60, 8, 'CERTIFICADO', 1, 1, 'C');
    $pdf->SetX(-75);
    $pdf->Cell(60, 8, str_pad($servico['id'], 7, '0', STR_PAD_LEFT), 1, 1, 'C');
    $pdf->SetX(-75);
    $pdf->Cell(60, 8, $data_registo_obj->format('d/m/Y'), 1, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Declaração de Higienização de Caminhões', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 9);
    $pdf->Write(5, 'Certifico para os devidos fins que o veículo de placa ');
    $pdf->SetFont('helvetica', 'B', 9); $pdf->Write(5, $servico['placa_carreta']);
    $pdf->SetFont('helvetica', '', 9); $pdf->Write(5, ' da transportadora ');
    $pdf->SetFont('helvetica', 'B', 9); $pdf->Write(5, $servico['nome_razao_social']);
    $pdf->SetFont('helvetica', '', 9); $pdf->Write(5, ', foi higienizado de acordo com os requisitos da empresa ');
    $pdf->SetFont('helvetica', 'B', 9); $pdf->Write(5, $servico['nome_razao_social']);
    $pdf->SetFont('helvetica', '', 9); $pdf->Write(5, ' tais como:');
    $pdf->Ln(9);
    
    $texto_padrao_1 = "Utilizacao de agua potavel e clorada;\nA mangueira utilizada na limpeza esta em bom estado de conservacao, sem remendos, sem rasgos, ou vazamentos e completamente limpa;\nSem a devida autorizacao da empresa " . $servico['nome_razao_social'] . " nenhum produto quimico deve ser usado na limpeza do tanque. No caso de uso de detergente os mesmos devem ser aprovados e apropriados para esse uso, apos utilizacao os mesmos devem ser enxaguados.";
    $pdf->MultiCell(0, 5, $texto_padrao_1, 0, 'L');
    $pdf->Ln(5);

    $texto_padrao_2 = "Realizar o procedimento de limpeza recomendado pela empresa " . $servico['nome_razao_social'] . " conforme abaixo:\n* Enxaguar o tanque do caminhao com agua quente (aproximadamente 40) atraves do bocal de limpeza na parte superior do tanque;\n* A agua quente e circulada no tanque ate que a mesma saia com coloracao limpida (aproximadamente de 10 a 15 minutos);\n* Os tubos sao removidos e devem ser lavados com agua quente ate que os mesmos saiam com coloracao limpida;\n* A secagem deve ser feita por completo no proprio local de lavagem;\n* Os caminhoes devem ser bem fechados em todas as entradas e saidas. E proibido viajar com o caminhao com as entradas e/ou saidas abertas;\n* Os tubos para colocacao da mangueira devem ser fechados (com tampa nas duas extremidades) e possuir local para lacres;\n* O tanque e examinado visualmente atraves do bocal localizado na parte superior do caminhao;\n* Nunca deixar qualquer residuo de carga anterior;\n* As pessoas envolvidas na lavagem atendem requisitos tais como: higiene, uniforme limpo, botas de borracha limpa e seca, nao ter ferimentos ou doencas, ter treinamento adequado para a operacao.\n* Korsher/Halal, evidenciar se os tres ultimos carregamentos nao foram carregados produtos de origem animal e que contem alcool.";
    $pdf->MultiCell(0, 5, $texto_padrao_2, 0, 'L');
    $pdf->Ln(5);

    // --- BLOCO DE INFORMAÇÕES ADICIONADO AO FINAL DA PÁGINA 1 ---
    $hora_saida = $servico['hora_saida'] ? date('H:i', strtotime($servico['hora_saida'])) : '';
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Write(5, 'Na empresa C. Matteucci na data de ' . $data_registo_obj->format('d/m/Y') . ' as ' . $hora_saida . ', conforme ordem de serviço de n° ' . str_pad($servico['id'], 7, '0', STR_PAD_LEFT) . '.');
    $pdf->Ln(8);

    $pdf->Write(5, 'O caminhão deixou as dependências da lavadora portando os lacres:');
    $pdf->Ln(8);

    $pdf->Cell(95, 5, 'Boca de Vista Sup. 1: ' . ($lacres_info['bvs1'] ?? '___'), 0, 0);
    $pdf->Cell(95, 5, 'Boca de Vista Sup. 2: ' . ($lacres_info['bvs2'] ?? '___'), 0, 1);
    $pdf->Cell(95, 5, 'Boca de Vista Sup. 3: ' . ($lacres_info['bvs3'] ?? '___'), 0, 0);
    $pdf->Cell(95, 5, 'Válvula Dreno: ' . ($lacres_info['val_dreno'] ?? '___'), 0, 1);
    $pdf->Cell(95, 5, 'Válvula Descarga: ' . ($lacres_info['val_descarga'] ?? '___'), 0, 0);
    $pdf->Cell(95, 5, 'Suporte Mangote: ' . ($lacres_info['sup_mangote'] ?? '___'), 0, 1);
    $pdf->Ln(8);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Write(5, 'Último carregamento: ' . $servico['carga_ultima']);
    $pdf->Ln(8);
    

    $data_vencimento = clone $data_registo_obj;

    if ($servico['cliente_id'] == 21) {
        $data_vencimento->modify('+10 days');
    } else {
        $data_vencimento->modify('+5 days');
    }

    $pdf->Write(5, 'Este laudo tem validade até ' . $data_vencimento->format('d/m/Y') . '.');
    $pdf->Ln(15);

    $caminho_assinatura = '../assets/img/assinatura.png';
    if (file_exists($caminho_assinatura)) {
        $pdf->Image($caminho_assinatura, 75, $pdf->GetY() - 10, 60, 15, 'PNG');
    }

    $pdf->Cell(0, 9, '_________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'TROVÃO BRASIL', 0, 1, 'C');
    // --- FIM DO NOVO BLOCO ---


    // ---------- PÁGINA 2 ----------
    $pdf->AddPage();
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(15, 6, 'Cliente:', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, $servico['nome_razao_social'], 0, 1);

     // --- LINHA ADICIONADA ---
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(20, 6, 'Motorista:', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, $servico['motorista_nome'], 0, 1);
    // --- FIM DA LINHA ADICIONADA ---
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(35, 6, 'Placa Carreta/Truck:', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(67, 6, $servico['placa_carreta'], 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(25, 6, 'Placa Cavalo:', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, $servico['placa_cavalo'], 0, 1);
    $pdf->Ln(5);

    $pdf->Cell(25, 6, 'Últimos 3 (três) carregamentos:', 0, 0);
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(13, 6, 'Último', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(70, 6, $servico['carga_ultima'], 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(20, 6, 'Penúltimo:', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 6, $servico['carga_penultima'], 0, 0);
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(25, 6, 'Antepenúltimo:', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, $servico['carga_antepenultima'], 0, 1);
    $pdf->Ln(3);

    $pdf->TabelaProcedimentos($procedimentos_realizados);
    $pdf->Ln(1);

    $texto_certificacao = "Certificamos que o tanque e seus equipamentos, seus compartimentos e suas conexoes foram lavados e/ou descontaminados conforme os procedimentos apresentados, estando o veiculo apto a efetuar o proximo carregamento. No teste de lavagem nao foram evidenciados residuos da ultima carga.\nTodos os equipamentos e utensilios utilizados para secagem e lavagem de tanques foram removidos e evidenciados pelo chefe de turno.";
    $pdf->MultiCell(0, 5, $texto_certificacao, 0, 'J');
    $pdf->Ln(1);
    
    // --- BLOCO DE LACRES RESTAURADO NA PÁGINA 2 ---
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Write(5, 'Declaramos que o tanque foi liberado após check list portando os lacres abaixo:');
    $pdf->Ln(8);
    $pdf->Cell(95, 5, 'Boca de Vista Sup. 1: ' . ($lacres_info['bvs1'] ?? '___'), 0, 0);
    $pdf->Cell(95, 5, 'Válvula Descarga: ' . ($lacres_info['val_descarga'] ?? '___'), 0, 1);
    $pdf->Cell(95, 5, 'Boca de Vista Sup. 2: ' . ($lacres_info['bvs2'] ?? '___'), 0, 0);
    $pdf->Cell(95, 5, 'Válvula Dreno: ' . ($lacres_info['val_dreno'] ?? '___'), 0, 1);
    $pdf->Cell(95, 5, 'Boca de Vista Sup. 3: ' . ($lacres_info['bvs3'] ?? '___'), 0, 0);
    $pdf->Cell(95, 5, 'Suporte Mangote: ' . ($lacres_info['sup_mangote'] ?? '___'), 0, 1);
    $pdf->Ln(8);
    // --- FIM DO BLOCO RESTAURADO ---

    // ==========================================================
    // DESTAQUE DA OBSERVAÇÃO (VERMELHO E NEGRITO)
    // ==========================================================
    $pdf->SetTextColor(255, 0, 0); // Define a cor do texto para VERMELHO
    $pdf->SetFont('helvetica', 'B', 8); // Mantém a fonte em NEGRITO
    $pdf->Cell(15, 6, 'OBS:', 0, 0);
    $pdf->MultiCell(0, 6, $servico['observacoes'], 0, 'L');
    $pdf->SetTextColor(0, 0, 0); // Retorna a cor do texto para PRETO imediatamente
    // ==========================================================
    $pdf->Ln(20);

    $caminho_assinatura = '../assets/img/assinatura.png';
    if (file_exists($caminho_assinatura)) {
        $pdf->Image($caminho_assinatura, 35, $pdf->GetY() - 10, 60, 15, 'PNG');
    }

    $pdf->Cell(95, 10, '_________________________', 0, 0, 'C');
    $pdf->Cell(95, 10, '_________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(95, 5, 'TROVÃO BRASIL', 0, 0, 'C');
    $pdf->Cell(95, 5, $servico['nome_razao_social'], 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->Cell(0, 9, '_________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 5, 'Assinatura do Responsável', 0, 1, 'C');
    // --- FIM DO NOVO BLOCO ---
}
// =============================================================================
// LÓGICA PARA O LAUDO AAK
// =============================================================================
else if ($tipo_laudo === 'aak') {
    $pdf->AddPage();
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Write(5, 'Certifico para os devidos fins que este veículo da transportadora: ');
    $pdf->SetFont('helvetica', 'B', 10); $pdf->Write(5, $servico['nome_razao_social']);
    $pdf->SetFont('helvetica', '', 10); $pdf->Write(5, ' foi higienizado de acordo com os requisitos da empresa AAK do Brasil, tais como:');
    $pdf->Ln(10);
    
    $texto_padrao_aak_1 = "* Utilizacao de agua potavel e clorada;\n* A mangueira usada na limpeza esta em bom estado de conservacao, sem remendos, sem rasgos ou vazamentos e completamente limpa;\n* Sem a devida aprovacao da AAK do Brasil, nenhum produto quimico dever ser usado na limpeza do tanque. No caso do uso de detergentes, os mesmos devem ser aprovados e apropriados para esse uso. Apos utilizacao os mesmos devem ser enxaguados e deve haver a medicao do pH da agua de enxague;\n* Quando necessario o uso de detergente para limpeza, devera ser informado o Nome do Produto usado, Registro da Anvisa e pH da agua de enxague.";
    $pdf->MultiCell(0, 5, $texto_padrao_aak_1, 0, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Write(5, 'Realizar o procedimento de limpeza recomendado pela AAK do Brasil, conforme abaixo:');
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', '', 10);
    $texto_padrao_aak_2 = "* Enxaguar o tanque do caminhao com agua quente (aproximadamente 90 C) atraves do bocal de limpeza na parte superior do tanque;\n* A agua quente e circulada no tanque ate que a mesma saia com coloracao limpida (aproximadamente de 10 a 15 minutos);\n* Os tubos sao removidos e devem ser lavados com agua quente ate que a mesma saia com coloracao limpida;\n* A secagem deve ser feita por completo, com pano que nao solte fiapo e em local adequado e coberto;\n* Os caminhoes devem ser bem fechados em todas as entradas e saidas. E proibido viajar com o caminhao com as fechadas/saidas abertas;\n* Os tubos para colocacao da mangueira devem ser fechados (com tampa nas duas extremidades) e possuir local para lacres;\n* O tanque e examinado visualmente atraves do bocal localizado na parte superior do caminhao;\n* O tanque e os tubos para colocacao da mangueira devem ser lacrados;\n* Nunca deixar qualquer residuo da carga anterior;\n* Todos os envolvidos na lavagem devem atender aos requisitos minimos de higiene, como: uniforme limpo, botas de borracha limpa e seca, nao usar adornos, nao possuir barba, nao ter ferimentos ou doencas e ter treinamento adequado para operacao.";
    $pdf->MultiCell(0, 5, $texto_padrao_aak_2, 0, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(35, 6, 'Placa Carreta/Truck:', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(67, 6, $servico['placa_carreta'], 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(25, 6, 'Placa Cavalo:', 0, 0); $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, $servico['placa_cavalo'], 0, 1);
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'B', 10); $pdf->Cell(0, 5, 'Última carga carregada: ' . $servico['carga_ultima'], 0, 1);
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    $data_registo_obj = new DateTime($servico['data_registo']);
    $numeros_lacres = implode(', ', array_filter($lacres_info));
    $pdf->Write(5, 'Na empresa TROVAO BRASIL COMERCIO E SERVICOS AUTOMOTIVOS LTDA, na data de ' . $data_registo_obj->format('d/m/Y') . ' conforme a ordem de serviço: ' . str_pad($servico['id'], 7, '0', STR_PAD_LEFT) . ' o caminhão deixou as dependências da lavadora portando os lacres: ' . $numeros_lacres);
    $pdf->Ln(20);

    $caminho_assinatura = '../assets/img/assinatura.png';
    if (file_exists($caminho_assinatura)) {
        $pdf->Image($caminho_assinatura, 80, $pdf->GetY() - 5, 60, 15, 'PNG');
    }

    $pdf->Cell(0, 10, '_________________________', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 10); $pdf->Cell(0, 5, $servico['nome_profissional'], 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10); $pdf->Cell(0, 5, 'Assinatura do Responsavel', 0, 1, 'C');
}

// Desenha um campo de assinatura visível (opcional)
$pdf->setSignatureAppearance(15, 250, 60, 20);

// Saída do PDF
$pdf->Output('Laudo_' . ucfirst($tipo_laudo) . '_' . $service_id . '.pdf', 'I');
?>