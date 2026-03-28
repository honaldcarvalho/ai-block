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

    // Populando a Master List AUTOMATICAMENTE com TUDO do ai_list.json
    if ($pdo->query("SELECT COUNT(*) FROM hosts_master")->fetchColumn() == 0) {
        $full_list = [
            "chatgpt.com", "openai.com", "api.openai.com", "platform.openai.com", "chat.openai.com",
            "claude.ai", "anthropic.com", "api.anthropic.com", "console.anthropic.com", "gemini.google.com",
            "aistudio.google.com", "bard.google.com", "deepmind.com", "deepmind.google", "perplexity.ai",
            "copilot.microsoft.com", "sydney.bing.com", "bing.com/chat", "phind.com", "you.com", "poe.com",
            "character.ai", "pi.ai", "heypi.com", "huggingface.co", "replicate.com", "mistral.ai",
            "chat.mistral.ai", "console.mistral.ai", "cohere.com", "ai21.com", "inflection.ai", "x.ai",
            "grok.com", "meta.ai", "llama.meta.com", "qwen.ai", "moonshot.cn", "kimi.moonshot.cn",
            "baichuan-ai.com", "zhipuai.cn", "minimax.chat", "01.ai", "midjourney.com", "stability.ai",
            "clipdrop.co", "runwayml.com", "runway.com", "pika.art", "suno.ai", "suno.com", "udio.com",
            "elevenlabs.io", "leonardo.ai", "nightcafe.studio", "civitai.com", "artbreeder.com", "d-id.com",
            "heygen.com", "synthesia.io", "kaiber.ai", "githubcopilot.com", "copilot.github.com",
            "tabnine.com", "cursor.sh", "cursor.com", "sourcegraph.com", "codeium.com", "blackbox.ai",
            "pieces.app", "notion.so", "jasper.ai", "copy.ai", "writesonic.com", "rytr.me", "tome.app",
            "gamma.app", "beautiful.ai", "otter.ai", "fireflies.ai", "tldv.io", "grammarly.com",
            "quillbot.com", "deepl.com", "linksquares.com", "concordnow.com", "vlex.com", "jusbrasil.com.br",
            "jurishand.com.br", "jusfy.com.br", "escavador.com", "contraktor.com.br", "neoway.com.br",
            "loylegal.com.br", "datalawyer.com.br", "brainlaw.com.br", "legalsense.com.br", "lawsync.com.br",
            "legalbot.com.br", "jurisai.com.br", "lexminds.com.br", "casesolver.com.br", "legaltechbrasil.com.br",
            "ailegalsolutions.com.br", "aurum.com.br", "judex.com.br", "juridicoai.com.br", "chatadv.com.br",
            "turivius.com.br", "projuris.com.br", "advise.com.br", "lexia.com.br"
        ];
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO hosts_master (url, description) VALUES (?, ?)");
        foreach($full_list as $h) {
            $stmt->execute([$h, "Importado do AI-Block List"]);
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
