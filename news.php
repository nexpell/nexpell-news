<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\RoleManager;
use nexpell\SeoUrlHandler;

global $languageService, $_database;

$newsOverviewUrl = SeoUrlHandler::convertToSeoUrl('index.php?site=news');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style'] ?? '');

/** Header-Daten */
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'News'
];

echo $tpl->loadTemplate("news", "head", $data_array, 'plugin');

/** Action auslesen */
$action = $_GET['action'] ?? '';

/** Rollenprüfung */
if (!function_exists('has_role')) {
    function has_role(int $userID, string $roleName): bool {
        $roleID = RoleManager::getUserRoleID($userID);
        if ($roleID === null) return false;
        return ($roleID === $roleName);
    }
}

if (!function_exists('escape_string')) {
    function escape_string(string $s): string {
        global $_database;
        return mysqli_real_escape_string($_database, $s);
    }
}

if (!function_exists('news_lang_table_ready')) {
    function news_lang_table_ready(mysqli $db): bool {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $res = mysqli_query($db, "SHOW TABLES LIKE 'plugins_news_lang'");
        $ready = ($res && mysqli_num_rows($res) > 0);
        return $ready;
    }
}

if (!function_exists('news_lang_get_field')) {
    function news_lang_get_field(mysqli $db, int $newsId, string $field, string $lang, string $fallback = ''): string {
        static $cache = [];
        $lang = strtolower($lang);
        $cacheKey = $newsId . ':' . $field . ':' . $lang;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        if (!news_lang_table_ready($db) || $newsId <= 0) {
            $cache[$cacheKey] = $fallback;
            return $fallback;
        }

        $keyEsc = mysqli_real_escape_string($db, 'news_' . $newsId . '_' . $field);
        $order = array_values(array_unique([$lang, 'en', 'gb', 'de', 'it']));
        foreach ($order as $iso) {
            $isoEsc = mysqli_real_escape_string($db, $iso);
            $res = mysqli_query($db, "
                SELECT content
                FROM plugins_news_lang
                WHERE content_key='{$keyEsc}' AND language='{$isoEsc}'
                LIMIT 1
            ");
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $txt = (string)($row['content'] ?? '');
                if ($txt !== '') {
                    $cache[$cacheKey] = $txt;
                    return $txt;
                }
            }
        }

        $cache[$cacheKey] = $fallback;
        return $fallback;
    }
}

if (!function_exists('news_release_timestamp')) {
    function news_release_timestamp(array $row): int {
        $value = $row['publish_at'] ?? ($row['updated_at'] ?? null);
        if ($value === null || $value === '') {
            return time();
        }

        if (is_numeric($value)) {
            $timestamp = (int)$value;
            return $timestamp > 0 ? $timestamp : time();
        }

        $timestamp = strtotime((string)$value);
        return $timestamp !== false && $timestamp > 0 ? $timestamp : time();
    }
}

if (!function_exists('news_render_html_content')) {
    function news_render_html_content(string $content): string {
        if ($content === '') {
            return '';
        }

        return html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('news_find_by_slug_alias')) {
    function news_find_by_slug_alias(mysqli $db, string $slug, string $lang): int {
        $slug = trim($slug);
        if ($slug === '') {
            return 0;
        }

        $slugEsc = mysqli_real_escape_string($db, $slug);
        $res = safe_query("
            SELECT id, title, slug
            FROM plugins_news
            WHERE is_active = 1
              AND (publish_at IS NULL OR publish_at <= NOW())
              AND (
                  slug = '{$slugEsc}'
                  OR LOWER(slug) = LOWER('{$slugEsc}')
              )
            LIMIT 1
        ");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return (int)$row['id'];
        }

        $res = safe_query("
            SELECT id, title, slug
            FROM plugins_news
            WHERE is_active = 1
              AND (publish_at IS NULL OR publish_at <= NOW())
            ORDER BY id DESC
        ");
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $candidates = [
                (string)($row['slug'] ?? ''),
                SeoUrlHandler::slugify((string)($row['title'] ?? '')),
                SeoUrlHandler::slugify(news_lang_get_field($db, $id, 'title', $lang, (string)($row['title'] ?? ''))),
                SeoUrlHandler::slugify(news_lang_get_field($db, $id, 'title', 'de', (string)($row['title'] ?? ''))),
                SeoUrlHandler::slugify(news_lang_get_field($db, $id, 'title', 'en', (string)($row['title'] ?? ''))),
                SeoUrlHandler::slugify(news_lang_get_field($db, $id, 'title', 'it', (string)($row['title'] ?? ''))),
            ];

            foreach (array_unique(array_filter($candidates)) as $candidate) {
                if (strcasecmp($candidate, $slug) === 0) {
                    return $id;
                }
            }
        }

        return 0;
    }
}

$newsLang = strtolower((string)($languageService->detectLanguage() ?: ($_SESSION['language'] ?? 'de')));

// Ermittelt category_id per cat/categoryID oder slug (aus Query).
$category_id = 0;

if (isset($_GET['cat'])) {
    $category_id = (int) $_GET['cat'];
} elseif (isset($_GET['categoryID'])) {
    $category_id = (int) $_GET['categoryID'];
} elseif (!empty($_GET['slug'])) {
    $slug = escape_string($_GET['slug']);
    $res  = safe_query("SELECT id FROM plugins_news_categories WHERE slug = '{$slug}' LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) {
        $category_id = (int) $row['id'];
    }
}

if ($category_id > 0) {
    $id = $category_id;

    // Pagination
    $page   = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
    $limit  = 6;
    $offset = ($page - 1) * $limit;

    // Kategorie laden
    $getcat = safe_query("SELECT * FROM plugins_news_categories WHERE id=".(int)$category_id." LIMIT 1");
    $ds_cat = mysqli_fetch_array($getcat);
    if (!$ds_cat) {
        echo $languageService->get('no_categories');
        exit;
    }
    $name     = htmlspecialchars($ds_cat['name']);
    $cat_slug = !empty($ds_cat['slug']) ? $ds_cat['slug'] : SeoUrlHandler::slugify($ds_cat['name']);

    // Gesamtanzahl News
    $total_news_query  = safe_query("SELECT COUNT(*) AS total FROM plugins_news WHERE category_id=".(int)$category_id." AND is_active='1' AND (publish_at IS NULL OR publish_at <= NOW())");
    $total_news_result = mysqli_fetch_array($total_news_query);
    $total_news        = (int)($total_news_result['total'] ?? 0);
    $total_pages       = max(1, (int)ceil($total_news / $limit));

    // Übersichtslink (News-Start)
    $title_url = SeoUrlHandler::convertToSeoUrl('index.php?site=news');

    // News laden
    $ergebnis = safe_query("
        SELECT *
        FROM plugins_news
        WHERE category_id=".(int)$category_id." AND is_active='1' AND (publish_at IS NULL OR publish_at <= NOW())
        ORDER BY COALESCE(publish_at, updated_at) DESC, id DESC
        LIMIT $offset, $limit
    ");

    // Header
    $data_array = [
        'name'              => $name,
        'title_url'         => $title_url,
        'title'             => $languageService->get('title'),
        'title_categories'  => $languageService->get('title_categories'),
        'categories'        => $languageService->get('categories'),
        'category'          => $languageService->get('category'),
    ];

    echo $tpl->loadTemplate("news", "details_head", $data_array, 'plugin');
    echo $tpl->loadTemplate("news", "content_all_head", $data_array, 'plugin');

    if (mysqli_num_rows($ergebnis)) {
        $monate = [
            1 => $languageService->get('jan'),  2 => $languageService->get('feb'),
            3 => $languageService->get('mar'),  4 => $languageService->get('apr'),
            5 => $languageService->get('may'),  6 => $languageService->get('jun'),
            7 => $languageService->get('jul'),  8 => $languageService->get('aug'),
            9 => $languageService->get('sep'), 10 => $languageService->get('oct'),
           11 => $languageService->get('nov'), 12 => $languageService->get('dec')
        ];

        while ($ds = mysqli_fetch_array($ergebnis)) {
            $newsId = (int)($ds['id'] ?? 0);
            $newsTitle = news_lang_get_field($_database, $newsId, 'title', $newsLang, (string)($ds['title'] ?? ''));
            $newsContent = news_render_html_content(
                news_lang_get_field($_database, $newsId, 'content', $newsLang, (string)($ds['content'] ?? ''))
            );

            // Timestamp robust
            $ts = news_release_timestamp($ds);

            $tag        = date("d", $ts);
            $monat      = (int)date("n", $ts);
            $year       = date("Y", $ts);
            $monatname  = $monate[$monat] ?? date("M", $ts);
            $title      = $newsTitle;
            $short_title = mb_strlen($title) > 70 ? mb_substr($title, 0, 70) . '...' : $title;

            if (!function_exists('truncateHtml')) {
                /** Kürzt HTML-Text auf eine bestimmte Länge (HTML entfernt) */
                function truncateHtml(string $text, int $length = 350, string $ending = '...', bool $considerHtml = true): string {
                    $plain = strip_tags($text);
                    if (mb_strlen($plain) <= $length) return $text;
                    return mb_substr($plain, 0, $length) . $ending;
                }
            }

            $short_content = truncateHtml($newsContent, 350);
            $new_id        = (int)$ds['id'];

            // Autor-URL
            $profileUrl = SeoUrlHandler::convertToSeoUrl('index.php?site=profile&userID=' . (int)$ds['userID']);
            $username = '<a href="' . htmlspecialchars($profileUrl) . '">
                <img src="' . htmlspecialchars(getavatar($ds['userID'])) . '"
                     class="img-fluid align-middle rounded me-1"
                     style="height: 23px; width: 23px;"
                     alt="' . htmlspecialchars(getusername($ds['userID'])) . '">
                <strong>' . htmlspecialchars(getusername($ds['userID'])) . '</strong>
            </a>';

            // Kategorie-Daten je News
            $catID = (int)$ds['category_id'];
            $cat_query = safe_query("SELECT name, image, slug FROM plugins_news_categories WHERE id = ".$catID." LIMIT 1");
            $cat = mysqli_fetch_assoc($cat_query) ?: [];
            $cat_name = htmlspecialchars($cat['name'] ?? $name);

            // Kategorie-Link
            $catUrl = SeoUrlHandler::buildPluginUrl('plugins_news_categories', $catID);
            $new_catname = '<a href="' . htmlspecialchars($catUrl) . '">
                             <strong style="font-size: 12px">' . $cat_name . '</strong></a>';

            // News-Detail-Link
            $url_watch_seo = SeoUrlHandler::buildPluginUrl('plugins_news', $new_id);

            // Kategorie-Bild
            $image = !empty($cat['image'])
                ? "/includes/plugins/news/images/news_categories/" . $cat['image']
                : "/includes/plugins/news/images/no-image.jpg";

            if (!function_exists('truncateHtmlShort')) {
                function truncateHtmlShort(string $text, int $length = 150, string $ending = '...', bool $considerHtml = true): string {
                    $plain = strip_tags($text);
                    if (mb_strlen($plain) <= $length) return $text;
                    return mb_substr($plain, 0, $length) . $ending;
                }
            }
            $content = truncateHtmlShort($newsContent, 150);

            $data_array = [
                'name'          => $new_catname,
                'title'         => $short_title,
                'content'       => $content,
                'username'      => $username,
                'image'         => $image,
                'tag'           => $tag,
                'monat'         => $monatname,
                'year'          => $year,
                'url_watch'     => $url_watch_seo,
                'lang_rating'   => $languageService->get('rating'),
                'lang_votes'    => $languageService->get('votes'),
                'link'          => $languageService->get('link'),
                'info'          => $languageService->get('info'),
                'stand'         => $languageService->get('stand'),
                'by'            => $languageService->get('by'),
                'on'            => $languageService->get('on'),
                'read_more'     => $languageService->get('read_more'),
            ];

            echo $tpl->loadTemplate("news", "content_all", $data_array, 'plugin');
        }

        echo $tpl->loadTemplate("news", "content_all_foot", $data_array, 'plugin');

        // Pagination
        if ($total_pages > 1) {
            $baseUrl = SeoUrlHandler::convertToSeoUrl("index.php?site=news&slug={$cat_slug}");
            $baseUrl = rtrim($baseUrl, '/') . '/';
            echo $tpl->renderPagination($baseUrl, (int)$page, (int)$total_pages);
        }
    } else {
        echo $languageService->get('no_news') . '<br><br>[ <a href="' .
             htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=news')) .
             '" class="alert-new">' .
             $languageService->get('go_back') .
             '</a> ]';
    }
    return;
}


// USER/SESSION-HELFER
$loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
$userID   = $loggedin ? (int)$_SESSION['userID'] : 0;

// Kommentare speichern
if (isset($_POST['submit_comment'])) {
    $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
    $userID   = $loggedin ? (int)$_SESSION['userID'] : 0;

    if (
        $loggedin &&
        !empty($_POST['comment']) &&
        is_numeric($_POST['id']) &&
        isset($_POST['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $comment = htmlspecialchars($_POST['comment']);
        $itemID  = (int)$_POST['id'];

        safe_query("INSERT INTO comments (plugin, itemID, userID, comment, date, parentID, modulname)
                    VALUES ('news', $itemID, $userID, '$comment', NOW(), 0, 'news')");

        // Zurück zur News – konsistent via Helper, ohne lang=
        $backUrl = SeoUrlHandler::buildPluginUrl('plugins_news', $itemID);
        header("Location: " . $backUrl);
        exit;
    } else {
        die("Ungültiger CSRF-Token oder fehlende Eingaben.");
    }
}

// Kommentare löschen
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'deletecomment' && is_numeric($_GET['id'])) {
    $commentID = (int)$_GET['id'];

    // Referer setzen
    if (isset($_GET['ref'])) {
        $referer = urldecode($_GET['ref']);
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
    } else {
        $referer = $newsOverviewUrl;
    }

    // CSRF prüfen
    if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        die('Ungültiger CSRF-Token.');
    }

    // Kommentar löschen
    $res = safe_query("DELETE FROM comments WHERE commentID = $commentID");

    if ($res) {
        header("Location: " . htmlspecialchars($referer));
        exit();
    } else {
        die('<div class="alert alert-danger">Fehler beim Löschen des Kommentars.</div>');
    }
}

// Ermittelt news_id per newsID/id oder slug (aus Query).
$news_id = 0;

if (isset($_GET['newsID'])) {
    $news_id = (int) $_GET['newsID'];
} elseif (isset($_GET['id'])) {
    $news_id = (int) $_GET['id'];
} elseif (!empty($_GET['slug'])) {
    $slug = escape_string($_GET['slug']);
    $res  = safe_query("SELECT id FROM plugins_news WHERE slug = '{$slug}' LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) {
        $news_id = (int) $row['id'];
    } else {
        $news_id = news_find_by_slug_alias($_database, (string)$_GET['slug'], $newsLang);
        if ($news_id > 0) {
            $canonicalNewsUrl = SeoUrlHandler::buildPluginUrl('plugins_news', $news_id, $newsLang);
            $currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
            $canonicalPath = (string)(parse_url($canonicalNewsUrl, PHP_URL_PATH) ?? $canonicalNewsUrl);
            if ($canonicalPath !== '' && rtrim($currentPath, '/') !== rtrim($canonicalPath, '/')) {
                header("Location: {$canonicalNewsUrl}", true, 301);
                exit;
            }
        }
    }
}

if ($news_id > 0) {
    $id         = $news_id;
    $pluginName = 'news';

    $newQuery = safe_query("SELECT * FROM plugins_news WHERE id = $id AND is_active = 1 AND (publish_at IS NULL OR publish_at <= NOW()) LIMIT 1");
    if (mysqli_num_rows($newQuery)) {
        $new = mysqli_fetch_array($newQuery);
        $newTitle = news_lang_get_field($_database, (int)$id, 'title', $newsLang, (string)($new['title'] ?? ''));
        $newContent = news_render_html_content(
            news_lang_get_field($_database, (int)$id, 'content', $newsLang, (string)($new['content'] ?? ''))
        );
        $newLinkName = news_lang_get_field($_database, (int)$id, 'link_name', $newsLang, (string)($new['link_name'] ?? ''));

        $categoryQuery = safe_query("SELECT * FROM plugins_news_categories WHERE id = " . (int)$new['category_id'] . " LIMIT 1");
        $category = mysqli_fetch_array($categoryQuery);

        $name = $category ? htmlspecialchars($category['name']) : 'Unbekannte Kategorie';

        // Basis-Links
        $title_url      = SeoUrlHandler::convertToSeoUrl('index.php?site=news');
        $title_url_show = SeoUrlHandler::buildPluginUrl('plugins_news_categories', (int)$new['category_id']);

        $data_array = [
            'name'              => $name,
            'title_url'         => $title_url,
            'title_url_show'    => $title_url_show,
            'title'             => htmlspecialchars($newTitle),
            'title_categories'  => $languageService->get('title_categories'),
            'categories'        => $languageService->get('categories'),
            'category'          => $languageService->get('category'),
        ];
        echo $tpl->loadTemplate("news", "content_details_head", $data_array, 'plugin');

        // Cookie-Views
        $cookieName = 'new_view_' . $id;
        if (!isset($_COOKIE[$cookieName])) {
            safe_query("UPDATE plugins_news SET views = views + 1 WHERE id = $id");
            setcookie($cookieName, '1', time() + 86400, '/', '', isset($_SERVER['HTTPS']), true);
            $new['views']++;
        }

        $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
        $userID   = $loggedin ? (int)$_SESSION['userID'] : 0;

        $image = !empty($category['image'])
            ? "includes/plugins/news/images/news_categories/" . $category['image']
            : "includes/plugins/news/images/no-image.jpg";

        // updated_at → Timestamp
        $ts = news_release_timestamp($new);

        $day   = date('d', $ts);
        $month = $languageService->get(strtolower(date('F', $ts)));
        $year  = date('Y', $ts);
        $category_name = htmlspecialchars($category['name'] ?? '');

        // Profil-Link
        $profileUrl = SeoUrlHandler::convertToSeoUrl('index.php?site=profile&userID=' . (int)$new['userID']);
        $username = '<a href="' . htmlspecialchars($profileUrl) . '">
            <img src="' . htmlspecialchars(getavatar($new['userID'])) . '"
                 class="img-fluid align-middle rounded-circle me-1"
                 style="height: 23px; width: 23px;"
                 alt="' . htmlspecialchars(getusername($new['userID'])) . '">
            <strong>' . htmlspecialchars(getusername($new['userID'])) . '</strong>
        </a>';

        // Externer Link
        if (!empty($new['link'])) {
            $link = '<a href="' . htmlspecialchars($new['link']) . '" target="_blank" rel="noopener noreferrer">'
                . htmlspecialchars($newLinkName) . '</a>';
        } else {
            $link = $languageService->get('no_link');
        }

        $data_array = [
            'title'       => $newTitle,
            'content'     => $newContent,
            'username'    => $username,
            'date'        => date('d.m.Y H:i', $ts),
            'views'       => $new['views'],
            'image'       => $image,
            'link'        => $link,
            'lang_link'   => $languageService->get('link'),
            'info'        => $languageService->get('info'),
            'stand'       => $languageService->get('stand'),
            'lang_views'  => $languageService->get('views'),
            'day'         => $day,
            'month'       => $month,
            'year'        => $year,
            'category'    => $category_name,
        ];

        echo $tpl->loadTemplate("news", "content_details", $data_array, 'plugin');

        // Kommentare anzeigen
        if (!empty($new['allow_comments'])) {
            $comments = safe_query("
                SELECT c.*, u.username
                FROM comments c
                JOIN users u ON c.userID = u.userID
                WHERE c.plugin = '" . escape_string($pluginName) . "'
                  AND c.itemID = $id
                ORDER BY c.date DESC
            ");

            echo '<div class="mt-5"><h5 class="border-bottom p-2">' . $languageService->get('comments') . '</h5><ul class="list-group">';
            while ($row = mysqli_fetch_array($comments)) {
                $deleteLink = '';
                $loggedin = isset($_SESSION['userID']) && $_SESSION['userID'] > 0;
                $userID   = $loggedin ? (int)$_SESSION['userID'] : 0;

                $commentUserID = (int)$row['userID'];
                $profileUrl = SeoUrlHandler::convertToSeoUrl('index.php?site=profile&userID=' . $commentUserID);
                $username = '<a href="' . htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') . '">
                    <img src="' . getavatar($commentUserID) . '" class="img-fluid align-middle rounded me-1" style="height: 23px; width: 23px;" alt="' . getusername($commentUserID) . '">
                    <strong>' . getusername($commentUserID) . '</strong>
                </a>';

                $canDelete = ($userID == $row['userID'] || has_role($userID, 'Admin'));

                if ($canDelete) {
                    $deleteLink = '<a href="index.php?site=news&action=deletecomment&id=' . (int)$row['commentID'] . '&ref=' . urlencode($_SERVER['REQUEST_URI']) . '&token=' . $_SESSION['csrf_token'] . '" class="btn btn-sm btn-danger ms-2" onclick="return confirm(\'Kommentar wirklich löschen?\')">' . $languageService->get('delete') . '</a>';
                }

                echo '<li class="list-group-item border-bottom">
                    <div class="d-flex mt-4">
                        <div class="flex-shrink-0">
                            <a href="' . htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=profile&userID=' . (int)$row['userID']), ENT_QUOTES, 'UTF-8') . '">
                                <img src="' . getavatar((int)$row['userID']) . '" class="img-fluid rounded-circle me-3" style="height: 60px; width: 60px;" alt="' . htmlspecialchars($row['username']) . '">
                            </a>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <a href="' . htmlspecialchars(SeoUrlHandler::convertToSeoUrl('index.php?site=profile&userID=' . (int)$row['userID']), ENT_QUOTES, 'UTF-8') . '">
                                        <strong>'. htmlspecialchars($row['username']) .'</strong>
                                    </a>
                                    <span class="text-muted small">'. date('d.m.Y H:i', strtotime($row['date'])) .'</span>
                                </div>
                                <div>
                                    '. $deleteLink .'
                                </div>
                            </div>

                            <div class="mt-2 mb-4">
                                ' . nl2br(htmlspecialchars($row['comment'])) . '
                            </div>
                        </div>
                    </div>
                </li>';
            }
            echo '</ul></div>';

            // Kommentarformular
            if ($loggedin) {
                echo '<form method="POST" action="' . htmlspecialchars(SeoUrlHandler::buildPluginUrl('plugins_news', $id)) . '" class="mt-4">
                    <textarea class="form-control" name="comment" rows="6" required></textarea>
                    <input type="hidden" name="id" value="' . $id . '">
                    <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                    <button type="submit" name="submit_comment" class="btn btn-success mt-3">Kommentar abschicken</button>
                </form>';
            } else {
                echo '<div class="alert alert-warning">' . $languageService->get('must_login_comment') . '</div>';
            }
        }
    } else {
        http_response_code(404);
        echo $languageService->get('new_not_found');
    }
    return;
}

// NEWS-ÜBERSICHT (Startseite), wenn keine Kategorie & kein Detail
elseif ($action == "") {

    $newsPerPageFirst = 3;
    $newsPerPageOther = 3;

    $filterCategory = isset($_GET['filter_cat']) ? max((int)$_GET['filter_cat'], 0) : 0;
    $filterArchive = trim((string)($_GET['archive'] ?? ''));
    if ($filterArchive !== '' && !preg_match('/^\d{4}-\d{2}$/', $filterArchive)) {
        $filterArchive = '';
    }

    $monthMap = [
        1 => $languageService->get('jan'),  2 => $languageService->get('feb'),
        3 => $languageService->get('mar'),  4 => $languageService->get('apr'),
        5 => $languageService->get('may'),  6 => $languageService->get('jun'),
        7 => $languageService->get('jul'),  8 => $languageService->get('aug'),
        9 => $languageService->get('sep'), 10 => $languageService->get('oct'),
       11 => $languageService->get('nov'), 12 => $languageService->get('dec')
    ];

    $categories = [];
    $cats_result = safe_query("SELECT * FROM plugins_news_categories ORDER BY sort_order ASC, name ASC");
    while ($catRow = mysqli_fetch_assoc($cats_result)) {
        $categories[] = $catRow;
    }

    if (!empty($categories)) {
        $archives = [];
        $archive_result = safe_query("
            SELECT DATE_FORMAT(COALESCE(publish_at, updated_at), '%Y-%m') AS archive_key, COUNT(*) AS total
            FROM plugins_news
            WHERE is_active = 1 AND (publish_at IS NULL OR publish_at <= NOW())
            GROUP BY archive_key
            ORDER BY archive_key DESC
        ");
        while ($archiveRow = mysqli_fetch_assoc($archive_result)) {
            $archiveKey = (string)($archiveRow['archive_key'] ?? '');
            if ($archiveKey === '') {
                continue;
            }
            [$archiveYear, $archiveMonth] = array_pad(explode('-', $archiveKey), 2, '');
            $archiveLabel = ($monthMap[(int)$archiveMonth] ?? $archiveMonth) . ' ' . $archiveYear;
            $archives[] = [
                'key'   => $archiveKey,
                'label' => $archiveLabel,
                'count' => (int)($archiveRow['total'] ?? 0)
            ];
        }

        $page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;

        $whereClauses = [
            'is_active = 1',
            '(publish_at IS NULL OR publish_at <= NOW())'
        ];

        if ($filterCategory > 0) {
            $whereClauses[] = 'category_id = ' . $filterCategory;
        }
        if ($filterArchive !== '') {
            $archiveEsc = escape_string($filterArchive);
            $whereClauses[] = "DATE_FORMAT(COALESCE(publish_at, updated_at), '%Y-%m') = '{$archiveEsc}'";
        }

        $whereSql = implode("\n            AND ", $whereClauses);

        $total_news_result = safe_query("SELECT COUNT(*) AS total FROM plugins_news WHERE {$whereSql}");
        $total_news_row = mysqli_fetch_assoc($total_news_result);
        $total_news = (int)($total_news_row['total'] ?? 0);

        $newsAfterFirst = max(0, $total_news - $newsPerPageFirst);
        $totalPagesOther = ($newsPerPageOther > 0) ? ceil($newsAfterFirst / $newsPerPageOther) : 0;
        $total_pages = ($total_news > $newsPerPageFirst) ? 1 + $totalPagesOther : 1;
        if ($total_pages < 1) {
            $total_pages = 1;
        }
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        if ($page === 1) {
            $offset = 0;
            $limit = $newsPerPageFirst;
        } else {
            $offset = $newsPerPageFirst + ($page - 2) * $newsPerPageOther;
            $limit = $newsPerPageOther;
        }

        $news_result = safe_query("
            SELECT *
            FROM plugins_news
            WHERE {$whereSql}
            ORDER BY sort_order ASC, COALESCE(publish_at, updated_at) DESC, id DESC
            LIMIT $offset, $limit
        ");

        $categoryOptions = '<option value="0"' . ($filterCategory === 0 ? ' selected' : '') . '>' . $languageService->get('filter_all_categories') . '</option>';
        foreach ($categories as $categoryRow) {
            $catId = (int)($categoryRow['id'] ?? 0);
            $catName = htmlspecialchars((string)($categoryRow['name'] ?? ''));
            $selected = ($filterCategory === $catId) ? ' selected' : '';
            $categoryOptions .= '<option value="' . $catId . '"' . $selected . '>' . $catName . '</option>';
        }

        $archiveOptions = '<option value=""' . ($filterArchive === '' ? ' selected' : '') . '>' . $languageService->get('filter_all_archives') . '</option>';
        foreach ($archives as $archive) {
            $selected = ($filterArchive === $archive['key']) ? ' selected' : '';
            $archiveOptions .= '<option value="' . htmlspecialchars($archive['key']) . '"' . $selected . '>'
                . htmlspecialchars($archive['label']) . ' (' . (int)$archive['count'] . ')</option>';
        }

        $filterData = [
            'filter_category_label'   => $languageService->get('filter_category'),
            'filter_archive_label'    => $languageService->get('filter_archive'),
            'filter_submit'           => $languageService->get('sort'),
            'filter_reset'            => $languageService->get('filter_reset'),
            'filter_category_options' => $categoryOptions,
            'filter_archive_options'  => $archiveOptions,
            'reset_url'               => $newsOverviewUrl
        ];

        echo $tpl->loadTemplate("news", "filters", $filterData, 'plugin');

        if (mysqli_num_rows($news_result) > 0) {
            $data_array = [
                'title_categories' => $languageService->get('title_categories'),
            ];

            echo $tpl->loadTemplate("news", "category", $data_array, 'plugin');
            echo $tpl->loadTemplate("news", "content_all_head", $data_array, 'plugin');

            while ($new = mysqli_fetch_assoc($news_result)) {
                $timestamp = news_release_timestamp($new);

                $tag       = date("d", $timestamp);
                $monatNum  = (int)date("n", $timestamp);
                $year      = date("Y", $timestamp);
                $monatname = $monthMap[$monatNum] ?? date("M", $timestamp);

                $id    = (int)$new['id'];
                $title = news_lang_get_field($_database, $id, 'title', $newsLang, (string)($new['title'] ?? ''));
                $contentLong = news_render_html_content(
                    news_lang_get_field($_database, $id, 'content', $newsLang, (string)($new['content'] ?? ''))
                );

                $catID = (int)$new['category_id'];
                $cat_query = safe_query("SELECT name, slug, image FROM plugins_news_categories WHERE id = $catID LIMIT 1");
                $cat = mysqli_fetch_assoc($cat_query) ?: [];
                $cat_name = $cat['name'] ?? '';

                $catUrl = SeoUrlHandler::buildPluginUrl('plugins_news_categories', $catID);
                $new_catname = '<a href="' . htmlspecialchars($catUrl) . '"><strong style="font-size: 12px">' . htmlspecialchars($cat_name) . '</strong></a>';

                $url_watch_seo = SeoUrlHandler::buildPluginUrl('plugins_news', $id);

                $profileUrl = SeoUrlHandler::convertToSeoUrl("index.php?site=profile&userID=" . (int)$new['userID']);
                $username = '<a href="' . htmlspecialchars($profileUrl) . '">
                                <img src="' . htmlspecialchars(getavatar($new['userID'])) . '"
                                     class="img-fluid align-middle rounded me-1"
                                     style="height: 23px; width: 23px;"
                                     alt="' . htmlspecialchars(getusername($new['userID'])) . '">
                                <strong>' . htmlspecialchars(getusername($new['userID'])) . '</strong>
                             </a>';

                $short_title = mb_strlen($title) > 70 ? mb_substr($title, 0, 70) . '...' : $title;

                if (!function_exists('truncateHtml')) {
                    function truncateHtml(string $text, int $length = 150, string $ending = '...', bool $considerHtml = true): string {
                        $plain = strip_tags($text);
                        if (mb_strlen($plain) <= $length) return $text;
                        return mb_substr($plain, 0, $length) . $ending;
                    }
                }

                $short_content = truncateHtml($contentLong, 150);

                $image = !empty($cat['image'])
                    ? "/includes/plugins/news/images/news_categories/" . $cat['image']
                    : "/includes/plugins/news/images/no-image.jpg";

                $data_array = [
                    'name'      => $new_catname,
                    'title'     => htmlspecialchars($short_title),
                    'content'   => $short_content,
                    'image'     => $image,
                    'username'  => $username,
                    'url_watch' => $url_watch_seo,
                    'tag'       => $tag,
                    'monat'     => $monatname,
                    'year'      => $year,
                    'by'        => $languageService->get('by'),
                    'read_more' => $languageService->get('read_more'),
                ];

                echo $tpl->loadTemplate("news", "content_all", $data_array, 'plugin');
            }
        } else {
            echo '<div class="alert alert-info">' . $languageService->get('info_no_news_found') . '</div>';
        }

        echo $tpl->loadTemplate("news", "content_all_foot", $data_array ?? [], 'plugin');

        if ($total_pages > 1) {
            $pageParams = ['site' => 'news'];
            if ($filterCategory > 0) {
                $pageParams['filter_cat'] = $filterCategory;
            }
            if ($filterArchive !== '') {
                $pageParams['archive'] = $filterArchive;
            }
            $baseUrl = SeoUrlHandler::convertToSeoUrl('index.php?' . http_build_query($pageParams));
            $baseUrl = rtrim($baseUrl, '/') . '/';
            echo $tpl->renderPagination($baseUrl, (int)$page, (int)$total_pages);
        }
    } else {
        echo '<div class="alert alert-info">' . $languageService->get('info_no_news_categories_found') . '</div>';
    }
}


