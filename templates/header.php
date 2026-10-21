<?php
// Ficheiro: templates/header.php
// A chamada session_start() foi removida daqui para evitar redundância.
// A nossa função secure_session_start() já trata disso em cada página.
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema TrovãoBrasil</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Nosso CSS customizado -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-dark-custom">

    <div class="d-flex toggled" id="wrapper">
        <!-- O menu lateral será inserido aqui -->