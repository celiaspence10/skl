<?php

declare(strict_types=1);

class LinkService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM links WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $link = $stmt->fetch();
        return $link ?: null;
    }

    public function create(array $payload, int $userId): array
    {
        $slug = $payload['slug'] ?? '';
        if ($slug === '') {
            $slug = $this->generateUniqueSlug();
        }
        if (!validate_slug($slug)) {
            throw new InvalidArgumentException('INVALID_SLUG');
        }
        if ($this->slugExists($slug)) {
            throw new InvalidArgumentException('SLUG_EXISTS');
        }
        $defaultUrl = trim($payload['default_url'] ?? '');
        if (!validate_url($defaultUrl)) {
            throw new InvalidArgumentException('INVALID_URL');
        }
        $title = trim($payload['title'] ?? '');

        $stmt = $this->pdo->prepare('INSERT INTO links (slug, title, default_url, is_active, created_by) VALUES (:slug, :title, :default_url, 1, :created_by)');
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title === '' ? null : $title,
            ':default_url' => $defaultUrl,
            ':created_by' => $userId,
        ]);

        return $this->get((int) $this->pdo->lastInsertId());
    }

    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $payload): array
    {
        $link = $this->get($id);
        if (!$link) {
            throw new InvalidArgumentException('NOT_FOUND');
        }
        $title = array_key_exists('title', $payload) ? trim((string) $payload['title']) : $link['title'];
        $defaultUrl = array_key_exists('default_url', $payload) ? trim((string) $payload['default_url']) : $link['default_url'];
        if (!validate_url($defaultUrl)) {
            throw new InvalidArgumentException('INVALID_URL');
        }
        $stmt = $this->pdo->prepare('UPDATE links SET title = :title, default_url = :default_url WHERE id = :id');
        $stmt->execute([
            ':title' => $title === '' ? null : $title,
            ':default_url' => $defaultUrl,
            ':id' => $id,
        ]);
        return $this->get($id);
    }

    public function toggle(int $id, int $isActive): array
    {
        $link = $this->get($id);
        if (!$link) {
            throw new InvalidArgumentException('NOT_FOUND');
        }
        $stmt = $this->pdo->prepare('UPDATE links SET is_active = :active WHERE id = :id');
        $stmt->execute([
            ':active' => $isActive ? 1 : 0,
            ':id' => $id,
        ]);
        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function list(int $page, int $size, ?string $query): array
    {
        [$offset, $limit] = pagination($page, $size);
        $params = [];
        $where = '';
        if ($query) {
            $where = 'WHERE slug LIKE :q OR title LIKE :q';
            $params[':q'] = '%' . $query . '%';
        }
        $stmt = $this->pdo->prepare("SELECT SQL_CALC_FOUND_ROWS l.*, 
            (SELECT COUNT(*) FROM clicks c WHERE c.link_id = l.id) AS clicks_total,
            (SELECT COUNT(*) FROM clicks c WHERE c.link_id = l.id AND DATE(c.created_at) = CURRENT_DATE()) AS clicks_today
            FROM links l $where ORDER BY l.created_at DESC LIMIT :offset, :limit");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        $total = (int) $this->pdo->query('SELECT FOUND_ROWS()')->fetchColumn();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'size' => $size];
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM links WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function generateUniqueSlug(): string
    {
        $config = app_config();
        $attempts = 0;
        do {
            $slug = generate_slug($config['default_slug_length']);
            $attempts++;
        } while ($this->slugExists($slug) && $attempts < 10);
        while ($this->slugExists($slug)) {
            $slug = generate_slug($config['default_slug_length'] + 1);
        }
        return $slug;
    }

    public function addTarget(int $linkId, array $payload): array
    {
        $link = $this->get($linkId);
        if (!$link) {
            throw new InvalidArgumentException('NOT_FOUND');
        }
        $targetUrl = trim($payload['target_url'] ?? '');
        if (!validate_url($targetUrl)) {
            throw new InvalidArgumentException('INVALID_URL');
        }
        $weight = isset($payload['weight']) ? (int) $payload['weight'] : 0;
        if ($weight < 0) {
            throw new InvalidArgumentException('INVALID_WEIGHT');
        }
        $stmt = $this->pdo->prepare('INSERT INTO link_targets (link_id, target_url, weight, is_active) VALUES (:link_id, :target_url, :weight, 1)');
        $stmt->execute([
            ':link_id' => $linkId,
            ':target_url' => $targetUrl,
            ':weight' => $weight,
        ]);
        return $this->getTarget((int) $this->pdo->lastInsertId());
    }

    public function getTarget(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM link_targets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listTargets(int $linkId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM link_targets WHERE link_id = :link_id ORDER BY created_at DESC');
        $stmt->execute([':link_id' => $linkId]);
        return $stmt->fetchAll();
    }

    public function updateTarget(int $id, array $payload): array
    {
        $target = $this->getTarget($id);
        if (!$target) {
            throw new InvalidArgumentException('NOT_FOUND');
        }
        $targetUrl = array_key_exists('target_url', $payload) ? trim((string) $payload['target_url']) : $target['target_url'];
        if (!validate_url($targetUrl)) {
            throw new InvalidArgumentException('INVALID_URL');
        }
        $weight = array_key_exists('weight', $payload) ? (int) $payload['weight'] : $target['weight'];
        if ($weight < 0) {
            throw new InvalidArgumentException('INVALID_WEIGHT');
        }
        $stmt = $this->pdo->prepare('UPDATE link_targets SET target_url = :target_url, weight = :weight WHERE id = :id');
        $stmt->execute([
            ':target_url' => $targetUrl,
            ':weight' => $weight,
            ':id' => $id,
        ]);
        return $this->getTarget($id);
    }

    public function toggleTarget(int $id, int $isActive): array
    {
        $target = $this->getTarget($id);
        if (!$target) {
            throw new InvalidArgumentException('NOT_FOUND');
        }
        $stmt = $this->pdo->prepare('UPDATE link_targets SET is_active = :active WHERE id = :id');
        $stmt->execute([
            ':active' => $isActive ? 1 : 0,
            ':id' => $id,
        ]);
        return $this->getTarget($id);
    }

    public function deleteTarget(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM link_targets WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function activeTargets(int $linkId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM link_targets WHERE link_id = :link_id AND is_active = 1');
        $stmt->execute([':link_id' => $linkId]);
        return $stmt->fetchAll();
    }

    public function statsOverview(): array
    {
        $totalClicks = (int) $this->pdo->query('SELECT COUNT(*) FROM clicks')->fetchColumn();
        $todayClicksStmt = $this->pdo->query('SELECT COUNT(*) FROM clicks WHERE DATE(created_at) = CURRENT_DATE()');
        $todayClicks = (int) $todayClicksStmt->fetchColumn();
        $activeLinks = (int) $this->pdo->query('SELECT COUNT(*) FROM links WHERE is_active = 1')->fetchColumn();
        return [
            'total_clicks' => $totalClicks,
            'today_clicks' => $todayClicks,
            'active_links' => $activeLinks,
        ];
    }

    public function statsByDay(int $days): array
    {
        $stmt = $this->pdo->prepare('SELECT DATE(created_at) AS day, COUNT(*) AS total FROM clicks WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY) GROUP BY day ORDER BY day ASC');
        $stmt->bindValue(':days', $days - 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['day']] = (int) $row['total'];
        }
        $result = [];
        $now = new DateTime();
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = clone $now;
            $day->modify("-$i day");
            $key = $day->format('Y-m-d');
            $result[] = ['day' => $key, 'total' => $map[$key] ?? 0];
        }
        return $result;
    }

    public function statsByTarget(?int $linkId = null): array
    {
        if ($linkId) {
            $stmt = $this->pdo->prepare('SELECT lt.id, lt.target_url, COUNT(c.id) AS total FROM link_targets lt LEFT JOIN clicks c ON c.target_id = lt.id WHERE lt.link_id = :link_id GROUP BY lt.id ORDER BY total DESC');
            $stmt->execute([':link_id' => $linkId]);
        } else {
            $stmt = $this->pdo->query('SELECT lt.id, lt.target_url, COUNT(c.id) AS total FROM link_targets lt LEFT JOIN clicks c ON c.target_id = lt.id GROUP BY lt.id ORDER BY total DESC');
        }
        return $stmt->fetchAll();
    }

    public function recentClicks(array $filters, int $page, int $size): array
    {
        [$offset, $limit] = pagination($page, $size);
        $where = [];
        $params = [];
        if (!empty($filters['slug'])) {
            $where[] = 'slug = :slug';
            $params[':slug'] = $filters['slug'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['utm_source'])) {
            $where[] = 'utm_source = :utm_source';
            $params[':utm_source'] = $filters['utm_source'];
        }
        if (!empty($filters['utm_content'])) {
            $where[] = 'utm_content = :utm_content';
            $params[':utm_content'] = $filters['utm_content'];
        }
        $whereSql = '';
        if ($where) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }
        $sql = "SELECT * FROM clicks $whereSql ORDER BY created_at DESC LIMIT :offset, :limit";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM clicks $whereSql");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'size' => $size];
    }
}
