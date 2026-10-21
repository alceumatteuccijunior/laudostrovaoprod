<?php
// Ficheiro: pages/service_register.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    header('Location: ../index.php?error=notloggedin');
    exit();
}

// Busca a lista de profissionais e serviços ativos para os dropdowns
try {
    $sql_profissionais = "SELECT id, nome FROM profissionais WHERE ativo = 1 ORDER BY nome ASC";
    $profissionais = $pdo->query($sql_profissionais)->fetchAll();

    $sql_catalogo = "SELECT id, nome_servico, preco FROM catalogo_servicos WHERE ativo = 1 ORDER BY nome_servico ASC";
    $catalogo_servicos = $pdo->query($sql_catalogo)->fetchAll();

} catch (PDOException $e) {
    $profissionais = [];
    $catalogo_servicos = [];
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<h1 class="mt-4 page-title-main">Registrar Novo Pedido de Serviço</h1>
<p class="text-muted">Preencha os dados abaixo para criar um novo pedido.</p>

<div class="card-kpi mt-4">
    <div class="card-body p-4">
        <form id="register-service-form" action="../includes/service_process.php" method="POST" novalidate>
            
            <!-- Dados do Pedido -->
            <fieldset class="mb-4">
                <legend class="fs-5 mb-3">Dados do Pedido</legend>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="cliente_search" class="form-label">Cliente</label>
                        <div class="autocomplete-container">
                            <input type="text" class="form-control" id="cliente_search" placeholder="Procure um cliente..." required>
                            <input type="hidden" id="cliente_id" name="cliente_id" required>
                            <div class="autocomplete-results" id="autocomplete-results"></div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="profissional_id" class="form-label">Profissional Responsável</label>
                        <select class="form-select" id="profissional_id" name="profissional_id" required>
                            <option value="" disabled selected>Selecione...</option>
                            <?php foreach ($profissionais as $profissional): ?>
                                <option value="<?php echo $profissional['id']; ?>"><?php echo htmlspecialchars($profissional['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="profissional_id_2" class="form-label">Segundo Responsável (Comissão)</label>
                        <select class="form-select" id="profissional_id_2" name="profissional_id_2">
                            <option value="">Nenhum</option>
                            <?php foreach ($profissionais as $profissional): ?>
                                <option value="<?php echo $profissional['id']; ?>"><?php echo htmlspecialchars($profissional['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                     <div class="col-md-4 mb-3">
                        <label for="motorista" class="form-label">Motorista</label>
                        <input type="text" class="form-control" id="motorista" name="motorista" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="doc_motorista" class="form-label">Documento do Motorista</label>
                        <input type="text" class="form-control" id="doc_motorista" name="doc_motorista">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="hora_chegada" class="form-label">Horário Chegada</label>
                        <input type="time" class="form-control" id="hora_chegada" name="hora_chegada" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="hora_saida" class="form-label">Horário Saída</label>
                        <input type="time" class="form-control" id="hora_saida" name="hora_saida" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="placa_carreta" class="form-label">Placa Carreta/Truck</label>
                        <input type="text" class="form-control" id="placa_carreta" name="placa_carreta" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="placa_cavalo" class="form-label">Placa Cavalo</label>
                        <input type="text" class="form-control" id="placa_cavalo" name="placa_cavalo">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="turno" class="form-label">Turno</label>
                        <select class="form-select" id="turno" name="turno" required>
                            <option value="Manha" selected>Manhã</option>
                            <option value="Tarde">Tarde</option>
                            <option value="Noite">Noite</option>
                        </select>
                    </div>
                </div>
                 <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="carga_ultima" class="form-label">Último Carregamento</label>
                        <input type="text" class="form-control" id="carga_ultima" name="carga_ultima" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="carga_penultima" class="form-label">Penúltimo Carregamento</label>
                        <input type="text" class="form-control" id="carga_penultima" name="carga_penultima">
                    </div>
                     <div class="col-md-4 mb-3">
                        <label for="carga_antepenultima" class="form-label">Antepenúltimo Carregamento</label>
                        <input type="text" class="form-control" id="carga_antepenultima" name="carga_antepenultima">
                    </div>
                </div>
            </fieldset>

            <!-- Serviços Prestados (Financeiro) -->
            <fieldset class="mb-4">
                <legend class="fs-5 mb-3">Serviços Prestados (Financeiro)</legend>
                <div class="row align-items-end">
                    <div class="col-md-6">
                        <label for="servico_select" class="form-label">Adicionar Serviço</label>
                        <select id="servico_select" class="form-select">
                            <option value="">Selecione um serviço para adicionar...</option>
                            <?php foreach ($catalogo_servicos as $servico): ?>
                                <option value="<?php echo $servico['id']; ?>" data-preco="<?php echo $servico['preco']; ?>">
                                    <?php echo htmlspecialchars($servico['nome_servico']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="add-service-btn" class="btn btn-primary w-100">Adicionar</button>
                    </div>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Serviço</th>
                                <th class="text-end">Preço</th>
                                <th class="text-center">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="servicos-selecionados-tabela">
                            <!-- Os serviços adicionados aparecerão aqui -->
                        </tbody>
                    </table>
                </div>
            </fieldset>

            <!-- Procedimentos Realizados (para o Laudo) -->
            <fieldset class="mb-4">
                <legend class="fs-5 mb-3">Procedimentos Realizados (para o Laudo)</legend>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Lavagem Interna" id="proc1"><label class="form-check-label" for="proc1">Lavagem Interna</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Vapor" id="proc2"><label class="form-check-label" for="proc2">Vapor</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Lavagem Com Agua Quente" id="proc3"><label class="form-check-label" for="proc3">Lavagem Com Água Quente</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Lavagem Com Detergente Neutro" id="proc4"><label class="form-check-label" for="proc4">Lavagem Com Detergente Neutro</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Enxague Com Agua - 30 Minutos" id="proc5"><label class="form-check-label" for="proc5">Enxague Com Água - 30 Minutos</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Vaporizacao Korsher/Halal" id="proc6"><label class="form-check-label" for="proc6">Vaporização Korsher/Halal</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Secagem Manual" id="proc7"><label class="form-check-label" for="proc7">Secagem Manual</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Ventilacao / Exaustao" id="proc8"><label class="form-check-label" for="proc8">Ventilação / Exaustão</label></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Lavagem Da Tubulacao" id="proc9"><label class="form-check-label" for="proc9">Lavagem Da Tubulação</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Lavagem Das Valvulas" id="proc10"><label class="form-check-label" for="proc10">Lavagem Das Válvulas</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Lavagem Dos Mangotes" id="proc11"><label class="form-check-label" for="proc11">Lavagem Dos Mangotes</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Troca De Junta Das Valvulas" id="proc12"><label class="form-check-label" for="proc12">Troca De Junta Das Válvulas</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Desmonte Das Valvulas Dreno" id="proc13"><label class="form-check-label" for="proc13">Desmonte Das Válvulas Dreno</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Troca Das Juntas Da Boca Visita" id="proc14"><label class="form-check-label" for="proc14">Troca Das Juntas Da Boca Visita</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="Limpeza De Manometros" id="proc15"><label class="form-check-label" for="proc15">Limpeza De Manômetros</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="procedimentos[]" value="PH enxague final (7,5 a 9,0)" id="proc16"><label class="form-check-label" for="proc16">PH enxague final (7,5 a 9,0)</label></div>
                    </div>
                </div>
            </fieldset>

            <!-- Finalização e Valores -->
            <fieldset>
                <legend class="fs-5 mb-3">Finalização e Valores</legend>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="data_lavagem" class="form-label">Data da Lavagem</label>
                            <input type="date" class="form-control" id="data_lavagem" name="data_lavagem" value="<?php echo date('Y-m-d'); ?>" required>
                            <small class="form-text text-muted">O vencimento do laudo será 5 dias após esta data.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Informações dos Lacres</label>
                            <div class="row g-2">
                                <div class="col-6"><div class="input-group"><span class="input-group-text">Boca Sup. 1</span><input type="text" class="form-control" name="lacres[bvs1]"></div></div>
                                <div class="col-6"><div class="input-group"><span class="input-group-text">Vál. Descarga</span><input type="text" class="form-control" name="lacres[val_descarga]"></div></div>
                                <div class="col-6"><div class="input-group"><span class="input-group-text">Boca Sup. 2</span><input type="text" class="form-control" name="lacres[bvs2]"></div></div>
                                <div class="col-6"><div class="input-group"><span class="input-group-text">Vál. Dreno</span><input type="text" class="form-control" name="lacres[val_dreno]"></div></div>
                                <div class="col-6"><div class="input-group"><span class="input-group-text">Boca Sup. 3</span><input type="text" class="form-control" name="lacres[bvs3]"></div></div>
                                <div class="col-6"><div class="input-group"><span class="input-group-text">Sup. Mangote</span><input type="text" class="form-control" name="lacres[sup_mangote]"></div></div>
                            </div>
                        </div>
                         <div class="mb-3">
                            <label for="observacoes" class="form-label">Observações Gerais</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="resumo-financeiro p-3 rounded">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span id="subtotal-val">R$ 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="desconto" class="form-label mb-0">Desconto (R$):</label>
                                <input type="number" step="0.01" class="form-control form-control-sm w-50" id="desconto" name="desconto" value="0.00">
                            </div>
                            <hr class="border-secondary">
                            <div class="d-flex justify-content-between fs-4 fw-bold mb-3">
                                <span>TOTAL:</span>
                                <span id="total-val" class="text-warning">R$ 0,00</span>
                            </div>
                            <div class="mb-2">
                                <label for="metodo_pagamento" class="form-label">Forma de Pagamento:</label>
                                <select class="form-select" id="metodo_pagamento" name="metodo_pagamento" required>
                                    <option value="Pix">Pix</option>
                                    <option value="Dinheiro">Dinheiro</option>
                                    <option value="Cartão">Cartão</option>
                                    <option value="Faturamento">Faturamento</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>
            
            <div class="text-end mt-4">
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-lg"></i> Finalizar e Gerar Recibo</button>
            </div>
        </form>
    </div>
</div>

<!-- Fim do Conteúdo Específico da Página -->

<?php
include '../templates/footer.php';
?>
