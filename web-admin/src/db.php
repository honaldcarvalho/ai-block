<?php
// web-admin/src/db.php - Banco de Dados e Helpers
$db_dir = '/var/www/html/db';
$db_file = $db_dir . '/ai_block_web.sqlite';
// Cria a pasta se ela não existir no container
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0777, true);
}

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Inicializa tabelas se não existirem
    $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS macs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        mac TEXT UNIQUE,
        description TEXT,
        group_id INTEGER,
        FOREIGN KEY(group_id) REFERENCES groups(id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS domains (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT,
        group_id INTEGER,
        FOREIGN KEY(group_id) REFERENCES groups(id) ON DELETE CASCADE,
        UNIQUE(url, group_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT
    )");

    // Usuário padrão (admin / admin123)
    $res = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($res == 0) {
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password) VALUES ('admin', '$pass')");
    }

} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

session_start();
function is_logged() {
    return isset($_SESSION['user_id']);
}

function redirect_if_not_logged() {
    if (!is_logged()) {
        header("Location: index.php");
        exit;
    }
}
?>
