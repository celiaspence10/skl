<?php
return [
    'app_name' => getenv('APP_NAME') ?: 'ShortLink',
    'default_slug_length' => 6,
    'slug_min_length' => 3,
    'slug_max_length' => 20,
    'reserved_slugs' => [
        'admin','admin.php','assets','index.php','healthz','robots.txt','favicon.ico','api','login','logout'
    ],
    'redirect_code' => (int) (getenv('REDIRECT_CODE') ?: 302),
    'rate_limit_per_min' => (int) (getenv('RATE_LIMIT_PER_MIN') ?: 0),
];
