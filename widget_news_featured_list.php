<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\SeoUrlHandler;

global $languageService, $_database;
require_once __DIR__ . '/builder_widget_helper.php';

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');

$newsBuilderSettings = news_widget_builder_settings('widget_news_featured_list', isset($settings) && is_array($settings) ? $settings : []);
$limit = (int)$newsBuilderSettings['limit'];
$orderSql = news_widget_builder_order_sql((string)$newsBuilderSettings['order']);
$whereSql = news_widget_builder_where_sql($_database, $newsBuilderSettings);

echo news_widget_builder_asset('widget_news_featured_list.css');
echo news_widget_builder_asset('news_featured_list.css');
?>
<style>
.news-featured-list,
.news-featured-widget {
  box-sizing: border-box !important;
  width: min(calc(100% - clamp(1.25rem, 4vw, 3rem)), 1120px) !important;
  max-width: 1120px !important;
  margin: 1.5rem auto !important;
  color: var(--bs-body-color, #f4f7fb) !important;
}
.news-featured-list .featured-news,
.news-featured-list .news-item,
.news-featured-widget .news-featured-widget__hero,
.news-featured-widget .news-featured-widget__item {
  border: 1px solid color-mix(in srgb, var(--bs-body-color, #f4f7fb) 14%, transparent) !important;
  border-radius: var(--nxm-radius, 6px) !important;
  background: color-mix(in srgb, var(--bs-body-color, #f4f7fb) 4%, var(--bs-body-bg, #101317)) !important;
  color: var(--bs-body-color, #f4f7fb) !important;
}
.news-featured-list .featured-news:hover,
.news-featured-list .news-item:hover,
.news-featured-widget .news-featured-widget__hero:hover,
.news-featured-widget .news-featured-widget__item:hover {
  background: color-mix(in srgb, var(--bs-primary, #fe821d) 8%, var(--bs-body-bg, #101317)) !important;
  color: var(--bs-body-color, #f4f7fb) !important;
}
.news-featured-list .featured-title,
.news-featured-list .featured-title a,
.news-featured-list .news-title,
.news-featured-list .news-title a,
.news-featured-list .news-item a:not(.btn),
.news-featured-widget strong,
.news-featured-widget a:not(.btn) {
  color: var(--bs-heading-color, var(--bs-body-color, #f4f7fb)) !important;
  -webkit-text-fill-color: var(--bs-heading-color, var(--bs-body-color, #f4f7fb)) !important;
}
.news-featured-list .featured-excerpt,
.news-featured-list .news-excerpt,
.news-featured-list .news-meta,
.news-featured-list .featured-meta,
.news-featured-list small,
.news-featured-widget span,
.news-featured-widget small,
.news-featured-widget p {
  color: color-mix(in srgb, var(--bs-body-color, #f4f7fb) 78%, var(--bs-body-bg, #101317)) !important;
  -webkit-text-fill-color: color-mix(in srgb, var(--bs-body-color, #f4f7fb) 78%, var(--bs-body-bg, #101317)) !important;
}
.news-featured-list .news-list {
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: .9rem !important;
  margin-top: 1rem !important;
}
.news-featured-list .news-list > *,
.news-featured-list .news-item {
  width: auto !important;
  max-width: none !important;
}
@media (max-width: 991.98px) {
  .news-featured-list .news-list { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
}
@media (max-width: 767.98px) {
  .news-featured-list,
  .news-featured-widget {
    width: min(calc(100% - 1rem), 1120px) !important;
  }
  .news-featured-list .news-list { grid-template-columns: 1fr !important; }
}
</style>
<?php

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

$featured = mysqli_fetch_assoc($res);
$fid = (int)$featured['id'];
$featuredTitle = news_widget_builder_lang_field($_database, $fid, 'title', $lang, (string)($featured['title'] ?? ''));
$featuredContent = news_widget_builder_lang_field($_database, $fid, 'content', $lang, (string)($featured['content'] ?? ''));
$featuredUrl = SeoUrlHandler::buildPluginUrl('plugins_news', $fid, $lang);
$featuredCategory = news_widget_builder_normalize_text((string)($featured['category_name'] ?? 'Kategorie'));
$featuredDate = date('d.m.Y', news_widget_builder_timestamp($featured));
?>

<section class="news-featured-widget my-4">
  <?php echo news_widget_builder_heading_html($newsBuilderSettings, 'News Featured', 'h4', 'news-widget-title'); ?>
  <div class="news-featured-widget__stack">
    <article class="news-featured-widget__hero">
      <a class="news-featured-widget__hero-media" href="<?php echo htmlspecialchars($featuredUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <img src="<?php echo htmlspecialchars(news_widget_builder_image($featured), ENT_QUOTES, 'UTF-8'); ?>"
             alt="<?php echo htmlspecialchars($featuredTitle, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($newsBuilderSettings['show_category'])): ?>
          <span class="news-featured-widget__badge"><?php echo htmlspecialchars($featuredCategory, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
      </a>
      <div class="news-featured-widget__hero-body">
        <h3>
          <a href="<?php echo htmlspecialchars($featuredUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($featuredTitle, ENT_QUOTES, 'UTF-8'); ?>
          </a>
        </h3>
        <p><?php echo htmlspecialchars(news_widget_builder_excerpt($featuredContent, (int)$newsBuilderSettings['featured_excerpt_chars']), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!empty($newsBuilderSettings['show_date'])): ?>
          <small><?php echo htmlspecialchars($featuredDate, ENT_QUOTES, 'UTF-8'); ?></small>
        <?php endif; ?>
      </div>
    </article>

    <div class="news-featured-widget__list">
      <?php while ($row = mysqli_fetch_assoc($res)):
          $id = (int)$row['id'];
          $titleRaw = news_widget_builder_lang_field($_database, $id, 'title', $lang, (string)($row['title'] ?? ''));
          $contentRaw = news_widget_builder_lang_field($_database, $id, 'content', $lang, (string)($row['content'] ?? ''));
          $url = SeoUrlHandler::buildPluginUrl('plugins_news', $id, $lang);
          $categoryRaw = news_widget_builder_normalize_text((string)($row['category_name'] ?? 'Kategorie'));
          $dateText = date('d.m.Y', news_widget_builder_timestamp($row));
      ?>
        <a class="news-featured-widget__item" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
          <span class="news-featured-widget__thumb">
            <img src="<?php echo htmlspecialchars(news_widget_builder_image($row), ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if (!empty($newsBuilderSettings['show_category'])): ?>
              <span><?php echo htmlspecialchars($categoryRaw, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
          </span>
          <span class="news-featured-widget__item-body">
            <strong><?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars(news_widget_builder_excerpt($contentRaw, (int)$newsBuilderSettings['list_excerpt_chars']), ENT_QUOTES, 'UTF-8'); ?></span>
            <small>
              <?php
              $meta = [];
              if (!empty($newsBuilderSettings['show_date'])) {
                  $meta[] = $dateText;
              }
              if (!empty($newsBuilderSettings['show_category'])) {
                  $meta[] = $categoryRaw;
              }
              echo htmlspecialchars(implode(' / ', $meta), ENT_QUOTES, 'UTF-8');
              ?>
            </small>
            <em><?php echo htmlspecialchars(news_widget_builder_read_more(), ENT_QUOTES, 'UTF-8'); ?> <i class="bi bi-arrow-right-short" aria-hidden="true"></i></em>
          </span>
        </a>
      <?php endwhile; ?>
    </div>
  </div>
</section>
