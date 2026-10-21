<?php
// Ficheiro: pages/professional_productivity.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado. Apenas administradores podem aceder a esta página.");
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: professionals_list.php');
    exit();
}

// Filtros de data
$data_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_STRING) ?: date('Y-m-01');
$data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_STRING) ?: date('Y-m-t');

try {
    // Busca dados do profissional
    $stmt_prof = $pdo->prepare("SELECT nome, cargo, ativo FROM profissionais WHERE id = :id");
    $stmt_prof->execute([':id' => $id]);
    $profissional = $stmt_prof->fetch();

    if (!$profissional) {
        header('Location: professionals_list.php?error=notfound');
        exit();
    }

    // --- KPIs ---
    $sql_kpi = "SELECT 
                    COUNT(id) as total_servicos,
                    SUM(CASE WHEN status = 'Ativo' THEN valor_final ELSE 0 END) as total_faturado,
                    SUM(CASE WHEN status = 'Cancelado' THEN 1 ELSE 0 END) as total_cancelados,
                    SUM(CASE WHEN reprovado = 1 THEN 1 ELSE 0 END) as total_reprovados
                FROM ordens_servico 
                WHERE (profissional_id = :id1 OR profissional_id_2 = :id2) 
                  AND data_lavagem BETWEEN :data_inicio AND :data_fim";
                  
    $stmt_kpi = $pdo->prepare($sql_kpi);
    $stmt_kpi->execute([
        ':id1' => $id,
        ':id2' => $id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $kpis = $stmt_kpi->fetch();

    // --- Histórico de Serviços ---
    $sql_servicos = "SELECT os.id, os.status, os.reprovado, c.nome_razao_social, os.placa_carreta, os.valor_final, os.data_lavagem,
                     CASE WHEN os.profissional_id = :id_case THEN 'Principal' ELSE 'Secundário' END as tipo_atuacao
                     FROM ordens_servico os
                     JOIN clientes c ON os.cliente_id = c.id
                     WHERE (os.profissional_id = :id1 OR os.profissional_id_2 = :id2)
                       AND os.data_lavagem BETWEEN :data_inicio AND :data_fim
                     ORDER BY os.data_lavagem DESC, os.id DESC";
                     
    $stmt_servicos = $pdo->prepare($sql_servicos);
    $stmt_servicos->execute([
        ':id_case' => $id,
        ':id1' => $id,
        ':id2' => $id,
        ':data_inicio' => $data_inicio,
        ':data_fim' => $data_fim
    ]);
    $servicos = $stmt_servicos->fetchAll();

} catch (PDOException $e) {
    die("Erro ao buscar dados de produtividade: " . $e->getMessage());
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Cabeçalho -->
<div class="d-flex justify-content-between align-items-center mt-4">
    <div>
        <h1 class="page-title-main">Produtividade: <?php echo htmlspecialchars($profissional['nome']); ?></h1>
        <p class="text-muted">
            Cargo: <?php echo htmlspecialchars($profissional['cargo']); ?> | 
            Status: <?php echo $profissional['ativo'] ? '<span class="text-success">Ativo</span>' : '<span class="text-danger">Desligado</span>'; ?>
        </p>
    </div>
    <div>
        <a href="professionals_list.php" class="btn btn-secondary btn-lg"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
    </div>
</div>

<!-- Filtro -->
<div class="card-kpi my-4">
    <div class="card-body">
        <form method="GET" action="professional_productivity.php" class="row g-3 align-items-end">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Início (Lavagem)</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?php echo htmlspecialchars($data_inicio); ?>">
            </div>
            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Fim (Lavagem)</label>
                <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?php echo htmlspecialchars($data_fim); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-warning w-100"><i class="bi bi-filter"></i> Filtrar Período</button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card-kpi text-center p-3 h-100">
            <small class="text-muted">Serviços Realizados</small>
            <h3 class="text-white fw-bold mt-1"><?php echo $kpis['total_servicos'] ?? 0; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-kpi text-center p-3 h-100">
            <small class="text-muted">Valor Total Movimentado</small>
            <h3 class="text-success fw-bold mt-1">R$ <?php echo number_format($kpis['total_faturado'] ?? 0, 2, ',', '.'); ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-kpi text-center p-3 h-100">
            <small class="text-muted">Laudos Reprovados</small>
            <h3 class="text-warning fw-bold mt-1"><?php echo $kpis['total_reprovados'] ?? 0; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-kpi text-center p-3 h-100">
            <small class="text-muted">Pedidos Cancelados</small>
            <h3 class="text-danger fw-bold mt-1"><?php echo $kpis['total_cancelados'] ?? 0; ?></h3>
        </div>
    </div>
</div>

<!-- Tabela de Histórico -->
<div class="card-kpi mt-4">
    <div class="card-header"><i class="bi bi-list-task me-1"></i>Histórico de Serviços no Período</div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover table-services">
                <thead>
                    <tr>
                        <th scope="col">Data</th>
                        <th scope="col">Nº Pedido</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Placa</th>
                        <th scope="col">Atuação</th>
                        <th scope="col">Valor Final</th>
                        <th scope="col">Status</th>
                        <th scope="col">Reprova</th>
                        <th scope="col">Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servicos)): ?>
                        <tr><td colspan="9" class="text-center">Nenhum serviço registrado neste período.</td></tr>
                    <?php else: ?>
                        <?php foreach ($servicos as $servico): ?>
                            <?php 
                                $row_class = '';
                                if ($servico['status'] === 'Cancelado') $row_class = 'table-danger-custom';
                                elseif ($servico['reprovado']) $row_class = 'table-warning text-dark';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo date('d/m/Y', strtotime($servico['data_lavagem'])); ?></td>
                                <th scope="row"><?php echo htmlspecialchars($servico['id']); ?></th>
                                <td><?php echo htmlspecialchars($servico['nome_razao_social']); ?></td>
                                <td><?php echo htmlspecialchars($servico['placa_carreta']); ?></td>
                                <td>
                                    <?php if ($servico['tipo_atuacao'] === 'Principal'): ?>
                                        <span class="badge bg-primary">Principal</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Apoio</span>
                                    <?php endif; ?>
                                </td>
                                <td>R$ <?php echo number_format($servico['valor_final'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $servico['status'] == 'Ativo' ? 'success' : 'danger'; ?>">
                                        <?php echo htmlspecialchars($servico['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($servico['reprovado']): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Sim</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="service_details.php?id=<?php echo $servico['id']; ?>" class="btn btn-sm btn-outline-warning" title="Ver Detalhes">
                                        <i class="bi bi-search"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
