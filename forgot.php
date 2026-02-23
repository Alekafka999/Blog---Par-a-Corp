<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

if (blog_is_logged_in()) {
    blog_redirect('admin.php');
}

$errors = [];
$success = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim((string) ($_POST['username'] ?? ''));
    $username = blog_normalize_username($usernameInput);

    if ($username === '') {
        $errors[] = 'Informe o nome de usuario.';
    } else {
        $users = blog_load_users();
        $targetType = '';
        $targetId = '';

        if ($username === blog_normalize_username(BLOG_ADMIN_USER)) {
            $targetType = 'admin';
            $targetId = 'admin';
        } else {
            $user = blog_find_user_by_username($users, $username);
            if ($user !== null) {
                $targetType = 'user';
                $targetId = (string) ($user['id'] ?? '');
            }
        }

        if ($targetType === '' || $targetId === '') {
            $errors[] = 'Usuario nao encontrado.';
        } else {
            $tokenData = blog_create_reset_token($targetType, $targetId, 1800);
            if ($tokenData === null) {
                $errors[] = 'Nao foi possivel gerar o link de recuperacao.';
            } else {
                $resetLink = 'reset.php?token=' . $tokenData['token'];
                $success = 'Link gerado com sucesso (expira em 30 minutos).';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha - <?= blog_e(BLOG_SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <main class="login-wrap">
        <section class="login-card">
            <h1>Recuperar senha</h1>
            <p class="muted">Gere um link de recuperacao para redefinir sua senha.</p>

            <?php if ($success !== ''): ?>
                <div class="alert success"><?= blog_e($success) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert error"><?= blog_e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" class="stack-form">
                <label>
                    Nome de usuario
                    <input type="text" name="username" required value="<?= blog_e((string) ($_POST['username'] ?? '')) ?>">
                </label>
                <button type="submit">Gerar link</button>
            </form>

            <?php if ($resetLink !== ''): ?>
                <div class="alert success">
                    Link de recuperacao: <a href="<?= blog_e($resetLink) ?>"><?= blog_e($resetLink) ?></a>
                </div>
            <?php endif; ?>

            <p class="small-note">Lembrei a senha: <a href="login.php">Voltar ao login</a></p>
            <p><a href="index.php">Voltar para o blog</a></p>
        </section>
    </main>
</body>
</html>