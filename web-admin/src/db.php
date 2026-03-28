<?php
// web-admin/src/db.php - Banco de Dados em Volume Isolado
$db_dir = '/var/www/db';
$db_file = $db_dir . '/ai_block_web.sqlite';
// O Docker Volume gerencia as permissões desta pasta
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

    // Novo: Lista Global de Hosts (Domínios de IA)
    $pdo->exec("CREATE TABLE IF NOT EXISTS hosts_master (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT UNIQUE,
        description TEXT
    )");

    // Novo: Vínculo entre Grupos e Hosts (Quais bloqueios cada grupo tem)
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_blocks (
        group_id INTEGER,
        host_id INTEGER,
        PRIMARY KEY(group_id, host_id),
        FOREIGN KEY(group_id) REFERENCES groups(id) ON DELETE CASCADE,
        FOREIGN KEY(host_id) REFERENCES hosts_master(id) ON DELETE CASCADE
    )");

    // Populando a Master List automaticamente (Seeding)
    if ($pdo->query("SELECT COUNT(*) FROM hosts_master")->fetchColumn() == 0) {
        $default_hosts = [
            "chatgpt.com", "openai.com", "claude.ai", "anthropic.com", "gemini.google.com",
            "perplexity.ai", "mistral.ai", "meta.ai", "midjourney.com", "stability.ai",
            "runwayml.com", "pika.art", "suno.com", "elevenlabs.io", "leonardo.ai",
            "huggingface.co", "replicate.com", "cursor.com", "tabnine.com", "codeium.com",
            "notion.so", "jasper.ai", "copy.ai", "grammarly.com", "deepl.com",
            "jusbrasil.com.br", "escavador.com", "vlex.com"
        ];
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO hosts_master (url, description) VALUES (?, ?)");
        foreach($default_hosts as $h) {
            $stmt->execute([$h, "IA Automatizada"]);
        }
    }

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
