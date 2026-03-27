<?php
// web-admin/lib.php - Funções Auxiliares (Zero-Deps JSON Persistence)
$data_file = __DIR__ . '/data.json';

// Inicializa dados se não existirem
if (!file_exists($data_file)) {
    $initial_data = [
        'admins' => [
            ['user' => 'admin', 'pass' => password_hash('admin123', PASSWORD_BCRYPT)]
        ],
        'macs' => [],
        'domains' => [
            ['url' => 'chatgpt.com'],
            ['url' => 'openai.com']
        ]
    ];
    file_put_contents($data_file, json_encode($initial_data, JSON_PRETTY_PRINT));
}

function load_data() {
    global $data_file;
    return json_decode(file_get_contents($data_file), true);
}

function save_data($data) {
    global $data_file;
    file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
}

// Sistema de Login básico
session_start();
function is_logged() {
    return isset($_SESSION['user']);
}

function require_login() {
    if (!is_logged()) {
        header('Location: index.php');
        exit;
    }
}
?>
