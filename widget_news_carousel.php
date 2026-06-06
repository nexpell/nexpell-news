<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\SeoUrlHandler;

global $languageService, $_database;
require_once __DIR__ . '/builder_widget_helper.php';

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('news');

$newsBuilderSettings = news_widget_builder_settings('widget_news_carousel', isset($settings) && is_array($settings) ? $settings : []);
$limit = (int)$newsBuilderSettings['limit'];
$orderSql = news_widget_builder_order_sql((string)$newsBuilderSettings['order']);
$whereSql = news_widget_builder_where_sql($_database, $newsBuilderSettings);
$widgetId = 'news-swiper-' . substr(md5(json_encode($newsBuilderSettings) . microtime(true)), 0, 8);

echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">' . PHP_EOL;
echo news_widget_builder_asset('widget_news_carousel.css');

$res = safe_query("
    SELECT a.id, a.title, a.content, a.updated_at, a.publish_at, a.banner_image, a.slug,
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

<section class="news-carousel-widget my-4" id="<?php echo htmlspecialchars($widgetId, ENT_QUOTES, 'UTF-8'); ?>">
  <?php echo news_widget_builder_heading_html($newsBuilderSettings, 'News Carousel', 'h4', 'news-widget-title'); ?>

  <div class="swiper news-carousel-widget__swiper">
    <div class="swiper-wrapper">
      <?php while ($news = mysqli_fetch_assoc($res)):
          $id = (int)$news['id'];
          $titleRaw = news_widget_builder_lang_field($_database, $id, 'title', $lang, (string)($news['title'] ?? ''));
          $contentRaw = news_widget_builder_lang_field($_database, $id, 'content', $lang, (string)($news['content'] ?? ''));
          $categoryRaw = news_widget_builder_normalize_text((string)($news['category_name'] ?? 'Kategorie'));
          $dateText = date('d.m.Y', news_widget_builder_timestamp($news));
          $url = SeoUrlHandler::buildPluginUrl('plugins_news', $id, $lang);
      ?>
        <div class="swiper-slide">
          <a class="news-carousel-widget__card" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="news-carousel-widget__media">
              <img src="<?php echo htmlspecialchars(news_widget_builder_image($news), ENT_QUOTES, 'UTF-8'); ?>"
                   alt="<?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?>">
              <?php if (!empty($newsBuilderSettings['show_category'])): ?>
                <span><?php echo htmlspecialchars($categoryRaw, ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endif; ?>
            </span>
            <span class="news-carousel-widget__body">
              <?php if (!empty($newsBuilderSettings['show_date'])): ?>
                <small><?php echo htmlspecialchars($dateText, ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
              <strong><?php echo htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8'); ?></strong>
              <em><?php echo htmlspecialchars(news_widget_builder_excerpt($contentRaw, 110), ENT_QUOTES, 'UTF-8'); ?></em>
              <span><?php echo htmlspecialchars(news_widget_builder_read_more(), ENT_QUOTES, 'UTF-8'); ?> <i class="bi bi-arrow-right-short" aria-hidden="true"></i></span>
            </span>
          </a>
        </div>
      <?php endwhile; ?>
    </div>
  </div>

  <div class="news-carousel-widget__controls">
    <button type="button" class="news-carousel-widget__prev" aria-label="Vorherige News"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
    <div class="news-carousel-widget__pagination"></div>
    <button type="button" class="news-carousel-widget__next" aria-label="Naechste News"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
(function() {
  var root = document.getElementById(<?php echo json_encode($widgetId); ?>);
  if (!root || typeof Swiper === 'undefined') return;
  new Swiper(root.querySelector('.news-carousel-widget__swiper'), {
    slidesPerView: <?php echo (int)$newsBuilderSettings['slides_mobile']; ?>,
    spaceBetween: 15,
    loop: true,
    watchOverflow: true,
    roundLengths: true,
    <?php if ((int)$newsBuilderSettings['autoplay_delay'] > 0): ?>
    autoplay: {
      delay: <?php echo (int)$newsBuilderSettings['autoplay_delay']; ?>,
      disableOnInteraction: false
    },
    <?php endif; ?>
    navigation: {
      nextEl: root.querySelector('.news-carousel-widget__next'),
      prevEl: root.querySelector('.news-carousel-widget__prev')
    },
    pagination: {
      el: root.querySelector('.news-carousel-widget__pagination'),
      clickable: true
    },
    breakpoints: {
      768: { slidesPerView: <?php echo (int)$newsBuilderSettings['slides_tablet']; ?> },
      992: { slidesPerView: <?php echo (int)$newsBuilderSettings['slides_desktop']; ?> }
    }
  });
})();
</script>
