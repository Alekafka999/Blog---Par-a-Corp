<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

if (blog_is_logged_in()) {
    blog_redirect('admin.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (blog_attempt_login($username, $password)) {
        blog_redirect('admin.php');
    }

    $error = 'Usuário ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= blog_e(BLOG_SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <main class="login-wrap">
        <section class="login-card">
            <h1>Login</h1>
            <p class="muted">Acesse o painel para publicar artigos.</p>

            <?php if ($error !== ''): ?>
                <div class="alert error"><?= blog_e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="stack-form">
                <label>
                    Usuário
                    <input type="text" name="username" required autocomplete="username">
                </label>
                <label>
                    Senha
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button type="submit">Entrar</button>
            </form>

            <p class="small-note">Nao tem conta? <a href="register.php">Criar cadastro</a></p>
            <p class="small-note"><a href="forgot.php">Esqueci minha senha</a></p>
            <p class="small-note">Credenciais definidas em <code>config.php</code>.</p>
            <p><a href="index.php">Voltar para o blog</a></p>
        </section>
    </main>
</body>
</html>
