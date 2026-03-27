<?php
// web-admin/db_init.php - Inicializador do Banco de Dados
$db = new SQLite3('ai_block_web.db');

// Tabela de Configurações Administrativas
$db->exec("CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password_hash TEXT
)");

// Tabela de Dispositivos (MACs)
$db->exec("CREATE TABLE IF NOT EXISTS macs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mac_address TEXT UNIQUE,
    description TEXT
)");

// Tabela de Domínios de IA
$db->exec("CREATE TABLE IF NOT EXISTS domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT UNIQUE
)");

// Cria usuário padrão (admin / admin123) - Recomendado alterar após login
$default_user = 'admin';
$default_pass = password_hash('admin123', PASSWORD_BCRYPT);

$stmt = $db->prepare("INSERT OR IGNORE INTO admins (username, password_hash) VALUES (:user, :pass)");
$stmt->bindValue(':user', $default_user);
$stmt->bindValue(':pass', $default_pass);
$stmt->execute();

echo "✅ Banco de dados 'ai_block_web.db' inicializado com sucesso!\n";
echo "Usuário padrão: admin / Senha: admin123\n";
?>
