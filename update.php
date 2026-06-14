<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $_database, $modulname, $version, $plugin;

$modulname = 'news';
$version = isset($plugin['version']) ? (string)$plugin['version'] : ($version ?? '0.0.0');

require __DIR__ . '/install.php';

if (!function_exists('news_sql')) {
    function news_sql($value): string
    {
        return escape((string)$value);
    }
}

if (!function_exists('news_column_exists')) {
    function news_column_exists(string $table, string $column): bool
    {
        $res = safe_query("SHOW COLUMNS FROM `" . news_sql($table) . "` LIKE '" . news_sql($column) . "'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (news_column_exists('plugins_news', 'sort') && !news_column_exists('plugins_news', 'sort_order')) {
    safe_query("ALTER TABLE plugins_news CHANGE `sort` `sort_order` INT(11) NOT NULL DEFAULT 0");
}
if (!news_column_exists('plugins_news', 'category_id')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN category_id INT(11) DEFAULT NULL AFTER id");
}
if (!news_column_exists('plugins_news', 'slug')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN slug VARCHAR(255) NOT NULL DEFAULT '' AFTER title");
    safe_query("UPDATE plugins_news SET slug = CONCAT('news-', id) WHERE slug = '' OR slug IS NULL");
}
if (!news_column_exists('plugins_news', 'link')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN link VARCHAR(255) NOT NULL DEFAULT '' AFTER content");
}
if (!news_column_exists('plugins_news', 'link_name')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN link_name VARCHAR(255) NOT NULL DEFAULT '' AFTER link");
}
if (!news_column_exists('plugins_news', 'banner_image')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN banner_image VARCHAR(255) NOT NULL DEFAULT '' AFTER link_name");
}
if (!news_column_exists('plugins_news', 'sort_order')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN sort_order INT(11) NOT NULL DEFAULT 0 AFTER banner_image");
}
if (!news_column_exists('plugins_news', 'updated_at')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sort_order");
}
if (!news_column_exists('plugins_news', 'publish_at')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN publish_at DATETIME DEFAULT NULL AFTER updated_at");
    safe_query("UPDATE plugins_news SET publish_at = updated_at WHERE publish_at IS NULL");
}
if (!news_column_exists('plugins_news', 'immediate_release')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN immediate_release TINYINT(1) NOT NULL DEFAULT 0 AFTER publish_at");
}
if (!news_column_exists('plugins_news', 'userID')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN userID INT(11) NOT NULL DEFAULT 0 AFTER immediate_release");
}
if (!news_column_exists('plugins_news', 'is_active')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER userID");
}
if (!news_column_exists('plugins_news', 'topnews_is_active')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN topnews_is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
}
if (!news_column_exists('plugins_news', 'views')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN views INT(11) NOT NULL DEFAULT 0 AFTER topnews_is_active");
}
if (!news_column_exists('plugins_news', 'allow_comments')) {
    safe_query("ALTER TABLE plugins_news ADD COLUMN allow_comments TINYINT(1) NOT NULL DEFAULT 0 AFTER views");
}

if (!function_exists('news_install_extract_lang')) {
    function news_install_extract_lang(string $text, string $lang): string
    {
        if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
            return trim((string)$m[1]);
        }
        if ($lang === 'gb' && preg_match('/\[\[lang:en\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
            return trim((string)$m[1]);
        }
        if ($lang === 'en' && preg_match('/\[\[lang:gb\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
            return trim((string)$m[1]);
        }
        if (preg_match('/\[\[lang:[a-z]{2}\]\]/i', $text)) {
            return '';
        }
        return trim($text);
    }
}

$langs = ['de', 'en', 'it'];
$resNews = safe_query("SELECT id, title, content, link_name FROM plugins_news");
if ($resNews) {
    while ($row = mysqli_fetch_assoc($resNews)) {
        $newsId = (int)($row['id'] ?? 0);
        if ($newsId <= 0) {
            continue;
        }
        foreach ($langs as $iso) {
            $isoEsc = mysqli_real_escape_string($_database, $iso);
            $titleEsc = mysqli_real_escape_string($_database, news_install_extract_lang((string)($row['title'] ?? ''), $iso));
            $contentEsc = mysqli_real_escape_string($_database, news_install_extract_lang((string)($row['content'] ?? ''), $iso));
            $linkNameEsc = mysqli_real_escape_string($_database, news_install_extract_lang((string)($row['link_name'] ?? ''), $iso));
            safe_query("INSERT INTO plugins_news_lang (content_key, language, content, updated_at)
                        VALUES ('news_{$newsId}_title', '{$isoEsc}', '{$titleEsc}', NOW())
                        ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()");
            safe_query("INSERT INTO plugins_news_lang (content_key, language, content, updated_at)
                        VALUES ('news_{$newsId}_content', '{$isoEsc}', '{$contentEsc}', NOW())
                        ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()");
            safe_query("INSERT INTO plugins_news_lang (content_key, language, content, updated_at)
                        VALUES ('news_{$newsId}_link_name', '{$isoEsc}', '{$linkNameEsc}', NOW())
                        ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=NOW()");
        }
    }
}
