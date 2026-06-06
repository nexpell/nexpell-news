<?php
declare(strict_types=1);

if (!function_exists('news_widget_builder_defaults')) {
    function news_widget_builder_defaults(string $widgetKey): array
    {
        $defaults = [
            'title' => '',
            'limit' => 6,
            'category_id' => 0,
            'order' => 'latest',
            'show_heading' => 1,
            'show_date' => 1,
            'show_category' => 1,
            'excerpt_chars' => 180,
            'columns_desktop' => 3,
            'columns_tablet' => 2,
            'columns_mobile' => 1,
            'slides_mobile' => 1,
            'slides_tablet' => 2,
            'slides_desktop' => 3,
            'autoplay_delay' => 4000,
            'featured_excerpt_chars' => 220,
            'list_excerpt_chars' => 120,
            'content_chars' => 240,
        ];

        $byWidget = [
            'widget_news_carousel' => [
                'title' => 'News Carousel',
                'limit' => 8,
                'slides_mobile' => 1,
                'slides_tablet' => 2,
                'slides_desktop' => 3,
                'autoplay_delay' => 4000,
            ],
            'widget_news_featured_list' => [
                'title' => 'News Featured',
                'limit' => 5,
                'featured_excerpt_chars' => 220,
                'list_excerpt_chars' => 120,
            ],
            'widget_news_flip' => [
                'title' => 'News Flip',
                'limit' => 6,
                'content_chars' => 260,
            ],
            'widget_news_magazine' => [
                'title' => 'News Magazine',
                'limit' => 4,
                'featured_excerpt_chars' => 260,
            ],
            'widget_news_masonry' => [
                'title' => 'News Masonry',
                'limit' => 12,
                'columns_desktop' => 3,
                'columns_tablet' => 2,
                'columns_mobile' => 1,
                'excerpt_chars' => 180,
            ],
            'widget_news_topnews' => [
                'title' => 'Top News',
                'limit' => 5,
            ],
        ];

        return array_replace($defaults, $byWidget[$widgetKey] ?? []);
    }
}

if (!function_exists('news_widget_builder_settings')) {
    function news_widget_builder_settings(string $widgetKey, array $settings): array
    {
        $merged = array_replace(news_widget_builder_defaults($widgetKey), $settings);
        $merged['title'] = trim((string)($merged['title'] ?? ''));
        $merged['limit'] = max(1, min(24, (int)($merged['limit'] ?? 6)));
        $merged['category_id'] = max(0, (int)($merged['category_id'] ?? 0));
        $merged['order'] = trim((string)($merged['order'] ?? 'latest'));
        $merged['show_heading'] = !empty($merged['show_heading']) ? 1 : 0;
        $merged['show_date'] = !empty($merged['show_date']) ? 1 : 0;
        $merged['show_category'] = !empty($merged['show_category']) ? 1 : 0;
        $merged['excerpt_chars'] = max(40, min(1000, (int)($merged['excerpt_chars'] ?? 180)));
        $merged['featured_excerpt_chars'] = max(60, min(1200, (int)($merged['featured_excerpt_chars'] ?? 220)));
        $merged['list_excerpt_chars'] = max(40, min(800, (int)($merged['list_excerpt_chars'] ?? 120)));
        $merged['content_chars'] = max(60, min(1600, (int)($merged['content_chars'] ?? 240)));
        $merged['columns_desktop'] = max(1, min(4, (int)($merged['columns_desktop'] ?? 3)));
        $merged['columns_tablet'] = max(1, min(3, (int)($merged['columns_tablet'] ?? 2)));
        $merged['columns_mobile'] = max(1, min(2, (int)($merged['columns_mobile'] ?? 1)));
        $merged['slides_mobile'] = max(1, min(2, (int)($merged['slides_mobile'] ?? 1)));
        $merged['slides_tablet'] = max(1, min(3, (int)($merged['slides_tablet'] ?? 2)));
        $merged['slides_desktop'] = max(1, min(4, (int)($merged['slides_desktop'] ?? 3)));
        $merged['autoplay_delay'] = max(0, min(20000, (int)($merged['autoplay_delay'] ?? 4000)));

        $allowedOrders = ['latest', 'sort_desc', 'sort_asc', 'title_asc'];
        if (!in_array($merged['order'], $allowedOrders, true)) {
            $merged['order'] = 'latest';
        }

        return $merged;
    }
}

if (!function_exists('news_widget_builder_order_sql')) {
    function news_widget_builder_order_sql(string $order): string
    {
        $dateExpr = 'COALESCE(a.publish_at, a.updated_at)';
        switch ($order) {
            case 'sort_desc':
                return 'ORDER BY IFNULL(a.sort_order, 0) DESC, ' . $dateExpr . ' DESC, a.id DESC';
            case 'sort_asc':
                return 'ORDER BY IFNULL(a.sort_order, 9999) ASC, ' . $dateExpr . ' DESC, a.id DESC';
            case 'title_asc':
                return 'ORDER BY a.title ASC, ' . $dateExpr . ' DESC, a.id DESC';
            case 'latest':
            default:
                return 'ORDER BY ' . $dateExpr . ' DESC, a.id DESC';
        }
    }
}

if (!function_exists('news_widget_builder_where_sql')) {
    function news_widget_builder_where_sql(mysqli $db, array $settings): string
    {
        $where = ['a.is_active = 1', '(a.publish_at IS NULL OR a.publish_at <= NOW())'];
        $categoryId = (int)($settings['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'a.category_id = ' . (int)$categoryId;
        }
        return 'WHERE ' . implode(' AND ', $where);
    }
}

if (!function_exists('news_widget_builder_timestamp')) {
    function news_widget_builder_timestamp(array $row): int
    {
        $value = $row['publish_at'] ?? ($row['updated_at'] ?? null);
        if ($value === null || $value === '') {
            return time();
        }

        return is_numeric($value) ? (int)$value : ((int)strtotime((string)$value) ?: time());
    }
}

if (!function_exists('news_widget_builder_heading_html')) {
    function news_widget_builder_heading_html(array $settings, string $fallbackTitle, string $tag = 'h5', string $class = 'mb-3'): string
    {
        if (empty($settings['show_heading'])) {
            return '';
        }
        $title = trim((string)($settings['title'] ?? ''));
        if ($title === '') {
            $title = $fallbackTitle;
        }
        if ($title === '') {
            return '';
        }
        if (function_exists('news_widget_builder_normalize_text')) {
            $title = news_widget_builder_normalize_text($title);
        }
        $safeTag = preg_match('/^h[1-6]$/', $tag) ? $tag : 'h5';
        return '<' . $safeTag . ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</' . $safeTag . '>';
    }
}

if (!function_exists('news_widget_builder_normalize_text')) {
    function news_widget_builder_normalize_text(string $text): string
    {
        $decoded = $text;
        for ($i = 0; $i < 5; $i++) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        if (preg_match('/(?:\xC3\x83|\xC3\x82|\xC3\xA2)/', $decoded)) {
            $fixed = @mb_convert_encoding($decoded, 'Windows-1252', 'UTF-8');
            if (is_string($fixed) && $fixed !== '') {
                $decoded = $fixed;
            }
        }

        return $decoded;
    }
}

if (!function_exists('news_widget_builder_lang_table_ready')) {
    function news_widget_builder_lang_table_ready(mysqli $db): bool
    {
        $res = $db->query("SHOW TABLES LIKE 'plugins_news_lang'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('news_widget_builder_lang_field')) {
    function news_widget_builder_lang_field(mysqli $db, int $newsId, string $field, string $lang, string $fallback = ''): string
    {
        $value = $fallback;
        if ($newsId > 0 && news_widget_builder_lang_table_ready($db)) {
            $key = mysqli_real_escape_string($db, 'news_' . $newsId . '_' . $field);
            $langEsc = mysqli_real_escape_string($db, strtolower($lang));
            $res = $db->query("
                SELECT content
                FROM plugins_news_lang
                WHERE content_key = '{$key}'
                  AND language IN ('{$langEsc}', 'de', 'en')
                ORDER BY FIELD(language, '{$langEsc}', 'de', 'en')
                LIMIT 1
            ");
            if ($res instanceof mysqli_result && ($row = $res->fetch_assoc())) {
                $value = (string)($row['content'] ?? $fallback);
            }
        }

        return news_widget_builder_normalize_text($value);
    }
}

if (!function_exists('news_widget_builder_excerpt')) {
    function news_widget_builder_excerpt(string $text, int $length): string
    {
        $plain = trim(strip_tags(news_widget_builder_normalize_text($text)));
        if ($plain === '') {
            return '';
        }
        if (mb_strlen($plain) <= $length) {
            return $plain;
        }
        return mb_substr($plain, 0, $length) . '...';
    }
}

if (!function_exists('news_widget_builder_asset')) {
    function news_widget_builder_asset(string $file): string
    {
        $path = __DIR__ . '/css/' . $file;
        $version = file_exists($path) ? filemtime($path) : time();
        return '<link rel="stylesheet" href="/includes/plugins/news/css/' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '?v=' . (int)$version . '">' . PHP_EOL;
    }
}

if (!function_exists('news_widget_builder_image')) {
    function news_widget_builder_image(array $row): string
    {
        $banner = trim((string)($row['banner_image'] ?? ''));
        if ($banner !== '') {
            if (preg_match('#^https?://#i', $banner) || strpos($banner, '/') === 0) {
                return $banner;
            }
            return '/includes/plugins/news/images/news_images/' . rawurlencode($banner);
        }

        $categoryImage = trim((string)($row['category_image'] ?? ''));
        if ($categoryImage !== '') {
            return '/includes/plugins/news/images/news_categories/' . rawurlencode($categoryImage);
        }

        return '/includes/plugins/news/images/no-image.jpg';
    }
}

if (!function_exists('news_widget_builder_read_more')) {
    function news_widget_builder_read_more(): string
    {
        global $languageService;
        if (isset($languageService) && method_exists($languageService, 'get')) {
            $value = $languageService->get('read_more');
            if (is_string($value) && $value !== '' && !preg_match('/^\[.+\]$/', $value)) {
                return $value;
            }
        }

        return 'Mehr lesen';
    }
}
