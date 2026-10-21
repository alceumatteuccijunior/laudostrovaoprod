<?php
// Ficheiro: pages/client_form.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    header('Location: ../index.php?error=notloggedin');
    exit();
}

// --- Lógica para Edição vs. Adição ---
$is_editing = false;
$cliente = [
    'id' => '',
    'nome_razao_social' => '',
    'cpf_cnpj' => '',
    'telefone' => '',
    'email' => '',
    'endereco' => ''
];
$page_title = "Adicionar Novo Cliente";
$form_action = "client_process.php";

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $is_editing = true;
    $client_id = $_GET['id'];
    $page_title = "Editar Cliente";

    try {
        $sql = "SELECT * FROM clientes WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $client_id);
        $stmt->execute();
        $cliente = $stmt->fetch();

        if (!$cliente) {
            header('Location: clients_list.php?error=notfound');
            exit();
        }
    } catch (PDOException $e) {
        die("Erro ao buscar dados do cliente: " . $e->getMessage());
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<h1 class="mt-4 page-title-main"><?php echo $page_title; ?></h1>
<p class="text-muted">Preencha os dados abaixo para registar ou atualizar um cliente.</p>

<div class="card-kpi mt-4">
    <div class="card-body p-4">
        <form action="../includes/<?php echo $form_action; ?>" method="POST">
            <!-- Campo oculto para o ID no modo de edição -->
            <?php if ($is_editing): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($cliente['id']); ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="nome_razao_social" class="form-label">Nome / Razão Social</label>
                <input type="text" class="form-control" id="nome_razao_social" name="nome_razao_social" value="<?php echo htmlspecialchars($cliente['nome_razao_social']); ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cpf_cnpj" class="form-label">CPF / CNPJ</label>
                    <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo htmlspecialchars($cliente['cpf_cnpj']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($cliente['telefone']); ?>">
                </div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($cliente['email']); ?>">
            </div>
            <div class="mb-3">
                <label for="endereco" class="form-label">Endereço</label>
                <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($cliente['endereco']); ?>">
            </div>

            <div class="text-end mt-4">
                <a href="clients_list.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-check-lg me-2"></i>
                    <?php echo $is_editing ? 'Atualizar Cliente' : 'Salvar Cliente'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Fim do Conteúdo Específico da Página -->

<?php
include '../templates/footer.php';
?>
