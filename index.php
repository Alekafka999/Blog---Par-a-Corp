<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

$allPosts = blog_sort_posts(blog_load_posts(), 'asc');
$selectedDate = blog_valid_date_filter($_GET['date'] ?? null);
$selectedMonth = blog_valid_month_filter($_GET['month'] ?? null);

$posts = $allPosts;
if ($selectedDate !== null) {
    $posts = array_values(array_filter($allPosts, static function (array $post) use ($selectedDate): bool {
        $publishedAt = (string) ($post['published_at'] ?? '');
        return date('Y-m-d', strtotime($publishedAt) ?: 0) === $selectedDate;
    }));
}

$postsByDay = blog_posts_by_day($allPosts);
$postsByMonth = blog_posts_by_month($allPosts);
$calendar = blog_calendar_matrix($selectedMonth, $postsByDay);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= blog_e(BLOG_SITE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div>
            <h1><?= blog_e(BLOG_SITE_NAME) ?></h1>
            <p>Blog minimalista em PHP (sem banco de dados)</p>
        </div>
        <nav class="header-actions">
            <?php if (blog_is_logged_in()): ?>
                <a href="admin.php">Painel</a>
                <a href="logout.php">Sair</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="layout">
        <aside class="sidebar">
            <section class="card">
                <h2>Links úteis</h2>
                <ul class="link-list">
                    <li><a href="https://github.com/" target="_blank" rel="noopener">GitHub</a></li>
                    <li><a href="https://www.php.net/manual/pt_BR/" target="_blank" rel="noopener">Manual PHP</a></li>
                    <li><a href="https://developer.mozilla.org/pt-BR/" target="_blank" rel="noopener">MDN Web Docs</a></li>
                    <li><a href="https://laragon.org/" target="_blank" rel="noopener">Laragon</a></li>
                </ul>
            </section>

            <section class="card">
                <div class="calendar-head">
                    <h2>Calendário</h2>
                    <div class="calendar-nav">
                        <a href="?month=<?= blog_e($calendar['prev']) ?>">&lsaquo;</a>
                        <a href="?month=<?= blog_e(date('Y-m')) ?>">Hoje</a>
                        <a href="?month=<?= blog_e($calendar['next']) ?>">&rsaquo;</a>
                    </div>
                </div>
                <p class="calendar-label"><?= blog_e($calendar['label']) ?></p>
                <table class="calendar">
                    <thead>
                        <tr>
                            <th>Seg</th>
                            <th>Ter</th>
                            <th>Qua</th>
                            <th>Qui</th>
                            <th>Sex</th>
                            <th>Sáb</th>
                            <th>Dom</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendar['weeks'] as $week): ?>
                            <tr>
                                <?php foreach ($week as $cell): ?>
                                    <?php if ($cell === null): ?>
                                        <td class="empty"></td>
                                    <?php else: ?>
                                        <?php
                                        $classes = [];
                                        if ($cell['has_post']) {
                                            $classes[] = 'has-post';
                                        }
                                        if ($cell['is_today']) {
                                            $classes[] = 'today';
                                        }
                                        if ($selectedDate === $cell['date']) {
                                            $classes[] = 'selected';
                                        }
                                        ?>
                                        <td class="<?= blog_e(implode(' ', $classes)) ?>">
                                            <?php if ($cell['has_post']): ?>
                                                <a href="?date=<?= blog_e($cell['date']) ?>&month=<?= blog_e($calendar['month']) ?>" title="<?= (int) $cell['count'] ?> publicação(ões)">
                                                    <?= (int) $cell['day'] ?>
                                                </a>
                                            <?php else: ?>
                                                <span><?= (int) $cell['day'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($selectedDate !== null): ?>
                    <p class="small-note">
                        Filtro: <?= blog_e(blog_format_date($selectedDate)) ?>.
                        <a href="index.php?month=<?= blog_e($calendar['month']) ?>">Limpar</a>
                    </p>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Arquivo</h2>
                <?php if ($postsByMonth === []): ?>
                    <p class="muted">Ainda não há publicações.</p>
                <?php else: ?>
                    <ul class="link-list">
                        <?php foreach ($postsByMonth as $month => $count): ?>
                            <li>
                                <a href="?month=<?= blog_e($month) ?>"><?= blog_e(date('m/Y', strtotime($month . '-01') ?: time())) ?></a>
                                <span class="count"><?= (int) $count ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </aside>

        <main class="content">
            <?php if ($posts === []): ?>
                <section class="empty-state">
                    <h2>Nenhum artigo encontrado</h2>
                    <p>Faça login e publique o primeiro artigo no painel administrativo.</p>
                    <p><a class="button" href="login.php">Ir para login</a></p>
                </section>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post-card" id="post-<?= blog_e((string) ($post['id'] ?? '')) ?>">
                        <header>
                            <h2><?= blog_e((string) ($post['title'] ?? 'Sem título')) ?></h2>
                            <p class="post-meta">Publicado em <?= blog_e(blog_format_datetime((string) ($post['published_at'] ?? ''))) ?></p>
                        </header>

                        <?php if (!empty($post['image_path'])): ?>
                            <img
                                class="post-image"
                                src="<?= blog_e((string) $post['image_path']) ?>"
                                alt="<?= blog_e((string) ($post['title'] ?? 'Imagem do post')) ?>"
                                loading="lazy"
                            >
                        <?php endif; ?>

                        <div class="post-body">
                            <?= nl2br(blog_e((string) ($post['content'] ?? ''))) ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

