<?php
// Ficheiro: includes/functions.php

/**
 * Inicia a sessão de forma segura, apenas se não houver já uma sessão ativa.
 */
function secure_session_start() {
    // Verifica se uma sessão já não está ativa
    if (session_status() == PHP_SESSION_NONE) {
        $session_name = 'trovao_session_id';
        $secure = false; // Mude para true se estiver usando HTTPS
        $httponly = true;

        if (ini_set('session.use_only_cookies', 1) === FALSE) {
            header("Location: ../index.php?error=Could not initiate a safe session (ini_set)");
            exit();
        }

        $cookieParams = session_get_cookie_params();
        session_set_cookie_params(
            $cookieParams["lifetime"],
            $cookieParams["path"],
            $cookieParams["domain"],
            $secure,
            $httponly
        );

        session_name($session_name);
        session_start();
    }
}

/**
 * Verifica se o utilizador está autenticado de forma segura.
 * @return bool Retorna true se o utilizador estiver autenticado, false caso contrário.
 */
function is_user_logged_in() {
    // A função precisa da conexão com a base de dados para funcionar
    global $pdo; 

    if (isset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['login_string'])) {
        $user_id = $_SESSION['user_id'];
        $login_string = $_SESSION['login_string'];
        
        try {
            $sql = "SELECT senha FROM profissionais WHERE id = :user_id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                $password_hash = $user['senha'];
                $login_check = hash('sha512', $password_hash . $_SERVER['HTTP_USER_AGENT']);

                if (hash_equals($login_check, $login_string)) {
                    // Autenticado!
                    return true;
                }
            }
        } catch (PDOException $e) {
            // Erro na base de dados, não se pode validar
            return false;
        }
    }
    
    // Não está autenticado
    return false;
}
