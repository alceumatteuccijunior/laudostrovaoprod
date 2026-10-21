<?php
// Ficheiro: pages/service_details.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    header('Location: ../index.php?error=notloggedin');
    exit();
}

$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$service_id) {
    header('Location: services_list.php');
    exit();
}

try {
    // Busca os dados do serviço
    $sql = "SELECT os.*, c.nome_razao_social, p1.nome AS nome_profissional, p2.nome AS nome_profissional_2
            FROM ordens_servico AS os
            JOIN clientes AS c ON os.cliente_id = c.id
            JOIN profissionais AS p1 ON os.profissional_id = p1.id
            LEFT JOIN profissionais AS p2 ON os.profissional_id_2 = p2.id
            WHERE os.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $service_id);
    $stmt->execute();
    $servico = $stmt->fetch();

    if (!$servico) {
        header('Location: services_list.php?error=notfound');
        exit();
    }
    
    // Busca os itens atuais do serviço
    $sql_itens = "SELECT i.servico_id as id, c.nome_servico as nome, i.preco_cobrado as preco 
                  FROM ordens_servico_itens AS i
                  JOIN catalogo_servicos AS c ON i.servico_id = c.id
                  WHERE i.ordem_servico_id = :id";
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([':id' => $service_id]);
    $servicos_prestados = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    $lacres_info = json_decode($servico['lacres_info'], true) ?? [];
    
    $procedimentos_info = json_decode($servico['procedimentos_json'], true);
    if (!is_array($procedimentos_info)) {
        $procedimentos_info = [];
    }
    
    // Busca listas para os dropdowns (Profissionais e Catálogo)
    // Profissionais (para edição de responsáveis)
    $sql_profissionais = "SELECT id, nome FROM profissionais WHERE ativo = 1 ORDER BY nome ASC";
    $profissionais = $pdo->query($sql_profissionais)->fetchAll();

    // Catálogo (apenas se for admin, para editar serviços)
    if ($_SESSION['user_perfil'] === 'admin') {
        $sql_catalogo = "SELECT id, nome_servico, preco FROM catalogo_servicos WHERE ativo = 1 ORDER BY nome_servico ASC";
        $catalogo_servicos = $pdo->query($sql_catalogo)->fetchAll();
    }

} catch (PDOException $e) {
    die("Erro ao buscar detalhes do serviço: " . $e->getMessage());
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mt-4 page-title-main">Pedido de Serviço #<?php echo htmlspecialchars($servico['id']); ?></h1>
        <p class="text-muted">Registado em: <?php echo date('d/m/Y H:i', strtotime($servico['data_registo'])); ?></p>
    </div>
    <div class="d-flex align-items-center">
        <?php if ($servico['status'] === 'Ativo'): ?>
             <button onclick="cancelarPedido(<?php echo $servico['id']; ?>)" class="btn btn-outline-danger btn-lg me-3">
                <i class="bi bi-x-circle"></i> Cancelar Pedido
            </button>
            <?php if (!$servico['reprovado']): ?>
             <button onclick="reprovarPedido(<?php echo $servico['id']; ?>)" class="btn btn-outline-warning btn-lg me-3">
                <i class="bi bi-exclamation-triangle"></i> Reprovar Laudo
            </button>
            <?php endif; ?>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-warning btn-lg dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-file-earmark-pdf-fill me-2"></i>Gerar Documento
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="../includes/generate_laudo.php?id=<?php echo $servico['id']; ?>&tipo=trovao" target="_blank">Laudo Trovão</a></li>
                <li><a class="dropdown-item" href="../includes/generate_laudo.php?id=<?php echo $servico['id']; ?>&tipo=aak" target="_blank">Laudo AAK</a></li>
                <li><a class="dropdown-item disabled" href="#">Laudo Bunge (em breve)</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../includes/generate_recibo.php?id=<?php echo $servico['id']; ?>" target="_blank">Recibo</a></li>
                <?php if ($_SESSION['user_perfil'] == 'admin'): ?>
                    <li><a class="dropdown-item" href="../includes/generate_reciboOld.php?id=<?php echo $servico['id']; ?>" target="_blank">Recibo com Valor Total</a></li>                
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Alerta de Pedido Cancelado -->
<?php if ($servico['status'] === 'Cancelado'): ?>
<div class="alert alert-danger mt-4" role="alert">
  <h4 class="alert-heading">Pedido Cancelado!</h4>
  <p><strong>Motivo:</strong> <?php echo htmlspecialchars($servico['motivo_cancelamento']); ?></p>
</div>
<?php endif; ?>

<!-- Alerta de Pedido Reprovado -->
<?php if ($servico['reprovado']): ?>
<div class="alert alert-warning mt-4" role="alert">
  <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Laudo Reprovado!</h4>
  <p><strong>Motivo:</strong> <?php echo htmlspecialchars($servico['motivo_reprova']); ?></p>
  <?php if ($servico['data_reprova']): ?>
  <p class="mb-0"><small>Data da Reprovação: <?php echo date('d/m/Y H:i', strtotime($servico['data_reprova'])); ?></small></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Feedback de Sucesso/Erro -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success mt-3" role="alert">Dados atualizados com sucesso!</div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger mt-3" role="alert">Erro ao atualizar dados. Verifique as informações e tente novamente.</div>
<?php endif; ?>

<!-- FORMULÁRIO GERAL DE EDIÇÃO -->
<form id="edit-service-form" action="../includes/service_update_details_process.php" method="POST">
    <input type="hidden" name="service_id" value="<?php echo $servico['id']; ?>">

    <div class="row mt-4">
        <!-- Coluna de Informações Principais (Editável) -->
        <div class="col-lg-8">
            <!-- DADOS DO CLIENTE E PROFISSIONAIS -->
            <div class="card-kpi mb-4">
                <div class="card-header"><i class="bi bi-person-lines-fill me-1"></i>Responsáveis e Cliente</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="cliente_search" class="form-label">Cliente</label>
                            <div class="autocomplete-container">
                                <!-- Campo de busca inicializado com o nome do cliente atual -->
                                <input type="text" class="form-control" id="cliente_search" name="cliente_nome_display" value="<?php echo htmlspecialchars($servico['nome_razao_social']); ?>" placeholder="Procure um cliente..." required>
                                <!-- ID do cliente atual -->
                                <input type="hidden" id="cliente_id" name="cliente_id" value="<?php echo htmlspecialchars($servico['cliente_id']); ?>" required>
                                <div class="autocomplete-results" id="autocomplete-results"></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="profissional_id" class="form-label">Profissional Responsável</label>
                            <select class="form-select" id="profissional_id" name="profissional_id" required <?php echo ($_SESSION['user_perfil'] !== 'admin') ? 'disabled' : ''; ?>>
                                <?php foreach ($profissionais as $prof): ?>
                                    <option value="<?php echo $prof['id']; ?>" <?php echo ($prof['id'] == $servico['profissional_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prof['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($_SESSION['user_perfil'] !== 'admin'): ?>
                                <input type="hidden" name="profissional_id" value="<?php echo htmlspecialchars($servico['profissional_id']); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="profissional_id_2" class="form-label">Segundo Responsável</label>
                            <select class="form-select" id="profissional_id_2" name="profissional_id_2" <?php echo ($_SESSION['user_perfil'] !== 'admin') ? 'disabled' : ''; ?>>
                                <option value="">Nenhum</option>
                                <?php foreach ($profissionais as $prof): ?>
                                    <option value="<?php echo $prof['id']; ?>" <?php echo ($prof['id'] == $servico['profissional_id_2']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prof['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($_SESSION['user_perfil'] !== 'admin'): ?>
                                <input type="hidden" name="profissional_id_2" value="<?php echo htmlspecialchars($servico['profissional_id_2']); ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DADOS DA OPERAÇÃO (VEÍCULO E MOTORISTA) -->
            <div class="card-kpi mb-4">
                <div class="card-header"><i class="bi bi-truck me-1"></i>Dados do Veículo e Motorista</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="motorista" class="form-label">Nome do Motorista</label>
                            <input type="text" class="form-control" id="motorista" name="motorista" value="<?php echo htmlspecialchars($servico['motorista_nome']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="doc_motorista" class="form-label">Documento (CNH/CPF)</label>
                            <input type="text" class="form-control" id="doc_motorista" name="doc_motorista" value="<?php echo htmlspecialchars($servico['doc_motorista']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="placa_carreta" class="form-label">Placa Carreta/Tanque</label>
                            <input type="text" class="form-control" id="placa_carreta" name="placa_carreta" value="<?php echo htmlspecialchars($servico['placa_carreta']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="placa_cavalo" class="form-label">Placa Cavalo</label>
                            <input type="text" class="form-control" id="placa_cavalo" name="placa_cavalo" value="<?php echo htmlspecialchars($servico['placa_cavalo']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="hora_chegada" class="form-label">Chegada</label>
                            <input type="time" class="form-control" id="hora_chegada" name="hora_chegada" value="<?php echo htmlspecialchars($servico['hora_chegada']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="hora_saida" class="form-label">Saída</label>
                            <input type="time" class="form-control" id="hora_saida" name="hora_saida" value="<?php echo htmlspecialchars($servico['hora_saida']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="turno" class="form-label">Turno</label>
                            <select class="form-select" id="turno" name="turno">
                                <option value="Manha" <?php echo ($servico['turno'] == 'Manha') ? 'selected' : ''; ?>>Manhã</option>
                                <option value="Tarde" <?php echo ($servico['turno'] == 'Tarde') ? 'selected' : ''; ?>>Tarde</option>
                                <option value="Noite" <?php echo ($servico['turno'] == 'Noite') ? 'selected' : ''; ?>>Noite</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HISTÓRICO DE CARGAS -->
            <div class="card-kpi mb-4">
                <div class="card-header"><i class="bi bi-box-seam me-1"></i>Histórico de Cargas</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="carga_ultima" class="form-label">Última Carga</label>
                            <input type="text" class="form-control" id="carga_ultima" name="carga_ultima" value="<?php echo htmlspecialchars($servico['carga_ultima']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="carga_penultima" class="form-label">Penúltima Carga</label>
                            <input type="text" class="form-control" id="carga_penultima" name="carga_penultima" value="<?php echo htmlspecialchars($servico['carga_penultima']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="carga_antepenultima" class="form-label">Antepenúltima Carga</label>
                            <input type="text" class="form-control" id="carga_antepenultima" name="carga_antepenultima" value="<?php echo htmlspecialchars($servico['carga_antepenultima']); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- PROCEDIMENTOS REALIZADOS (Para o Laudo) -->
            <div class="card-kpi mb-4">
                <div class="card-header"><i class="bi bi-ui-checks me-1"></i>Procedimentos Realizados (para o Laudo)</div>
                <div class="card-body">
                    <?php if ($_SESSION['user_perfil'] === 'admin'): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <?php 
                                $proc_list_1 = [
                                    'proc1' => 'Lavagem Interna', 'proc2' => 'Vapor', 'proc3' => 'Lavagem Com Agua Quente', 'proc4' => 'Lavagem Com Detergente Neutro',
                                    'proc5' => 'Enxague Com Agua - 30 Minutos', 'proc6' => 'Vaporizacao Korsher/Halal', 'proc7' => 'Secagem Manual', 'proc8' => 'Ventilacao / Exaustao'
                                ];
                                foreach($proc_list_1 as $id => $val):
                                    $checked = in_array($val, $procedimentos_info) ? 'checked' : '';
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="procedimentos[]" value="<?php echo htmlspecialchars($val); ?>" id="<?php echo $id; ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label" for="<?php echo $id; ?>"><?php echo htmlspecialchars($val); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-md-6">
                                <?php 
                                $proc_list_2 = [
                                    'proc9' => 'Lavagem Da Tubulacao', 'proc10' => 'Lavagem Das Valvulas', 'proc11' => 'Lavagem Dos Mangotes', 'proc12' => 'Troca De Junta Das Valvulas',
                                    'proc13' => 'Desmonte Das Valvulas Dreno', 'proc14' => 'Troca Das Juntas Da Boca Visita', 'proc15' => 'Limpeza De Manometros', 'proc16' => 'PH enxague final (7,5 a 9,0)'
                                ];
                                foreach($proc_list_2 as $id => $val):
                                    $checked = in_array($val, $procedimentos_info) ? 'checked' : '';
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="procedimentos[]" value="<?php echo htmlspecialchars($val); ?>" id="<?php echo $id; ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label" for="<?php echo $id; ?>"><?php echo htmlspecialchars($val); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-12">
                                <?php if(empty($procedimentos_info)): ?>
                                    <p class="text-muted mb-0">Nenhum procedimento registrado.</p>
                                <?php else: ?>
                                    <ul class="mb-0 text-light">
                                        <?php foreach($procedimentos_info as $p): ?>
                                            <li><?php echo htmlspecialchars($p); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- OBSERVAÇÕES E LACRES -->
            <div class="card-kpi mb-4">
                <div class="card-header"><i class="bi bi-info-circle me-1"></i>Observações e Lacres</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_lavagem" class="form-label">Data da Lavagem</label>
                            <input type="date" class="form-control" id="data_lavagem" name="data_lavagem" value="<?php echo htmlspecialchars($servico['data_lavagem']); ?>">
                            <small class="text-muted">Vencimento atual: <?php echo date('d/m/Y', strtotime($servico['data_vencimento'])); ?></small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($servico['observacoes']); ?></textarea>
                        </div>
                    </div>
                    <hr class="border-secondary">
                    <label class="form-label">Informações dos Lacres</label>
                    <div class="row g-2">
                        <div class="col-6"><div class="input-group"><span class="input-group-text">Boca Sup. 1</span><input type="text" class="form-control" name="lacres[bvs1]" value="<?php echo htmlspecialchars($lacres_info['bvs1'] ?? ''); ?>"></div></div>
                        <div class="col-6"><div class="input-group"><span class="input-group-text">Vál. Descarga</span><input type="text" class="form-control" name="lacres[val_descarga]" value="<?php echo htmlspecialchars($lacres_info['val_descarga'] ?? ''); ?>"></div></div>
                        <div class="col-6"><div class="input-group"><span class="input-group-text">Boca Sup. 2</span><input type="text" class="form-control" name="lacres[bvs2]" value="<?php echo htmlspecialchars($lacres_info['bvs2'] ?? ''); ?>"></div></div>
                        <div class="col-6"><div class="input-group"><span class="input-group-text">Vál. Dreno</span><input type="text" class="form-control" name="lacres[val_dreno]" value="<?php echo htmlspecialchars($lacres_info['val_dreno'] ?? ''); ?>"></div></div>
                        <div class="col-6"><div class="input-group"><span class="input-group-text">Boca Sup. 3</span><input type="text" class="form-control" name="lacres[bvs3]" value="<?php echo htmlspecialchars($lacres_info['bvs3'] ?? ''); ?>"></div></div>
                        <div class="col-6"><div class="input-group"><span class="input-group-text">Sup. Mangote</span><input type="text" class="form-control" name="lacres[sup_mangote]" value="<?php echo htmlspecialchars($lacres_info['sup_mangote'] ?? ''); ?>"></div></div>
                    </div>
                </div>
            </div>

             <div class="text-end mb-5">
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-floppy-fill me-2"></i>Salvar Todas as Alterações
                </button>
            </div>
        </div>

        <!-- Coluna de Serviços e Valores (Com Lógica de Permissão) -->
        <div class="col-lg-4">
            <?php if ($_SESSION['user_perfil'] === 'admin'): ?>
                <!-- Bloco para Administradores (Editável) -->
                <div class="card-kpi mb-4">
                    <div class="card-header"><i class="bi bi-list-check me-1"></i>Editar Serviços Prestados</div>
                    <div class="card-body">
                         <div class="row align-items-end mb-3">
                            <div class="col-md-12 mb-2">
                                <label for="servico_select" class="form-label">Adicionar Serviço</label>
                                <select id="servico_select" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($catalogo_servicos as $catalogo_item): ?>
                                        <option value="<?php echo $catalogo_item['id']; ?>" data-preco="<?php echo $catalogo_item['preco']; ?>">
                                            <?php echo htmlspecialchars($catalogo_item['nome_servico']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <button type="button" id="add-service-btn-details" class="btn btn-primary w-100">Adicionar Serviço</button>
                            </div>
                        </div>
                        <table class="table table-dark table-sm">
                            <tbody id="servicos-selecionados-tabela"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-kpi">
                    <div class="card-header"><i class="bi bi-receipt me-1"></i>Resumo Financeiro</div>
                    <div class="card-body resumo-financeiro-detalhes">
                        <div class="d-flex justify-content-between"><span>Subtotal:</span> <span id="subtotal-val">R$ 0,00</span></div>
                        <div class="d-flex justify-content-between align-items-center">
                            <label for="desconto" class="form-label mb-0">Desconto:</label>
                            <input type="number" step="0.01" class="form-control form-control-sm w-50" id="desconto" name="desconto" value="<?php echo number_format($servico['desconto'], 2, '.', ''); ?>">
                        </div>
                        <hr class="border-secondary">
                        <div class="d-flex justify-content-between fw-bold fs-4"><span class="text-warning">TOTAL:</span> <span id="total-val" class="text-warning">R$ 0,00</span></div>
                        <hr class="border-secondary">
                         <div class="mb-3">
                            <label for="metodo_pagamento" class="form-label">Forma de Pagamento:</label>
                            <select class="form-select" id="metodo_pagamento" name="metodo_pagamento" required>
                                <option value="Pix" <?php echo ($servico['metodo_pagamento'] == 'Pix') ? 'selected' : ''; ?>>Pix</option>
                                <option value="Dinheiro" <?php echo ($servico['metodo_pagamento'] == 'Dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                                <option value="Cartão" <?php echo ($servico['metodo_pagamento'] == 'Cartão') ? 'selected' : ''; ?>>Cartão</option>
                                <option value="Faturamento" <?php echo ($servico['metodo_pagamento'] == 'Faturamento') ? 'selected' : ''; ?>>Faturamento</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-between"><span>Status do Pedido:</span> <span class="badge bg-<?php echo $servico['status'] == 'Ativo' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($servico['status']); ?></span></div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Bloco para Operadores (Apenas Visualização) -->
                <div class="card-kpi mb-4">
                    <div class="card-header"><i class="bi bi-list-check me-1"></i>Serviços Prestados</div>
                    <div class="card-body">
                        <table class="table table-dark table-sm">
                            <tbody>
                                <?php foreach ($servicos_prestados as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                        <td class="text-end">R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-kpi">
                    <div class="card-header"><i class="bi bi-receipt me-1"></i>Resumo Financeiro</div>
                    <div class="card-body resumo-financeiro-detalhes">
                        <div class="d-flex justify-content-between"><span>Subtotal:</span> <span>R$ <?php echo number_format($servico['subtotal'], 2, ',', '.'); ?></span></div>
                        <div class="d-flex justify-content-between"><span>Desconto:</span> <span>- R$ <?php echo number_format($servico['desconto'], 2, ',', '.'); ?></span></div>
                        <hr class="border-secondary">
                        <div class="d-flex justify-content-between fw-bold fs-4"><span class="text-warning">TOTAL:</span> <span class="text-warning">R$ <?php echo number_format($servico['valor_final'], 2, ',', '.'); ?></span></div>
                        <hr class="border-secondary">
                        <div class="d-flex justify-content-between"><span>Forma de Pagamento:</span> <span><?php echo htmlspecialchars($servico['metodo_pagamento']); ?></span></div>
                        <div class="d-flex justify-content-between"><span>Status do Pedido:</span> <span class="badge bg-<?php echo $servico['status'] == 'Ativo' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($servico['status']); ?></span></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
<?php if ($_SESSION['user_perfil'] === 'admin'): ?>
// Passa os serviços iniciais do PHP para o JavaScript APENAS se for admin
const initialServices = <?php echo json_encode($servicos_prestados); ?>;
<?php endif; ?>

function cancelarPedido(id) {
    const motivo = prompt("Por favor, insira o motivo do cancelamento:");
    if (motivo === null) { return; }
    if (motivo.trim() === "") { alert("O motivo do cancelamento é obrigatório."); return; }
    if (confirm("Tem certeza que deseja cancelar este pedido com o motivo:\n\n" + motivo)) {
        window.location.href = `../includes/service_cancel_process.php?id=${id}&motivo=${encodeURIComponent(motivo)}`;
    }
}

function reprovarPedido(id) {
    const motivo = prompt("Por favor, insira a justificativa para a reprovação do laudo:");
    if (motivo === null) { return; }
    if (motivo.trim() === "") { alert("A justificativa de reprovação é obrigatória."); return; }
    if (confirm("Tem certeza que deseja marcar este laudo como REPROVADO com a justificativa:\n\n" + motivo)) {
        window.location.href = `../includes/service_reject_process.php?id=${id}&motivo=${encodeURIComponent(motivo)}`;
    }
}
</script>

<?php
include '../templates/footer.php';
?>