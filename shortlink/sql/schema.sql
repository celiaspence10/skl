-- 用户（后台登录）
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 短链主表
CREATE TABLE IF NOT EXISTS links (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(32) NOT NULL UNIQUE,
  title VARCHAR(255) DEFAULT NULL,
  default_url TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active),
  INDEX idx_created_at (created_at),
  CONSTRAINT fk_links_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 分流目标
CREATE TABLE IF NOT EXISTS link_targets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  link_id BIGINT NOT NULL,
  target_url TEXT NOT NULL,
  weight INT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_targets_link FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
  INDEX idx_link_active (link_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 点击日志
CREATE TABLE IF NOT EXISTS clicks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  link_id BIGINT NOT NULL,
  slug VARCHAR(32) NOT NULL,
  target_id BIGINT DEFAULT NULL,
  ip VARCHAR(64) DEFAULT NULL,
  ua TEXT DEFAULT NULL,
  referrer TEXT DEFAULT NULL,
  accept_lang VARCHAR(255) DEFAULT NULL,
  utm_source VARCHAR(128) DEFAULT NULL,
  utm_medium VARCHAR(128) DEFAULT NULL,
  utm_campaign VARCHAR(128) DEFAULT NULL,
  utm_content VARCHAR(128) DEFAULT NULL,
  utm_term VARCHAR(128) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_link_time (link_id, created_at),
  INDEX idx_slug_time (slug, created_at),
  INDEX idx_utm (utm_source, utm_campaign, utm_content),
  CONSTRAINT fk_clicks_link FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
  CONSTRAINT fk_clicks_target FOREIGN KEY (target_id) REFERENCES link_targets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 默认管理员（admin / Admin@123）
INSERT INTO users (username, password_hash)
VALUES ('admin', '$2y$10$gQv2aY8e0e0yPpHjHkQv2e4Lz0H1Sxv4w2Q4Z9a0qf7mJkqVQ2Lse');

-- 示例数据（可选）
INSERT INTO links (slug, title, default_url, is_active)
VALUES ('viBU4P', 'WhatsApp 轮询', 'https://wa.me/19995550123', 1);
INSERT INTO link_targets (link_id, target_url, weight, is_active)
  SELECT id, 'https://wa.me/19995550123?text=Hi', 1, 1 FROM links WHERE slug='viBU4P';
INSERT INTO link_targets (link_id, target_url, weight, is_active)
  SELECT id, 'https://wa.me/19995550124?text=Hi', 3, 1 FROM links WHERE slug='viBU4P';
