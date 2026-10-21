<?php
// Ficheiro: pages/service_success.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    header('Location: ../index.php?error=notloggedin');
    exit();
}

$last_service_id = $_SESSION['last_service_id'] ?? null;

// Limpa o ID da sessão para não o usar novamente por engano
unset($_SESSION['last_service_id']);

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<div class="container-fluid d-flex align-items-center justify-content-center" style="min-height: 70vh;">
    <div class="text-center">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
        <h1 class="mt-4">Pedido Registrado com Sucesso!</h1>
        <p class="lead text-muted">O que deseja fazer agora?</p>
        
        <div class="mt-4">
            <?php if ($last_service_id): ?>
                <a href="../includes/generate_recibo.php?id=<?php echo $last_service_id; ?>" target="_blank" class="btn btn-warning btn-lg me-2">
                    <i class="bi bi-receipt"></i> Imprimir Recibo
                </a>
            <?php endif; ?>
            <a href="service_register.php" class="btn btn-primary btn-lg me-2">
                <i class="bi bi-plus-circle"></i> Registrar Novo Pedido
            </a>
            <a href="services_list.php" class="btn btn-secondary btn-lg">
                <i class="bi bi-list-ul"></i> Ver Todos os Pedidos
            </a>
        </div>
    </div>
</div>
<!-- Fim do Conteúdo Específico da Página -->

<?php
include '../templates/footer.php';
?>
