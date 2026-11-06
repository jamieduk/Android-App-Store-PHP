/* J~Net App Store — live catalogue behaviour */
(function () {
    'use strict';

    var dataEl = document.getElementById('app-data');
    var apps = [];
    try {
        apps = JSON.parse(dataEl ? dataEl.textContent : '[]');
    } catch (error) {
        apps = [];
    }
    if (!Array.isArray(apps)) {
        apps = [];
    }

    var bySlug = new Map(apps.map(function (app) { return [app.slug, app]; }));

    var grid = document.getElementById('appGrid');
    var cards = new Map();
    if (grid) {
        grid.querySelectorAll('.app-card').forEach(function (card) {
            cards.set(card.dataset.slug, card);
        });
    }

    var searchForm = document.getElementById('searchForm');
    var searchInput = document.getElementById('searchInput');
    var searchClear = document.getElementById('searchClear');
    var sortSelect = document.getElementById('sortSelect');
    var dirToggle = document.getElementById('dirToggle');
    var dirIconUse = dirToggle ? dirToggle.querySelector('.dir-icon use') : null;
    var favToggle = document.getElementById('favToggle');
    var favCountEl = document.getElementById('favCount');
    var resultCountEl = document.getElementById('resultCount');
    var resultLabelEl = document.getElementById('resultLabel');
    var emptyState = document.getElementById('emptyState');
    var emptyReset = document.getElementById('emptyReset');
    var themeToggle = document.getElementById('themeToggle');
    var viewButtons = Array.prototype.slice.call(document.querySelectorAll('.view-toggle [data-view]'));
    var themeMeta = document.querySelector('meta[name="theme-color"]');

    var dialog = document.getElementById('appDialog');
    var dlgClose = document.getElementById('dlgClose');
    var dlgIcon = document.getElementById('dlgIcon');
    var dlgInitials = document.getElementById('dlgInitials');
    var dlgEmoji = document.getElementById('dlgEmoji');
    var dlgCategory = document.getElementById('dlgCategory');
    var dlgName = document.getElementById('dlgName');
    var dlgRating = document.getElementById('dlgRating');
    var dlgLang = document.getElementById('dlgLang');
    var dlgDesc = document.getElementById('dlgDesc');
    var dlgTopics = document.getElementById('dlgTopics');
    var dlgUpdated = document.getElementById('dlgUpdated');
    var dlgCreated = document.getElementById('dlgCreated');
    var dlgStars = document.getElementById('dlgStars');
    var dlgForks = document.getElementById('dlgForks');
    var dlgSize = document.getElementById('dlgSize');
    var dlgLicense = document.getElementById('dlgLicense');
    var dlgGet = document.getElementById('dlgGet');
    var dlgReleases = document.getElementById('dlgReleases');
    var dlgZip = document.getElementById('dlgZip');
    var dlgFav = document.getElementById('dlgFav');
    var dlgFavText = document.getElementById('dlgFavText');
    var dlgHome = document.getElementById('dlgHome');
    var dlgIssues = document.getElementById('dlgIssues');
    var dlgRepo = document.getElementById('dlgRepo');

    var FAV_KEY = 'jnetstore:favs';
    var VIEW_KEY = 'jnetstore:view';
    var THEME_KEY = 'jnetstore:theme';

    var favorites = new Set();
    try {
        var savedFavs = JSON.parse(localStorage.getItem(FAV_KEY) || '[]');
        if (Array.isArray(savedFavs)) {
            savedFavs.forEach(function (slug) {
                if (bySlug.has(slug)) {
                    favorites.add(slug);
                }
            });
        }
    } catch (error) {
        favorites = new Set();
    }

    var activeChip = document.querySelector('.chip.is-active[data-category]');
    var state = {
        q: searchInput ? searchInput.value : '',
        category: activeChip ? activeChip.dataset.category : 'All',
        sort: sortSelect ? sortSelect.value : 'date',
        dir: dirToggle && dirToggle.dataset.dir === 'asc' ? 'asc' : 'desc',
        favOnly: false,
        view: document.documentElement.getAttribute('data-view') === 'list' ? 'list' : 'grid'
    };

    var searchIndex = new Map(apps.map(function (app) {
        var haystack = [
            app.name,
            app.repo,
            app.description,
            app.category,
            app.language
        ].concat(app.topics || []).join(' ').toLowerCase();
        return [app.slug, haystack];
    }));

    var currentSlug = null;
    var frame = null;

    function icon(name) {
        var cls = 'icon' + (name === 'github' ? ' gh-icon' : '');
        return '<svg class="' + cls + '" aria-hidden="true"><use href="#i-' + name + '"></use></svg>';
    }

    function formatNumber(value) {
        var number = Number(value) || 0;
        if (number >= 10000) {
            return Math.round(number / 1000) + 'k';
        }
        if (number >= 1000) {
            return (number / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
        }
        return String(number);
    }

    function saveFavorites() {
        try {
            localStorage.setItem(FAV_KEY, JSON.stringify(Array.from(favorites)));
        } catch (error) {
            /* Storage may be unavailable in private mode. */
        }
    }

    function compareApps(a, b) {
        var result = 0;
        switch (state.sort) {
            case 'name':
                result = a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
                break;
            case 'description':
                result = a.description.localeCompare(b.description);
                break;
            case 'rating':
                result = a.rating - b.rating;
                break;
            case 'stars':
                result = a.stars - b.stars;
                break;
            case 'date':
            default:
                result = a.updatedTs - b.updatedTs;
                break;
        }
        if (result === 0) {
            result = a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
        }
        return state.dir === 'asc' ? result : -result;
    }

    function matches(app) {
        if (state.favOnly && !favorites.has(app.slug)) {
            return false;
        }
        if (state.category !== 'All' && app.category !== state.category) {
            return false;
        }
        var query = state.q.trim().toLowerCase();
        if (!query) {
            return true;
        }
        var haystack = searchIndex.get(app.slug) || '';
        return query.split(/\s+/).every(function (token) {
            return haystack.indexOf(token) !== -1;
        });
    }

    function syncChips() {
        document.querySelectorAll('.chip[data-category]').forEach(function (chip) {
            var active = chip.dataset.category === state.category;
            chip.classList.toggle('is-active', active);
            if (active) {
                chip.setAttribute('aria-current', 'true');
            } else {
                chip.removeAttribute('aria-current');
            }
        });
    }

    function syncDirButton() {
        if (!dirToggle) {
            return;
        }
        var descending = state.dir === 'desc';
        dirToggle.dataset.dir = state.dir;
        dirToggle.setAttribute('aria-label', descending ? 'Sort descending' : 'Sort ascending');
        dirToggle.title = descending ? 'Descending' : 'Ascending';
        if (dirIconUse) {
            dirIconUse.setAttribute('href', descending ? '#i-arrow-down' : '#i-arrow-up');
        }
    }

    function syncView() {
        document.documentElement.setAttribute('data-view', state.view);
        viewButtons.forEach(function (button) {
            var active = button.dataset.view === state.view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function syncFavoriteButtons() {
        document.querySelectorAll('[data-fav]').forEach(function (button) {
            var slug = button.dataset.fav;
            var saved = favorites.has(slug);
            var app = bySlug.get(slug);
            button.classList.toggle('is-active', saved);
            button.setAttribute('aria-pressed', saved ? 'true' : 'false');
            button.setAttribute('aria-label', (saved ? 'Remove ' : 'Save ') + (app ? app.name : 'app'));
        });

        if (favCountEl) {
            favCountEl.textContent = String(favorites.size);
        }
        if (favToggle) {
            favToggle.classList.toggle('is-active', state.favOnly);
            favToggle.setAttribute('aria-pressed', state.favOnly ? 'true' : 'false');
        }
        syncDialogFavorite();
    }

    function syncDialogFavorite() {
        if (!dlgFav) {
            return;
        }
        var saved = Boolean(currentSlug && favorites.has(currentSlug));
        dlgFav.classList.toggle('is-active', saved);
        dlgFav.setAttribute('aria-pressed', saved ? 'true' : 'false');
        if (dlgFavText) {
            dlgFavText.textContent = saved ? 'Saved' : 'Save';
        }
    }

    function toggleFavorite(slug) {
        if (!bySlug.has(slug)) {
            return;
        }
        if (favorites.has(slug)) {
            favorites.delete(slug);
        } else {
            favorites.add(slug);
        }
        saveFavorites();
        syncFavoriteButtons();
        if (state.favOnly) {
            scheduleApply();
        }
    }

    function updateUrl() {
        try {
            var url = new URL(window.location.href);
            var setOrDelete = function (key, value, fallback) {
                if (!value || value === fallback) {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, value);
                }
            };
            setOrDelete('q', state.q.trim(), '');
            setOrDelete('category', state.category, 'All');
            setOrDelete('sort', state.sort, 'date');
            setOrDelete('dir', state.dir, 'desc');
            url.searchParams.delete('app');
            window.history.replaceState(null, '', url.toString());
        } catch (error) {
            /* history API can be unavailable in sandboxed iframes */
        }
    }

    function syncEmptyState(visible) {
        if (!emptyState) {
            return;
        }
        emptyState.hidden = visible !== 0;
        var heading = emptyState.querySelector('h3');
        var copy = emptyState.querySelector('p');
        if (state.favOnly && favorites.size === 0 && !state.q) {
            if (heading) heading.textContent = 'No saved apps yet';
            if (copy) copy.textContent = 'Tap the heart on any app to keep it here for later.';
        } else if (state.favOnly) {
            if (heading) heading.textContent = 'No saved apps match';
            if (copy) copy.textContent = 'Try another search or switch back to all apps.';
        } else {
            if (heading) heading.textContent = 'No apps found';
            if (copy) copy.textContent = 'Try a different search term or clear the filters to see the full catalogue.';
        }
    }

    function applyFilters() {
        frame = null;

        var ordered = apps.slice().sort(compareApps);
        var visible = 0;
        var desiredOrder = [];

        ordered.forEach(function (app) {
            var card = cards.get(app.slug);
            if (!card) {
                return;
            }
            var show = matches(app);
            card.hidden = !show;
            if (show) {
                visible += 1;
                desiredOrder.push(card);
            }
        });

        var currentOrder = Array.prototype.slice.call(
            grid.querySelectorAll('.app-card:not([hidden])')
        );
        var orderChanged = currentOrder.length !== desiredOrder.length ||
            currentOrder.some(function (card, index) { return card !== desiredOrder[index]; });

        if (orderChanged) {
            var fragment = document.createDocumentFragment();
            desiredOrder.forEach(function (card) {
                fragment.appendChild(card);
            });
            grid.appendChild(fragment);
        }

        if (resultCountEl) {
            resultCountEl.textContent = String(visible);
        }
        if (resultLabelEl) {
            resultLabelEl.textContent = visible === 1 ? 'app' : 'apps';
        }
        if (searchClear) {
            searchClear.hidden = state.q === '';
        }

        syncChips();
        syncDirButton();
        syncEmptyState(visible);
        updateUrl();
    }

    function scheduleApply() {
        if (frame !== null) {
            cancelAnimationFrame(frame);
        }
        frame = requestAnimationFrame(applyFilters);
    }

    function openDialog(slug) {
        var app = bySlug.get(slug);
        if (!app || !dialog) {
            return;
        }
        currentSlug = slug;

        if (dlgIcon) {
            dlgIcon.className = 'app-icon app-icon-lg g-' + app.gradient;
        }
        if (dlgInitials) dlgInitials.textContent = app.initials;
        if (dlgEmoji) dlgEmoji.textContent = app.categoryIcon || '📱';
        if (dlgCategory) dlgCategory.textContent = (app.categoryIcon || '') + ' ' + app.category;
        if (dlgName) dlgName.textContent = app.name;
        if (dlgRating) dlgRating.textContent = Number(app.rating).toFixed(1);
        if (dlgLang) dlgLang.textContent = app.language || 'Android';
        if (dlgDesc) dlgDesc.textContent = app.description;
        if (dlgUpdated) dlgUpdated.textContent = app.updatedLabel;
        if (dlgCreated) dlgCreated.textContent = app.createdLabel;
        if (dlgStars) dlgStars.textContent = formatNumber(app.stars);
        if (dlgForks) dlgForks.textContent = formatNumber(app.forks);
        if (dlgSize) dlgSize.textContent = app.size || '—';
        if (dlgLicense) dlgLicense.textContent = app.license || 'Open source';

        if (dlgTopics) {
            dlgTopics.textContent = '';
            var topics = Array.isArray(app.topics) ? app.topics.slice(0, 10) : [];
            topics.forEach(function (topic) {
                var chip = document.createElement('span');
                chip.className = 'topic';
                chip.textContent = topic;
                dlgTopics.appendChild(chip);
            });
            dlgTopics.hidden = topics.length === 0;
        }

        if (dlgGet) {
            if (app.apkUrl) {
                dlgGet.href = app.apkUrl;
                dlgGet.innerHTML = icon('download') + ' Download APK';
                dlgGet.setAttribute('download', '');
            } else {
                dlgGet.href = app.repoUrl;
                dlgGet.innerHTML = icon('github') + ' View on GitHub';
                dlgGet.removeAttribute('download');
            }
        }
        if (dlgReleases) dlgReleases.href = app.releasesUrl;
        if (dlgZip) dlgZip.href = app.zipUrl;
        if (dlgIssues) dlgIssues.href = app.repoUrl + '/issues';
        if (dlgRepo) dlgRepo.href = app.repoUrl;

        if (dlgHome) {
            if (app.homepage) {
                dlgHome.href = app.homepage;
                dlgHome.hidden = false;
            } else {
                dlgHome.hidden = true;
                dlgHome.removeAttribute('href');
            }
        }

        syncDialogFavorite();

        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) {
                dialog.showModal();
            }
        } else {
            dialog.setAttribute('open', '');
        }
        document.body.classList.add('modal-open');
    }

    function closeDialog() {
        if (!dialog) {
            return;
        }
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        document.body.classList.remove('modal-open');
        currentSlug = null;
    }

    /* --------------------------------------------------------------------
       Events
       -------------------------------------------------------------------- */

    if (searchInput) {
        var searchTimer = null;
        searchInput.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () {
                state.q = searchInput.value;
                scheduleApply();
            }, 90);
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            state.q = searchInput ? searchInput.value : '';
            applyFilters();
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            state.q = '';
            applyFilters();
        });
    }

    document.addEventListener('click', function (event) {
        var favButton = event.target.closest('[data-fav]');
        if (favButton) {
            event.preventDefault();
            event.stopPropagation();
            toggleFavorite(favButton.dataset.fav);
            return;
        }

        var chip = event.target.closest('.chip[data-category]');
        if (chip) {
            event.preventDefault();
            state.category = chip.dataset.category || 'All';
            if (searchInput) {
                state.q = searchInput.value;
            }
            scheduleApply();
            return;
        }

        var card = event.target.closest('.app-card, .fresh-card');
        if (card && !event.target.closest('a, button')) {
            openDialog(card.dataset.slug);
        }
    });

    document.addEventListener('keydown', function (event) {
        var clickable = event.target.closest ? event.target.closest('.app-card, .fresh-card') : null;
        if (clickable && event.target === clickable && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openDialog(clickable.dataset.slug);
            return;
        }

        if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey) {
            var target = event.target;
            var typing = target && (
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT' ||
                target.isContentEditable
            );
            if (!typing) {
                event.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        }

        if (event.key === 'Escape' && document.activeElement === searchInput && searchInput.value) {
            searchInput.value = '';
            state.q = '';
            applyFilters();
        }
    });

    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            if (searchInput) {
                state.q = searchInput.value;
            }
            state.sort = sortSelect.value;
            state.dir = (state.sort === 'name' || state.sort === 'description') ? 'asc' : 'desc';
            applyFilters();
        });
    }

    if (dirToggle) {
        dirToggle.addEventListener('click', function () {
            state.dir = state.dir === 'desc' ? 'asc' : 'desc';
            applyFilters();
        });
    }

    if (favToggle) {
        favToggle.addEventListener('click', function () {
            if (searchInput) {
                state.q = searchInput.value;
            }
            state.favOnly = !state.favOnly;
            syncFavoriteButtons();
            applyFilters();
        });
    }

    viewButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            state.view = button.dataset.view === 'list' ? 'list' : 'grid';
            syncView();
            try {
                localStorage.setItem(VIEW_KEY, state.view);
            } catch (error) {
                /* ignore */
            }
        });
    });

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            var next = current === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', next);
            if (themeMeta) {
                themeMeta.setAttribute('content', next === 'light' ? '#f3f5fc' : '#080d1a');
            }
            try {
                localStorage.setItem(THEME_KEY, next);
            } catch (error) {
                /* ignore */
            }
        });
    }

    if (emptyReset) {
        emptyReset.addEventListener('click', function () {
            state.q = '';
            state.category = 'All';
            state.favOnly = false;
            state.sort = 'date';
            state.dir = 'desc';
            if (searchInput) searchInput.value = '';
            if (sortSelect) sortSelect.value = 'date';
            syncFavoriteButtons();
            applyFilters();
        });
    }

    if (dlgClose) {
        dlgClose.addEventListener('click', closeDialog);
    }

    if (dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                closeDialog();
            }
        });
        dialog.addEventListener('close', function () {
            document.body.classList.remove('modal-open');
            currentSlug = null;
        });
    }

    if (dlgFav) {
        dlgFav.addEventListener('click', function () {
            if (currentSlug) {
                toggleFavorite(currentSlug);
            }
        });
    }

    /* --------------------------------------------------------------------
       Boot
       -------------------------------------------------------------------- */

    if (themeMeta) {
        var theme = document.documentElement.getAttribute('data-theme');
        themeMeta.setAttribute('content', theme === 'light' ? '#f3f5fc' : '#080d1a');
    }

    syncView();
    syncFavoriteButtons();
    applyFilters();

    try {
        var params = new URLSearchParams(window.location.search);
        var deepLink = params.get('app');
        if (deepLink && bySlug.has(deepLink)) {
            openDialog(deepLink);
        }
    } catch (error) {
        /* ignore malformed URLs */
    }
})();
