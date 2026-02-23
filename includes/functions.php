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

    if (!file_exists(BLOG_USERS_FILE)) {
        file_put_contents(BLOG_USERS_FILE, "[]\n", LOCK_EX);
    }

    if (!file_exists(BLOG_RESET_TOKENS_FILE)) {
        file_put_contents(BLOG_RESET_TOKENS_FILE, "[]\n", LOCK_EX);
    }

    if (!file_exists(BLOG_ADMIN_FILE)) {
        file_put_contents(BLOG_ADMIN_FILE, "{}\n", LOCK_EX);
    }
}

function blog_storage_warnings(): array
{
    blog_ensure_storage();

    $warnings = [];

    if (!is_writable(BLOG_DATA_DIR)) {
        $warnings[] = 'A pasta data/ nao esta com permissao de escrita.';
    }

    if (!file_exists(BLOG_POSTS_FILE) || !is_writable(BLOG_POSTS_FILE)) {
        $warnings[] = 'O arquivo data/posts.json nao esta com permissao de escrita.';
    }

    if (!file_exists(BLOG_USERS_FILE) || !is_writable(BLOG_USERS_FILE)) {
        $warnings[] = 'O arquivo data/users.json nao esta com permissao de escrita.';
    }

    if (!file_exists(BLOG_RESET_TOKENS_FILE) || !is_writable(BLOG_RESET_TOKENS_FILE)) {
        $warnings[] = 'O arquivo data/reset_tokens.json nao esta com permissao de escrita.';
    }

    if (!file_exists(BLOG_ADMIN_FILE) || !is_writable(BLOG_ADMIN_FILE)) {
        $warnings[] = 'O arquivo data/admin.json nao esta com permissao de escrita.';
    }

    if (!is_writable(BLOG_UPLOADS_DIR)) {
        $warnings[] = 'A pasta uploads/ nao esta com permissao de escrita.';
    }

    return $warnings;
}

function blog_load_reset_tokens(): array
{
    blog_ensure_storage();

    $raw = file_get_contents(BLOG_RESET_TOKENS_FILE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function blog_save_reset_tokens(array $tokens): bool
{
    blog_ensure_storage();
    $json = json_encode(array_values($tokens), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(BLOG_RESET_TOKENS_FILE, $json . PHP_EOL, LOCK_EX) !== false;
}

function blog_create_reset_token(string $type, string $id, int $ttlSeconds = 1800): ?array
{
    $token = bin2hex(random_bytes(16));
    $expiresAt = time() + $ttlSeconds;

    $tokens = blog_load_reset_tokens();
    $tokens = array_filter($tokens, static function (array $item) use ($type, $id): bool {
        return !isset($item['type'], $item['id']) || $item['type'] !== $type || $item['id'] !== $id;
    });

    $tokens[] = [
        'token' => $token,
        'type' => $type,
        'id' => $id,
        'expires_at' => $expiresAt,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    if (!blog_save_reset_tokens($tokens)) {
        return null;
    }

    return ['token' => $token, 'expires_at' => $expiresAt];
}

function blog_find_reset_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $now = time();
    $tokens = blog_load_reset_tokens();
    $valid = [];
    $found = null;

    foreach ($tokens as $item) {
        $expiresAt = (int) ($item['expires_at'] ?? 0);
        if ($expiresAt > $now) {
            $valid[] = $item;
            if ((string) ($item['token'] ?? '') === $token) {
                $found = $item;
            }
        }
    }

    if (count($valid) !== count($tokens)) {
        blog_save_reset_tokens($valid);
    }

    return $found;
}

function blog_consume_reset_token(string $token): bool
{
    $tokens = blog_load_reset_tokens();
    $remaining = [];
    $consumed = false;

    foreach ($tokens as $item) {
        if (!$consumed && (string) ($item['token'] ?? '') === $token) {
            $consumed = true;
            continue;
        }
        $remaining[] = $item;
    }

    if ($consumed) {
        blog_save_reset_tokens($remaining);
    }

    return $consumed;
}

function blog_load_admin_credentials(): array
{
    blog_ensure_storage();
    $raw = file_get_contents(BLOG_ADMIN_FILE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function blog_save_admin_credentials(array $data): bool
{
    blog_ensure_storage();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(BLOG_ADMIN_FILE, $json . PHP_EOL, LOCK_EX) !== false;
}

function blog_load_users(): array
{
    blog_ensure_storage();

    $raw = file_get_contents(BLOG_USERS_FILE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function blog_save_users(array $users): bool
{
    blog_ensure_storage();
    $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(BLOG_USERS_FILE, $json . PHP_EOL, LOCK_EX) !== false;
}

function blog_normalize_username(string $username): string
{
    return strtolower(trim($username));
}

function blog_find_user_by_username(array $users, string $username): ?array
{
    $needle = blog_normalize_username($username);
    if ($needle === '') {
        return null;
    }

    foreach ($users as $user) {
        if (blog_normalize_username((string) ($user['username'] ?? '')) === $needle) {
            return $user;
        }
    }

    return null;
}

function blog_username_exists(array $users, string $username): bool
{
    return blog_find_user_by_username($users, $username) !== null;
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
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        return [false, blog_upload_error_message($errorCode)];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, 'Falha no upload da imagem.'];
    }

    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return [false, 'A imagem deve ter no maximo 5 MB.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        return [false, 'O arquivo enviado nao e uma imagem.'];
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        return [false, 'Formato nao suportado. Use JPG, PNG, GIF ou WEBP.'];
    }

    blog_ensure_storage();

    if (!is_writable(BLOG_UPLOADS_DIR)) {
        return [false, 'A pasta uploads/ nao esta com permissao de escrita.'];
    }

    $filename = date('YmdHis') . '-' . blog_slugify($title) . '.' . $extension;
    $destination = BLOG_UPLOADS_DIR . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        return [false, 'Nao foi possivel salvar a imagem.'];
    }

    return [true, BLOG_UPLOADS_WEB_PATH . '/' . $filename];
}

function blog_upload_error_message(int $errorCode): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'A imagem excede o tamanho maximo permitido.';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload incompleto. Tente novamente.';
        case UPLOAD_ERR_NO_FILE:
            return 'Selecione uma imagem para enviar.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Pasta temporaria do servidor indisponivel.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Falha ao gravar a imagem no servidor.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload bloqueado por extensao do PHP.';
        default:
            return 'Falha no upload da imagem.';
    }
}
