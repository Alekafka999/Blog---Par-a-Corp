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
$token = trim((string) ($_GET['token'] ?? ($_POST['token'] ?? '')));
$tokenData = $token !== '' ? blog_find_reset_token($token) : null;

if ($token === '' || $tokenData === null) {
    $errors[] = 'Token invalido ou expirado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenData !== null) {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($password) < 6) {
        $errors[] = 'Senha deve ter pelo menos 6 caracteres.';
    }

    if ($password !== $confirm) {
        $errors[] = 'As senhas nao conferem.';
    }

    if ($errors === []) {
        $type = (string) ($tokenData['type'] ?? '');
        $id = (string) ($tokenData['id'] ?? '');

        if ($type === 'admin' && $id === 'admin') {
            $ok = blog_save_admin_credentials([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$ok) {
                $errors[] = 'Nao foi possivel atualizar a senha do admin.';
            }
        } elseif ($type === 'user') {
            $users = blog_load_users();
            $updated = false;

            foreach ($users as $index => $user) {
                if ((string) ($user['id'] ?? '') === $id) {
                    $users[$index]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $users[$index]['updated_at'] = date('Y-m-d H:i:s');
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $errors[] = 'Usuario nao encontrado.';
            } elseif (!blog_save_users($users)) {
                $errors[] = 'Nao foi possivel atualizar a senha.';
            }
        } else {
            $errors[] = 'Token invalido.';
        }

        if ($errors === []) {
            blog_consume_reset_token($token);
            $success = 'Senha atualizada com sucesso. Voce ja pode entrar.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir senha - <?= blog_e(BLOG_SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <main class="login-wrap">
        <section class="login-card">
            <h1>Redefinir senha</h1>
            <p class="muted">Defina uma nova senha para sua conta.</p>

            <?php if ($success !== ''): ?>
                <div class="alert success"><?= blog_e($success) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert error"><?= blog_e($error) ?></div>
            <?php endforeach; ?>

            <?php if ($tokenData !== null): ?>
                <form method="post" class="stack-form">
                    <input type="hidden" name="token" value="<?= blog_e($token) ?>">
                    <label>
                        Nova senha
                        <input type="password" name="password" required autocomplete="new-password">
                    </label>
                    <label>
                        Confirmar senha
                        <input type="password" name="confirm_password" required autocomplete="new-password">
                    </label>
                    <button type="submit">Atualizar senha</button>
                </form>
            <?php endif; ?>

            <p class="small-note">Voltar ao <a href="login.php">login</a></p>
            <p><a href="index.php">Voltar para o blog</a></p>
        </section>
    </main>
</body>
</html>