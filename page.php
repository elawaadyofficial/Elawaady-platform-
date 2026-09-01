<?php
/**
 * A page whose content lives in the database.
 *
 * The 18 policy and information pages were 18 PHP files with their text baked
 * in, which meant a wording change was a deployment. They are rows now, edited
 * in the dashboard under «الصفحات والسياسات».
 *
 * Content is stored and rendered as text, never as markup: a page body is
 * escaped and its line breaks preserved. That keeps an editor from being able
 * to inject script into the storefront, deliberately or by pasting.
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/settings_helper.php';
auth_boot();

$slug = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string) ($_GET['slug'] ?? ''))));

$page = $slug !== ''
    ? fetch_one($conn, 'SELECT * FROM static_pages WHERE slug = ? AND is_published = 1 LIMIT 1', 's', $slug)
    : null;

// A slug can also name a policy, in which case the published version is shown
// and the version number with it — the reader sees exactly which text applies.
$policy = null;
if ($page === null && $slug !== '') {
    $policy = fetch_one(
        $conn,
        'SELECT p.policy_key, p.title, v.content, v.version, v.published_at
           FROM policies p
           JOIN policy_versions v ON v.id = p.current_version_id
          WHERE p.policy_key = ? LIMIT 1',
        's',
        $slug
    );
}

if ($page === null && $policy === null) {
    http_response_code(404);
    $page_title = 'الصفحة غير موجودة';
    require_once __DIR__ . '/header.php';
    echo '<section class="page-hero"><div class="container">'
       . '<h1>الصفحة غير موجودة</h1><p>الرابط قديم أو الصفحة لم تُنشر بعد.</p>'
       . '<a class="btn btn-primary" href="index.php">العودة للمتجر</a></div></section>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$title   = (string) ($page['title'] ?? $policy['title']);
$content = (string) ($page['content'] ?? $policy['content']);

$page_title       = ($page['seo_title'] ?? '') ?: $title;
$page_title      .= ' | Elawaady XDigital';
$meta_description = (string) ($page['seo_description'] ?? '');

require_once __DIR__ . '/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= e($title) ?></h1>
        <?php if ($policy !== null): ?>
            <p class="text-muted">
                الإصدار <span dir="ltr"><?= e((string) $policy['version']) ?></span>
                <?php if (!empty($policy['published_at'])): ?>
                    — منشور في <span dir="ltr"><?= e(date('Y/m/d', strtotime((string) $policy['published_at']))) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <article class="content-block reveal">
            <?php foreach (preg_split('/\n\s*\n/', trim($content)) ?: [] as $paragraph): ?>
                <p><?= nl2br(e(trim($paragraph))) ?></p>
            <?php endforeach; ?>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
