<?php

declare(strict_types=1);

class ExportService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function export(array $filters): array
    {
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
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT created_at, slug, link_id, target_id, ip, referrer, ua, utm_source, utm_medium, utm_campaign, utm_content, utm_term FROM clicks $whereSql ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $data = [];
        $data[] = ['time','slug','link_id','target_id','ip','referrer','ua','utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
        foreach ($rows as $row) {
            $data[] = [
                format_datetime($row['created_at']),
                sanitize_csv_cell((string) $row['slug']),
                (string) $row['link_id'],
                $row['target_id'] !== null ? (string) $row['target_id'] : '',
                sanitize_csv_cell((string) $row['ip']),
                sanitize_csv_cell((string) ($row['referrer'] ?? '')),
                sanitize_csv_cell((string) ($row['ua'] ?? '')),
                sanitize_csv_cell((string) ($row['utm_source'] ?? '')),
                sanitize_csv_cell((string) ($row['utm_medium'] ?? '')),
                sanitize_csv_cell((string) ($row['utm_campaign'] ?? '')),
                sanitize_csv_cell((string) ($row['utm_content'] ?? '')),
                sanitize_csv_cell((string) ($row['utm_term'] ?? '')),
            ];
        }
        return $data;
    }
}
