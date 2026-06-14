<?php

if (!function_exists('safe_query')) {
    die('Access denied');
}

global $plugin;

PluginInstallerHelper::install([

    'modulname'  => 'news',
    'name'       => 'News',
    'version'    => (string)($plugin['version'] ?? '0.0.0'),
    'author'     => 'T-Seven',
    'website'    => 'https://www.nexpell.de',
    'path'       => 'includes/plugins/news/',

    'admin_file' => 'admin_news',
    'index_link' => 'news',
    'sidebar'    => 'deactivated',

    'languages' => [
        'plugin_info_news' => [
            'de' => 'Dieses Plugin ermöglicht das Erstellen und Verwalten von News-Artikeln auf Ihrer Webspell-RM-Seite.',
            'en' => 'This plugin allows you to create and manage news articles on your Webspell-RM site.',
            'it' => 'Questo plugin consente di creare e gestire articoli di notizie sul tuo sito Webspell-RM.'
        ]
    ],

    'permissions' => [
        'news'
    ],

    'widgets' => [
        [
            'widget_key'    => 'widget_news_masonry',
            'title'         => 'News Masonry',
            'description'   => null,
            'allowed_zones' => 'maintop,mainbottom'
        ],
        [
            'widget_key'    => 'widget_news_carousel',
            'title'         => 'News Carousel',
            'description'   => null,
            'allowed_zones' => 'maintop,mainbottom'
        ],
        [
            'widget_key'    => 'widget_news_featured_list',
            'title'         => 'News Featured List',
            'description'   => null,
            'allowed_zones' => 'maintop,mainbottom'
        ],
        [
            'widget_key'    => 'widget_news_flip',
            'title'         => 'News Flip',
            'description'   => null,
            'allowed_zones' => 'maintop,mainbottom'
        ],
        [
            'widget_key'    => 'widget_news_magazine',
            'title'         => 'News Magazine',
            'description'   => null,
            'allowed_zones' => 'maintop,mainbottom'
        ],
        [
            'widget_key'    => 'widget_news_topnews',
            'title'         => 'News Topnews',
            'description'   => null,
            'allowed_zones' => 'maintop,mainbottom'
        ]
    ],

    'admin_navigation' => [
        [
            'url'   => 'admincenter.php?site=admin_news',
            'catID' => 8,
            'sort'  => 1,
            'labels' => [
                'de' => 'News',
                'en' => 'News',
                'it' => 'Notizie'
            ]
        ]
    ],

    'website_navigation' => [
        [
            'url'        => 'index.php?site=news',
            'mnavID'     => 1,
            'sort'       => 1,
            'indropdown' => 1,
            'labels' => [
                'de' => 'News',
                'en' => 'News',
                'it' => 'Notizie'
            ]
        ]
    ]

]);

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

## BEISPIELDATEN ################################################################

safe_query("
INSERT IGNORE INTO plugins_news_categories (id, name, slug, description, image, sort_order) VALUES
(1, 'Allgemein', 'allgemein', 'Standard-Rubrik für News-Beiträge.', NULL, 0)
");

safe_query("
INSERT IGNORE INTO plugins_news
(id, category_id, title, slug, content, link, link_name, banner_image, sort_order, publish_at, userID, is_active, topnews_is_active, views, allow_comments)
VALUES
(1, 1, 'Willkommen im News-Plugin', 'willkommen-im-news-plugin',
'Das ist ein automatisch angelegter Beispielartikel.\\r\\n\\r\\nDu kannst ihn im Adminbereich bearbeiten oder löschen.',
'', '', '', 0, NOW(), 1, 1, 0, 0, 1)
");

