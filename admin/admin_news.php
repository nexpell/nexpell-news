<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\NavigationUpdater;// SEO Anpassung
use nexpell\AccessControl;
global $languageService;

// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('news');

// Parameter zuerst holen
$action = $_GET['action'] ?? '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (ob_get_length()) ob_end_clean();

    require_once __DIR__ . '/../../../../system/config.inc.php';
    require_once __DIR__ . '/../../../../system/core/init.php';
    global $_database;

    $id = (int)$_GET['id'];
    if ($id <= 0) {
        nx_alert('danger', 'alert_invalid_id', false);
        return;
    }

    try {
        // Bild + Content + Titel abrufen
        $stmt = $_database->prepare("SELECT banner_image, content, title FROM plugins_news WHERE id = ?");
        if (!$stmt) throw new Exception('alert_db_error');

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($bannerImageRaw, $contentHtml, $title);
        $found = $stmt->fetch();
        $stmt->close();

        if (!$found) {
            nx_alert('danger', 'alert_not_found', false);
            return;
        }

        $imageFilename = '';
        if (!empty($bannerImageRaw)) {
            $imageFilename = basename($bannerImageRaw);
        }

        // Hauptbild löschen (falls vorhanden)
        if (!empty($imageFilename)) {
            $possiblePaths = [
                __DIR__ . '/../../../../images/news/' . $imageFilename,
                __DIR__ . '/../../../../includes/plugins/news/images/news_images/' . $imageFilename
            ];
            foreach ($possiblePaths as $imagePath) {
                if (is_file($imagePath)) {
                    @unlink($imagePath);
                    break;
                }
            }
        }

        $contentPool = [];
        if (!empty($contentHtml)) {
            $contentPool[] = (string)$contentHtml;
        }
        if (news_lang_table_ready($_database)) {
            $keyEsc = mysqli_real_escape_string($_database, 'news_' . $id . '_content');
            $resLangContent = mysqli_query($_database, "
                SELECT content
                FROM plugins_news_lang
                WHERE content_key='{$keyEsc}'
            ");
            if ($resLangContent) {
                while ($langRow = mysqli_fetch_assoc($resLangContent)) {
                    $txt = (string)($langRow['content'] ?? '');
                    if ($txt !== '') {
                        $contentPool[] = $txt;
                    }
                }
            }
        }

        // Eingebettete Content-Bilder löschen
        foreach ($contentPool as $htmlChunk) {
            preg_match_all(
                '#/includes/plugins/news/images/news_images/([a-zA-Z0-9._-]+\.(?:png|jpg|jpeg|gif|webp))#i',
                $htmlChunk,
                $matches
            );

            if (!empty($matches[1])) {
                foreach ($matches[1] as $filename) {
                    $imgPath = __DIR__ . '/../../../../includes/plugins/news/images/news_images/' . $filename;
                    if (is_file($imgPath)) {
                        @unlink($imgPath);
                    }
                }
            }
        }

        if (news_lang_table_ready($_database)) {
            $kTitle = mysqli_real_escape_string($_database, 'news_' . $id . '_title');
            $kContent = mysqli_real_escape_string($_database, 'news_' . $id . '_content');
            $kLinkName = mysqli_real_escape_string($_database, 'news_' . $id . '_link_name');
            mysqli_query($_database, "
                DELETE FROM plugins_news_lang
                WHERE content_key IN ('{$kTitle}', '{$kContent}', '{$kLinkName}')
            ");
        }

        // Datensatz löschen
        $stmtDel = $_database->prepare("DELETE FROM plugins_news WHERE id = ?");
        if (!$stmtDel) throw new Exception('alert_db_error');

        $stmtDel->bind_param("i", $id);
        $ok = $stmtDel->execute();
        $stmtDel->close();

        if ($ok) {
            nx_audit_delete('admin_news', (string)$id, $title ?: (string)$id, 'admincenter.php?site=admin_news');
            nx_alert('success', 'alert_deleted', false);
        } else {
            nx_alert('danger', 'alert_db_error', false);
        }

    } catch (Throwable $e) {
        nx_alert('danger', 'alert_db_error', false);
    }
}
$plugin_path = __DIR__ . '/../';
$filepath = $plugin_path."images/news_images/";

// Parameter aus URL lesen
$action = $_GET['action'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortDir = ($_GET['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// Max News pro Seite
$perPage = 10;

// Whitelist für Sortierung
$allowedSorts = ['title', 'created_at'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'created_at';
}

$languages = [];
$resLang = mysqli_query($_database, "SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
if ($resLang) {
    while ($row = mysqli_fetch_assoc($resLang)) {
        $languages[strtolower((string)$row['iso_639_1'])] = (string)$row['name_de'];
    }
}
if (empty($languages)) {
    $languages = ['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'];
}

if (!empty($_SESSION['news_active_lang'])) {
    $currentLang = strtolower((string)$_SESSION['news_active_lang']);
    unset($_SESSION['news_active_lang']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['active_lang'])) {
    $currentLang = strtolower((string)$_POST['active_lang']);
} elseif (!empty($_SESSION['language'])) {
    $currentLang = strtolower((string)$_SESSION['language']);
} else {
    $currentLang = strtolower((string)$languageService->detectLanguage());
}
if (!isset($languages[$currentLang])) {
    $currentLang = (string)array_key_first($languages);
}

$uploadDir = __DIR__ . '/../images/'; // für allgemeine Uploads

function news_lang_table_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $res = mysqli_query($db, "SHOW TABLES LIKE 'plugins_news_lang'");
    $ready = ($res && mysqli_num_rows($res) > 0);
    return $ready;
}

function news_immediate_release_column_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $res = mysqli_query($db, "SHOW COLUMNS FROM plugins_news LIKE 'immediate_release'");
    $ready = ($res && mysqli_num_rows($res) > 0);
    return $ready;
}

function news_lang_get_all(mysqli $db, int $newsId, array $languages): array
{
    $out = ['title' => [], 'content' => [], 'link_name' => []];
    if (!news_lang_table_ready($db) || $newsId <= 0) {
        return $out;
    }

    $newsId = (int)$newsId;
    $res = mysqli_query($db, "
        SELECT content_key, language, content
        FROM plugins_news_lang
        WHERE content_key IN ('news_{$newsId}_title','news_{$newsId}_content','news_{$newsId}_link_name')
    ");
    if (!$res) {
        return $out;
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $key = (string)($row['content_key'] ?? '');
        $iso = strtolower((string)($row['language'] ?? ''));
        $val = (string)($row['content'] ?? '');

        if ($key === "news_{$newsId}_title") {
            $out['title'][$iso] = $val;
        } elseif ($key === "news_{$newsId}_content") {
            $out['content'][$iso] = $val;
        } elseif ($key === "news_{$newsId}_link_name") {
            $out['link_name'][$iso] = $val;
        }
    }

    foreach ($languages as $iso => $_label) {
        foreach (['title', 'content', 'link_name'] as $field) {
            if (!array_key_exists($iso, $out[$field])) {
                $out[$field][$iso] = '';
            }
        }
    }

    return $out;
}

function news_lang_upsert(mysqli $db, int $newsId, array $valuesByFieldLang, array $languages): void
{
    if (!news_lang_table_ready($db) || $newsId <= 0) {
        return;
    }

    foreach (['title', 'content', 'link_name'] as $field) {
        foreach ($languages as $iso => $_label) {
            $key = 'news_' . (int)$newsId . '_' . $field;
            $keyEsc = mysqli_real_escape_string($db, $key);
            $isoEsc = mysqli_real_escape_string($db, (string)$iso);
            $valEsc = mysqli_real_escape_string($db, (string)($valuesByFieldLang[$field][$iso] ?? ''));
            mysqli_query($db, "
                INSERT INTO plugins_news_lang (content_key, language, content, updated_at)
                VALUES ('{$keyEsc}', '{$isoEsc}', '{$valEsc}', NOW())
                ON DUPLICATE KEY UPDATE
                    content = VALUES(content),
                    updated_at = NOW()
            ");
        }
    }
}

function makeUniqueSlug($slug, $id = 0) {
    global $_database;

    $baseSlug = $slug;
    $i = 1;

    $stmt = $_database->prepare("SELECT id FROM plugins_news WHERE slug = ? AND id != ?");
    $stmt->bind_param("si", $slug, $id);

    while (true) {
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            break; // Slug ist frei
        }

        $slug = $baseSlug . '-' . $i;
        $i++;
    }

    $stmt->close();
    return $slug;
}

function makeUniqueSlugCategory(string $slug, int $ignoreId = 0): string {
    global $_database;
    $base = $slug;
    $i = 1;
    while (true) {
        if ($ignoreId > 0) {
            $stmt = $_database->prepare("SELECT id FROM plugins_news_categories WHERE slug = ? AND id != ?");
            $stmt->bind_param("si", $slug, $ignoreId);
        } else {
            $stmt = $_database->prepare("SELECT id FROM plugins_news_categories WHERE slug = ?");
            $stmt->bind_param("s", $slug);
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            return $slug;
        }
        $stmt->close();
        $slug = $base . '-' . $i++;
    }
}

function generateSlug(string $text): string {
    // Umlaute & Sonderzeichen ersetzen
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    // Kleinbuchstaben
    $text = strtolower($text);
    // Nicht alphanumerische Zeichen durch Bindestrich
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    // Mehrfache Bindestriche entfernen
    $text = preg_replace('/-+/', '-', $text);
    // Bindestriche am Rand entfernen
    $text = trim($text, '-');
    return $text ?: 'news-' . time(); // Fallback
}

// News hinzufügen / bearbeiten
if (($action ?? '') === "add" || ($action ?? '') === "edit") {
    $id = intval($_GET['id'] ?? 0);
    $isEdit = $id > 0;

    // Default-Daten
    $data = [
        'category_id'    => 0,
        'title'          => '',
        'slug'           => '',
        'link_name'      => '',
        'link'           => '',
        'content'        => '',
        'sort_order'     => 0,
        'updated_at'     => '',
        'publish_at'     => '',
        'immediate_release' => 0,
        'is_active'      => 0,
        'allow_comments' => 0,
    ];

    $oldSlug = ''; // alter Slug für SEO-Warnung

    // Beim Edit vorhandene Daten laden
    if ($isEdit) {
        $immediateSelect = news_immediate_release_column_ready($_database) ? 'immediate_release' : '0 AS immediate_release';
        $stmt = $_database->prepare("
            SELECT category_id, title, slug, link_name, link, content, sort_order, updated_at, publish_at, {$immediateSelect}, is_active, allow_comments
            FROM plugins_news
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result(
            $data['category_id'],
            $data['title'],
            $data['slug'],
            $data['link_name'],
            $data['link'],
            $data['content'],
            $data['sort_order'],
            $data['updated_at'],
            $data['publish_at'],
            $data['immediate_release'],
            $data['is_active'],
            $data['allow_comments']
        );
        if (!$stmt->fetch()) {
            echo "<div class='alert alert-danger'>News nicht gefunden.</div>";
            exit;
        }
        $stmt->close();

        $oldSlug = $data['slug']; // merken für späteren Vergleich
    }

    if ($isEdit && !empty($data['immediate_release'])) {
        if (!empty($data['publish_at'])) {
            $data['publish_at'] = '';
        }
    }

    $error = '';
    $slugWarning = '';

    $dataLang = [
        'title' => [],
        'content' => [],
        'link_name' => [],
    ];
    foreach ($languages as $iso => $_label) {
        $dataLang['title'][$iso] = (string)($data['title'] ?? '');
        $dataLang['content'][$iso] = (string)($data['content'] ?? '');
        $dataLang['link_name'][$iso] = (string)($data['link_name'] ?? '');
    }

    if ($isEdit && news_lang_table_ready($_database)) {
        $existingLang = news_lang_get_all($_database, $id, $languages);
        foreach (['title', 'content', 'link_name'] as $field) {
            foreach ($languages as $iso => $_label) {
                if (isset($existingLang[$field][$iso]) && $existingLang[$field][$iso] !== '') {
                    $dataLang[$field][$iso] = (string)$existingLang[$field][$iso];
                }
            }
        }
    }

    // Hilfsfunktion: Alle Formulardaten als hidden-Felder ausgeben
    function hiddenFields(array $data, string $prefix = ''): string {
        $html = '';
        foreach ($data as $k => $v) {
            $name = $prefix === '' ? (string)$k : $prefix . '[' . (string)$k . ']';
            if (is_array($v)) {
                $html .= hiddenFields($v, $name);
                continue;
            }
            $html .= '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        return $html;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cat            = intval($_POST['category_id'] ?? 0);
        $activeLang     = strtolower(trim((string)($_POST['active_lang'] ?? $currentLang)));
        if (!isset($languages[$activeLang])) {
            $activeLang = $currentLang;
        }
        $_SESSION['news_active_lang'] = $activeLang;

        $titleByLang = $_POST['title_lang'] ?? [];
        if (!is_array($titleByLang)) {
            $titleByLang = [];
        }
        $contentByLang = $_POST['content_lang'] ?? [];
        if (!is_array($contentByLang)) {
            $contentByLang = [];
        }
        $linkNameByLang = $_POST['link_name_lang'] ?? [];
        if (!is_array($linkNameByLang)) {
            $linkNameByLang = [];
        }

        if (empty($titleByLang) && isset($_POST['title'])) {
            $titleByLang[$activeLang] = (string)$_POST['title'];
        }
        if (empty($contentByLang) && isset($_POST['content'])) {
            $contentByLang[$activeLang] = (string)$_POST['content'];
        }
        if (empty($linkNameByLang) && isset($_POST['link_name'])) {
            $linkNameByLang[$activeLang] = (string)$_POST['link_name'];
        }

        $title          = trim((string)($titleByLang[$activeLang] ?? ''));
        $slugInput      = trim($_POST['slug'] ?? '');
        $link_name      = trim((string)($linkNameByLang[$activeLang] ?? ''));
        $link           = trim($_POST['link'] ?? '');
        $sort_order     = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : intval($data['sort_order'] ?? 0);

        // Release-Datum/Uhrzeit (optional). Leer = sofort.
        // HTML datetime-local liefert "YYYY-MM-DDTHH:MM"
        $publish_at_raw = trim($_POST['publish_at'] ?? '');
        $publishSql = "NOW()";
        $immediateRelease = 1;
        if ($publish_at_raw !== '') {
            $publish_at = str_replace('T', ' ', $publish_at_raw);
            if (strlen($publish_at) === 16) {
                $publish_at .= ':00';
            }
            $publishSql = "'" . escape($publish_at) . "'";
            $immediateRelease = 0;
        }

        $data['category_id'] = $cat;
        $data['publish_at'] = $publish_at_raw !== '' ? $publish_at : '';
        $data['immediate_release'] = $immediateRelease;

        $is_active      = isset($_POST['is_active']) ? 1 : 0;
        $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
        $content        = (string)($contentByLang[$activeLang] ?? '');
        $contentSql     = escape($content);
        $confirmChange  = isset($_POST['confirm_slug_change']); // kommt vom Warn-Dialog

        if ($content === '') {
            nx_alert('warning', 'alert_missing_required', false);
            return;
        }

        // Slug automatisch generieren, wenn leer
        if ($slugInput === '') {
            $slugInput = generateSlug($title);
        }

        // Slug automatisch generieren, wenn leer
        if ($slugInput === '') {
            if ($title !== '') {
                $slugInput = generateSlug($title);
            } else {
                // Fallback: Slug aus Timestamp, falls kein Titel vorhanden
                $slugInput = 'news-' . time();
            }
        }

        // Prüfen ob sich der Slug beim Edit geändert hat (nur wenn noch nicht bestätigt)
        if ($isEdit && !$confirmChange && $oldSlug !== '' && $slugInput !== $oldSlug) {
            echo '<div class="alert alert-warning">' . $languageService->get('info_seo_slug') . '</div>';
            echo '<form method="post">';
            echo hiddenFields($_POST);
            echo '<input type="hidden" name="confirm_slug_change" value="1">';
            echo '<button type="submit" class="btn btn-danger">' . $languageService->get('btn_go_forward') . '</button> ';
            echo '<a href="admincenter.php?site=admin_news&action=edit&id=' . (int)$id . '" class="btn btn-secondary">' . $languageService->get('btn_back') . '</a>';
            echo '</form>';
            exit;
        }

        // Slug eindeutig machen
        $slug = makeUniqueSlug($slugInput, $isEdit ? $id : 0);

        if (!$error) {
            $savedNewsId = $isEdit ? (int)$id : 0;
            if ($isEdit) {
                safe_query("
                    UPDATE plugins_news SET
                        category_id    = '" . (int)$cat . "',
                        title          = '" . escape($title) . "',
                        slug           = '" . escape($slug) . "',
                        link_name      = '" . escape($link_name) . "',
                        link           = '" . escape($link) . "',
                        content        = '" . $contentSql . "',
                        sort_order     = '" . (int)$sort_order . "',
                        updated_at     = NOW(),
                        publish_at     = " . $publishSql . ",
                        " . (news_immediate_release_column_ready($_database) ? "immediate_release = '" . (int)$immediateRelease . "'," : "") . "
                        is_active      = '" . (int)$is_active . "',
                        allow_comments = '" . (int)$allow_comments . "'
                    WHERE id = '" . (int)$id . "'
                ");
            } else {
                $userID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
                $insertColumns = "category_id, title, slug, link, link_name, content, sort_order, publish_at, updated_at";
                $insertValues = "
                        '" . (int)$cat . "',
                        '" . escape($title) . "',
                        '" . escape($slug) . "',
                        '" . escape($link) . "',
                        '" . escape($link_name) . "',
                        '" . $contentSql . "',
                        '" . (int)$sort_order . "',
                        " . $publishSql . ",
                        NOW()";
                if (news_immediate_release_column_ready($_database)) {
                    $insertColumns .= ", immediate_release";
                    $insertValues .= ",
                        '" . (int)$immediateRelease . "'";
                }
                $insertColumns .= ", userID, is_active, allow_comments";
                $insertValues .= ",
                        '" . (int)$userID . "',
                        '" . (int)$is_active . "',
                        '" . (int)$allow_comments . "'";
                safe_query("
                    INSERT INTO plugins_news
                    (" . $insertColumns . ")
                    VALUES
                    (
                        " . $insertValues . "
                    )
                ");
                $savedNewsId = (int)($_database->insert_id ?? 0);
            }

            if ($savedNewsId > 0) {
                foreach ($languages as $iso => $_label) {
                    if (!isset($titleByLang[$iso])) {
                        $titleByLang[$iso] = '';
                    }
                    if (!isset($contentByLang[$iso])) {
                        $contentByLang[$iso] = '';
                    }
                    if (!isset($linkNameByLang[$iso])) {
                        $linkNameByLang[$iso] = '';
                    }
                }
                news_lang_upsert($_database, $savedNewsId, [
                    'title' => $titleByLang,
                    'content' => $contentByLang,
                    'link_name' => $linkNameByLang,
                ], $languages);
            }

            $admin_file = basename(__FILE__, '.php');
            echo NavigationUpdater::updateFromAdminFile($admin_file);

            nx_redirect('admincenter.php?site=admin_news', 'success', 'alert_saved', false);
        }
    }
    ?>

    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-journal-text"></i> <span><?=$languageService->get('title_news') ?></span>
                <small class="text-muted"><?= $languageService->get($isEdit ? 'edit' : 'add') ?></small>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?= $slugWarning ?>
            <form method="post" class="needs-validation" novalidate>
            <div class="nx-lang-editor">
            <input type="hidden" name="active_lang" id="active_lang" value="<?= htmlspecialchars($currentLang, ENT_QUOTES, 'UTF-8') ?>">

            <!-- Kategorie + Titel + Release-Datum/Uhrzeit -->
            <div class="row mb-3 news-editor-top-row">

                <div class="news-editor-title-head">
                        <div class="btn-group btn-group-sm news-lang-switch" id="lang-switch">
                            <?php foreach ($languages as $iso => $label): ?>
                                <button type="button"
                                        class="btn <?= $iso === $currentLang ? 'btn-primary' : 'btn-secondary' ?>"
                                        data-lang="<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= strtoupper(htmlspecialchars($iso, ENT_QUOTES, 'UTF-8')) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>


                <div class="col-md-3">
                    <label for="category_id" class="form-label"><?=$languageService->get('label_category') ?>:</label>
                    <select class="form-select" name="category_id" id="category_id" required>
                        <option value="" <?= empty($data['category_id']) ? 'selected' : '' ?> disabled hidden><?=$languageService->get('select_choose') ?></option>
                        <?php
                        $stmtCat = $_database->prepare("SELECT id, name FROM plugins_news_categories ORDER BY name");
                        $stmtCat->execute();
                        $resCat = $stmtCat->get_result();
                        while ($cat = $resCat->fetch_assoc()) {
                            $selected = ($cat['id'] == $data['category_id']) ? 'selected' : '';
                            echo '<option value="' . (int)$cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
                        }
                        $stmtCat->close();
                        ?>
                    </select>
                    <div class="invalid-feedback"><?=$languageService->get('invalid_choose_catg') ?></div>
                </div>

                <div class="col-md-6 d-flex flex-column">
                    
                    <label for="nx-title-input" class="form-label"><?=$languageService->get('label_title') ?>:</label>
                        
                    <input class="form-control" type="text" name="title" id="nx-title-input" value="<?= htmlspecialchars((string)($dataLang['title'][$currentLang] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    <?php foreach ($languages as $iso => $_label): ?>
                        <input type="hidden" name="title_lang[<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>]" id="title_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)($dataLang['title'][$iso] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                    <div class="invalid-feedback"><?=$languageService->get('invalid_title') ?></div>
                </div>
                <div class="col-md-3">
                    <label for="publish_at" class="form-label"><?=$languageService->get('label_release_date') ?>:</label>
                    <input class="form-control" type="datetime-local" name="publish_at" id="publish_at"
                           value="<?= !empty($data['publish_at']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($data['publish_at']))) : '' ?>">
                    <div class="form-text"><?=$languageService->get('formtext_release_date') ?></div>
                </div>
            </div>

            <!-- Inhalt -->
            <div class="mb-3">
                <label for="nx-editor-main" class="form-label"><?=$languageService->get('label_content') ?>:</label>
                <textarea id="nx-editor-main" name="content" class="form-control" data-editor="nx_editor" rows="10" style="resize: vertical; width: 100%;" required><?= htmlspecialchars((string)($dataLang['content'][$currentLang] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <?php foreach ($languages as $iso => $_label): ?>
                    <input type="hidden" name="content_lang[<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>]" id="content_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)($dataLang['content'][$iso] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
                <div class="invalid-feedback"><?=$languageService->get('invalid_content') ?></div>


            </div>
            <!-- SEO-Slug + Interner Linkname + Interner Link + Sortierung -->
            <div class="row mb-3 news-editor-top-row">
                <div class="col-md-4">
                    <label for="slug" class="form-label"><?=$languageService->get('label_seo_slug') ?>:</label>
                    <input class="form-control" type="text" name="slug" id="slug" value="<?= htmlspecialchars($data['slug']) ?>">
                    <div class="form-text"><?=$languageService->get('formtext_seo_slug') ?></div>
                </div>

                <div class="col-md-4">
                    <label for="link_name_main" class="form-label"><?=$languageService->get('label_linkname') ?>:</label>
                    <input class="form-control" type="text" name="link_name" id="link_name_main" data-nx-lang-hidden-prefix="news_link_name_" value="<?= htmlspecialchars((string)($dataLang['link_name'][$currentLang] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($languages as $iso => $_label): ?>
                        <input type="hidden" name="link_name_lang[<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>]" id="news_link_name_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)($dataLang['link_name'][$iso] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                </div>

                <div class="col-md-4">
                    <label for="link" class="form-label"><?=$languageService->get('label_link') ?>:</label>
                    <input class="form-control" type="text" name="link" id="link" value="<?= htmlspecialchars($data['link']) ?>">
                </div>
            </div>


            <!-- Toggles -->
            <div class="mb-4">
                <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $data['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active"><?=$languageService->get('active') ?></label>
                </div><br>

                <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="allow_comments" id="allow_comments" <?= $data['allow_comments'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="allow_comments"><?=$languageService->get('label_allow_comments') ?></label>
                </div>
            </div>

            <!-- Buttons -->
            <button type="submit" class="btn btn-primary"><?= $languageService->get('save') ?></button>
            </div>
            </form>
        </div>
    </div>


    <?php


} elseif (($action ?? '') === 'addcategory' || ($action ?? '') === 'editcategory') {
    $isEdit = $action === 'editcategory';
    $errorCat = '';
    $cat_name = '';
    $cat_description = '';
    $cat_image = ''; // neues Feld
    $cat_slug = '';  // Slug-Feld
    $editId = 0;
    $slugWarning = '';

    if ($isEdit) {
        $editId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $stmt = $_database->prepare("SELECT name, description, image, slug FROM plugins_news_categories WHERE id = ?");
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $catData = $result->fetch_assoc();
        $stmt->close();

        if ($catData) {
            $cat_name = $catData['name'];
            $cat_description = $catData['description'];
            $cat_image = $catData['image']; // aktuelles Bild
            $cat_slug = $catData['slug'];
        } else {
            $errorCat = $languageService->get('error_catg_not_found');
        }
    }

    // Speichern
    // Slug-Vergleich: alten Slug beim Edit merken
    $oldSlug = $isEdit ? $cat_slug : '';
    $confirmChange = isset($_POST['confirm_slug_change']); // Wurde schon bestätigt?

    // Speichern
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cat_name'])) {

        $cat_name = trim($_POST['cat_name']);
        $cat_description = trim($_POST['cat_description'] ?? '');
        $cat_image_path = $cat_image; // altes Bild
        $cat_slug_input = trim($_POST['cat_slug'] ?? '');
        $confirmChange = isset($_POST['confirm_slug_change']);

        // Slug automatisch erstellen
        if ($cat_slug_input === '') {
            $cat_slug_input = $cat_name !== '' ? generateSlug($cat_name) : 'category-' . time();
        }

        // Warnung beim geänderten Slug (nur Edit & noch nicht bestätigt)
        if ($isEdit && !$confirmChange && $oldSlug !== '' && $cat_slug_input !== $oldSlug) {
            $slugWarning = '
                <div class="alert alert-warning">
                    ' . $languageService->get('info_seo_slug') . '
                </div>

                <form method="post">
                    " . hiddenFields($_POST) . "
                    <input type="hidden" name="confirm_slug_change" value="1">
                    <button type="submit" class="btn btn-danger">' . $languageService->get('btn_go_forward') . '</button>
                    <a href="admincenter.php?site=admin_news&action=edit&id={$id}" class="btn btn-secondary">' . $languageService->get('btn_back') . '</a>
                </form>
            ';
        }

        // Wenn Warnung angezeigt wird, abbrechen
        if ($slugWarning === '') {

            // Slug eindeutig machen
            $cat_slug = makeUniqueSlugCategory($cat_slug_input, $isEdit ? $editId : 0);

            // Bild-Upload
            if (isset($_FILES['cat_image']) && $_FILES['cat_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/includes/plugins/news/images/news_categories/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = time() . '_' . basename($_FILES['cat_image']['name']);
                $targetFile = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['cat_image']['tmp_name'], $targetFile)) {
                    $cat_image_path = $filename;
                } else {
                    nx_alert('danger', 'alert_upload_failed', false);
                    return;
                }
            }

            if ($cat_name === '') {
                nx_alert('warning', 'alert_missing_required', false);
                return;
            } else {
                if ($isEdit && $editId > 0) {
                    $stmt = $_database->prepare(
                        "UPDATE plugins_news_categories SET name = ?, slug = ?, description = ?, image = ? WHERE id = ?"
                    );
                    $stmt->bind_param("ssssi", $cat_name, $cat_slug, $cat_description, $cat_image_path, $editId);
                    if ($stmt->execute() && $stmt->affected_rows > 0) nx_audit_update('admin_news', (string)$editId, true, $cat_name, 'admincenter.php?site=admin_news&action=categories');
                    $stmt->close();
                } else {
                    $stmt = $_database->prepare(
                        "INSERT INTO plugins_news_categories (name, slug, description, image) VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssss", $cat_name, $cat_slug, $cat_description, $cat_image_path);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $newId = (int)($_database->insert_id ?? 0);
                        nx_audit_create('admin_news', (string)$newId, $cat_name, 'admincenter.php?site=admin_news&action=categories');
                    }
                    $stmt->close();
                }

                nx_redirect('admincenter.php?site=admin_news&action=categories', 'success', 'alert_saved', false);
            }
        }
    }   
?>

<div class="card shadow-sm mt-4">
    <div class="card-header">
        <div class="card-title">
            <i class="bi bi-tags"></i> <?= $languageService->get($isEdit ? 'edit_category' : 'add_category') ?>
        </div>
    </div>
    <div class="card-body">
        <?= $slugWarning ?>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="cat_name" class="form-label"><?= $languageService->get('label_categoryname') ?>:</label>
                <input type="text"
                        class="form-control"
                        id="cat_name"
                        name="cat_name"
                        value="<?= htmlspecialchars($cat_name) ?>"
                        required>
            </div>
            <div class="mb-3">
                <label for="cat_slug" class="form-label"><?= $languageService->get('label_seo_slug') ?>:</label>
                <input type="text"
                        class="form-control"
                        id="cat_slug"
                        name="cat_slug"
                        value="<?= htmlspecialchars($cat_slug) ?>"
                        placeholder="<?= $languageService->get('placeholder_seo_empty') ?>">
            </div>
            <div class="mb-3">
                <label for="cat_description" class="form-label"><?= $languageService->get('description') ?>:</label>
                <textarea class="form-control"
                            id="cat_description"
                            name="cat_description"
                            rows="3"><?= htmlspecialchars($cat_description) ?></textarea>
            </div>
            <div class="mb-3">
                <label for="cat_image" class="form-label"><?= $languageService->get('label_picture') ?>:</label>
                <input type="file" class="form-control" id="cat_image" name="cat_image">
                <?php if ($cat_image): ?>
                    <small class="text-muted"><?= $languageService->get('label_current_picture') ?>:</small><br>
                    <?php $basePath = '/includes/plugins/news/images/news_categories/'; ?>
                    <img src="<?= htmlspecialchars($basePath . $cat_image) ?>" alt="<?= $languageService->get('alt_catg_picture') ?>" style="height:60px;">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">
                <?= $languageService->get('save') ?>
            </button>
        </form>
    </div>
</div>

<?php
}

elseif (($action ?? '') === 'categories') {
    $errorCat = '';

    // Kategorie löschen
    if (isset($_GET['delcat'])) {
        $delcat = (int)$_GET['delcat'];

        $stmt = $_database->prepare("DELETE FROM plugins_news_categories WHERE id = ?");
        $stmt->bind_param("i", $delcat);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            nx_audit_delete('admin_news', (string)$delcat, (string)$delcat, 'admincenter.php?site=admin_news&action=categories');
            nx_redirect('admincenter.php?site=admin_news&action=categories', 'success', 'alert_deleted', false);
        }
        $stmt->close();

        nx_redirect('admincenter.php?site=admin_news&action=categories', 'danger', 'alert_not_found', false);
    }

    // Kategorien laden inkl. Beschreibung
    $result = $_database->query("SELECT id, name, description, image FROM plugins_news_categories ORDER BY name");
?>
    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <div class="card-title">
                <i class="bi bi-tags"></i> <?= $languageService->get('categories') ?>
            </div>
        </div>
        <div class="card-body">
            <a href="admincenter.php?site=admin_news&action=addcategory" class="btn btn-secondary mb-3">
                <?= $languageService->get('add_category') ?>
            </a>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= $languageService->get('id') ?></th>
                        <th><?= $languageService->get('th_picture') ?></th>
                        <th><?= $languageService->get('name') ?></th>
                        <th><?= $languageService->get('description') ?></th>
                        <th><?= $languageService->get('actions') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php while ($cat = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$cat['id'] ?></td>
                            <td>
                                <?php if (!empty($cat['image'])):
                                    $basePath = '/includes/plugins/news/images/news_categories/'; ?>
                                    <img src="<?= htmlspecialchars($basePath . $cat['image']) ?>" alt="Kategorie-Bild" style="height:60px;">
                                <?php else: ?>
                                    <span class="text-muted"><?= $languageService->get('info_no_picture') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><?= htmlspecialchars($cat['description']) ?></td>
                            <td>
                                <a href="admincenter.php?site=admin_news&action=editcategory&id=<?= (int)$cat['id'] ?>"
                                    class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto"><i class="bi bi-pencil-square"></i> <?= $languageService->get('edit') ?></a>

                                <?php
                                    $deleteUrl = 'admincenter.php?site=admin_news&action=categories&delcat=' . (int)$cat['id'];
                                ?>
                                <a
                                href="#"
                                class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto"
                                data-bs-toggle="modal"
                                data-bs-target="#confirmDeleteModal"
                                data-confirm-url="<?= htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-trash3"></i> <?= $languageService->get('delete') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
}

else {
$action = $_GET['action'] ?? 'list';

switch ($action) {

    // NEWS LISTE
    case 'list':
    default:
        $immediateSelect = news_immediate_release_column_ready($_database) ? 'a.immediate_release' : '0 AS immediate_release';
        if (news_lang_table_ready($_database)) {
            $langEsc = mysqli_real_escape_string($_database, $currentLang);
            $result = $_database->query("
                SELECT
                    a.id,
                    COALESCE(
                        (SELECT l.content FROM plugins_news_lang l WHERE l.content_key = CONCAT('news_', a.id, '_title') AND l.language = '{$langEsc}' LIMIT 1),
                        (SELECT l.content FROM plugins_news_lang l WHERE l.content_key = CONCAT('news_', a.id, '_title') AND l.language = 'en' LIMIT 1),
                        a.title
                    ) AS title,
                    a.sort_order, a.topnews_is_active, a.is_active,
                    a.updated_at,
                    a.publish_at,
                    {$immediateSelect},
                    CASE
                        WHEN a.publish_at <= NOW() THEN 1
                        ELSE 0
                    END AS is_published,
                    c.name AS category_name
                FROM plugins_news a
                LEFT JOIN plugins_news_categories c ON a.category_id = c.id
                ORDER BY a.sort_order ASC, COALESCE(a.publish_at, a.updated_at) DESC, a.id DESC
            ");
        } else {
            $result = $_database->query("
                SELECT a.id, a.title, a.sort_order, a.topnews_is_active, a.is_active,
                    a.updated_at,
                    a.publish_at,
                    {$immediateSelect},
                    CASE
                        WHEN a.publish_at <= NOW() THEN 1
                        ELSE 0
                    END AS is_published,
                    c.name AS category_name
                FROM plugins_news a
                LEFT JOIN plugins_news_categories c ON a.category_id = c.id
                ORDER BY a.sort_order ASC, COALESCE(a.publish_at, a.updated_at) DESC, a.id DESC
            ");
        }
    ?>

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-journal-text"></i> <span><?= $languageService->get('title_news') ?></span>
                    <small class="text-muted"><?= $languageService->get('overview') ?></small>
                </div>

                <a href="admincenter.php?site=admin_news&action=add" class="btn btn-secondary">
                    <?= $languageService->get('add') ?>
                </a>
                <a href="admincenter.php?site=admin_news&action=categories" class="btn btn-secondary">
                    <?= $languageService->get('categories') ?>
                </a>
            </div>

            <div class="card-body">
                <table class="table">
                    <thead>
                    <tr>
                        <th style="width:40px"></th>
                        <th><?= $languageService->get('id') ?></th>
                        <th><?= $languageService->get('label_title') ?></th>
                        <th><?= $languageService->get('category') ?></th>
                        <th><?= $languageService->get('th_release') ?></th>
                        <th><?= $languageService->get('th_top') ?></th>
                        <th><?= $languageService->get('active') ?></th>
                        <th><?= $languageService->get('actions') ?></th>
                        <th class="d-none"><?= $languageService->get('sort') ?></th>
                    </tr>
                    </thead>

                    <tbody id="news-sortable">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-id="<?= (int)$row['id'] ?>">
                            <td class="text-center cursor-move">
                                <i class="bi bi-list"></i>
                            </td>
                            <td><?= (int)$row['id'] ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['category_name'] ?? '-') ?></td>
                            <td>
                                <?php if ((int)($row['immediate_release'] ?? 0) === 1): ?>
                                    <span class="badge text-bg-secondary"><?= $languageService->get('badge_immediately') ?></span>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars(date('d.m.Y H:i', strtotime($row['publish_at']))) ?>
                                    </div>
                                <?php elseif ((int)$row['is_published'] === 1): ?>
                                    <span class="badge text-bg-success"><?= $languageService->get('badge_released') ?></span>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars(date('d.m.Y H:i', strtotime($row['publish_at']))) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge text-bg-warning"><?= $languageService->get('badge_planned') ?></span>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars(date('d.m.Y H:i', strtotime($row['publish_at']))) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td><?= $row['topnews_is_active'] ? '<span class="badge bg-success">' . $languageService->get('yes') . '' : '<span class="badge bg-secondary">' . $languageService->get('no') . '' ?></td>
                            <td><?= $row['is_active'] ? '<span class="badge bg-success">' . $languageService->get('active') . '' : '<span class="badge bg-danger">' . $languageService->get('inactive') . '' ?></td>
                            <td>
                                <a href="admincenter.php?site=admin_news&action=edit&id=<?= (int)$row['id'] ?>"
                                class="btn btn-warning d-inline-flex align-items-center gap-1 w-auto">
                                    <i class="bi bi-pencil-square"></i> <?= $languageService->get('edit') ?>
                                </a>

                                <?php
                                    $deleteUrl = 'admincenter.php?site=admin_news&action=delete&id=' . (int)$row['id'];
                                ?>
                                <a
                                href="#"
                                class="btn btn-danger d-inline-flex align-items-center gap-1 w-auto"
                                data-bs-toggle="modal"
                                data-bs-target="#confirmDeleteModal"
                                data-confirm-url="<?= htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-trash"></i> <?= $languageService->get('delete') ?>
                                </a>
                            </td>
                            <td class="sort-value d-none"><?= (int)$row['sort_order'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', () => {

            const tbody = document.getElementById('news-sortable');

            Sortable.create(tbody, {
                handle: '.bi-list',
                animation: 150,
                onEnd: () => {

                    const order = [];
                    tbody.querySelectorAll('tr').forEach((tr, i) => {
                        order.push({
                            id: tr.dataset.id,
                            sort_order: i + 1
                        });
                        tr.querySelector('.sort-value').textContent = i + 1;
                    });

                    fetch('admincenter.php?site=admin_news&action=save_sort', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(order)
                    });
                }
            });
        });
        </script>

        <?php
        break;

    // SORTIERUNG SPEICHERN (AJAX)
    case 'save_sort':

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            exit;
        }

        foreach ($data as $row) {
            $_database->query("
                UPDATE plugins_news
                SET sort_order = " . (int)$row['sort_order'] . "
                WHERE id = " . (int)$row['id']
            );
        }

        echo json_encode(['status' => 'ok']);
        exit;
}

}
?>
<style>
.cursor-move {
    cursor: grab;
}
.sortable-ghost {
    opacity: 0.5;
}
.news-editor-top-row > [class*='col-'] {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}
.news-editor-title-head {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 0.5rem;
    min-height: calc(1.5em + 0.75rem + 2px);
    margin-bottom: 0.5rem;
}
.news-editor-title-head .form-label {
    margin-bottom: 0;
}
.news-lang-switch {
    justify-content: flex-start;
}
@media (max-width: 767.98px) {
    .news-editor-title-head {
        align-items: flex-start;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  (() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  })();
});
</script>


