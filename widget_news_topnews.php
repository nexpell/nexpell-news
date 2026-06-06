<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\SeoUrlHandler;

global $languageService, $_database;
require_once __DIR__ . '/builder_widget_helper.php';

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');

$newsBuilderSettings = news_widget_builder_settings('widget_news_topnews', isset($settings) && is_array($settings) ? $settings : []);
$limit = (int)$newsBuilderSettings['limit'];
$orderSql = news_widget_builder_order_sql((string)$newsBuilderSettings['order']);
$whereSql = news_widget_builder_where_sql($_database, $newsBuilderSettings);

echo news_widget_builder_asset('widget_news_topnews.css');

$topNewsResult = $_database->query("
    SELECT a.id, a.title, a.updated_at, a.publish_at, a.sort_order, a.banner_image,
           c.name AS category_name, c.image AS category_image
    FROM plugins_news a
    LEFT JOIN plugins_news_categories c ON a.category_id = c.id
    {$whereSql}
    {$orderSql}
    LIMIT {$limit}
");

if (!$topNewsResult || $topNewsResult->num_rows === 0) {
    echo '<div class="news-widget-empty">Keine News verfuegbar.</div>';
    return;
}
?>

<section class="news-topnews-widget my-4">
  <?php echo news_widget_builder_heading_html($newsBuilderSettings, 'Top News', 'h4', 'news-widget-title'); ?>
  <div class="news-topnews-widget__list">
    <?php $rank = 1; ?>
    <?php while ($news = $topNewsResult->fetch_assoc()):
        $id = (int)$news['id'];
        $titleRaw = news_widget_builder_lang_field($_database, $id, 'title', $lang, (string)($news['title'] ?? ''));
        $url = SeoUrlHandler::buildPluginUrl('plugins_news', $id, $lang);
        $categoryRaw = news_widget_builder_normalize_text((string)($news['category_name'] ?? 'Kategorie'));
        $dateText = date('d.m.Y', news_widget_builder_timestamp($news));
    ?>
      <a class="news-topnews-widget__item" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="news-topnews-widget__rank"><?php echo str_pad((string)$rank, 2, '0', STR_PAD_LEFT); ?></span>
        <span class="news-topnews-widget__image">
          <img src="<?php echo htmlspecialchars(news_widget_builder_image($news), ENT_QUOTES, 'UTF-8'); ?>"
               alt="<?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?>">
        </span>
        <span class="news-topnews-widget__body">
          <strong><?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?></strong>
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
        </span>
        <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
      </a>
      <?php $rank++; ?>
    <?php endwhile; ?>
  </div>
</section>
