<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema TrovãoBrasil</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Nosso CSS customizado -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark-custom">
    <div class="login-container">
        <div class="login-box">
            <div class="text-center mb-4">
                <img src="https://trovaobrasil.com.br/estoque/logo.svg" alt="Logo TrovãoBrasil" height="40">
            </div>

            <?php
            // Exibe uma mensagem de erro se o login falhar
            if (isset($_GET['error'])) {
                echo '<div class="alert alert-danger">CPF ou senha inválidos.</div>';
            }
            ?>

            <form action="includes/login_process.php" method="POST">
                <div class="mb-3">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control form-control-lg" id="cpf" name="cpf" placeholder="000.000.000-00" required>
                </div>
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" class="form-control form-control-lg" id="senha" name="senha" required>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-warning btn-lg">Entrar</button>
                </div>
                <div class="text-center mt-3">
                    <a href="#" class="text-warning-custom">Esqueci minha senha</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
