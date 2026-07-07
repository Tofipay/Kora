<?php use Qamhad\Core\View; ?>
<article class="container article">
  <header class="article-head">
    <h1><?= e($n['title'] ?? '') ?></h1>
    <div class="article-meta">
      <time datetime="<?= e(date('c', to_ts($n['created_at'] ?? null) ?: time())) ?>">
        <?= e(t('news.published')) ?>: <?= e(format_datetime($n['created_at'] ?? null)) ?>
      </time>
      <button class="icon-btn" onclick="QShare()" aria-label="<?= e(t('misc.share')) ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8.2 10.8 7.6-3.6m-7.6 6 7.6 3.6"/></svg>
      </button>
    </div>
  </header>

  <figure class="article-cover">
    <img src="<?= e(news_img($n, '640')) ?>" alt="<?= e($n['title'] ?? '') ?>" width="960" height="540" fetchpriority="high">
  </figure>

  <div class="article-body">
    <?php
    // full_news is upstream HTML; keep only safe formatting tags
    $body = (string)($n['full_news'] ?? '');
    $body = strip_tags($body, '<p><br><strong><b><em><i><ul><ol><li><h2><h3><h4><blockquote><a>');
    $body = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $body);
    $body = preg_replace('/(href)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2?/iu', '', (string)$body);
    if (trim(strip_tags((string)$body)) === '') {
        echo '<p>' . e((string)($n['news_desc'] ?? $n['description'] ?? '')) . '</p>';
    } else {
        echo $body;
    }
    ?>
    <?php if (!empty($partial)):
        $src = '';
        foreach (['link', 'url', 'source_url', 'source'] as $k) {
            if (!empty($n[$k]) && preg_match('#^https?://#i', (string)$n[$k])) { $src = (string)$n[$k]; break; }
        } ?>
    <p class="article-partial muted">
      <?= e(t('news.partial')) ?>
      <?php if ($src !== ''): ?><a href="<?= e($src) ?>" target="_blank" rel="noopener nofollow"><?= e(t('news.read_source')) ?></a><?php endif; ?>
    </p>
    <?php endif; ?>
  </div>
</article>

<?php if (!empty($related)): ?>
<section class="section container">
  <div class="section-head"><h2><?= e(t('news.related')) ?></h2></div>
  <div class="news-list">
    <?php foreach ($related as $r): ?><?= View::partial('news-card', ['n' => $r]) ?><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
