<?php
/** News card. Expects: $n, optional $big */
$big = $big ?? false;
?>
<a class="news-card card-hover<?= $big ? ' news-big' : '' ?>" href="<?= e(news_url($n)) ?>">
  <div class="news-thumb">
    <img src="<?= e(news_img($n, $big ? '640' : '150')) ?>" alt="<?= e($n['title'] ?? '') ?>" loading="lazy" decoding="async" width="<?= $big ? 640 : 150 ?>" height="<?= $big ? 360 : 100 ?>">
  </div>
  <div class="news-body">
    <h3 class="news-title"><?= e($n['title'] ?? '') ?></h3>
    <?php if ($big && !empty($n['news_desc'])): ?>
      <p class="news-desc"><?= e(excerpt((string)$n['news_desc'], 120)) ?></p>
    <?php endif; ?>
    <time class="news-time" datetime="<?= e(date('c', to_ts($n['created_at'] ?? null) ?: time())) ?>"><?= e(time_ago($n['created_at'] ?? null)) ?></time>
  </div>
</a>
