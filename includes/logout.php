<?php
// Arquivo: includes/logout.php
require_once 'functions.php';
secure_session_start();

// Desfaz todas as variáveis da sessão
$_SESSION = array();

// Obtém os parâmetros do cookie da sessão
$params = session_get_cookie_params();

// Deleta o cookie atual
setcookie(session_name(),
    '', time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]);

// Destrói a sessão
session_destroy();

// Redireciona para a página de login
header('Location: ../index.php');
exit();
?>
