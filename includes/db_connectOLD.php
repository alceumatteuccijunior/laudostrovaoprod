<?php
// Arquivo: includes/db_connect.php

// --- Configurações do Banco de Dados ---
// Substitua com as suas credenciais reais
define("DB_HOST", "localhost");       // Endereço do servidor do banco de dados
define("DB_NAME", "castelob_laudos"); // Nome do banco de dados
define("DB_USER", "castelob_laudos");            // Usuário do banco de dados
define("DB_PASS", "agwYzQ3N3nqjUna33Fdf");                // Senha do banco de dados
define("DB_CHARSET", "utf8mb4");      // Charset

// --- Bloco de Conexão (PDO) ---
try {
    // Define o DSN (Data Source Name)
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    // Opções do PDO para uma conexão mais robusta
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna os resultados como arrays associativos
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Desativa a emulação de prepared statements
    ];

    // Cria a instância do PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (\PDOException $e) {
    // Em caso de falha na conexão, exibe uma mensagem de erro genérica e encerra o script.
    // Em um ambiente de produção, é recomendado logar o erro em vez de exibi-lo na tela.
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
