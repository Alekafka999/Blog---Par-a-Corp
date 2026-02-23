<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

blog_require_login();
blog_ensure_storage();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));
    $publishedInput = trim((string) ($_POST['published_at'] ?? ''));

    if ($title === '') {
        $errors[] = 'O título é obrigatório.';
    }

    if ($content === '') {
        $errors[] = 'O texto do artigo é obrigatório.';
    }

    if (empty($_FILES['image']) || (int) $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'A imagem do artigo é obrigatória.';
    }

    $publishedAt = date('Y-m-d H:i:s');
    if ($publishedInput !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $publishedInput);
        if ($dt) {
            $publishedAt = $dt->format('Y-m-d H:i:s');
        } else {
            $errors[] = 'Data/hora de publicação inválida.';
        }
    }

    $imagePath = '';
    if ($errors === []) {
        [$ok, $result] = blog_handle_image_upload($_FILES['image'], $title);
        if (!$ok) {
            $errors[] = (string) $result;
        } else {
            $imagePath = (string) $result;
        }
    }

    if ($errors === []) {
        $posts = blog_load_posts();
        $posts[] = [
            'id' => bin2hex(random_bytes(8)),
            'title' => $title,
            'content' => $content,
            'image_path' => $imagePath,
            'published_at' => $publishedAt,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!blog_save_posts($posts)) {
            $errors[] = 'Não foi possível salvar o artigo.';
        } else {
            blog_redirect('admin.php?status=ok');
        }
    }
}

if (($_GET['status'] ?? '') === 'ok') {
    $success = 'Artigo publicado com sucesso.';
}

$adminPosts = blog_sort_posts(blog_load_posts(), 'desc');

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(string $string, int $start, int $width, string $trimMarker = ''): string
    {
        $slice = substr($string, $start, $width);
        if (strlen($string) > $width) {
            return rtrim($slice) . $trimMarker;
        }

        return $slice;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel - <?= blog_e(BLOG_SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div>
            <h1>Painel administrativo</h1>
            <p>Publique artigos com título, texto e imagem</p>
        </div>
        <nav class="header-actions">
            <a href="index.php">Ver blog</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="admin-layout">
        <section class="card">
            <h2>Novo artigo</h2>

            <?php if ($success !== ''): ?>
                <div class="alert success"><?= blog_e($success) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert error"><?= blog_e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" enctype="multipart/form-data" class="stack-form">
                <label>
                    Título
                    <input type="text" name="title" required value="<?= blog_e((string) ($_POST['title'] ?? '')) ?>">
                </label>

                <label>
                    Texto do artigo
                    <textarea name="content" rows="8" required><?= blog_e((string) ($_POST['content'] ?? '')) ?></textarea>
                </label>

                <label>
                    Data/hora de publicação (opcional)
                    <input type="datetime-local" name="published_at" value="<?= blog_e((string) ($_POST['published_at'] ?? '')) ?>">
                </label>

                <label>
                    Imagem
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" required>
                </label>

                <button type="submit">Publicar artigo</button>
            </form>
        </section>

        <section class="card">
            <div class="panel-title">
                <h2>Artigos publicados</h2>
                <span><?= count($adminPosts) ?> item(ns)</span>
            </div>

            <?php if ($adminPosts === []): ?>
                <p class="muted">Nenhum artigo publicado ainda.</p>
            <?php else: ?>
                <div class="admin-post-list">
                    <?php foreach ($adminPosts as $post): ?>
                        <article class="admin-post-item">
                            <?php if (!empty($post['image_path'])): ?>
                                <img src="<?= blog_e((string) $post['image_path']) ?>" alt="" loading="lazy">
                            <?php endif; ?>
                            <div>
                                <h3><?= blog_e((string) ($post['title'] ?? 'Sem título')) ?></h3>
                                <p class="post-meta"><?= blog_e(blog_format_datetime((string) ($post['published_at'] ?? ''))) ?></p>
                                <p class="excerpt">
                                    <?= blog_e(mb_strimwidth((string) ($post['content'] ?? ''), 0, 140, '...')) ?>
                                </p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>

