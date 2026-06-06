<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\SeoUrlHandler;

global $languageService, $_database;
require_once __DIR__ . '/builder_widget_helper.php';

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');

$newsBuilderSettings = news_widget_builder_settings('widget_news_flip', isset($settings) && is_array($settings) ? $settings : []);
$limit = (int)$newsBuilderSettings['limit'];
$orderSql = news_widget_builder_order_sql((string)$newsBuilderSettings['order']);
$whereSql = news_widget_builder_where_sql($_database, $newsBuilderSettings);

echo news_widget_builder_asset('widget_news_flip.css');

$res = safe_query("
    SELECT a.id, a.title, a.content, a.updated_at, a.publish_at, a.banner_image,
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

<section class="news-flip-widget my-4">
  <?php echo news_widget_builder_heading_html($newsBuilderSettings, 'News Flip', 'h4', 'news-widget-title'); ?>
  <div class="news-flip-widget__grid">
    <?php while ($news = mysqli_fetch_assoc($res)):
        $id = (int)$news['id'];
        $titleRaw = news_widget_builder_lang_field($_database, $id, 'title', $lang, (string)($news['title'] ?? ''));
        $contentRaw = news_widget_builder_lang_field($_database, $id, 'content', $lang, (string)($news['content'] ?? ''));
        $categoryRaw = news_widget_builder_normalize_text((string)($news['category_name'] ?? 'Kategorie'));
        $dateText = date('d.m.Y', news_widget_builder_timestamp($news));
        $url = SeoUrlHandler::buildPluginUrl('plugins_news', $id, $lang);
    ?>
      <article class="news-flip-widget__card">
        <div class="news-flip-widget__inner">
          <a class="news-flip-widget__front" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
            <img src="<?php echo htmlspecialchars(news_widget_builder_image($news), ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?>">
            <span>
              <?php if (!empty($newsBuilderSettings['show_category'])): ?>
                <small><?php echo htmlspecialchars($categoryRaw, ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
              <strong><?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?></strong>
            </span>
          </a>
          <a class="news-flip-widget__back" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
            <strong><?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars(news_widget_builder_excerpt($contentRaw, (int)$newsBuilderSettings['content_chars']), ENT_QUOTES, 'UTF-8'); ?></span>
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
          </a>
        </div>
      </article>
    <?php endwhile; ?>
  </div>
</section>
