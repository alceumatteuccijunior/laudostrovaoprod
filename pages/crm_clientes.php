<?php
// Ficheiro: pages/crm_clientes.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado. Apenas administradores podem aceder a esta página.");
}

// Ler limite da configuração, padrão 20%
$limite_alerta = 20;
try {
    // A tabela configuracoes e crm_alertas_log devem existir (criadas no script de setup)
    $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'limite_alerta_queda_crm'");
    if ($row = $stmt->fetch()) {
        $limite_alerta = (float) $row['valor'];
    }
} catch (PDOException $e) {
    // Tabela ainda não criada
}

// Salvar nova configuração se via POST
$mensagem_sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_limite'])) {
    $novo = (float) $_POST['novo_limite'];
    if ($novo > 0) {
        try {
            $pdo->prepare("REPLACE INTO configuracoes (chave, valor, descricao) VALUES ('limite_alerta_queda_crm', :novo, 'Porcentagem limite de queda para alerta')")->execute([':novo' => $novo]);
            $limite_alerta = $novo;
            $mensagem_sucesso = "Limite de alerta atualizado com sucesso!";
        } catch (PDOException $e) {}
    }
}

// Consulta principal: Últimos 30 dias x 30 dias anteriores (de 60 a 30 atrás)
$clientes_processados = [];
$total_alerta = 0;
$total_ativos = 0;

$data_hoje = date('d/m/Y');
$data_30 = date('d/m/Y', strtotime('-30 days'));
$data_60 = date('d/m/Y', strtotime('-60 days'));

try {
    $sql_crm = "
        SELECT 
            c.id, c.nome_razao_social, c.telefone, c.email,
            COUNT(CASE WHEN os.data_lavagem >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN os.id END) as qtd_atual,
            COUNT(CASE WHEN os.data_lavagem >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND os.data_lavagem < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN os.id END) as qtd_anterior
        FROM clientes c
        LEFT JOIN ordens_servico os ON os.cliente_id = c.id AND os.status != 'Cancelado'
        GROUP BY c.id
    ";
    $stmt = $pdo->query($sql_crm);
    $clientes = $stmt->fetchAll();

    foreach ($clientes as $c) {
        $atual = (int) $c['qtd_atual'];
        $anterior = (int) $c['qtd_anterior'];
        
        // Ignorar clientes inativos nos dois períodos recentes
        if ($atual == 0 && $anterior == 0) continue;
        $total_ativos++;
        
        if ($anterior == 0) {
            $variacao = 100; // Cliente novo ou retornando
        } else {
            $variacao = (($atual - $anterior) / $anterior) * 100;
        }
        
        $status = '';
        $cor = '';
        $icone = '';
        $badge_cor = '';
        
        if ($variacao >= 0) {
            $status = 'Crescimento/Estável';
            $cor = 'success';
            $badge_cor = 'bg-success';
            $icone = 'bi-arrow-up-right-circle-fill';
        } elseif (abs($variacao) >= $limite_alerta) {
            $status = 'Alerta de Risco (Churn)';
            $cor = 'danger';
            $badge_cor = 'bg-danger';
            $icone = 'bi-exclamation-octagon-fill';
            $total_alerta++;
        } else {
            $status = 'Atenção';
            $cor = 'warning';
            $badge_cor = 'bg-warning text-dark';
            $icone = 'bi-exclamation-triangle-fill';
        }
        
        $c['variacao_formatada'] = number_format($variacao, 1, ',', '.') . '%';
        // Prefixar com + se for positivo
        if ($variacao > 0) $c['variacao_formatada'] = '+' . $c['variacao_formatada'];
        
        $c['status_texto'] = $status;
        $c['cor'] = $cor;
        $c['badge_cor'] = $badge_cor;
        $c['icone'] = $icone;
        $c['variacao_numero'] = $variacao;
        
        $clientes_processados[] = $c;
    }

    // Ordenar: Piores variações primeiro
    usort($clientes_processados, function($a, $b) {
        return $a['variacao_numero'] <=> $b['variacao_numero'];
    });

} catch (PDOException $e) {
    die("Erro ao processar CRM: " . $e->getMessage() . ". Você já rodou o setup_crm_db.php no navegador?");
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Modal de Configuração -->
<div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content text-dark">
      <div class="modal-header">
        <h5 class="modal-title" id="configModalLabel"><i class="bi bi-gear-fill me-2"></i>Configurações de Alerta CRM</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
            <p>Os clientes que tiverem uma queda maior ou igual a esta porcentagem na comparação dos últimos 30 dias serão marcados com <strong>Alerta Vermelho</strong> e incluídos nos e-mails automáticos.</p>
            <div class="mb-3">
                <label class="form-label">Limite de Alerta de Queda (%)</label>
                <div class="input-group">
                    <input type="number" step="0.1" name="novo_limite" class="form-control" value="<?php echo htmlspecialchars($limite_alerta); ?>" required min="1">
                    <span class="input-group-text">%</span>
                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          </div>
      </form>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-4">
    <div>
        <h1 class="page-title-main"><i class="bi bi-radar me-2"></i>Monitoramento CRM</h1>
        <p class="text-muted">Acompanhe a retenção dos clientes e atue antes de perdê-los.</p>
    </div>
    <div>
        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#configModal">
            <i class="bi bi-sliders"></i> Ajustar Sensibilidade
        </button>
    </div>
</div>

<?php if ($mensagem_sucesso): ?>
    <div class="alert alert-success mt-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo $mensagem_sucesso; ?></div>
<?php endif; ?>

<!-- KPIs do CRM -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card-kpi text-center p-4 h-100" style="border-left: 5px solid #0d6efd; background: linear-gradient(145deg, #1e1e1e 0%, #2a2a2a 100%);">
            <small class="text-light text-uppercase fw-bold"><i class="bi bi-briefcase-fill me-2"></i>Clientes Ativos (60 dias)</small>
            <h2 class="text-white fw-bold mt-3 mb-0" style="font-size: 3rem;"><?php echo $total_ativos; ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-kpi text-center p-4 h-100" style="border-left: 5px solid #dc3545; background: linear-gradient(145deg, #2c1a1d 0%, #1a1012 100%);">
            <small class="text-danger text-uppercase fw-bold"><i class="bi bi-shield-fill-exclamation me-2"></i>Em Risco (Queda ≥ <?php echo $limite_alerta; ?>%)</small>
            <h2 class="text-danger fw-bold mt-3 mb-0" style="font-size: 3rem;"><?php echo $total_alerta; ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-kpi p-4 h-100 d-flex flex-column justify-content-center" style="background: linear-gradient(145deg, #1e1e1e 0%, #252525 100%);">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-arrow-up-right-circle-fill text-success fs-4 me-2"></i>
                <small class="text-white">Crescimento (Aumentaram)</small>
            </div>
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-2"></i>
                <small class="text-white">Atenção (Queda Leve)</small>
            </div>
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-octagon-fill text-danger fs-4 me-2"></i>
                <small class="text-white">Risco (Queda Severa)</small>
            </div>
        </div>
    </div>
</div>

<!-- Radar Table -->
<div class="card-kpi mt-4 shadow-lg border-0">
    <div class="card-header bg-dark d-flex justify-content-between align-items-center py-3 border-bottom border-secondary">
        <span class="fs-5 fw-bold text-white"><i class="bi bi-people-fill me-2 text-primary"></i>Radar de Desempenho Analítico</span>
        
        <div class="btn-group" role="group" aria-label="Filtros de CRM">
            <button type="button" class="btn btn-outline-light btn-sm active filter-btn fw-bold px-3" data-filter="all">Todos</button>
            <button type="button" class="btn btn-outline-success btn-sm filter-btn fw-bold px-3" data-filter="crescimento">Crescimento</button>
            <button type="button" class="btn btn-outline-warning btn-sm filter-btn fw-bold px-3" data-filter="atencao">Atenção</button>
            <button type="button" class="btn btn-outline-danger btn-sm filter-btn fw-bold px-3" data-filter="risco">Risco (Alerta)</button>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">Cliente</th>
                        <th scope="col" class="text-center">30 Dias Anteriores</th>
                        <th scope="col" class="text-center">Últimos 30 Dias</th>
                        <th scope="col" class="text-center">Variação</th>
                        <th scope="col">Status do Relacionamento</th>
                        <th scope="col">Ação Comercial</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes_processados)): ?>
                        <tr><td colspan="6" class="text-center">Nenhum dado de cliente para o período.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clientes_processados as $c): ?>
                            <?php 
                                $filter_status = 'crescimento';
                                if ($c['cor'] === 'warning') $filter_status = 'atencao';
                                if ($c['cor'] === 'danger') $filter_status = 'risco';
                            ?>
                            <tr class="crm-row <?php echo ($c['cor'] === 'danger') ? 'table-danger-custom text-white' : ''; ?>" data-status="<?php echo $filter_status; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($c['nome_razao_social']); ?></strong><br>
                                    <small class="text-muted"><i class="bi bi-telephone-fill me-1"></i><?php echo htmlspecialchars($c['telefone']); ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="fs-5 text-muted"><?php echo $c['qtd_anterior']; ?> lav.</span><br>
                                    <small class="text-muted" style="font-size: 11px;">(de <?php echo $data_60; ?> a <?php echo $data_30; ?>)</small>
                                </td>
                                <td class="text-center">
                                    <span class="fs-5 fw-bold"><?php echo $c['qtd_atual']; ?> lav.</span><br>
                                    <small class="text-muted" style="font-size: 11px;">(de <?php echo $data_30; ?> até Hoje)</small>
                                </td>
                                <td class="text-center">
                                    <span class="fs-4 fw-bold text-<?php echo $c['cor']; ?>">
                                        <?php echo $c['variacao_formatada']; ?>
                                    </span><br>
                                    <small class="text-muted" style="font-size: 11px;">vs período anterior</small>
                                </td>
                                <td>
                                    <span class="badge <?php echo $c['badge_cor']; ?> p-2 px-3">
                                        <i class="bi <?php echo $c['icone']; ?> me-1"></i> <?php echo $c['status_texto']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($c['cor'] === 'danger'): ?>
                                        <a href="services_list.php?filtro_cliente=<?php echo urlencode($c['nome_razao_social']); ?>" class="btn btn-sm btn-danger fw-bold" target="_blank">
                                            <i class="bi bi-telephone-outbound me-1"></i> Contatar Cliente
                                        </a>
                                    <?php else: ?>
                                        <a href="services_list.php?filtro_cliente=<?php echo urlencode($c['nome_razao_social']); ?>" class="btn btn-sm btn-outline-light" title="Ver Histórico">
                                            <i class="bi bi-clock-history"></i> Histórico
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const rows = document.querySelectorAll('.crm-row');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active style from all buttons
            filterBtns.forEach(b => {
                b.classList.remove('active');
            });
            // Add active style to clicked button
            this.classList.add('active');

            const filter = this.getAttribute('data-filter');

            // Filter rows
            rows.forEach(row => {
                if (filter === 'all' || row.getAttribute('data-status') === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
