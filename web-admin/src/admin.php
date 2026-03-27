<?php
// web-admin/src/admin.php - Dashboard de Controle com Suporte a Grupos
require_once 'db.php';
redirect_if_not_logged();

// Ações de CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Grupos
    if (isset($_POST['add_group'])) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO groups (name) VALUES (?)");
        $stmt->execute([$_POST['name']]);
    }
    if (isset($_POST['del_group'])) {
        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    // MACs
    if (isset($_POST['add_mac'])) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO macs (mac, description, group_id) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['mac'], $_POST['desc'], $_POST['group_id']]);
    }
    if (isset($_POST['del_mac'])) {
        $stmt = $pdo->prepare("DELETE FROM macs WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    // Domains
    if (isset($_POST['add_domain'])) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO domains (url, group_id) VALUES (?, ?)");
        $stmt->execute([$_POST['url'], $_POST['group_id']]);
    }
    if (isset($_POST['del_domain'])) {
        $stmt = $pdo->prepare("DELETE FROM domains WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    header("Location: admin.php"); exit;
}

$groups = $pdo->query("SELECT * FROM groups")->fetchAll();
$macs = $pdo->query("SELECT m.*, g.name as group_name FROM macs m LEFT JOIN groups g ON m.group_id = g.id")->fetchAll();
$domains = $pdo->query("SELECT d.*, g.name as group_name FROM domains d LEFT JOIN groups g ON d.group_id = g.id")->fetchAll();

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>AI-Block - Administração Grupos</title>
    <style>
        :root { --bg: #0f172a; --surface: #1e293b; --primary: #3b82f6; --text: #f8fafc; --muted: #94a3b8; }
        body { font-family: sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-bottom: 20px; }
        .card { background: var(--surface); padding: 20px; border-radius: 12px; margin-bottom: 30px; }
        h2 { color: var(--primary); margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #334155; }
        th { color: var(--muted); text-transform: uppercase; font-size: 0.8em; }
        input, select { padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #0f172a; color: white; }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; cursor: pointer; color: white; }
        .btn-add { background: #10b981; }
        .btn-del { background: #ef4444; }
        .btn-logout { background: #64748b; text-decoration: none; font-size: 0.9em; }
        .badge { background: #3b82f6; padding: 3px 8px; border-radius: 10px; font-size: 0.8em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Painel AI-Block Multi-Grupo</h1>
            <a href="?logout=1" class="btn btn-logout">Sair</a>
        </div>

        <!-- Gerenciar Grupos -->
        <div class="card">
            <h2>1. Gerenciar Grupos (Perfis)</h2>
            <form method="POST">
                <input type="text" name="name" placeholder="Nome do Grupo (ex: Kids)" required>
                <button type="submit" name="add_group" class="btn btn-add">Criar Grupo</button>
            </form>
            <table>
                <thead><tr><th>ID</th><th>Nome</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($groups as $g): ?>
                    <tr>
                        <td><?php echo $g['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($g['name']); ?></strong></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                                <button type="submit" name="del_group" class="btn btn-del">Excluir</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Gerenciar MACs -->
        <div class="card">
            <h2>2. Vincular Dispositivos (MACs)</h2>
            <form method="POST">
                <input type="text" name="mac" placeholder="MAC" required>
                <input type="text" name="desc" placeholder="Descrição">
                <select name="group_id" required>
                    <option value="">Selecione um Grupo</option>
                    <?php foreach($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_mac" class="btn btn-add">Vincular</button>
            </form>
            <table>
                <thead><tr><th>MAC</th><th>Descrição</th><th>Grupo</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($macs as $m): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($m['mac']); ?></code></td>
                        <td><?php echo htmlspecialchars($m['description']); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars($m['group_name'] ?? 'Sem Grupo'); ?></span></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                <button type="submit" name="del_mac" class="btn btn-del">Remover</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Gerenciar Domínios -->
        <div class="card">
            <h2>3. Gerenciar Bloqueios por Grupo</h2>
            <form method="POST">
                <input type="text" name="url" placeholder="Domínio" required>
                <select name="group_id" required>
                    <option value="">Selecione um Grupo</option>
                    <?php foreach($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_domain" class="btn btn-add">Adicionar</button>
            </form>
            <table>
                <thead><tr><th>Domínio</th><th>Grupo</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($domains as $d): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($d['url']); ?></code></td>
                        <td><span class="badge"><?php echo htmlspecialchars($d['group_name'] ?? 'Sem Grupo'); ?></span></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                <button type="submit" name="del_domain" class="btn btn-del">Remover</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
