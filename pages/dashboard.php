<?php
// Ficheiro: pages/dashboard.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

secure_session_start();

if (!is_user_logged_in()) {
    header('Location: ../index.php?error=notloggedin');
    exit();
}

include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- Início do Conteúdo Específico da Página -->
<div class="container-fluid d-flex align-items-center justify-content-center" style="min-height: 80vh;">
    <div class="text-center">
        <img src="https://trovaobrasil.com.br/estoque/logo.svg" alt="Logo TrovãoBrasil" style="height: 80px; margin-bottom: 20px;">
        
        <h1 class="display-5 fw-bold">Bem-vindo(a), <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>!</h1>
        
        <p class="lead text-muted mb-4">
            O seu sistema de gestão de laudos está pronto para começar.
        </p>
        
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <a href="service_register.php" class="btn btn-warning btn-lg px-4 gap-3">
                <i class="bi bi-file-earmark-plus-fill me-2"></i>Registrar Novo Pedido
            </a>
            <a href="services_list.php" class="btn btn-outline-secondary btn-lg px-4">
                <i class="bi bi-list-ul me-2"></i>Ver Histórico de Pedidos
            </a>
        </div>
    </div>
</div>
<!-- Fim do Conteúdo Específico da Página -->

<?php
include '../templates/footer.php';
?>
