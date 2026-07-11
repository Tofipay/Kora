<?php require __DIR__ . '/_shell.php'; admin_top('SEO Manager', 'seo'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="row">
      <div><label>Home title (Arabic)</label><input type="text" name="title_ar" value="<?= e($s['title_ar'] ?? '') ?>" placeholder="قمهد لايف — نتائج مباشرة…"></div>
      <div><label>Home title (English)</label><input type="text" name="title_en" value="<?= e($s['title_en'] ?? '') ?>" placeholder="Qamhad Live — Live scores…"></div>
    </div>
    <label>Meta description (Arabic)</label><textarea name="desc_ar"><?= e($s['desc_ar'] ?? '') ?></textarea>
    <label>Meta description (English)</label><textarea name="desc_en"><?= e($s['desc_en'] ?? '') ?></textarea>
    <div class="row">
      <div><label>Google Search Console verification token</label><input type="text" name="gsc" value="<?= e($s['gsc'] ?? '') ?>"></div>
      <div><label>GA4 Measurement ID</label><input type="text" name="ga4" value="<?= e($s['ga4'] ?? '') ?>" placeholder="G-XXXXXXX"></div>
    </div>
    <button class="btn" type="submit">Save</button>
  </form>
</div>
<div class="card">
  <b>Built-in SEO (always on)</b>
  <ul style="color:#9FB0C8;font-size:13px;margin:8px 0 0;padding-left:18px">
    <li>Canonical + hreflang (ar/en) on every page — no duplicate URLs</li>
    <li>JSON-LD: Organization, WebSite+SearchAction, SportsEvent, NewsArticle, BreadcrumbList, SportsTeam, Person</li>
    <li>Open Graph + Twitter Cards</li>
    <li><a href="/sitemap.xml" target="_blank" style="color:#16C784">/sitemap.xml</a> + <a href="/sitemap-news.xml" target="_blank" style="color:#16C784">/sitemap-news.xml</a> + robots.txt</li>
    <li>301 redirects from all legacy .php URLs</li>
  </ul>
</div>
<?php admin_bottom();
