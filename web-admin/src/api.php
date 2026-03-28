<?php
// web-admin/src/api.php - API Relacional para OpenWRT (Master List)
require_once 'db.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'json';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Ação: Listar todos os grupos
if ($action === 'groups') {
    $stmt = $pdo->query("SELECT name FROM groups");
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($type === 'text') {
        foreach($groups as $g) echo $g . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode(['groups' => $groups]);
    }
    exit;
}

// Ação: Obter dados de um grupo específico usando a Master List
if ($action === 'config') {
    $group_name = $_GET['group'] ?? '';
    
    // Busca ID do grupo
    $stmt = $pdo->prepare("SELECT id FROM groups WHERE name = ?");
    $stmt->execute([$group_name]);
    $group_id = $stmt->fetchColumn();

    if (!$group_id) {
        http_response_code(404);
        die("Grupo nao encontrado.");
    }

    $target = $_GET['target'] ?? 'all';
    $data = [];

    // Busca domínios ATRAVÉS do relacionamento Master List -> Group Blocks
    if ($target === 'domains' || $target === 'all') {
        $stmt = $pdo->prepare("
            SELECT h.url 
            FROM hosts_master h 
            JOIN group_blocks b ON h.id = b.host_id 
            WHERE b.group_id = ?
        ");
        $stmt->execute([$group_id]);
        $data['domains'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($target === 'macs' || $target === 'all') {
        $stmt = $pdo->prepare("SELECT mac FROM macs WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $data['macs'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($type === 'text') {
        header('Content-Type: text/plain');
        if ($target === 'domains') {
            foreach($data['domains'] as $d) echo $d . "\n";
        } elseif ($target === 'macs') {
            foreach($data['macs'] as $m) echo $m . "\n";
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    exit;
}

echo "AI-Block Master API: Use ?action=groups ou ?action=config&group=NOME";

//docker compose down
//docker compose up -d --build
?>