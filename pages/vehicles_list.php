<?php
// Ficheiro: pages/vehicles_list.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado. Apenas administradores podem aceder a esta página.");
}

// Filtro de Busca (Placa ou Cliente)
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?: '';

try {
    $sql = "SELECT v.*, c.nome_razao_social as nome_cliente,
            (SELECT COUNT(*) FROM ordens_servico WHERE placa_carreta = v.placa OR placa_cavalo = v.placa) as total_lavagens
            FROM veiculos v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            WHERE 1=1";
            
    $params = [];
    if (!empty($search)) {
        $sql .= " AND (v.placa LIKE ? OR c.nome_razao_social LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    
    $sql .= " ORDER BY total_lavagens DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $veiculos = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erro ao carregar veículos: " . $e->getMessage() . ". Você já rodou o setup_veiculos_db.php no navegador?");
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4">
    <div>
        <h1 class="page-title-main"><i class="bi bi-truck me-2"></i>Frota e Veículos (Teia de Aranha)</h1>
        <p class="text-muted">Acompanhamento e mapeamento de todas as placas já lavadas no sistema.</p>
    </div>
</div>

<div class="card-kpi mb-4">
    <div class="card-body p-3">
        <form method="GET" action="vehicles_list.php" class="row g-2 align-items-center">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Buscar por placa ou empresa dona..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Buscar</button>
            </div>
        </form>
    </div>
</div>

<div class="card-kpi mt-2">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover table-services align-middle mb-0">
                <thead class="table-dark-header">
                    <tr>
                        <th scope="col" class="ps-4">Placa</th>
                        <th scope="col">Tipo Sugerido</th>
                        <th scope="col">Empresa Proprietária (Opcional)</th>
                        <th scope="col" class="text-center">Lavagens Totais</th>
                        <th scope="col">Última Entrada</th>
                        <th scope="col" class="text-center pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($veiculos)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                                Nenhum veículo encontrado no sistema.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($veiculos as $v): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-secondary border border-light text-uppercase fs-6 p-2 rounded-1" style="font-family: monospace; letter-spacing: 2px;">
                                        <?php echo htmlspecialchars($v['placa']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($v['tipo'] ?? 'Desconhecido'); ?></td>
                                <td>
                                    <?php if ($v['nome_cliente']): ?>
                                        <strong><?php echo htmlspecialchars($v['nome_cliente']); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="bi bi-dash"></i> Sem vínculo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold fs-5 text-primary">
                                    <?php echo $v['total_lavagens']; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($v['data_registro'])); ?>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="vehicle_dashboard.php?placa=<?php echo urlencode($v['placa']); ?>" class="btn btn-warning btn-sm fw-bold">
                                        <i class="bi bi-diagram-3-fill me-1"></i> Dashboard
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
