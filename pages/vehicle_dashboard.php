<?php
// Ficheiro: pages/vehicle_dashboard.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

$placa = isset($_GET['placa']) ? trim($_GET['placa']) : '';

if (empty($placa)) {
    header("Location: vehicles_list.php");
    exit();
}

// Processar atualização de dono ou observações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_vehicle') {
    $novo_cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT) ?: null;
    $novas_obs = trim($_POST['observacoes'] ?? '');
    
    try {
        $stmt_upd = $pdo->prepare("UPDATE veiculos SET cliente_id = :cid, observacoes = :obs WHERE placa = :placa");
        $stmt_upd->execute([':cid' => $novo_cliente_id, ':obs' => $novas_obs, ':placa' => $placa]);
        header("Location: vehicle_dashboard.php?placa=" . urlencode($placa) . "&success=1");
        exit();
    } catch (PDOException $e) {
        $erro = "Erro ao atualizar: " . $e->getMessage();
    }
}

// 1. Buscar Dados Básicos do Veículo
try {
    $stmt_v = $pdo->prepare("SELECT * FROM veiculos WHERE placa = :placa");
    $stmt_v->execute([':placa' => $placa]);
    $veiculo = $stmt_v->fetch();

    if (!$veiculo) {
        // Se não existir (ex: placa velha que o setup não rodou), cria temporário na memória
        $veiculo = ['placa' => $placa, 'tipo' => 'Desconhecido', 'cliente_id' => null, 'observacoes' => ''];
    }
} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

// 2. Buscar Lista de Todos os Clientes (Para o Select de Dono)
$clientes_lista = [];
try {
    $stmt_cl = $pdo->query("SELECT id, nome_razao_social FROM clientes ORDER BY nome_razao_social");
    $clientes_lista = $stmt_cl->fetchAll();
} catch (PDOException $e) {}

// 3. Buscar Histórico de Serviços dessa Placa
$servicos = [];
$total_gasto = 0;
$motoristas_unicos = [];
$clientes_avulsos_unicos = [];

try {
    $sql_hist = "
        SELECT os.*, c.nome_razao_social, p.nome as profissional_nome
        FROM ordens_servico os
        LEFT JOIN clientes c ON os.cliente_id = c.id
        LEFT JOIN profissionais p ON os.profissional_id = p.id
        WHERE TRIM(UPPER(os.placa_carreta)) = :placa1 OR TRIM(UPPER(os.placa_cavalo)) = :placa2
        ORDER BY os.data_lavagem DESC
    ";
    $stmt_hist = $pdo->prepare($sql_hist);
    $placa_upper = strtoupper($placa);
    $stmt_hist->execute([':placa1' => $placa_upper, ':placa2' => $placa_upper]);
    $servicos = $stmt_hist->fetchAll();

    foreach ($servicos as $s) {
        if ($s['status'] !== 'Cancelado') {
            $total_gasto += (float)$s['valor_final'];
            
            // Coletar Motoristas
            $mot = trim($s['motorista_nome']);
            if (!empty($mot)) {
                $motoristas_unicos[$mot] = ($motoristas_unicos[$mot] ?? 0) + 1;
            }

            // Coletar Clientes Avulsos (quem pagou a lavagem)
            $cli = trim($s['nome_razao_social']);
            if (!empty($cli)) {
                $clientes_avulsos_unicos[$cli] = ($clientes_avulsos_unicos[$cli] ?? 0) + 1;
            }
        }
    }
    
} catch (PDOException $e) {}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4">
    <div>
        <a href="vehicles_list.php" class="btn btn-outline-light btn-sm mb-2"><i class="bi bi-arrow-left"></i> Voltar à Frota</a>
        <h1 class="page-title-main"><i class="bi bi-diagram-3-fill me-2 text-warning"></i>Teia de Aranha do Veículo: <span class="badge bg-secondary border border-light ms-2" style="font-family: monospace; letter-spacing: 2px; font-size: 1.5rem;"><?php echo htmlspecialchars($placa); ?></span></h1>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>Dados do veículo atualizados com sucesso!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Coluna Esquerda: KPIs e Configurações -->
    <div class="col-lg-4 mb-4">
        
        <!-- KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="card-kpi p-3 h-100 text-center" style="background: linear-gradient(145deg, #1e1e1e 0%, #2a2a2a 100%); border-bottom: 4px solid #0d6efd;">
                    <small class="text-muted d-block mb-2">Total de Lavagens</small>
                    <h3 class="text-white mb-0 fw-bold"><?php echo count($servicos); ?></h3>
                </div>
            </div>
            <div class="col-6">
                <div class="card-kpi p-3 h-100 text-center" style="background: linear-gradient(145deg, #1e1e1e 0%, #2a2a2a 100%); border-bottom: 4px solid #198754;">
                    <small class="text-muted d-block mb-2">Total Gasto (R$)</small>
                    <h3 class="text-success mb-0 fw-bold"><?php echo number_format($total_gasto, 2, ',', '.'); ?></h3>
                </div>
            </div>
        </div>

        <!-- Formulário de Propriedade -->
        <div class="card-kpi mb-4">
            <div class="card-header bg-dark"><i class="bi bi-card-checklist me-2"></i>Ficha do Veículo</div>
            <div class="card-body p-3">
                <form method="POST" action="vehicle_dashboard.php?placa=<?php echo urlencode($placa); ?>">
                    <input type="hidden" name="action" value="update_vehicle">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small">Tipo Sugerido (Pelo Histórico)</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($veiculo['tipo'] ?? 'Desconhecido'); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-white fw-bold">Empresa Proprietária (Dono)</label>
                        <select name="cliente_id" class="form-select bg-dark text-white border-secondary">
                            <option value="">-- Sem vínculo definido --</option>
                            <?php foreach ($clientes_lista as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($veiculo['cliente_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nome_razao_social']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" style="font-size: 11px;">Isto define a quem este caminhão pertence na vida real.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-white">Observações Fixas do Veículo</label>
                        <textarea name="observacoes" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="Ex: Cuidado especial na pintura, número do renavam, etc..."><?php echo htmlspecialchars($veiculo['observacoes']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Salvar Ficha</button>
                </form>
            </div>
        </div>

        <!-- Mapeamento: Motoristas -->
        <div class="card-kpi mb-4">
            <div class="card-header bg-dark"><i class="bi bi-person-badge-fill me-2 text-info"></i>Motoristas Relacionados</div>
            <div class="card-body p-3">
                <?php if(empty($motoristas_unicos)): ?>
                    <p class="text-muted small mb-0">Nenhum motorista registrado no histórico.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush rounded bg-dark">
                        <?php foreach($motoristas_unicos as $nome => $qtd): ?>
                            <li class="list-group-item bg-transparent text-white d-flex justify-content-between align-items-center border-secondary px-2 py-1">
                                <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($nome); ?></span>
                                <span class="badge bg-secondary rounded-pill" title="Quantidade de viagens/serviços"><?php echo $qtd; ?>x</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mapeamento: Empresas Avulsas -->
        <div class="card-kpi">
            <div class="card-header bg-dark"><i class="bi bi-buildings-fill me-2 text-warning"></i>Empresas Pagantes</div>
            <div class="card-body p-3">
                <small class="text-muted d-block mb-2">Quem já pagou lavagem para esta placa (Além do dono):</small>
                <?php if(empty($clientes_avulsos_unicos)): ?>
                    <p class="text-muted small mb-0">Nenhuma empresa registrada.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush rounded bg-dark">
                        <?php foreach($clientes_avulsos_unicos as $nome => $qtd): ?>
                            <li class="list-group-item bg-transparent text-white d-flex justify-content-between align-items-center border-secondary px-2 py-1">
                                <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($nome); ?></span>
                                <span class="badge bg-secondary rounded-pill"><?php echo $qtd; ?>x</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Coluna Direita: Timeline de Serviços -->
    <div class="col-lg-8">
        <div class="card-kpi h-100">
            <div class="card-header bg-dark border-bottom border-secondary py-3">
                <i class="bi bi-clock-history me-2 text-success"></i>Histórico Cronológico de Lavagens
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 800px; overflow-y: auto;">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead class="table-dark-header position-sticky top-0 shadow-sm">
                            <tr>
                                <th class="ps-3">Data</th>
                                <th>Empresa Pagante</th>
                                <th>Motorista</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th class="pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($servicos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        Nenhum serviço registrado para esta placa.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($servicos as $s): ?>
                                    <tr>
                                        <td class="ps-3 text-nowrap">
                                            <strong><?php echo date('d/m/Y', strtotime($s['data_lavagem'])); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($s['nome_razao_social'] ?? 'Avulso'); ?></td>
                                        <td><?php echo htmlspecialchars($s['motorista_nome']); ?></td>
                                        <td class="text-success fw-bold">R$ <?php echo number_format($s['valor_final'], 2, ',', '.'); ?></td>
                                        <td>
                                            <?php if ($s['status'] == 'Cancelado'): ?>
                                                <span class="badge bg-danger">Cancelado</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Concluído</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="service_details.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-light btn-sm" target="_blank" title="Ver Laudo Completo">
                                                <i class="bi bi-box-arrow-up-right"></i>
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
    </div>
</div>

<?php include '../templates/footer.php'; ?>
