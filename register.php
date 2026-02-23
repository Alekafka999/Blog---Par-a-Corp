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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $usernameRaw = trim((string) ($_POST['username'] ?? ''));
    $username = blog_normalize_username($usernameRaw);
    $nickname = trim((string) ($_POST['nickname'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $email = trim((string) ($_POST['email'] ?? ''));
    $whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));

    if ($name === '') {
        $errors[] = 'Nome e obrigatorio.';
    }

    if ($username === '') {
        $errors[] = 'Nome de usuario e obrigatorio.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,}$/', $username)) {
        $errors[] = 'Nome de usuario invalido. Use ao menos 3 caracteres (letras, numeros, ponto, underline ou hifen).';
    } elseif (blog_normalize_username(BLOG_ADMIN_USER) === $username) {
        $errors[] = 'Nome de usuario ja em uso.';
    }

    if ($nickname === '') {
        $errors[] = 'Nickname no blog e obrigatorio.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Senha deve ter pelo menos 6 caracteres.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email invalido.';
    }

    if ($whatsapp === '' || !preg_match('/^[0-9+()\s-]{6,}$/', $whatsapp)) {
        $errors[] = 'Whatsapp invalido.';
    }

    $users = blog_load_users();
    if ($errors === [] && blog_username_exists($users, $username)) {
        $errors[] = 'Nome de usuario ja em uso.';
    }

    if ($errors === []) {
        $users[] = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'username' => $username,
            'nickname' => $nickname,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!blog_save_users($users)) {
            $errors[] = 'Nao foi possivel salvar o cadastro.';
        } else {
            $success = 'Cadastro criado com sucesso. Voce ja pode entrar.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - <?= blog_e(BLOG_SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <main class="login-wrap">
        <section class="login-card">
            <h1>Cadastro</h1>
            <p class="muted">Crie seu usuario para acessar o painel.</p>

            <?php if ($success !== ''): ?>
                <div class="alert success"><?= blog_e($success) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert error"><?= blog_e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" class="stack-form">
                <label>
                    Nome
                    <input type="text" name="name" required value="<?= blog_e((string) ($_POST['name'] ?? '')) ?>">
                </label>
                <label>
                    Nome de usuario
                    <input type="text" name="username" required autocomplete="username" value="<?= blog_e((string) ($_POST['username'] ?? '')) ?>">
                </label>
                <label>
                    Nickname no blog
                    <input type="text" name="nickname" required value="<?= blog_e((string) ($_POST['nickname'] ?? '')) ?>">
                </label>
                <label>
                    Senha
                    <input type="password" name="password" required autocomplete="new-password">
                </label>
                <label>
                    Email
                    <input type="email" name="email" required value="<?= blog_e((string) ($_POST['email'] ?? '')) ?>">
                </label>
                <label>
                    Whatsapp
                    <input type="text" name="whatsapp" required value="<?= blog_e((string) ($_POST['whatsapp'] ?? '')) ?>">
                </label>
                <button type="submit">Criar cadastro</button>
            </form>

            <p class="small-note">Ja tem conta? <a href="login.php">Entrar</a></p>
            <p><a href="index.php">Voltar para o blog</a></p>
        </section>
    </main>
</body>
</html>