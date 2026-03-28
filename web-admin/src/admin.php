<?php
// web-admin/src/admin.php - Dashboard c/ Select2 e Busca
require_once 'db.php';
redirect_if_not_logged();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_group'])) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO groups (name) VALUES (?)");
        $stmt->execute([$_POST['name']]);
    }
    if (isset($_POST['del_group'])) {
        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    if (isset($_POST['add_host'])) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO hosts_master (url, description) VALUES (?, ?)");
        $stmt->execute([$_POST['url'], $_POST['desc']]);
    }
    if (isset($_POST['del_host'])) {
        $stmt = $pdo->prepare("DELETE FROM hosts_master WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    }
    if (isset($_POST['save_blocks'])) {
        $gid = $_POST['group_id'];
        $pdo->prepare("DELETE FROM group_blocks WHERE group_id = ?")->execute([$gid]);
        if (isset($_POST['hosts'])) {
            $stmt = $pdo->prepare("INSERT INTO group_blocks (group_id, host_id) VALUES (?, ?)");
            foreach($_POST['hosts'] as $hid) {
                $stmt->execute([$gid, $hid]);
            }
        }
    }
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
$hosts = $pdo->query("SELECT * FROM hosts_master ORDER BY url ASC")->fetchAll();
$macs = $pdo->query("SELECT m.*, g.name as group_name FROM macs m LEFT JOIN groups g ON m.group_id = g.id")->fetchAll();

$blocks_raw = $pdo->query("SELECT * FROM group_blocks")->fetchAll();
$blocks = [];
foreach($blocks_raw as $b) { $blocks[$b['group_id']][] = $b['host_id']; }

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>AI-Block - Master Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
        .btn-del { background: var(--danger); font-size: 0.75em; padding: 4px 8px; }
        .btn-save { background: var(--primary); width: 100%; margin-top: 10px; font-weight: bold; }
        
        /* Select2 Dark Mode Fix */
        .select2-container--default .select2-selection--multiple { background-color: #0f172a; border: 1px solid #475569; }
        .select2-container--default .select2-selection--multiple .select2-selection__choice { background-color: #3b82f6; border: none; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AI-Block Admin</h1>
            <a href="?logout=1" class="btn" style="background:#64748b">Sair</a>
        </div>

        <div class="grid">
            <!-- 1. MASTER LIST -->
            <div class="card">
                <h2>Lista Global de Hosts</h2>
                <form method="POST" style="display:flex; gap:5px;">
                    <input type="text" name="url" placeholder="Domínio" required style="flex:1">
                    <button type="submit" name="add_host" class="btn btn-add">Add</button>
                </form>
                <div style="max-height: 400px; overflow-y: auto; margin-top: 15px;">
                    <table>
                        <tbody>
                            <?php foreach($hosts as $h): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($h['url']); ?></code></td>
                                <td style="text-align:right">
                                    <form method="POST" onsubmit="return confirm('Apagar host global?');">
                                        <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                                        <button type="submit" name="del_host" class="btn btn-del">X</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. GROUPS & SELECTION -->
            <div class="card">
                <h2>Canais de Bloqueio p/ Grupo</h2>
                <form method="POST" style="margin-bottom:15px; display:flex; gap:5px;">
                    <input type="text" name="name" placeholder="Nome do Grupo" required style="flex:1">
                    <button type="submit" name="add_group" class="btn btn-add">Novo Grupo</button>
                </form>
                
                <?php foreach($groups as $g): ?>
                <div style="background:#0f172a; padding:15px; border-radius:8px; margin-bottom:15px; border-left: 4px solid var(--primary);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0;"><?php echo htmlspecialchars($g['name']); ?></h3>
                        <form method="POST" onsubmit="return confirm('Remover este grupo?');">
                            <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                            <button type="submit" name="del_group" class="btn btn-del">Remover Grupo</button>
                        </form>
                    </div>

                    <form method="POST" style="margin-top:10px;">
                        <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <label style="font-size:0.8em; color:var(--muted)">Bloqueios ativos:</label>
                            <button type="button" class="btn-select-all" data-target="grp-<?php echo $g['id']; ?>" style="font-size:0.7em; background:none; border:none; color:var(--primary); cursor:pointer; text-decoration:underline;">Selecionar Todos</button>
                        </div>
                        <select name="hosts[]" id="grp-<?php echo $g['id']; ?>" class="host-selector" multiple="multiple" style="width: 100%">
                            <?php foreach($hosts as $h): 
                                $selected = in_array($h['id'], $blocks[$g['id']] ?? []) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $h['id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($h['url']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="save_blocks" class="btn btn-save">Atualizar Bloqueios</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3. DEVICES -->
        <div class="card">
            <h2>Dispositivos (MACs)</h2>
            <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="text" name="mac" placeholder="AA:BB:CC:DD:EE:FF" required>
                <input type="text" name="desc" placeholder="Ex: Celular João">
                <select name="group_id" required>
                    <option value="">Selecione o Grupo</option>
                    <?php foreach($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_mac" class="btn btn-add">Vincular MAC</button>
            </form>
            <table>
                <thead><tr><th>MAC</th><th>Descrição</th><th>Grupo</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach($macs as $m): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($m['mac']); ?></code></td>
                        <td><?php echo htmlspecialchars($m['description']); ?></td>
                        <td><span style="color:var(--primary)"><?php echo htmlspecialchars($m['group_name'] ?? 'Sem Grupo'); ?></span></td>
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

    <script>
    $(document).ready(function() {
        $('.host-selector').select2({
            placeholder: "Pesquisar hosts...",
            allowClear: true
        });

        $('.btn-select-all').on('click', function() {
            var targetId = $(this).data('target');
            var selectElement = $('#' + targetId);
            var allValues = selectElement.find('option').map(function() { return $(this).val(); }).get();
            
            // Toggle logic: se todos selecionados, limpa. Caso contrário, seleciona tudo.
            var currentValues = selectElement.val();
            if (currentValues && currentValues.length === allValues.length) {
                selectElement.val(null).trigger('change');
            } else {
                selectElement.val(allValues).trigger('change');
            }
        });
    });
    </script>
</body>
</html>
