<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_name('simple_blog_session');
    session_start();
}

define('BLOG_SITE_NAME', 'Blog da Comunidade');
define('BLOG_DATA_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'data');
define('BLOG_POSTS_FILE', BLOG_DATA_DIR . DIRECTORY_SEPARATOR . 'posts.json');
define('BLOG_UPLOADS_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads');
define('BLOG_UPLOADS_WEB_PATH', 'uploads');

// Troque essas credenciais antes de publicar em produção.
define('BLOG_ADMIN_USER', 'admin');
define('BLOG_ADMIN_PASS', 'admin123');

