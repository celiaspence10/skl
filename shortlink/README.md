# ShortLink 短链跳转与分流系统

基于 PHP 8 + MySQL 的广告短链管理系统，实现短链生成、多目标权重分流、UTM 记录、点击日志、Tailwind 风格 Dashboard、CSV 导出、登录鉴权、CSRF 防护与速率限制等功能。所有逻辑符合《项目开发指南（TXT）v1.5》要求，可直接部署上线。

## 功能特性
- **短链管理**：支持自定义/自动生成 slug，校验长度与字符集，保留关键字保护。
- **多目标分流**：按权重进行随机命中（AB 测试），并支持目标启停、编辑、删除。
- **跳转追踪**：记录 IP、UA、Referrer、Accept-Language、UTM、命中目标等信息，并提供每分钟速率限制。
- **UTM 合并**：保留目标已有参数，仅补充缺失的 utm 字段。
- **统计面板**：KPI 卡片、近 7/30 天折线图、目标占比饼图、短链列表、访问明细筛选。
- **导出 CSV**：遵循筛选条件输出 UTF-8 带 BOM 的 CSV，并防止 CSV 注入。
- **安全机制**：password_hash、会话安全、CSRF Token 校验、PDO 预处理、robots 禁止后台抓取。

## 目录结构
```
shortlink/
├─ public/
│  ├─ index.php
│  ├─ admin.php
│  ├─ robots.txt
│  └─ assets/
│     ├─ app.js
│     ├─ app.css
│     └─ chart.umd.js
├─ app/
│  ├─ bootstrap.php
│  ├─ helpers.php
│  ├─ auth.php
│  ├─ LinkService.php
│  ├─ RedirectService.php
│  └─ ExportService.php
├─ config/
│  ├─ config.php
│  └─ database.php
├─ sql/
│  └─ schema.sql
├─ storage/
│  ├─ logs/
│  └─ cache/
├─ .env
└─ README.md
```

## 环境变量示例（`.env`）
```
APP_ENV=prod
APP_DEBUG=false
APP_URL=https://scc.us.cc
APP_NAME="ShortLink"

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=scc
DB_USER=scc
DB_PASS=WwpyffKNKYAiW5ep

APP_SALT=K7pX9sRbT2yQ4vLmN8hC5wDzU3

RATE_LIMIT_PER_MIN=120

REDIRECT_CODE=302
```

## 数据库初始化
导入 `sql/schema.sql`，自动创建表结构、默认管理员（admin / Admin@123）及示例数据。

## Nginx 配置示例
```
server {
    listen 80;
    server_name scc.us.cc;
    root /www/wwwroot/scc.us.cc/public;
    index index.php;

    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";

    location = /admin { return 302 /admin.php; }

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/tmp/php-cgi.sock; # 按 PHP 版本调整
    }
}
```

## 部署步骤
1. 在宝塔创建站点 `scc.us.cc`，运行目录指向 `/public`。
2. 新建数据库 `scc`（用户 `scc` / 密码 `WwpyffKNKYAiW5ep`）。
3. 上传项目至 `/www/wwwroot/scc.us.cc/`。
4. 导入 `sql/schema.sql`。
5. 配置 `.env`（参考上方示例，生产环境可调整）。
6. 应用上述 Nginx 配置并重载服务。
7. 访问 `http://scc.us.cc/admin` 登录后台（admin / Admin@123），首次登录后请立即修改密码。
8. 创建短链并添加目标，验证跳转、统计、导出等功能。

## 开发说明
- PHP 版本 ≥ 8.0，需启用 pdo、pdo_mysql、mbstring、openssl、json、curl 等扩展。
- 默认时区：Asia/Phnom_Penh。
- 会话安全：HttpOnly、SameSite=Lax，HTTPS 环境自动启用 Secure。
- 所有数据库操作使用 PDO 预处理，防 SQL 注入。
- `storage/cache` 用于点击写入速率限制，`storage/logs` 存放错误日志。
- 前端优先加载 Tailwind/Chart.js CDN，失败时回退到本地 `app.css` 与 `chart.umd.js`。

## 验收要点
- 登录/登出、CSRF 校验、权限拦截均正常。
- 短链创建、slug 校验、分流权重、UTM 合并与跳转记录符合要求。
- Dashboard 图表、列表与筛选交互可用，CSV 导出格式正确。
- 速率限制生效：超过阈值时仍跳转但不写入日志。

更多扩展建议：可引入 Redis 队列、多角色权限、历史数据归档等（详见项目指南）。
