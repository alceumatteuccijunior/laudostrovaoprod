<?php
// Ficheiro: pages/clients_list.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado.");
}

// --- Lógica de Paginação e Pesquisa ---
$resultados_por_pagina = 10;
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$offset = ($pagina_atual - 1) * $resultados_por_pagina;

$termo_pesquisa = filter_input(INPUT_GET, 'pesquisa', FILTER_SANITIZE_STRING) ?: '';
$params = [];
$sql_where = "";

if (!empty($termo_pesquisa)) {
    $sql_where = " WHERE nome_razao_social LIKE :termo OR cpf_cnpj LIKE :termo OR email LIKE :termo";
    $params[':termo'] = '%' . $termo_pesquisa . '%';
}

try {
    // Conta o total de resultados
    $stmt_total = $pdo->prepare("SELECT COUNT(id) FROM clientes" . $sql_where);
    $stmt_total->execute($params);
    $total_resultados = $stmt_total->fetchColumn();
    $total_paginas = ceil($total_resultados / $resultados_por_pagina);

    // Busca os resultados para a página atual
    $sql_clientes = "SELECT id, nome_razao_social, cpf_cnpj, telefone, email FROM clientes" . $sql_where . " ORDER BY nome_razao_social ASC LIMIT :limit OFFSET :offset";
    $stmt_clientes = $pdo->prepare($sql_clientes);
    $stmt_clientes->bindValue(':limit', $resultados_por_pagina, PDO::PARAM_INT);
    $stmt_clientes->bindValue(':offset', $offset, PDO::PARAM_INT);

    if (!empty($termo_pesquisa)) {
        $stmt_clientes->bindValue(':termo', $params[':termo']);
    }

    $stmt_clientes->execute();
    $clientes = $stmt_clientes->fetchAll();

} catch (PDOException $e) {
    $clientes = [];
    $total_paginas = 0;
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mt-4 page-title-main">Gestão de Clientes</h1>
        <p class="text-muted">Adicione, edite e visualize os clientes da empresa.</p>
    </div>
    <div>
        <a href="client_form.php" class="btn btn-warning btn-lg"><i class="bi bi-plus-circle me-2"></i>Adicionar Novo Cliente</a>
    </div>
</div>

<!-- Área de Notificações -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success mt-3" role="alert">Operação realizada com sucesso!</div>
<?php elseif (isset($_GET['deleted'])): ?>
     <div class="alert alert-success mt-3" role="alert">Cliente excluído com sucesso!</div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger mt-3" role="alert">Ocorreu um erro.</div>
<?php endif; ?>

<!-- Formulário de Pesquisa -->
<div class="card-kpi my-4">
    <div class="card-body">
        <form method="GET" action="clients_list.php" class="d-flex">
            <input type="text" name="pesquisa" class="form-control me-2" placeholder="Pesquisar por nome, CPF/CNPJ ou e-mail..." value="<?php echo htmlspecialchars($termo_pesquisa); ?>">
            <button type="submit" class="btn btn-warning">Pesquisar</button>
        </form>
    </div>
</div>

<div class="card-kpi mt-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover table-services">
                <thead>
                    <tr>
                        <th scope="col">Nome / Razão Social</th>
                        <th scope="col">CPF / CNPJ</th>
                        <th scope="col">Telefone</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum cliente encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['nome_razao_social']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['cpf_cnpj']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefone']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                <td>
                                    <a href="client_form.php?id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="../includes/client_delete_process.php?id=<?php echo $cliente['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir este cliente? A ação não pode ser desfeita.');" class="btn btn-sm btn-outline-danger" title="Excluir">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Navegação da Paginação -->
        <?php if ($total_paginas > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>">
                            <a class="page-link" href="clients_list.php?pagina=<?php echo $i; ?>&pesquisa=<?php echo urlencode($termo_pesquisa); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Fim do Conteúdo Específico da Página -->

<?php
include '../templates/footer.php';
?>
