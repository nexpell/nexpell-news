<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\SeoUrlHandler;

global $languageService, $_database;
require_once __DIR__ . '/builder_widget_helper.php';

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');

$newsBuilderSettings = news_widget_builder_settings('widget_news_masonry', isset($settings) && is_array($settings) ? $settings : []);
$limit = (int)$newsBuilderSettings['limit'];
$orderSql = news_widget_builder_order_sql((string)$newsBuilderSettings['order']);
$whereSql = news_widget_builder_where_sql($_database, $newsBuilderSettings);

echo news_widget_builder_asset('widget_news_masonry.css');

$res = safe_query("
    SELECT a.id, a.title, a.content, a.updated_at, a.publish_at, a.banner_image, a.category_id,
           c.name AS category_name, c.image AS category_image
    FROM plugins_news a
    LEFT JOIN plugins_news_categories c ON a.category_id = c.id
    {$whereSql}
    {$orderSql}
    LIMIT {$limit}
");

if (!$res || mysqli_num_rows($res) === 0) {
    echo '<div class="news-widget-empty">Keine News verfuegbar.</div>';
    return;
}
?>

<section class="news-masonry-widget my-4"
         style="--news-columns-desktop: <?php echo (int)$newsBuilderSettings['columns_desktop']; ?>; --news-columns-tablet: <?php echo (int)$newsBuilderSettings['columns_tablet']; ?>; --news-columns-mobile: <?php echo (int)$newsBuilderSettings['columns_mobile']; ?>;">
  <?php echo news_widget_builder_heading_html($newsBuilderSettings, 'News Masonry', 'h4', 'news-widget-title'); ?>
  <div class="news-masonry-widget__grid">
    <?php while ($row = mysqli_fetch_assoc($res)):
        $id = (int)$row['id'];
        $titleRaw = news_widget_builder_lang_field($_database, $id, 'title', $lang, (string)($row['title'] ?? ''));
        $contentRaw = news_widget_builder_lang_field($_database, $id, 'content', $lang, (string)($row['content'] ?? ''));
        $url = SeoUrlHandler::buildPluginUrl('plugins_news', $id, $lang);
        $categoryRaw = news_widget_builder_normalize_text((string)($row['category_name'] ?? 'Kategorie'));
        $dateText = date('d.m.Y', news_widget_builder_timestamp($row));
        $excerpt = news_widget_builder_excerpt($contentRaw, (int)$newsBuilderSettings['excerpt_chars']);
    ?>
      <a class="news-masonry-widget__card" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="news-masonry-widget__media">
          <img src="<?php echo htmlspecialchars(news_widget_builder_image($row), ENT_QUOTES, 'UTF-8'); ?>"
               alt="<?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?>">
          <?php if (!empty($newsBuilderSettings['show_category'])): ?>
            <span class="news-masonry-widget__badge"><?php echo htmlspecialchars($categoryRaw, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </span>
        <span class="news-masonry-widget__body">
          <?php if (!empty($newsBuilderSettings['show_date'])): ?>
            <span class="news-masonry-widget__date"><?php echo htmlspecialchars($dateText, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
          <strong><?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?></strong>
          <span class="news-masonry-widget__excerpt"><?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="news-masonry-widget__more"><?php echo htmlspecialchars(news_widget_builder_read_more(), ENT_QUOTES, 'UTF-8'); ?> <i class="bi bi-arrow-right-short" aria-hidden="true"></i></span>
        </span>
      </a>
    <?php endwhile; ?>
  </div>
</section>
