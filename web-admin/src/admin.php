<?php
// web-admin/src/admin.php - Dashboard Avançado (Master List + Grupos)
require_once 'db.php';
redirect_if_not_logged();

// Ações de CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Grupos
    if (isset($_POST['add_group'])) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO groups (name) VALUES (?)");
        $stmt->execute([$_POST['name']]);
    }
    if (isset($_POST['del_group'])) {
        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    // 2. Master List de Hosts
    if (isset($_POST['add_host'])) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO hosts_master (url, description) VALUES (?, ?)");
        $stmt->execute([$_POST['url'], $_POST['desc']]);
    }
    if (isset($_POST['del_host'])) {
        $stmt = $pdo->prepare("DELETE FROM hosts_master WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    // 3. Vincular Hosts ao Grupo (Toggle)
    if (isset($_POST['toggle_block'])) {
        $gid = $_POST['group_id'];
        $hid = $_POST['host_id'];
        if ($_POST['status'] === 'off') {
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO group_blocks (group_id, host_id) VALUES (?, ?)");
            $stmt->execute([$gid, $hid]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM group_blocks WHERE group_id = ? AND host_id = ?");
            $stmt->execute([$gid, $hid]);
        }
    }
    // 4. MACs
    if (isset($_POST['add_mac'])) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO macs (mac, description, group_id) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['mac'], $_POST['desc'], $_POST['group_id']]);
    }
    if (isset($_POST['del_mac'])) {
        $stmt = $pdo->prepare("DELETE FROM macs WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    header("Location: admin.php"); exit;
}

$groups = $pdo->query("SELECT * FROM groups")->fetchAll();
$hosts = $pdo->query("SELECT * FROM hosts_master")->fetchAll();
$macs = $pdo->query("SELECT m.*, g.name as group_name FROM macs m LEFT JOIN groups g ON m.group_id = g.id")->fetchAll();

// Mapeamento de quais hosts cada grupo bloqueia
$blocks_raw = $pdo->query("SELECT * FROM group_blocks")->fetchAll();
$blocks = [];
foreach($blocks_raw as $b) {
    $blocks[$b['group_id']][$b['host_id']] = true;
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>AI-Block - Master List Admin</title>
    <style>
        :root { --bg: #0f172a; --surface: #1e293b; --primary: #3b82f6; --text: #f8fafc; --muted: #94a3b8; --success: #10b981; --danger: #ef4444; }
        body { font-family: sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; padding-bottom: 10px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: var(--surface); padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        h2 { color: var(--primary); margin-top: 0; border-bottom: 1px solid #334155; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #334155; }
        input, select { padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #0f172a; color: white; }
        .btn { padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; color: white; font-size: 0.9em; }
        .btn-add { background: var(--success); }
        .btn-del { background: var(--danger); }
        .badge { background: #3b82f6; padding: 2px 6px; border-radius: 8px; font-size: 0.75em; }
        .toggle-btn { background: #334155; border: 1px solid #475569; color: var(--muted); }
        .toggle-btn.active { background: var(--danger); color: white; border-color: var(--danger); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AI-Block: Gestão de Bloqueios</h1>
            <a href="?logout=1" class="btn" style="background:#64748b">Sair</a>
        </div>

        <div class="grid">
            <!-- 1. MASTER LIST -->
            <div class="card">
                <h2>Lista Global de Hosts (IAs)</h2>
                <form method="POST">
                    <input type="text" name="url" placeholder="Domínio" required>
                    <input type="text" name="desc" placeholder="Nome/Descrição">
                    <button type="submit" name="add_host" class="btn btn-add">Cadastrar Host</button>
                </form>
                <table>
                    <thead><tr><th>Host</th><th>Ação</th></tr></thead>
                    <tbody>
                        <?php foreach($hosts as $h): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($h['url']); ?></strong><br><small style="color:var(--muted)"><?php echo htmlspecialchars($h['description']); ?></small></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                                    <button type="submit" name="del_host" class="btn btn-del">Remover</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 2. GROUPS & SELECTION -->
            <div class="card">
                <h2>Configuração por Grupo</h2>
                <form method="POST" style="margin-bottom:15px;">
                    <input type="text" name="name" placeholder="Nome do Grupo" required>
                    <button type="submit" name="add_group" class="btn btn-add">Novo Grupo</button>
                </form>
                
                <?php foreach($groups as $g): ?>
                <div style="background:#0f172a; padding:15px; border-radius:8px; margin-bottom:15px;">
                    <div style="display:flex; justify-content:space-between;">
                        <h3 style="margin:0; color:var(--success)">Grupo: <?php echo htmlspecialchars($g['name']); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                            <button type="submit" name="del_group" class="btn btn-del" style="padding:2px 8px">Apagar</button>
                        </form>
                    </div>
                    <p style="font-size:0.8em; color:var(--muted)">Selecione os hosts para bloquear neste perfil:</p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <?php foreach($hosts as $h): 
                            $isActive = isset($blocks[$g['id']][$h['id']]);
                        ?>
                        <form method="POST">
                            <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                            <input type="hidden" name="host_id" value="<?php echo $h['id']; ?>">
                            <input type="hidden" name="status" value="<?php echo $isActive ? 'on' : 'off'; ?>">
                            <button type="submit" name="toggle_block" class="btn toggle-btn <?php echo $isActive ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($h['url']); ?>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3. DEVICES (MACs) -->
        <div class="card">
            <h2>Vincular Dispositivos aos Grupos</h2>
            <form method="POST">
                <input type="text" name="mac" placeholder="MAC (AA:BB:CC...)" required>
                <input type="text" name="desc" placeholder="Dono/Aparelho">
                <select name="group_id" required>
                    <option value="">Escolha um Grupo</option>
                    <?php foreach($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_mac" class="btn btn-add">Vincular MAC</button>
            </form>
            <table>
                <thead><tr><th>MAC</th><th>Aparelho</th><th>Grupo</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($macs as $m): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($m['mac']); ?></code></td>
                        <td><?php echo htmlspecialchars($m['description']); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars($m['group_name'] ?? 'Nenhum'); ?></span></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                <button type="submit" name="del_mac" class="btn btn-del">Remover</button>
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
