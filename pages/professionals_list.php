<?php
// Ficheiro: pages/professionals_list.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in() || $_SESSION['user_perfil'] !== 'admin') {
    die("Acesso negado. Apenas administradores podem aceder a esta página.");
}

// --- Lógica de Paginação e Pesquisa ---
$resultados_por_pagina = 10;
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$offset = ($pagina_atual - 1) * $resultados_por_pagina;

$termo_pesquisa = filter_input(INPUT_GET, 'pesquisa', FILTER_SANITIZE_STRING) ?: '';
$params = [];
$sql_where = " WHERE 1=1";

if (!empty($termo_pesquisa)) {
    $sql_where .= " AND (nome LIKE :termo OR cargo LIKE :termo OR cpf LIKE :termo)";
    $params[':termo'] = '%' . $termo_pesquisa . '%';
}

try {
    // Conta o total de resultados
    $stmt_total = $pdo->prepare("SELECT COUNT(id) FROM profissionais" . $sql_where);
    $stmt_total->execute($params);
    $total_resultados = $stmt_total->fetchColumn();
    $total_paginas = ceil($total_resultados / $resultados_por_pagina);

    // Busca os resultados para a página atual
    $sql_profissionais = "SELECT id, nome, cargo, telefone, email, ativo FROM profissionais" . $sql_where . " ORDER BY nome ASC LIMIT :limit OFFSET :offset";
    $stmt_profissionais = $pdo->prepare($sql_profissionais);
    $stmt_profissionais->bindValue(':limit', $resultados_por_pagina, PDO::PARAM_INT);
    $stmt_profissionais->bindValue(':offset', $offset, PDO::PARAM_INT);

    if (!empty($termo_pesquisa)) {
        $stmt_profissionais->bindValue(':termo', $params[':termo']);
    }

    $stmt_profissionais->execute();
    $profissionais = $stmt_profissionais->fetchAll();

} catch (PDOException $e) {
    $profissionais = [];
    $total_paginas = 0;
    // Para depuração: die("Erro ao buscar profissionais: " . $e->getMessage());
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="mt-4 page-title-main">Gestão de Profissionais</h1>
        <p class="text-muted">Adicione, edite e visualize os profissionais da empresa.</p>
    </div>
    <div>
        <a href="professional_form.php" class="btn btn-warning btn-lg"><i class="bi bi-plus-circle me-2"></i>Adicionar Novo Profissional</a>
    </div>
</div>

<!-- Área de Notificações -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success mt-3" role="alert">
        Operação realizada com sucesso!
    </div>
<?php elseif (isset($_GET['deleted'])): ?>
     <div class="alert alert-success mt-3" role="alert">
        Profissional excluído com sucesso!
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger mt-3" role="alert">
        <?php
            switch ($_GET['error']) {
                case 'self_delete':
                    echo 'Não é possível excluir o seu próprio utilizador.';
                    break;
                default:
                    echo 'Ocorreu um erro. Por favor, tente novamente.';
                    break;
            }
        ?>
    </div>
<?php endif; ?>

<!-- Formulário de Pesquisa -->
<div class="card-kpi my-4">
    <div class="card-body">
        <form method="GET" action="professionals_list.php" class="d-flex">
            <input type="text" name="pesquisa" class="form-control me-2" placeholder="Pesquisar por nome, cargo ou CPF..." value="<?php echo htmlspecialchars($termo_pesquisa); ?>">
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
                        <th scope="col">Nome</th>
                        <th scope="col">Cargo</th>
                        <th scope="col">Telefone</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Status</th>
                        <th scope="col">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($profissionais)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum profissional encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($profissionais as $profissional): ?>
                            <tr class="<?php echo ($profissional['ativo'] == 0) ? 'table-danger-custom' : ''; ?>">
                                <td><?php echo htmlspecialchars($profissional['nome']); ?></td>
                                <td><?php echo htmlspecialchars($profissional['cargo']); ?></td>
                                <td><?php echo htmlspecialchars($profissional['telefone']); ?></td>
                                <td><?php echo htmlspecialchars($profissional['email']); ?></td>
                                <td>
                                    <?php if ($profissional['ativo'] == 1): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Desligado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="professional_productivity.php?id=<?php echo $profissional['id']; ?>" class="btn btn-sm btn-outline-info" title="Ver Produtividade">
                                        <i class="bi bi-graph-up"></i>
                                    </a>
                                    <a href="professional_form.php?id=<?php echo $profissional['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <?php if ($profissional['ativo'] == 1): ?>
                                    <a href="../includes/professional_delete_process.php?id=<?php echo $profissional['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir/desligar este profissional?');" class="btn btn-sm btn-outline-danger" title="Excluir/Desligar">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                    <?php endif; ?>
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
                            <a class="page-link" href="professionals_list.php?pagina=<?php echo $i; ?>&pesquisa=<?php echo urlencode($termo_pesquisa); ?>"><?php echo $i; ?></a>
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
