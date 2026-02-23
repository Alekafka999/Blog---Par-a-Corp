<?php
declare(strict_types=1);

function blog_ensure_storage(): void
{
    if (!is_dir(BLOG_DATA_DIR)) {
        mkdir(BLOG_DATA_DIR, 0775, true);
    }

    if (!is_dir(BLOG_UPLOADS_DIR)) {
        mkdir(BLOG_UPLOADS_DIR, 0775, true);
    }

    if (!file_exists(BLOG_POSTS_FILE)) {
        file_put_contents(BLOG_POSTS_FILE, "[]\n", LOCK_EX);
    }
}

function blog_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function blog_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function blog_load_posts(): array
{
    blog_ensure_storage();

    $raw = file_get_contents(BLOG_POSTS_FILE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function blog_save_posts(array $posts): bool
{
    blog_ensure_storage();
    $json = json_encode(array_values($posts), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(BLOG_POSTS_FILE, $json . PHP_EOL, LOCK_EX) !== false;
}

function blog_sort_posts(array $posts, string $direction = 'asc'): array
{
    usort($posts, static function (array $a, array $b) use ($direction): int {
        $timeA = strtotime((string) ($a['published_at'] ?? '')) ?: 0;
        $timeB = strtotime((string) ($b['published_at'] ?? '')) ?: 0;

        return $direction === 'desc' ? ($timeB <=> $timeA) : ($timeA <=> $timeB);
    });

    return $posts;
}

function blog_slugify(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return 'post';
    }

    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? 'post';
    $text = trim($text, '-');

    return $text !== '' ? $text : 'post';
}

function blog_format_date(string $value): string
{
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : $value;
}

function blog_format_datetime(string $value): string
{
    $ts = strtotime($value);
    return $ts ? date('d/m/Y H:i', $ts) : $value;
}

function blog_valid_date_filter(?string $value): ?string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value ? $value : null;
}

function blog_valid_month_filter(?string $value): string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}$/', $value)) {
        return date('Y-m');
    }

    $dt = DateTime::createFromFormat('Y-m', $value);
    return $dt && $dt->format('Y-m') === $value ? $value : date('Y-m');
}

function blog_posts_by_day(array $posts): array
{
    $map = [];
    foreach ($posts as $post) {
        $publishedAt = (string) ($post['published_at'] ?? '');
        $day = date('Y-m-d', strtotime($publishedAt) ?: time());
        if (!isset($map[$day])) {
            $map[$day] = 0;
        }
        $map[$day]++;
    }
    ksort($map);

    return $map;
}

function blog_posts_by_month(array $posts): array
{
    $map = [];
    foreach ($posts as $post) {
        $publishedAt = (string) ($post['published_at'] ?? '');
        $month = date('Y-m', strtotime($publishedAt) ?: time());
        if (!isset($map[$month])) {
            $map[$month] = 0;
        }
        $map[$month]++;
    }
    krsort($map);

    return $map;
}

function blog_calendar_matrix(string $month, array $postsByDay): array
{
    $start = DateTime::createFromFormat('Y-m-d', $month . '-01');
    if (!$start) {
        $start = new DateTime('first day of this month');
    }

    $year = (int) $start->format('Y');
    $monthNumber = (int) $start->format('n');
    $daysInMonth = (int) $start->format('t');
    $firstWeekday = (int) $start->format('N');

    $weeks = [];
    $week = [];

    for ($i = 1; $i < $firstWeekday; $i++) {
        $week[] = null;
    }

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateKey = sprintf('%04d-%02d-%02d', $year, $monthNumber, $day);
        $week[] = [
            'day' => $day,
            'date' => $dateKey,
            'has_post' => isset($postsByDay[$dateKey]),
            'count' => $postsByDay[$dateKey] ?? 0,
            'is_today' => $dateKey === date('Y-m-d'),
        ];

        if (count($week) === 7) {
            $weeks[] = $week;
            $week = [];
        }
    }

    if ($week !== []) {
        while (count($week) < 7) {
            $week[] = null;
        }
        $weeks[] = $week;
    }

    $prev = (clone $start)->modify('-1 month')->format('Y-m');
    $next = (clone $start)->modify('+1 month')->format('Y-m');

    return [
        'label' => $start->format('F Y'),
        'month' => $start->format('Y-m'),
        'prev' => $prev,
        'next' => $next,
        'weeks' => $weeks,
    ];
}

function blog_handle_image_upload(array $file, string $title): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [false, 'Envie uma imagem válida.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, 'Falha no upload da imagem.'];
    }

    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return [false, 'A imagem deve ter no máximo 5 MB.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        return [false, 'O arquivo enviado não é uma imagem.'];
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        return [false, 'Formato não suportado. Use JPG, PNG, GIF ou WEBP.'];
    }

    blog_ensure_storage();

    $filename = date('YmdHis') . '-' . blog_slugify($title) . '.' . $extension;
    $destination = BLOG_UPLOADS_DIR . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        return [false, 'Não foi possível salvar a imagem.'];
    }

    return [true, BLOG_UPLOADS_WEB_PATH . '/' . $filename];
}

