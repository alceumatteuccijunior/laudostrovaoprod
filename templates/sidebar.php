<?php
// Ficheiro: templates/sidebar.php

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="bg-dark-sidebar" id="sidebar-wrapper">
    <div class="sidebar-heading text-center py-4">
        <img src="https://trovaobrasil.com.br/estoque/logo.svg" alt="Logo TrovãoBrasil" height="30">
    </div>
    <div class="list-group list-group-flush my-3">
        <a href="dashboard.php"
            class="list-group-item list-group-item-action <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="bi bi-house-door-fill me-2"></i>Início
        </a>
        <a href="service_register.php"
            class="list-group-item list-group-item-action <?php echo ($current_page == 'service_register.php') ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-plus-fill me-2"></i>Registrar Serviço
        </a>
        <a href="services_list.php"
            class="list-group-item list-group-item-action <?php echo ($current_page == 'services_list.php' || $current_page == 'service_details.php') ? 'active' : ''; ?>">
            <i class="bi bi-list-ul me-2"></i>Serviços
        </a>

        <?php // Apenas administradores podem ver os menus de gestão ?>
        <?php if (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] == 'admin'): ?>
            <a href="reports.php"
                class="list-group-item list-group-item-action <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <i class="bi bi-graph-up me-2"></i>Relatórios
            </a>
            <a href="clients_list.php"
                class="list-group-item list-group-item-action <?php echo (strpos($current_page, 'client') !== false && $current_page !== 'crm_clientes.php') ? 'active' : ''; ?>">
                <i class="bi bi-people-fill me-2"></i>Clientes
            </a>
            <a href="professionals_list.php"
                class="list-group-item list-group-item-action <?php echo (strpos($current_page, 'professional') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-person-badge-fill me-2"></i>Profissionais
            </a>
            <a href="catalog_list.php"
                class="list-group-item list-group-item-action <?php echo (strpos($current_page, 'catalog') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-book-fill me-2"></i>Catálogo de Serviços
            </a>
            <a href="vehicles_list.php"
                class="list-group-item list-group-item-action <?php echo (strpos($current_page, 'vehicle') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-truck me-2"></i>Frota / Veículos
                <span class="badge bg-success opacity-75" style="font-size: 0.65em;">Novo</span>
            </a>
            <a href="crm_clientes.php"
                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo ($current_page == 'crm_clientes.php') ? 'active' : ''; ?>">
                <span><i class="bi bi-radar me-2"></i>Monitoramento CRM</span>
                <span class="badge bg-success opacity-75" style="font-size: 0.65em;">Novo</span>
            </a>
        <?php endif; ?>

        <a href="#" class="list-group-item list-group-item-action">
            <i class="bi bi-gear-fill me-2"></i>Opções
        </a>
    </div>
</div>
<!-- /#sidebar-wrapper -->

<!-- Page Content -->
<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark-custom-nav px-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-list fs-4 me-3" id="menu-toggle"></i>
            <h2 class="fs-4 m-0 page-title" id="page-title-header">
                <?php
                if ($current_page == 'crm_clientes.php') {
                    echo 'Monitoramento CRM';
                } elseif (strpos($current_page, 'client') !== false) {
                    echo 'Gestão de Clientes';
                } elseif (strpos($current_page, 'professional') !== false) {
                    echo 'Gestão de Profissionais';
                } elseif (strpos($current_page, 'catalog') !== false) {
                    echo 'Catálogo de Serviços';
                } elseif (strpos($current_page, 'vehicle') !== false) {
                    echo 'Gestão de Frota';
                } elseif ($current_page == 'reports.php') {
                    echo 'Relatórios Gerenciais';
                } else {
                    switch ($current_page) {
                        case 'service_register.php':
                            echo 'Registrar Novo Serviço';
                            break;
                        case 'services_list.php':
                        case 'service_details.php':
                            echo 'Histórico de Serviços';
                            break;
                        default:
                            echo 'Dashboard';
                            break;
                    }
                }
                ?>
            </h2>
        </div>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="#">Perfil</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="../includes/logout.php">Sair</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <!-- O conteúdo específico de cada página será inserido aqui -->