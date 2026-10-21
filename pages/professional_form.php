<?php
// Ficheiro: pages/professional_form.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado. Apenas administradores podem aceder a esta página.");
}

// --- Lógica para Edição vs. Adição ---
$is_editing = false;
$professional = [
    'id' => '',
    'nome' => '',
    'cpf' => '',
    'cargo' => '',
    'telefone' => '',
    'email' => '',
    'perfil' => 'operador', // Perfil padrão
    'ativo' => 1,
    'recebe_alertas_crm' => 0
];
$page_title = "Adicionar Novo Profissional";
$form_action = "professional_process.php";

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $is_editing = true;
    $professional_id = $_GET['id'];
    $page_title = "Editar Profissional";

    try {
        $sql = "SELECT id, nome, cpf, cargo, telefone, email, perfil, ativo, recebe_alertas_crm FROM profissionais WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $professional_id);
        $stmt->execute();
        $professional = $stmt->fetch();

        if (!$professional) {
            header('Location: professionals_list.php?error=notfound');
            exit();
        }
    } catch (PDOException $e) {
        die("Erro ao buscar dados do profissional: " . $e->getMessage());
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<h1 class="mt-4 page-title-main"><?php echo $page_title; ?></h1>
<p class="text-muted">Preencha os dados abaixo para registar ou atualizar um profissional.</p>

<div class="card-kpi mt-4">
    <div class="card-body p-4">
        <form action="../includes/<?php echo $form_action; ?>" method="POST">
            <!-- Campo oculto para o ID no modo de edição -->
            <?php if ($is_editing): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($professional['id']); ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nome" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($professional['nome']); ?>" required>
                </div>
                 <div class="col-md-6 mb-3">
                    <label for="cargo" class="form-label">Cargo</label>
                    <input type="text" class="form-control" id="cargo" name="cargo" value="<?php echo htmlspecialchars($professional['cargo']); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cpf" class="form-label">CPF (será usado para login)</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" value="<?php echo htmlspecialchars($professional['cpf']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" <?php echo !$is_editing ? 'required' : ''; ?>>
                    <?php if ($is_editing): ?>
                        <small class="form-text text-muted">Deixe em branco para não alterar a senha atual.</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($professional['telefone']); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($professional['email']); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="perfil" class="form-label">Perfil de Acesso</label>
                    <select class="form-select" id="perfil" name="perfil" required>
                        <option value="operador" <?php echo ($professional['perfil'] == 'operador') ? 'selected' : ''; ?>>Operador</option>
                        <option value="admin" <?php echo ($professional['perfil'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="ativo" class="form-label">Status</label>
                    <select class="form-select" id="ativo" name="ativo" required>
                        <option value="1" <?php echo ($professional['ativo'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                        <option value="0" <?php echo ($professional['ativo'] == 0) ? 'selected' : ''; ?>>Desligado</option>
                    </select>
                </div>
            </div>

            <div class="row" id="divAlertasCRM" style="<?php echo ($professional['perfil'] === 'admin') ? '' : 'display:none;'; ?>">
                <div class="col-md-12 mb-3">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" id="recebe_alertas_crm" name="recebe_alertas_crm" value="1" <?php echo ($professional['recebe_alertas_crm'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="recebe_alertas_crm">
                            <strong>Receber Alertas de Queda de Clientes por E-mail</strong> <br>
                            <small class="text-muted">Apenas administradores podem receber estes alertas automáticos por e-mail caso algum cliente sofra queda nas lavagens.</small>
                        </label>
                    </div>
                </div>
            </div>

            <div class="text-end mt-4">
                <a href="professionals_list.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-check-lg me-2"></i>
                    <?php echo $is_editing ? 'Atualizar Profissional' : 'Salvar Profissional'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('perfil').addEventListener('change', function() {
    if (this.value === 'admin') {
        document.getElementById('divAlertasCRM').style.display = 'flex';
    } else {
        document.getElementById('divAlertasCRM').style.display = 'none';
        document.getElementById('recebe_alertas_crm').checked = false;
    }
});
</script>

<!-- Fim do Conteúdo Específico da Página -->

<?php
include '../templates/footer.php';
?>
