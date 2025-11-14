<?php

declare(strict_types=1);

class RedirectService
{
    public function __construct(private LinkService $links)
    {
    }

    public function handle(string $slug, array $query, array $server): void
    {
        $link = $this->links->findBySlug($slug);
        if (!$link || (int) $link['is_active'] !== 1) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Shortlink not found';
            return;
        }

        $targets = $this->links->activeTargets((int) $link['id']);
        $chosen = $this->pickTarget($targets);
        $targetUrl = $chosen ? $chosen['target_url'] : $link['default_url'];
        $targetId = $chosen ? (int) $chosen['id'] : null;

        $utm = parse_utm_from_array($query);
        $redirectUrl = merge_utm_into_url($targetUrl, $utm);

        $ip = get_client_ip($server);
        $ua = $server['HTTP_USER_AGENT'] ?? '';
        $referrer = $server['HTTP_REFERER'] ?? '';
        $acceptLang = $server['HTTP_ACCEPT_LANGUAGE'] ?? '';

        $this->recordClick($link, $slug, $targetId, $ip, $ua, $referrer, $acceptLang, $utm);

        $config = app_config();
        header('Cache-Control: no-store');
        http_response_code($config['redirect_code'] ?: 302);
        header('Location: ' . $redirectUrl);
    }

    private function pickTarget(array $targets): ?array
    {
        $active = array_filter($targets, static function (array $target): bool {
            return (int) $target['is_active'] === 1 && (int) $target['weight'] > 0;
        });
        if (empty($active)) {
            return null;
        }
        $sum = array_sum(array_map(static fn ($t) => (int) $t['weight'], $active));
        if ($sum <= 0) {
            return null;
        }
        $rand = random_int(1, $sum);
        foreach ($active as $target) {
            $rand -= (int) $target['weight'];
            if ($rand <= 0) {
                return $target;
            }
        }
        return array_values($active)[0];
    }

    private function recordClick(array $link, string $slug, ?int $targetId, string $ip, string $ua, string $referrer, string $acceptLang, array $utm): void
    {
        $config = app_config();
        $limit = $config['rate_limit_per_min'] ?? 0;
        if ($limit > 0 && !$this->consumeRateLimit($ip, $limit)) {
            return;
        }
        $stmt = db()->prepare('INSERT INTO clicks (link_id, slug, target_id, ip, ua, referrer, accept_lang, utm_source, utm_medium, utm_campaign, utm_content, utm_term) VALUES (:link_id, :slug, :target_id, :ip, :ua, :referrer, :accept_lang, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term)');
        $stmt->execute([
            ':link_id' => (int) $link['id'],
            ':slug' => $slug,
            ':target_id' => $targetId,
            ':ip' => $ip,
            ':ua' => $ua,
            ':referrer' => $referrer,
            ':accept_lang' => $acceptLang,
            ':utm_source' => $utm['utm_source'] ?? null,
            ':utm_medium' => $utm['utm_medium'] ?? null,
            ':utm_campaign' => $utm['utm_campaign'] ?? null,
            ':utm_content' => $utm['utm_content'] ?? null,
            ':utm_term' => $utm['utm_term'] ?? null,
        ]);
    }

    private function consumeRateLimit(string $ip, int $limit): bool
    {
        $bucket = (int) floor(time() / 60);
        $file = storage_path('cache/ratelimit_' . sha1(env('APP_SALT', '') . $ip) . '.json');
        $count = 0;
        if (file_exists($file)) {
            $content = json_decode((string) file_get_contents($file), true);
            if (is_array($content) && isset($content['bucket'], $content['count'])) {
                if ((int) $content['bucket'] === $bucket) {
                    $count = (int) $content['count'];
                }
            }
        }
        if ($count >= $limit) {
            return false;
        }
        $count++;
        file_put_contents($file, json_encode(['bucket' => $bucket, 'count' => $count]));
        return true;
    }
}
