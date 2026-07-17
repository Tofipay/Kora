<?php
/**
 * SEO article block for a match page — 100% server-rendered, crawlable,
 * semantic HTML. Data comes from match_article(). No JavaScript involved.
 *
 * @var array $article  output of match_article()
 * @var string $home
 * @var string $away
 */
$ar = \TofiXTv\Core\Lang::current() === 'ar';
if (empty($article['sections'])) return;
$factsTitle = $ar ? 'حقائق المباراة' : 'Match facts';
?>
<section class="match-article" aria-label="<?= e($ar ? 'تفاصيل المباراة' : 'Match details') ?>">
  <article class="ma-body">
    <?php if (!empty($article['lead'])): ?>
      <p class="ma-lead"><?= e($article['lead']) ?></p>
    <?php endif; ?>

    <?php if (!empty($article['facts'])): ?>
      <aside class="ma-facts" aria-label="<?= e($factsTitle) ?>">
        <h3 class="ma-facts-title"><?= e($factsTitle) ?></h3>
        <dl>
          <?php foreach ($article['facts'] as $f): ?>
            <div><dt><?= e($f['label']) ?></dt><dd><?= e($f['value']) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </aside>
    <?php endif; ?>

    <?php foreach ($article['sections'] as $sec): ?>
      <h2><?= e($sec['h2']) ?></h2>
      <?php foreach (($sec['paras'] ?? []) as $para): ?>
        <p><?= e($para) ?></p>
      <?php endforeach; ?>
      <?php if (!empty($sec['list'])): ?>
        <ul>
          <?php foreach ($sec['list'] as $li): ?>
            <li><?= e($li) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endforeach; ?>
  </article>
</section>
