<?php
// Ficheiro: pages/catalog_form.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

$is_editing = false;
$servico = ['id' => '', 'nome_servico' => '', 'preco' => ''];
$page_title = "Adicionar Novo Serviço ao Catálogo";

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $is_editing = true;
    $servico_id = $_GET['id'];
    $page_title = "Editar Serviço do Catálogo";

    try {
        $stmt = $pdo->prepare("SELECT * FROM catalogo_servicos WHERE id = ?");
        $stmt->execute([$servico_id]);
        $servico = $stmt->fetch();
        if (!$servico) {
            header('Location: catalog_list.php?error=notfound');
            exit();
        }
    } catch (PDOException $e) {
        die("Erro ao buscar dados do serviço: " . $e->getMessage());
    }
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<h1 class="mt-4 page-title-main"><?php echo $page_title; ?></h1>
<p class="text-muted">Preencha os dados abaixo para registar ou atualizar um serviço.</p>

<div class="card-kpi mt-4">
    <div class="card-body p-4">
        <form action="../includes/catalog_process.php" method="POST">
            <?php if ($is_editing): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($servico['id']); ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="nome_servico" class="form-label">Nome do Serviço</label>
                <input type="text" class="form-control" id="nome_servico" name="nome_servico" value="<?php echo htmlspecialchars($servico['nome_servico']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="preco" class="form-label">Preço (R$)</label>
                <input type="number" step="0.01" class="form-control" id="preco" name="preco" value="<?php echo htmlspecialchars($servico['preco']); ?>" placeholder="Ex: 150.00" required>
            </div>

            <div class="text-end mt-4">
                <a href="catalog_list.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-check-lg me-2"></i>
                    <?php echo $is_editing ? 'Atualizar Serviço' : 'Salvar Serviço'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
include '../templates/footer.php';
?>
