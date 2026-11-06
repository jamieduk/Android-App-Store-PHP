<?php
declare(strict_types=1);

require __DIR__ . '/lib/appstore.php';

$allApps = as_prepare_apps(as_load_apps());

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$category = isset($_GET['category']) ? trim((string) $_GET['category']) : 'All';
$sort = isset($_GET['sort']) ? (string) $_GET['sort'] : 'date';
$dir = isset($_GET['dir']) ? (string) $_GET['dir'] : 'desc';

$validSorts = ['date', 'name', 'description', 'rating', 'stars'];
if (!in_array($sort, $validSorts, true)) {
    $sort = 'date';
}
$dir = $dir === 'asc' ? 'asc' : 'desc';
if ($category !== 'All' && !isset(as_category_meta()[$category])) {
    $category = 'All';
}

$categories = as_category_meta();
$counts = ['All' => count($allApps)];
foreach ($allApps as $app) {
    $counts[$app['category']] = ($counts[$app['category']] ?? 0) + 1;
}

$filtered = as_filter_apps($allApps, $q, $category);
$filtered = as_sort_apps($filtered, $sort, $dir);
$filteredSlugs = array_flip(array_column($filtered, 'slug'));

// Render every card so the live search can reveal apps without a round trip,
// but hide the ones that do not match the initial server-side filter.
$renderApps = as_sort_apps($allApps, $sort, $dir);

$catalogByDate = as_sort_apps($allApps, 'date', 'desc');
$fresh = array_slice($catalogByDate, 0, 6);
$heroApps = array_slice($catalogByDate, 0, 4);

$newCount = count(array_filter($allApps, static fn (array $app): bool => $app['is_new']));
$gameCount = $counts['Games'] ?? 0;
$dataMtime = is_file(APP_STORE_DATA) ? (int) filemtime(APP_STORE_DATA) : time();
$payload = as_app_payload($allApps);

function catalog_url(array $overrides = []): string
{
    $params = array_merge([
        'q' => $GLOBALS['q'] ?? '',
        'category' => $GLOBALS['category'] ?? 'All',
        'sort' => $GLOBALS['sort'] ?? 'date',
        'dir' => $GLOBALS['dir'] ?? 'desc',
    ], $overrides);

    $params = array_filter($params, static fn ($value): bool => $value !== '' && $value !== null && $value !== 'All');
    $query = http_build_query($params);

    return $query === '' ? '?' : '?' . $query;
}
?>
<!doctype html>
<html lang="en" data-theme="dark" data-view="grid">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>J~Net App Store — Free &amp; open-source Android apps</title>
    <meta name="description" content="Browse <?= count($allApps) ?> free, open-source Android apps and games by J~Net. Live search, categories, ratings and one-tap links to GitHub.">
    <meta name="theme-color" content="#0a0f1e">
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/style.css?v=<?= as_e((string) filemtime(__DIR__ . '/assets/style.css')) ?>">
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('jnetstore:theme');
                if (!theme) {
                    theme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                }
                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.setAttribute('data-view', localStorage.getItem('jnetstore:view') || 'grid');
            } catch (e) {}
        })();
    </script>
</head>
<body>
<a class="skip-link" href="#apps">Skip to apps</a>

<svg class="svg-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
    <symbol id="i-star" viewBox="0 0 24 24"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09A6.02 6.02 0 0 1 16.5 3C19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54z"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.42-1.42"/></symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></symbol>
    <symbol id="i-github" viewBox="0 0 24 24"><path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.56 0-.28-.01-1.02-.02-2-3.2.7-3.88-1.54-3.88-1.54-.53-1.34-1.29-1.7-1.29-1.7-1.05-.72.08-.71.08-.71 1.16.08 1.77 1.19 1.77 1.19 1.03 1.77 2.7 1.26 3.36.96.1-.75.4-1.26.73-1.55-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.29 1.19-3.1-.12-.29-.52-1.46.11-3.05 0 0 .97-.31 3.18 1.18a11.1 11.1 0 0 1 5.8 0c2.2-1.49 3.17-1.18 3.17-1.18.63 1.59.23 2.76.11 3.05.74.81 1.19 1.84 1.19 3.1 0 4.42-2.69 5.39-5.25 5.68.41.35.78 1.05.78 2.12 0 1.53-.01 2.76-.01 3.14 0 .31.21.67.8.56A11.5 11.5 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5z"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v12M7 10l5 5 5-5M4 21h16"/></symbol>
    <symbol id="i-external" viewBox="0 0 24 24"><path d="M14 3h7v7M21 3l-9 9M19 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/></symbol>
    <symbol id="i-arrow-down" viewBox="0 0 24 24"><path d="M12 5v14M19 12l-7 7-7-7"/></symbol>
    <symbol id="i-arrow-up" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-sort" viewBox="0 0 24 24"><path d="M3 6h13M3 12h9M3 18h5M17 8l4-4 4 4M21 4v16"/></symbol>
    <symbol id="i-code" viewBox="0 0 24 24"><path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/></symbol>
    <symbol id="i-logo" viewBox="0 0 24 24"><path d="M17.6 9.48l1.84-3.18a.5.5 0 0 0-.87-.5l-1.87 3.23A11.3 11.3 0 0 0 12 8.5c-1.63 0-3.17.2-4.7.53L5.43 5.8a.5.5 0 0 0-.87.5L6.4 9.48A10.6 10.6 0 0 0 1.5 17h21a10.6 10.6 0 0 0-4.9-7.52zM7 14.25a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm10 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></symbol>
</svg>

<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="./" aria-label="J~Net App Store home">
            <span class="brand-mark"><?= as_icon('logo') ?></span>
            <span class="brand-text">
                <span class="brand-name">J~Net</span>
                <span class="brand-sub">App Store</span>
            </span>
        </a>

        <form class="search" id="searchForm" role="search" action="" method="get">
            <?= as_icon('search', 'icon search-icon') ?>
            <input id="searchInput" type="search" name="q" value="<?= as_e($q) ?>"
                   placeholder="Search <?= count($allApps) ?> apps, games &amp; tools…"
                   aria-label="Search apps by name or description" autocomplete="off" spellcheck="false">
            <button class="search-clear" id="searchClear" type="button" aria-label="Clear search" <?= $q === '' ? 'hidden' : '' ?>>
                <?= as_icon('close') ?>
            </button>
            <kbd class="search-kbd" aria-hidden="true">/</kbd>
            <?php if ($category !== 'All'): ?>
                <input type="hidden" name="category" value="<?= as_e($category) ?>">
            <?php endif; ?>
            <input type="hidden" name="sort" value="<?= as_e($sort) ?>">
            <input type="hidden" name="dir" value="<?= as_e($dir) ?>">
        </form>

        <div class="header-actions">
            <button class="icon-btn" id="themeToggle" type="button" aria-label="Switch colour theme">
                <?= as_icon('sun', 'icon sun-icon') ?>
                <?= as_icon('moon', 'icon moon-icon') ?>
            </button>
            <a class="gh-link" href="https://github.com/<?= as_e(APP_STORE_OWNER) ?>" target="_blank" rel="noopener">
                <?= as_icon('github', 'icon gh-icon') ?><span>GitHub</span>
            </a>
        </div>
    </div>
</header>

<main>
    <section class="hero">
        <div class="hero-copy">
            <span class="eyebrow"><span class="pulse"></span> Free &amp; open source · <?= count($allApps) ?> apps</span>
            <h1>Apps worth <span class="grad">downloading.</span></h1>
            <p class="lede">
                A hand-picked collection of Android apps and games — no ads, no accounts, no nonsense.
                Every listing links straight to its source on GitHub.
            </p>
            <div class="hero-stats">
                <div class="stat"><strong><?= count($allApps) ?></strong><span>Apps</span></div>
                <div class="stat"><strong><?= $gameCount ?></strong><span>Games</span></div>
                <div class="stat"><strong><?= $newCount ?></strong><span>Updated recently</span></div>
                <div class="stat"><strong>100%</strong><span>Free</span></div>
            </div>
            <div class="hero-actions">
                <a class="btn btn-primary" href="#apps">Browse all apps <?= as_icon('arrow-down') ?></a>
                <a class="btn btn-ghost" href="https://github.com/<?= as_e(APP_STORE_OWNER) ?>" target="_blank" rel="noopener">
                    <?= as_icon('github', 'icon gh-icon') ?> View on GitHub
                </a>
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <div class="hero-orbit"></div>
            <div class="hero-glow"></div>
            <?php foreach ($heroApps as $index => $app): ?>
                <div class="float-card f<?= $index + 1 ?>">
                    <div class="app-icon g-<?= $app['gradient'] ?>">
                        <span class="app-initials"><?= as_e($app['initials']) ?></span>
                        <span class="app-emoji"><?= as_e($app['category_icon']) ?></span>
                    </div>
                    <div class="float-meta">
                        <span class="float-name"><?= as_e($app['display_name']) ?></span>
                        <span class="float-cat"><?= as_e($app['category']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="fresh" aria-labelledby="freshTitle">
        <div class="section-head">
            <div>
                <span class="eyebrow eyebrow-small">Just landed</span>
                <h2 id="freshTitle">Fresh releases</h2>
            </div>
            <a class="text-link" href="#apps">See all <?= as_icon('arrow-down') ?></a>
        </div>
        <div class="fresh-row">
            <?php foreach ($fresh as $app): ?>
                <article class="fresh-card" data-slug="<?= as_e($app['slug']) ?>" tabindex="0" role="button"
                         aria-label="View details for <?= as_e($app['display_name']) ?>">
                    <div class="fresh-top">
                        <div class="app-icon app-icon-sm g-<?= $app['gradient'] ?>">
                            <span class="app-initials"><?= as_e($app['initials']) ?></span>
                            <span class="app-emoji"><?= as_e($app['category_icon']) ?></span>
                        </div>
                        <span class="fresh-rating"><?= as_icon('star', 'icon star-icon') ?> <?= number_format($app['rating'], 1) ?></span>
                    </div>
                    <h3><?= as_e($app['display_name']) ?></h3>
                    <p><?= as_e($app['description']) ?></p>
                    <span class="fresh-meta"><?= as_e($app['category_icon']) ?> <?= as_e($app['category']) ?> · Updated <?= as_e($app['updated_human']) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="catalog" id="apps" aria-labelledby="catalogTitle">
        <div class="catalog-head">
            <span class="eyebrow eyebrow-small">The catalogue</span>
            <h2 id="catalogTitle">All apps</h2>
            <p>Search, filter and sort the entire collection. Everything is free and open source.</p>
        </div>

        <div class="catalog-toolbar" id="catalogToolbar">
            <div class="chips" role="group" aria-label="Filter by category">
                <a class="chip<?= $category === 'All' ? ' is-active' : '' ?>" href="<?= as_e(catalog_url(['category' => 'All'])) ?>"
                   data-category="All"<?= $category === 'All' ? ' aria-current="true"' : '' ?>>All <span class="chip-count"><?= count($allApps) ?></span></a>
                <?php foreach ($categories as $key => $meta): ?>
                    <a class="chip<?= $category === $key ? ' is-active' : '' ?>" href="<?= as_e(catalog_url(['category' => $key])) ?>"
                       data-category="<?= as_e($key) ?>"<?= $category === $key ? ' aria-current="true"' : '' ?>>
                        <span aria-hidden="true"><?= as_e($meta['icon']) ?></span>
                        <?= as_e($meta['label']) ?>
                        <span class="chip-count"><?= (int) ($counts[$key] ?? 0) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="controls">
                <p class="results" aria-live="polite"><strong id="resultCount"><?= count($filtered) ?></strong> <span id="resultLabel"><?= count($filtered) === 1 ? 'app' : 'apps' ?></span></p>

                <label class="sort-control" for="sortSelect">
                    <?= as_icon('sort') ?>
                    <span class="sort-label">Sort</span>
                    <select id="sortSelect" aria-label="Sort apps">
                        <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>Last updated</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
                        <option value="description" <?= $sort === 'description' ? 'selected' : '' ?>>Description</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Rating</option>
                        <option value="stars" <?= $sort === 'stars' ? 'selected' : '' ?>>GitHub stars</option>
                    </select>
                    <?= as_icon('chevron', 'icon chev') ?>
                </label>

                <button class="icon-btn dir-btn" id="dirToggle" type="button" data-dir="<?= as_e($dir) ?>"
                        aria-label="<?= $dir === 'desc' ? 'Sort descending' : 'Sort ascending' ?>"
                        title="<?= $dir === 'desc' ? 'Descending' : 'Ascending' ?>">
                    <?= as_icon($dir === 'desc' ? 'arrow-down' : 'arrow-up', 'icon dir-icon') ?>
                </button>

                <button class="chip chip-fav" id="favToggle" type="button" aria-pressed="false">
                    <?= as_icon('heart') ?><span>Saved</span><span class="chip-count" id="favCount">0</span>
                </button>

                <div class="view-toggle" role="group" aria-label="Change layout">
                    <button type="button" data-view="grid" class="is-active" aria-label="Grid view"><?= as_icon('grid') ?></button>
                    <button type="button" data-view="list" aria-label="List view"><?= as_icon('list') ?></button>
                </div>
            </div>
        </div>

        <div class="app-grid" id="appGrid">
            <?php foreach ($renderApps as $app): ?>
                <?php $cardVisible = isset($filteredSlugs[$app['slug']]); ?>
                <article class="app-card" data-slug="<?= as_e($app['slug']) ?>" tabindex="0" role="button"
                         aria-label="View details for <?= as_e($app['display_name']) ?>" <?= $cardVisible ? '' : 'hidden' ?>>
                    <div class="card-head">
                        <div class="app-icon g-<?= $app['gradient'] ?>" aria-hidden="true">
                            <span class="app-initials"><?= as_e($app['initials']) ?></span>
                            <span class="app-emoji"><?= as_e($app['category_icon']) ?></span>
                        </div>
                        <div class="card-title">
                            <h3><?= as_e($app['display_name']) ?></h3>
                            <p class="dev">
                                J~Net <span class="verified" title="Open source"><?= as_icon('check') ?></span>
                                <?php if ($app['is_new']): ?><span class="badge badge-new">New</span><?php endif; ?>
                                <?php if ($app['apk_url']): ?><span class="badge badge-apk">APK</span><?php endif; ?>
                            </p>
                            <div class="card-rating">
                                <span class="star"><?= as_icon('star', 'icon star-icon') ?> <?= number_format($app['rating'], 1) ?></span>
                                <span class="sep">·</span>
                                <span class="cat"><?= as_e($app['category_icon']) ?> <?= as_e($app['category']) ?></span>
                            </div>
                        </div>
                    </div>
                    <p class="card-desc"><?= as_e($app['description']) ?></p>
                    <div class="card-foot">
                        <span class="updated"><?= as_icon('clock') ?> Updated <?= as_e($app['updated_human']) ?></span>
                        <div class="card-actions">
                            <button class="fav-btn" type="button" data-fav="<?= as_e($app['slug']) ?>"
                                    aria-label="Save <?= as_e($app['display_name']) ?>" aria-pressed="false">
                                <?= as_icon('heart') ?>
                            </button>
                            <a class="btn btn-get" href="<?= as_e($app['apk_url'] ?? $app['repo_url']) ?>"
                               <?= $app['apk_url'] ? 'download' : '' ?> target="_blank" rel="noopener"
                               aria-label="Get <?= as_e($app['display_name']) ?>">Get</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="empty" id="emptyState" <?= count($filtered) ? 'hidden' : '' ?>>
            <div class="empty-art"><?= as_icon('search') ?></div>
            <h3>No apps found</h3>
            <p>Try a different search term or clear the filters to see the full catalogue.</p>
            <button class="btn btn-primary" id="emptyReset" type="button">Clear filters</button>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="foot-brand">
            <a class="brand" href="./">
                <span class="brand-mark"><?= as_icon('logo') ?></span>
                <span class="brand-text">
                    <span class="brand-name">J~Net</span>
                    <span class="brand-sub">App Store</span>
                </span>
            </a>
            <p>Free, open-source Android apps and games, built in the open. Browse the source, grab a build and make it yours.</p>
        </div>
        <div class="foot-links">
            <a href="https://github.com/<?= as_e(APP_STORE_OWNER) ?>" target="_blank" rel="noopener">GitHub</a>
            <a href="https://github.com/<?= as_e(APP_STORE_OWNER) ?>?tab=repositories" target="_blank" rel="noopener">All repositories</a>
            <a href="https://jnetai.com" target="_blank" rel="noopener">jnetai.com</a>
            <a href="#apps">Back to apps</a>
        </div>
        <p class="fineprint">
            Catalogue synced <?= as_e(date('j M Y, H:i', $dataMtime)) ?> · <?= count($allApps) ?> apps and counting.
            Ratings are community-style estimates derived from public repository signals. Apps are provided as-is —
            review the source before installing.
        </p>
    </div>
</footer>

<dialog class="app-dialog" id="appDialog" aria-labelledby="dlgName">
    <div class="dialog-shell">
        <button class="dialog-close" id="dlgClose" type="button" aria-label="Close details"><?= as_icon('close') ?></button>
        <div class="dialog-hero">
            <div class="app-icon app-icon-lg" id="dlgIcon">
                <span class="app-initials" id="dlgInitials">AP</span>
                <span class="app-emoji" id="dlgEmoji">📱</span>
            </div>
            <div class="dialog-heading">
                <span class="dialog-eyebrow" id="dlgCategory">App</span>
                <h2 id="dlgName">App name</h2>
                <p class="dialog-dev">By J~Net · Free &amp; open source</p>
                <div class="dialog-rating">
                    <?= as_icon('star', 'icon star-icon') ?>
                    <strong id="dlgRating">4.6</strong>
                    <span class="sep">·</span>
                    <span id="dlgLang">Kotlin</span>
                </div>
            </div>
        </div>
        <div class="dialog-body">
            <p class="dialog-desc" id="dlgDesc"></p>
            <div class="topics" id="dlgTopics"></div>
            <div class="dialog-stats">
                <div class="dstat"><span>Last updated</span><strong id="dlgUpdated">—</strong></div>
                <div class="dstat"><span>Released</span><strong id="dlgCreated">—</strong></div>
                <div class="dstat"><span>GitHub stars</span><strong id="dlgStars">0</strong></div>
                <div class="dstat"><span>Forks</span><strong id="dlgForks">0</strong></div>
                <div class="dstat"><span>Repo size</span><strong id="dlgSize">—</strong></div>
                <div class="dstat"><span>License</span><strong id="dlgLicense">View repo</strong></div>
            </div>
            <div class="dialog-actions">
                <a class="btn btn-primary" id="dlgGet" href="#" target="_blank" rel="noopener"><?= as_icon('download') ?> Get app</a>
                <a class="btn btn-ghost" id="dlgReleases" href="#" target="_blank" rel="noopener"><?= as_icon('code') ?> Releases</a>
                <a class="btn btn-ghost" id="dlgZip" href="#" target="_blank" rel="noopener"><?= as_icon('download') ?> Source ZIP</a>
                <button class="btn btn-ghost" id="dlgFav" type="button"><?= as_icon('heart') ?> <span id="dlgFavText">Save</span></button>
            </div>
            <div class="dialog-links">
                <a id="dlgHome" href="#" target="_blank" rel="noopener" hidden><?= as_icon('external') ?> Website</a>
                <a id="dlgIssues" href="#" target="_blank" rel="noopener"><?= as_icon('github', 'icon gh-icon') ?> Report an issue</a>
                <a id="dlgRepo" href="#" target="_blank" rel="noopener"><?= as_icon('code') ?> Source on GitHub</a>
            </div>
        </div>
    </div>
</dialog>

<noscript>
    <div class="noscript">Live search, sorting and favourites need JavaScript. The search form and category links still work.</div>
</noscript>

<script type="application/json" id="app-data"><?= json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/app.js?v=<?= as_e((string) filemtime(__DIR__ . '/assets/app.js')) ?>" defer></script>
</body>
</html>
