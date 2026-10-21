<?php
// Ficheiro: api/bi_data.php
// Descrição: API para fornecer dados para o Super BI Drag & Drop

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado. Apenas administradores.']);
    exit();
}

header('Content-Type: application/json');

$acao = $_GET['acao'] ?? '';

try {
    switch ($acao) {
        case 'kpi_lavagens_mes':
            // Total de lavagens no mês atual vs mês passado
            $sql = "
                SELECT 
                    COUNT(CASE WHEN MONTH(data_lavagem) = MONTH(CURDATE()) AND YEAR(data_lavagem) = YEAR(CURDATE()) THEN 1 END) as atual,
                    COUNT(CASE WHEN MONTH(data_lavagem) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(data_lavagem) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN 1 END) as anterior
                FROM ordens_servico
                WHERE status != 'Cancelado'
            ";
            $stmt = $pdo->query($sql);
            $dados = $stmt->fetch();
            
            $atual = (int)$dados['atual'];
            $anterior = (int)$dados['anterior'];
            $crescimento = $anterior > 0 ? (($atual - $anterior) / $anterior) * 100 : 100;
            
            echo json_encode([
                'valor' => $atual,
                'subtitulo' => 'Lavagens este mês',
                'variacao' => round($crescimento, 1),
                'variacao_texto' => ($crescimento >= 0 ? '+' : '') . round($crescimento, 1) . '% vs mês passado'
            ]);
            break;

        case 'grafico_evolucao_lavagens':
            // Evolução das lavagens nos últimos 6 meses
            $sql = "
                SELECT 
                    DATE_FORMAT(data_lavagem, '%m/%Y') as mes_ano,
                    COUNT(id) as total
                FROM ordens_servico
                WHERE status != 'Cancelado' AND data_lavagem >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(data_lavagem, '%Y-%m')
                ORDER BY DATE_FORMAT(data_lavagem, '%Y-%m') ASC
            ";
            $stmt = $pdo->query($sql);
            $resultados = $stmt->fetchAll();
            
            $labels = [];
            $dados = [];
            foreach ($resultados as $row) {
                $labels[] = $row['mes_ano'];
                $dados[] = (int)$row['total'];
            }
            
            echo json_encode([
                'labels' => $labels,
                'dados' => $dados,
                'titulo' => 'Evolução de Lavagens (6 meses)'
            ]);
            break;

        case 'grafico_top_clientes':
            // Top 5 Clientes do mês
            $sql = "
                SELECT 
                    c.nome_razao_social, 
                    COUNT(os.id) as total
                FROM ordens_servico os
                JOIN clientes c ON os.cliente_id = c.id
                WHERE os.status != 'Cancelado' AND MONTH(os.data_lavagem) = MONTH(CURDATE()) AND YEAR(os.data_lavagem) = YEAR(CURDATE())
                GROUP BY c.id
                ORDER BY total DESC
                LIMIT 5
            ";
            $stmt = $pdo->query($sql);
            $resultados = $stmt->fetchAll();
            
            $labels = [];
            $dados = [];
            foreach ($resultados as $row) {
                // Pega só o primeiro nome ou nome fantasia se for muito longo
                $nome_curto = substr($row['nome_razao_social'], 0, 15);
                $labels[] = $nome_curto;
                $dados[] = (int)$row['total'];
            }
            
            echo json_encode([
                'labels' => $labels,
                'dados' => $dados,
                'titulo' => 'Top 5 Clientes (Mês)'
            ]);
            break;
            
        case 'kpi_clientes_ativos':
            $sql = "SELECT COUNT(DISTINCT cliente_id) as ativos FROM ordens_servico WHERE data_lavagem >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $stmt = $pdo->query($sql);
            $dados = $stmt->fetch();
            echo json_encode([
                'valor' => $dados['ativos'],
                'subtitulo' => 'Clientes Ativos (30 dias)',
                'variacao' => 0,
                'variacao_texto' => ''
            ]);
            break;

        default:
            echo json_encode(['erro' => 'Ação não especificada ou inválida.']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao processar dados de BI.']);
}
