<?php
// Ficheiro: pages/catalog_list.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

try {
    $sql = "SELECT id, nome_servico, preco FROM catalogo_servicos WHERE ativo = 1 ORDER BY nome_servico ASC";
    $servicos = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    $servicos = [];
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mt-4 page-title-main">Catálogo de Serviços</h1>
        <p class="text-muted">Adicione, edite e defina os preços dos serviços oferecidos.</p>
    </div>
    <div>
        <a href="catalog_form.php" class="btn btn-warning btn-lg"><i class="bi bi-plus-circle me-2"></i>Adicionar Novo Serviço</a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success mt-3" role="alert">Operação realizada com sucesso!</div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger mt-3" role="alert">Ocorreu um erro.</div>
<?php endif; ?>

<div class="card-kpi mt-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover table-services">
                <thead>
                    <tr>
                        <th scope="col">Nome do Serviço</th>
                        <th scope="col">Preço (R$)</th>
                        <th scope="col">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servicos)): ?>
                        <tr>
                            <td colspan="3" class="text-center">Nenhum serviço cadastrado ainda.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servicos as $servico): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($servico['nome_servico']); ?></td>
                                <td><?php echo number_format($servico['preco'], 2, ',', '.'); ?></td>
                                <td>
                                    <a href="catalog_form.php?id=<?php echo $servico['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="../includes/catalog_delete_process.php?id=<?php echo $servico['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir este serviço?');" class="btn btn-sm btn-outline-danger" title="Excluir">
                                        <i class="bi bi-trash-fill"></i>
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

<?php
include '../templates/footer.php';
?>
