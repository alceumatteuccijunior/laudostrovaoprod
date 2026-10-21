<?php
// Arquivo: includes/db_connect.php

// --- Configurações do Banco de Dados ---
// Identifica se está rodando localmente (XAMPP) ou no servidor online
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    define("DB_HOST", "localhost");
    define("DB_NAME", "castelob_laudos"); // Nome do banco importado no XAMPP
    define("DB_USER", "root");
    define("DB_PASS", "");
    // URL do servidor Mestre (online) para onde os dados serão enviados
    define("SYNC_SERVER_URL", "https://trovaobrasil.com.br/estoque"); 
} else {
    define("DB_HOST", "localhost");
    define("DB_NAME", "castelob_laudos");
    define("DB_USER", "castelob_laudos");
    define("DB_PASS", "agwYzQ3N3nqjUna33Fdf");
    define("SYNC_SERVER_URL", ""); // No online, não usamos
}
define("DB_CHARSET", "utf8mb4");

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
