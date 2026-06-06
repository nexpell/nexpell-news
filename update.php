<?php
global $str, $modulname, $version, $_database;

$modulname = 'news';
$version = '1.0';
$str = 'News';

echo "<div class='card'><div class='card-header'>{$str} Database Update</div><div class='card-body'>";

if (!function_exists('news_column_exists')) {
    function news_column_exists(string $table, string $column): bool
    {
        $res = safe_query("SHOW COLUMNS FROM `$table` LIKE '" . escape($column) . "'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

safe_query("
CREATE TABLE IF NOT EXISTS plugins_news (
  id int(11) NOT NULL AUTO_INCREMENT,
  category_id int(11) DEFAULT NULL,
  title varchar(255) NOT NULL DEFAULT '',
  slug varchar(255) NOT NULL,
  content text NOT NULL,
  link varchar(255) NOT NULL DEFAULT '',
  link_name varchar(255) NOT NULL DEFAULT '',
  banner_image varchar(255) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  publish_at DATETIME DEFAULT NULL,
  immediate_release tinyint(1) NOT NULL DEFAULT 0,
  userID int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 0,
  topnews_is_active tinyint(1) NOT NULL DEFAULT 0,
  views int(11) NOT NULL DEFAULT 0,
  allow_comments tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug),
  KEY category_id (category_id),
  KEY idx_publish_at (publish_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

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

safe_query("
CREATE TABLE IF NOT EXISTS plugins_news_lang (
  id int(11) NOT NULL AUTO_INCREMENT,
  content_key varchar(80) NOT NULL,
  language char(2) NOT NULL,
  content mediumtext NOT NULL,
  updated_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uniq_content_lang (content_key, language),
  KEY idx_content_key (content_key),
  KEY idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe_query("
CREATE TABLE IF NOT EXISTS plugins_news_categories (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL DEFAULT '',
  slug varchar(255) NOT NULL,
  description text NOT NULL,
  image varchar(255) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe_query("
    INSERT IGNORE INTO settings_plugins
        (pluginID, modulname, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar)
    VALUES
        ('', 'news', 'admin_news', 1, 'T-Seven', 'https://www.nexpell.de', 'news', '', '1.0', 'includes/plugins/news/', 1, 1, 1, 1, 'deactivated')
");

safe_query("
    INSERT INTO settings_plugins_lang
        (content_key, language, content, modulname, updated_at)
    VALUES
        ('plugin_name_news', 'de', 'News', 'news', NOW()),
        ('plugin_name_news', 'en', 'News', 'news', NOW()),
        ('plugin_name_news', 'it', 'News', 'news', NOW()),
        ('plugin_info_news', 'de', 'Dieses Plugin ermoeglicht das Erstellen und Verwalten von News-Artikeln auf Ihrer Webspell-RM-Seite.', 'news', NOW()),
        ('plugin_info_news', 'en', 'This plugin allows you to create and manage news articles on your Webspell-RM site.', 'news', NOW()),
        ('plugin_info_news', 'it', 'Questo plugin consente di creare e gestire articoli di notizie sul tuo sito Webspell-RM.', 'news', NOW())
    ON DUPLICATE KEY UPDATE
        content = VALUES(content),
        modulname = VALUES(modulname),
        updated_at = VALUES(updated_at)
");

safe_query("INSERT INTO settings_widgets
  (widget_key, title, plugin, modulname, description, allowed_zones, active, version, created_at)
VALUES
  ('widget_news_masonry', 'News Masonry', 'news', 'news', NULL, 'maintop,mainbottom', 1, '1.0.0', NOW()),
  ('widget_news_carousel', 'News Carousel', 'news', 'news', NULL, 'maintop,mainbottom', 1, '1.0.0', NOW()),
  ('widget_news_featured_list', 'News Featured List', 'news', 'news', NULL, 'maintop,mainbottom', 1, '1.0.0', NOW()),
  ('widget_news_flip', 'News Flip', 'news', 'news', NULL, 'maintop,mainbottom', 1, '1.0.0', NOW()),
  ('widget_news_magazine', 'News Magazine', 'news', 'news', NULL, 'maintop,mainbottom', 1, '1.0.0', NOW()),
  ('widget_news_topnews', 'News Topnews', 'news', 'news', NULL, 'maintop,mainbottom', 1, '1.0.0', NOW())
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  plugin = VALUES(plugin),
  modulname = VALUES(modulname),
  allowed_zones = VALUES(allowed_zones),
  active = VALUES(active),
  version = VALUES(version)");

$linkID = 0;
$linkRes = safe_query("
    SELECT linkID FROM navigation_dashboard_links
    WHERE modulname = 'news' AND url = 'admincenter.php?site=admin_news'
    ORDER BY linkID ASC LIMIT 1
");
if ($linkRes && ($linkRow = mysqli_fetch_assoc($linkRes))) {
    $linkID = (int) ($linkRow['linkID'] ?? 0);
} else {
    safe_query("
        INSERT INTO navigation_dashboard_links
            (catID, modulname, url, sort)
        VALUES
            (8, 'news', 'admincenter.php?site=admin_news', 1)
    ");
    $linkID = (int) mysqli_insert_id($_database);
}
if ($linkID > 0) {
    safe_query("
        INSERT INTO navigation_dashboard_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_link_{$linkID}', 'de', 'News', 'news', NOW()),
            ('nav_link_{$linkID}', 'en', 'News', 'news', NOW()),
            ('nav_link_{$linkID}', 'it', 'Notizie', 'news', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

$snavID = 0;
$snavRes = safe_query("
    SELECT snavID FROM navigation_website_sub
    WHERE modulname = 'news' AND url = 'index.php?site=news'
    ORDER BY snavID ASC LIMIT 1
");
if ($snavRes && ($snavRow = mysqli_fetch_assoc($snavRes))) {
    $snavID = (int) ($snavRow['snavID'] ?? 0);
} else {
    safe_query("
        INSERT INTO navigation_website_sub
            (mnavID, modulname, url, sort, indropdown, last_modified)
        VALUES
            (1, 'news', 'index.php?site=news', 1, 1, NOW())
    ");
    $snavID = (int) mysqli_insert_id($_database);
}
if ($snavID > 0) {
    safe_query("
        INSERT INTO navigation_website_lang
            (content_key, language, content, modulname, updated_at)
        VALUES
            ('nav_sub_{$snavID}', 'de', 'News', 'news', NOW()),
            ('nav_sub_{$snavID}', 'en', 'News', 'news', NOW()),
            ('nav_sub_{$snavID}', 'it', 'Notizie', 'news', NOW())
        ON DUPLICATE KEY UPDATE
            content = VALUES(content),
            modulname = VALUES(modulname),
            updated_at = VALUES(updated_at)
    ");
}

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights
    (id, roleID, type, modulname)
  VALUES
    ('', 1, 'link', 'news')
");

safe_query("
INSERT IGNORE INTO plugins_news_categories (id, name, slug, description, image, sort_order) VALUES
(1, 'Allgemein', 'allgemein', 'Standard-Rubrik fuer News-Beitraege.', NULL, 0)
");

safe_query("
INSERT IGNORE INTO plugins_news
(id, category_id, title, slug, content, link, link_name, banner_image, sort_order, publish_at, userID, is_active, topnews_is_active, views, allow_comments)
VALUES
(1, 1, 'Willkommen im News-Plugin', 'willkommen-im-news-plugin',
'Das ist ein automatisch angelegter Beispielartikel.\\r\\n\\r\\nDu kannst ihn im Adminbereich bearbeiten oder loeschen.',
'', '', '', 0, NOW(), 1, 1, 0, 0, 1)
");

if (!function_exists('news_install_extract_lang')) {
    function news_install_extract_lang(string $text, string $lang): string
    {
        if (preg_match('/\[\[lang:' . preg_quote($lang, '/') . '\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
            return trim((string) $m[1]);
        }
        if ($lang === 'gb' && preg_match('/\[\[lang:en\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
            return trim((string) $m[1]);
        }
        if ($lang === 'en' && preg_match('/\[\[lang:gb\]\](.*?)(?=\[\[lang:|$)/s', $text, $m)) {
            return trim((string) $m[1]);
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
        $newsId = (int) ($row['id'] ?? 0);
        if ($newsId <= 0) {
            continue;
        }
        foreach ($langs as $iso) {
            $isoEsc = mysqli_real_escape_string($_database, $iso);
            $titleEsc = mysqli_real_escape_string($_database, news_install_extract_lang((string) ($row['title'] ?? ''), $iso));
            $contentEsc = mysqli_real_escape_string($_database, news_install_extract_lang((string) ($row['content'] ?? ''), $iso));
            $linkNameEsc = mysqli_real_escape_string($_database, news_install_extract_lang((string) ($row['link_name'] ?? ''), $iso));
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

echo "</div></div>";
?>
