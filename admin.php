<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

blog_require_login();
blog_ensure_storage();

$errors = [];
$success = '';
$storageWarnings = blog_storage_warnings();

$posts = blog_load_posts();
$editingId = (string) ($_GET['edit'] ?? '');
$editingPost = null;

if ($editingId !== '') {
    foreach ($posts as $post) {
        if ((string) ($post['id'] ?? '') === $editingId) {
            $editingPost = $post;
            break;
        }
    }

    if ($editingPost === null) {
        $errors[] = 'Artigo nao encontrado para edicao.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'delete') {
        $deleteId = trim((string) ($_POST['id'] ?? ''));
        if ($deleteId === '') {
            $errors[] = 'ID do artigo ausente.';
        } else {
            $postsBefore = $posts;
            $found = false;

            foreach ($posts as $index => $post) {
                if ((string) ($post['id'] ?? '') === $deleteId) {
                    $found = true;
                    $imagePath = (string) ($post['image_path'] ?? '');
                    unset($posts[$index]);
                    $posts = array_values($posts);

                    if ($imagePath !== '' && strpos($imagePath, BLOG_UPLOADS_WEB_PATH . '/') === 0) {
                        $imageFile = BLOG_UPLOADS_DIR . DIRECTORY_SEPARATOR . basename($imagePath);
                        if (is_file($imageFile)) {
                            @unlink($imageFile);
                        }
                    }
                    break;
                }
            }

            if (!$found) {
                $errors[] = 'Artigo nao encontrado.';
            } elseif (!blog_save_posts($posts)) {
                $errors[] = 'Nao foi possivel excluir o artigo.';
                $posts = $postsBefore;
            } else {
                blog_redirect('admin.php?status=deleted');
            }
        }
    } else {
        $isUpdate = $action === 'update';
        $postId = trim((string) ($_POST['id'] ?? ''));
        $existingIndex = null;
        $existingPost = null;

        if ($isUpdate) {
            if ($postId === '') {
                $errors[] = 'ID do artigo ausente.';
            } else {
                foreach ($posts as $index => $post) {
                    if ((string) ($post['id'] ?? '') === $postId) {
                        $existingIndex = $index;
                        $existingPost = $post;
                        break;
                    }
                }
                if ($existingPost === null) {
                    $errors[] = 'Artigo nao encontrado para edicao.';
                }
            }
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $publishedInput = trim((string) ($_POST['published_at'] ?? ''));

        if ($title === '') {
            $errors[] = 'O titulo e obrigatorio.';
        }

        if ($content === '') {
            $errors[] = 'O texto do artigo e obrigatorio.';
        }

        $hasImage = !empty($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$isUpdate && !$hasImage) {
            $errors[] = 'A imagem do artigo e obrigatoria.';
        }

        if ($publishedInput !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $publishedInput);
            if ($dt) {
                $publishedAt = $dt->format('Y-m-d H:i:s');
            } else {
                $errors[] = 'Data/hora de publicacao invalida.';
                $publishedAt = date('Y-m-d H:i:s');
            }
        } elseif ($isUpdate && $existingPost !== null) {
            $publishedAt = (string) ($existingPost['published_at'] ?? date('Y-m-d H:i:s'));
        } else {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $imagePath = $isUpdate ? (string) ($existingPost['image_path'] ?? '') : '';

        if ($errors === [] && $hasImage) {
            [$ok, $result] = blog_handle_image_upload($_FILES['image'], $title);
            if (!$ok) {
                $errors[] = (string) $result;
            } else {
                if ($isUpdate && $imagePath !== '' && strpos($imagePath, BLOG_UPLOADS_WEB_PATH . '/') === 0) {
                    $oldFile = BLOG_UPLOADS_DIR . DIRECTORY_SEPARATOR . basename($imagePath);
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }
                $imagePath = (string) $result;
            }
        }

        if ($errors === []) {
            if ($isUpdate && $existingIndex !== null && $existingPost !== null) {
                $posts[$existingIndex] = array_merge($existingPost, [
                    'title' => $title,
                    'content' => $content,
                    'image_path' => $imagePath,
                    'published_at' => $publishedAt,
                ]);
            } else {
                $posts[] = [
                    'id' => bin2hex(random_bytes(8)),
                    'title' => $title,
                    'content' => $content,
                    'image_path' => $imagePath,
                    'published_at' => $publishedAt,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            if (!blog_save_posts($posts)) {
                $errors[] = $isUpdate ? 'Nao foi possivel atualizar o artigo.' : 'Nao foi possivel salvar o artigo.';
            } else {
                blog_redirect($isUpdate ? 'admin.php?status=updated' : 'admin.php?status=ok');
            }
        }
    }
}

if (($_GET['status'] ?? '') === 'ok') {
    $success = 'Artigo publicado com sucesso.';
} elseif (($_GET['status'] ?? '') === 'updated') {
    $success = 'Artigo atualizado com sucesso.';
} elseif (($_GET['status'] ?? '') === 'deleted') {
    $success = 'Artigo excluido com sucesso.';
}

$editMode = $editingPost !== null;
$formHeading = $editMode ? 'Editar artigo' : 'Novo artigo';
$formAction = $editMode ? 'update' : 'create';
$formButton = $editMode ? 'Salvar alteracoes' : 'Publicar artigo';
$formImageRequired = $editMode ? '' : 'required';
$formId = $editMode ? (string) ($editingPost['id'] ?? '') : '';

$formTitle = (string) ($_POST['title'] ?? ($editingPost['title'] ?? ''));
$formContent = (string) ($_POST['content'] ?? ($editingPost['content'] ?? ''));
$formPublished = (string) ($_POST['published_at'] ?? '');
if ($formPublished === '' && $editMode) {
    $ts = strtotime((string) ($editingPost['published_at'] ?? ''));
    if ($ts) {
        $formPublished = date('Y-m-d\TH:i', $ts);
    }
}

$adminPosts = blog_sort_posts($posts, 'desc');

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
            <p>Publique artigos com titulo, texto e imagem</p>
        </div>
        <nav class="header-actions">
            <a href="index.php">Ver blog</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="admin-layout">
        <section class="card">
            <div class="panel-title">
                <h2><?= blog_e($formHeading) ?></h2>
                <?php if ($editMode): ?>
                    <a href="admin.php">Cancelar edicao</a>
                <?php endif; ?>
            </div>

            <?php foreach ($storageWarnings as $warning): ?>
                <div class="alert error"><?= blog_e($warning) ?></div>
            <?php endforeach; ?>

            <?php if ($success !== ''): ?>
                <div class="alert success"><?= blog_e($success) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert error"><?= blog_e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" enctype="multipart/form-data" class="stack-form">
                <input type="hidden" name="action" value="<?= blog_e($formAction) ?>">
                <?php if ($editMode): ?>
                    <input type="hidden" name="id" value="<?= blog_e($formId) ?>">
                <?php endif; ?>

                <label>
                    Titulo
                    <input type="text" name="title" required value="<?= blog_e($formTitle) ?>">
                </label>

                <label>
                    Texto do artigo
                    <textarea name="content" rows="8" required><?= blog_e($formContent) ?></textarea>
                </label>

                <label>
                    Data/hora de publicacao (opcional)
                    <input type="datetime-local" name="published_at" value="<?= blog_e($formPublished) ?>">
                </label>

                <label>
                    Imagem
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" <?= $formImageRequired ?>>
                </label>

                <?php if ($editMode && !empty($editingPost['image_path'])): ?>
                    <p class="muted">Imagem atual: <?= blog_e((string) $editingPost['image_path']) ?></p>
                <?php endif; ?>

                <button type="submit"><?= blog_e($formButton) ?></button>
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
                            <div class="admin-post-info">
                                <h3><?= blog_e((string) ($post['title'] ?? 'Sem titulo')) ?></h3>
                                <p class="post-meta"><?= blog_e(blog_format_datetime((string) ($post['published_at'] ?? ''))) ?></p>
                                <p class="excerpt">
                                    <?= blog_e(mb_strimwidth((string) ($post['content'] ?? ''), 0, 140, '...')) ?>
                                </p>
                                <div class="admin-post-actions">
                                    <a href="admin.php?edit=<?= blog_e((string) ($post['id'] ?? '')) ?>">Editar</a>
                                    <form method="post" onsubmit="return confirm('Excluir este artigo?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= blog_e((string) ($post['id'] ?? '')) ?>">
                                        <button type="submit" class="link-button danger">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>