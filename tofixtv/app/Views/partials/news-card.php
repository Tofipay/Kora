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
    <?php if (!empty($n['news_desc'])): ?>
      <p class="news-desc"><?= e(excerpt((string)$n['news_desc'], $big ? 120 : 90)) ?></p>
    <?php endif; ?>
    <div class="news-meta-line">
      <span class="news-author"><?= e(t('news.by')) ?>: <?= e(news_author($n)) ?></span>
      <?php if (news_category($n) !== ''): ?><span class="news-category"><?= e(news_category($n)) ?></span><?php endif; ?>
    </div>
    <time class="news-time" datetime="<?= e(date('c', to_ts($n['created_at'] ?? null) ?: time())) ?>"><?= e(time_ago($n['created_at'] ?? null)) ?></time>
  </div>
</a>
