<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\SeoUrlHandler;

global $languageService, $_database;
require_once __DIR__ . '/builder_widget_helper.php';

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');

$newsBuilderSettings = news_widget_builder_settings('widget_news_magazine', isset($settings) && is_array($settings) ? $settings : []);
$limit = (int)$newsBuilderSettings['limit'];
$orderSql = news_widget_builder_order_sql((string)$newsBuilderSettings['order']);
$whereSql = news_widget_builder_where_sql($_database, $newsBuilderSettings);

echo news_widget_builder_asset('widget_news_magazine.css');

$res = safe_query("
    SELECT a.id, a.title, a.content, a.updated_at, a.publish_at, a.banner_image,
           c.name AS category_name, c.image AS category_image
    FROM plugins_news a
    LEFT JOIN plugins_news_categories c ON a.category_id = c.id
    {$whereSql}
    {$orderSql}
    LIMIT {$limit}
");

$news = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $row['link'] = SeoUrlHandler::buildPluginUrl('plugins_news', (int)$row['id'], $lang);
    $row['title_text'] = news_widget_builder_lang_field($_database, (int)$row['id'], 'title', $lang, (string)($row['title'] ?? ''));
    $row['content_text'] = news_widget_builder_lang_field($_database, (int)$row['id'], 'content', $lang, (string)($row['content'] ?? ''));
    $news[] = $row;
}

if (empty($news)) {
    echo '<div class="news-widget-empty">Keine News verfuegbar.</div>';
    return;
}

$featured = array_shift($news);
?>

<section class="news-magazine-widget my-4">
  <?php echo news_widget_builder_heading_html($newsBuilderSettings, 'News Magazine', 'h4', 'news-widget-title'); ?>
  <div class="news-magazine-widget__layout">
    <a class="news-magazine-widget__featured" href="<?php echo htmlspecialchars($featured['link'], ENT_QUOTES, 'UTF-8'); ?>">
      <img src="<?php echo htmlspecialchars(news_widget_builder_image($featured), ENT_QUOTES, 'UTF-8'); ?>"
           alt="<?php echo htmlspecialchars($featured['title_text'], ENT_QUOTES, 'UTF-8'); ?>">
      <span class="news-magazine-widget__featured-text">
        <small>
          <?php
          $featuredMeta = [];
          if (!empty($newsBuilderSettings['show_date'])) {
              $featuredMeta[] = date('d.m.Y', news_widget_builder_timestamp($featured));
          }
          if (!empty($newsBuilderSettings['show_category'])) {
              $featuredMeta[] = news_widget_builder_normalize_text((string)($featured['category_name'] ?? 'Kategorie'));
          }
          echo htmlspecialchars(implode(' / ', $featuredMeta), ENT_QUOTES, 'UTF-8');
          ?>
        </small>
        <strong><?php echo htmlspecialchars($featured['title_text'], ENT_QUOTES, 'UTF-8'); ?></strong>
        <span><?php echo htmlspecialchars(news_widget_builder_excerpt((string)$featured['content_text'], (int)$newsBuilderSettings['featured_excerpt_chars']), ENT_QUOTES, 'UTF-8'); ?></span>
      </span>
    </a>

    <div class="news-magazine-widget__side">
      <?php foreach ($news as $item): ?>
        <a class="news-magazine-widget__item" href="<?php echo htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8'); ?>">
          <span class="news-magazine-widget__thumb">
            <img src="<?php echo htmlspecialchars(news_widget_builder_image($item), ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo htmlspecialchars($item['title_text'], ENT_QUOTES, 'UTF-8'); ?>">
          </span>
          <span class="news-magazine-widget__item-text">
            <small>
              <?php
              $itemMeta = [];
              if (!empty($newsBuilderSettings['show_date'])) {
                  $itemMeta[] = date('d.m.Y', news_widget_builder_timestamp($item));
              }
              if (!empty($newsBuilderSettings['show_category'])) {
                  $itemMeta[] = news_widget_builder_normalize_text((string)($item['category_name'] ?? 'Kategorie'));
              }
              echo htmlspecialchars(implode(' / ', $itemMeta), ENT_QUOTES, 'UTF-8');
              ?>
            </small>
            <strong><?php echo htmlspecialchars($item['title_text'], ENT_QUOTES, 'UTF-8'); ?></strong>
          </span>
          <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
