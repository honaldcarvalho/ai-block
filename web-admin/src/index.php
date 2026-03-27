<?php
// web-admin/src/index.php - Landing e Login
require_once 'db.php';

$error = "";
if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $user_row = $stmt->fetch();

    if ($user_row && password_verify($pass, $user_row['password'])) {
        $_SESSION['user_id'] = $user_row['id'];
        $_SESSION['username'] = $user_row['username'];
        header("Location: admin.php");
        exit;
    } else {
        $error = "Credenciais inválidas!";
    }
}

if (is_logged()) {
    header("Location: admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>AI-Block Admin - Login</title>
    <style>
        :root { --bg: #0f172a; --surface: #1e293b; --primary: #3b82f6; --text: #f8fafc; }
        body { font-family: sans-serif; background: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-card { background: var(--surface); padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); width: 350px; text-align: center; }
        h1 { margin-bottom: 25px; color: var(--primary); }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        button:hover { background: #2563eb; }
        .error { color: #ef4444; margin-bottom: 15px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>AI-Block</h1>
        <p>Painel de Controle Central</p>
        <?php if($error): ?> <div class="error"><?php echo $error; ?></div> <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Usuário" required>
            <input type="password" name="password" placeholder="Senha" required>
            <button type="submit" name="login">Entrar</button>
        </form>
    </div>
</body>
</html>
