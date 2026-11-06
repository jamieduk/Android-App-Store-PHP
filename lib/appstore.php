<?php
declare(strict_types=1);

/**
 * Shared helpers for the J~Net Android App Store.
 *
 * The catalogue is cached in data/apps.json (produced by sync.php) so the
 * storefront renders instantly and does not depend on the GitHub API at
 * request time.
 */

const APP_STORE_ROOT = __DIR__ . '/..';
const APP_STORE_DATA = APP_STORE_ROOT . '/data/apps.json';
const APP_STORE_OWNER = 'jnetai-clawbot';
const APP_STORE_DEVELOPER = 'J~Net';

function as_load_apps(?string $path = null): array
{
    $path = $path ?? APP_STORE_DATA;
    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);
    if ($json === false || $json === '') {
        return [];
    }

    $apps = json_decode($json, true);
    return is_array($apps) ? $apps : [];
}

function as_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function as_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-') ?: 'app';
}

/**
 * Turn a repository name into a shop-ready display name.
 * CamelCase is split, separators become spaces and known acronyms are
 * upper-cased. Brand names that the algorithm cannot infer live in $overrides.
 */
function as_display_name(string $name): string
{
    static $overrides = [
        'Calc-Plus' => 'Calc+',
        'BT-Thermal-printer-Android-App' => 'BT Thermal Printer',
        'BT-KB-Mouse' => 'BT Keyboard & Mouse',
        'bitchat-plus' => 'BitChat Plus',
        'kik-red' => 'KIK-Red',
        'DeadMansSwitch' => "Dead Man's Switch",
        'Mobile-Ciper' => 'Mobile Cipher',
        'World-Times.apk' => 'World Times',
        'nascontrol' => 'NAS Control',
        'droplan' => 'DropPlan',
        'findai' => 'FindAI',
        'subsync' => 'SubSync',
        'permguard' => 'PermGuard',
        'screenai' => 'ScreenAI',
        'querylite' => 'QueryLite',
        'tidydroid' => 'TidyDroid',
        'jnet-notes-v2' => 'J~Net Notes v2',
        'jnet-video-player' => 'J~Net Video Player',
        'HROnboarding' => 'HR Onboarding',
        'PhDResearch' => 'PhD Research',
        'GDPRCompliance' => 'GDPR Compliance',
        'SubAudit' => 'SubAudit',
        'LocalShare' => 'LocalShare',
        'EventRSVP' => 'Event RSVP',
        'WebMediaPlayer' => 'Web Media Player',
        'jnetai-media-player' => 'JNetAI Media Player',
        'clawbot-games' => 'ClawBot Games',
        'web-app' => 'Web Apps',
        'industryradio-app' => 'Industry Radio',
        'fonts-app' => 'Fonts',
        'JNet-System-Monitor' => 'J~Net System Monitor',
        'JNet-App-Store' => 'J~Net App Store',
        'JNet-Keyboard' => 'J~Net Keyboard',
        'jnet-ai-assistant' => 'J~Net AI Assistant',
        'KIK-X2' => 'KIK-X2',
        'OCR-Lens-AI' => 'OCR Lens AI',
    ];

    if (isset($overrides[$name])) {
        return $overrides[$name];
    }

    $value = preg_replace('/\.apk$/i', '', $name) ?? $name;
    $value = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $value) ?? $value;
    $value = str_replace(['-', '_', '.'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? $value;

    $acronyms = [
        'ai', 'apk', 'api', 'bt', 'css', 'gps', 'html', 'http', 'https', 'ime',
        'json', 'kb', 'lan', 'mp3', 'nas', 'nda', 'nfc', 'ocr', 'p2p', 'pos',
        'qr', 'ssh', 'sms', 'sos', 'sql', 'totp', 'ui', 'uk', 'url', 'usb',
        'vpn', 'pdf', 'hr', 'gdpr', 'rsvp', 'ni', 'uc', 'id', 'pc', 'os',
    ];
    $brands = [
        'jnet' => 'J~Net',
        'jnetai' => 'JNetAI',
        'kik' => 'KIK',
        'wifi' => 'WiFi',
        'bitchat' => 'BitChat',
        'subaudit' => 'SubAudit',
        'localshare' => 'LocalShare',
        'droplan' => 'DropPlan',
        'querylite' => 'QueryLite',
        'tidydroid' => 'TidyDroid',
        'subsync' => 'SubSync',
        'permguard' => 'PermGuard',
        'findai' => 'FindAI',
        'screenai' => 'ScreenAI',
        'nascontrol' => 'NAS Control',
    ];

    $words = explode(' ', $value);
    foreach ($words as &$word) {
        $lower = strtolower($word);
        if (isset($brands[$lower])) {
            $word = $brands[$lower];
        } elseif (in_array($lower, $acronyms, true)) {
            $word = strtoupper($word);
        } else {
            $word = ucfirst($word);
        }
    }
    unset($word);

    return implode(' ', $words);
}

/**
 * Explicit category assignment for every repository, with a keyword fallback
 * for any new repositories picked up by sync.php.
 */
function as_category_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $groups = [
        'Games' => [
            'Checkers', 'Puzzle', 'flappier-birds', 'SporeHeist', 'LastCharge',
            'Muzzle', 'PhaseRunner', 'RogueReel', 'PaperPlaneTycoon', 'Substratum',
            'SignalDecay', 'GravityGraffiti', 'EchoDrifter', 'clawbot-games',
        ],
        'Tools' => [
            'Android-Screen-Recorder', 'JNet-System-Monitor', 'NetworkScanner',
            'JNet-App-Store', 'JNet-Keyboard', 'BT-Thermal-printer-Android-App',
            'DeviceInfoPro', 'OfflineTracker', 'ServerMonitor', 'LANControl',
            'BatterySaver', 'StopWatch', 'BT-KB-Mouse', 'screen-remote',
            'offline-maps', 'media-remote', 'remote-camera', 'Binoculars',
            'World-Times', 'World-Times.apk', 'nascontrol', 'droplan',
            'querylite', 'tidydroid', 'SSHCommander', 'ssh-commander',
            'AlarmVault', 'automation-scripts',
        ],
        'Media' => [
            'VoiceChanger', 'Music-Player', 'industryradio-app', 'fonts-app',
            'call-recorder', 'subsync', 'VoiceNotes', 'jnet-video-player',
            'PodcastPipe', 'MusicPracticeLog', 'Media-Player', 'WebMediaPlayer',
            'jnetai-media-player',
        ],
        'Communication' => [
            'random-p2p-chat', 'LocalShare', 'bitchat-plus', 'KIK-X2',
            'kik-red', 'Secure-Messenger-Mobile', 'wifi-file-transfer',
            'clipboard-sync', 'p2p-chat',
        ],
        'Security' => [
            'PixelSteg', 'Calc-Plus', 'TOTP-Authenticator', 'Mobile-Ciper',
            'SafeLook', 'permguard', 'Password-Generator', 'android-malware-scanner',
            'Password-Manager',
        ],
        'AI' => [
            'jnet-ai-assistant', 'findai', 'screenai', 'ocr-lens-ai',
        ],
        'Productivity' => [
            'web-app', 'ClipIntent', 'whiteboard-app', 'flashcard-app',
            'barcode-inventory', 'notes-app', 'jnet-notes-v2', 'PhDResearch',
            'ArtPortfolio', 'SkillBuilder', 'BookWriter', 'HomeworkCoach',
            'SubAudit', 'Work-Calc-Pro', 'professional-work-calc',
        ],
        'Business' => [
            'Web-Age-Verification', 'InvestorUpdate', 'HROnboarding',
            'LocalPermit', 'PoliTracker', 'PatentMonitor', 'ChurnPredictor',
            'GDPRCompliance', 'ContractNDA',
        ],
        'Lifestyle' => [
            'DeadMansSwitch', 'SelfImprovement', 'ParkingSpot', 'ElderCare',
            'DigitalLegacy', 'MindWell', 'CommunityAlert', 'VolunteerLog',
            'PlantCare', 'EventRSVP', 'PetCare', 'Carbon-Tracker', 'MedTracker',
        ],
    ];

    $map = [];
    foreach ($groups as $category => $names) {
        foreach ($names as $name) {
            $map[$name] = $category;
        }
    }

    return $map;
}

function as_category(string $name, string $description = ''): string
{
    $map = as_category_map();
    if (isset($map[$name])) {
        return $map[$name];
    }

    $haystack = strtolower($name . ' ' . $description);
    $rules = [
        'Games' => ['game', 'puzzle', 'heist', 'runner', 'tycoon', 'checkers', 'arcade'],
        'Security' => ['encrypt', 'cipher', 'password', 'secure', 'totp', 'steg', 'sos', 'malware', 'vault', 'permission'],
        'Communication' => ['chat', 'messenger', 'transfer', 'share', 'clipboard', 'kik', 'p2p', 'bluetooth mesh'],
        'Media' => ['music', 'video', 'audio', 'voice', 'radio', 'podcast', 'player', 'recorder', 'font'],
        'AI' => [' ai ', 'assistant', 'ocr', 'local model', 'rag'],
        'Lifestyle' => ['health', 'well', 'plant', 'pet', 'med', 'carbon', 'parking', 'community', 'elder', 'legacy', 'volunteer', 'rsvp'],
        'Business' => ['hr ', 'permit', 'poli', 'patent', 'churn', 'portfolio', 'gdpr', 'contract', 'nda', 'investor', 'audit'],
        'Productivity' => ['notes', 'flashcard', 'inventory', 'whiteboard', 'homework', 'book', 'skill', 'practice', 'work', 'calc', 'track', 'plan'],
    ];

    foreach ($rules as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return $category;
            }
        }
    }

    return 'Tools';
}

function as_category_meta(): array
{
    return [
        'Games' => ['label' => 'Games', 'icon' => '🎮'],
        'Tools' => ['label' => 'Tools', 'icon' => '🧰'],
        'Media' => ['label' => 'Media', 'icon' => '🎬'],
        'Communication' => ['label' => 'Chat', 'icon' => '💬'],
        'Security' => ['label' => 'Security', 'icon' => '🔐'],
        'AI' => ['label' => 'AI', 'icon' => '🤖'],
        'Productivity' => ['label' => 'Productivity', 'icon' => '⚡'],
        'Business' => ['label' => 'Business', 'icon' => '💼'],
        'Lifestyle' => ['label' => 'Lifestyle', 'icon' => '🌿'],
    ];
}

function as_category_icon(string $category): string
{
    $meta = as_category_meta();
    return $meta[$category]['icon'] ?? '📱';
}

/**
 * A deterministic store-style rating derived from public GitHub signals.
 * It is stable across requests (so sorting does not jump around) and is
 * intentionally kept in the 3.9 – 4.9 range.
 */
function as_rating(array $app): float
{
    $name = (string) ($app['name'] ?? '');
    $stars = max(0, (int) ($app['stars'] ?? 0));
    $forks = max(0, (int) ($app['forks'] ?? 0));
    $seed = crc32($name);

    $jitter = (($seed % 31) - 15) / 100;            // -0.15 … +0.15
    $starBonus = min(0.50, $stars * 0.12 + $forks * 0.03);

    $pushed = strtotime((string) ($app['pushed_at'] ?? $app['updated_at'] ?? ''));
    $recencyBonus = 0.0;
    if ($pushed !== false) {
        $days = (time() - $pushed) / 86400;
        if ($days <= 14) {
            $recencyBonus = 0.10;
        } elseif ($days <= 45) {
            $recencyBonus = 0.06;
        } elseif ($days <= 120) {
            $recencyBonus = 0.03;
        }
    }

    $rating = 4.18 + $starBonus + $jitter + $recencyBonus;
    return round(max(3.9, min(4.9, $rating)), 1);
}

function as_initials(string $displayName): string
{
    $clean = preg_replace('/[^A-Za-z0-9 ]/', ' ', $displayName) ?? $displayName;
    $parts = array_values(array_filter(preg_split('/\s+/', trim($clean)) ?: []));
    if (count($parts) === 0) {
        return 'AP';
    }
    if (count($parts) === 1) {
        return strtoupper(substr($parts[0], 0, 2));
    }
    return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
}

function as_gradient_index(string $name): int
{
    return crc32($name) % 10;
}

function as_fallback_description(string $category): string
{
    return match ($category) {
        'Games' => 'An original open-source Android game from the J~Net collection. Dive into the source and latest builds on GitHub.',
        'Tools' => 'A handy open-source Android utility from the J~Net collection. Explore its features, source code and releases on GitHub.',
        'Media' => 'An open-source media app from the J~Net collection for playing, recording and creating on Android.',
        'Communication' => 'A private, open-source communication tool from the J~Net collection. Read the code and download the latest build on GitHub.',
        'Security' => 'A privacy-first, open-source security tool from the J~Net collection. Inspect the source and grab the latest release on GitHub.',
        'AI' => 'An on-device AI app from the J~Net collection. Explore the models, source code and releases on GitHub.',
        'Productivity' => 'An open-source productivity app from the J~Net collection to help you get more done on Android.',
        'Business' => 'An open-source business utility from the J~Net collection. Read the documentation and source on GitHub.',
        'Lifestyle' => 'An open-source lifestyle app from the J~Net collection designed to make everyday life a little easier.',
        default => 'An open-source Android app from the J~Net collection. Explore the source code, features and releases on GitHub.',
    };
}

function as_time_ago(int $timestamp): string
{
    $diff = time() - $timestamp;
    if ($diff < 0) {
        $diff = 0;
    }
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . 'm ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . 'h ago';
    }
    if ($diff < 86400 * 30) {
        $days = (int) floor($diff / 86400);
        return $days . 'd ago';
    }
    if ($diff < 86400 * 365) {
        $months = (int) floor($diff / (86400 * 30));
        return $months . 'mo ago';
    }
    $years = (int) floor($diff / (86400 * 365));
    return $years . 'y ago';
}

function as_format_date(?string $iso): string
{
    if (!$iso) {
        return 'Unknown';
    }
    $ts = strtotime($iso);
    return $ts === false ? 'Unknown' : date('j M Y', $ts);
}

function as_format_size(int $kilobytes): string
{
    if ($kilobytes < 1024) {
        return $kilobytes . ' KB';
    }
    $mb = $kilobytes / 1024;
    return ($mb >= 10 ? round($mb) : round($mb, 1)) . ' MB';
}

/**
 * Add all derived display fields used by the storefront.
 */
function as_prepare_app(array $app): array
{
    $category = as_category((string) ($app['name'] ?? ''), (string) ($app['description'] ?? ''));
    $displayName = as_display_name((string) ($app['name'] ?? 'App'));
    $description = trim((string) ($app['description'] ?? ''));

    if ($description === '' || strtolower($description) === 'null') {
        $description = as_fallback_description($category);
    }

    $pushedIso = (string) ($app['pushed_at'] ?? $app['updated_at'] ?? '');
    $pushedTs = strtotime($pushedIso) ?: time();
    $repoUrl = (string) ($app['html_url'] ?? 'https://github.com/' . APP_STORE_OWNER . '/' . ($app['name'] ?? ''));

    $app['display_name'] = $displayName;
    $app['slug'] = as_slug((string) ($app['name'] ?? $displayName));
    $app['category'] = $category;
    $app['category_icon'] = as_category_icon($category);
    $app['description'] = $description;
    $app['rating'] = as_rating($app);
    $app['initials'] = as_initials($displayName);
    $app['gradient'] = as_gradient_index((string) ($app['name'] ?? $displayName));
    $app['updated_ts'] = $pushedTs;
    $app['updated_human'] = as_time_ago($pushedTs);
    $app['updated_label'] = as_format_date($pushedIso);
    $app['is_new'] = (time() - $pushedTs) <= 86400 * 30;
    $app['repo_url'] = $repoUrl;
    $app['releases_url'] = $repoUrl . '/releases';
    $app['zip_url'] = $repoUrl . '/archive/refs/heads/' . rawurlencode((string) ($app['default_branch'] ?? 'main')) . '.zip';
    $app['topics'] = array_values(array_filter((array) ($app['topics'] ?? [])));
    $app['language'] = (string) ($app['language'] ?? 'Android');
    $app['stars'] = (int) ($app['stars'] ?? 0);
    $app['forks'] = (int) ($app['forks'] ?? 0);
    $app['open_issues'] = (int) ($app['open_issues'] ?? 0);
    $app['size_label'] = as_format_size((int) ($app['size'] ?? 0));
    $app['created_label'] = as_format_date($app['created_at'] ?? null);
    $app['license'] = $app['license'] ?? null;
    $app['homepage'] = trim((string) ($app['homepage'] ?? '')) ?: null;
    $app['apk_url'] = trim((string) ($app['apk_url'] ?? '')) ?: null;
    $app['release_tag'] = $app['release_tag'] ?? null;

    return $app;
}

function as_prepare_apps(array $apps): array
{
    $prepared = [];
    foreach ($apps as $app) {
        if (is_array($app) && isset($app['name'])) {
            $prepared[] = as_prepare_app($app);
        }
    }
    return $prepared;
}

function as_sort_apps(array $apps, string $sort, string $direction): array
{
    $direction = $direction === 'asc' ? 'asc' : 'desc';

    $comparators = [
        'date' => static fn (array $a, array $b): int => $a['updated_ts'] <=> $b['updated_ts'],
        'name' => static fn (array $a, array $b): int => strcasecmp($a['display_name'], $b['display_name']),
        'description' => static fn (array $a, array $b): int => strcasecmp($a['description'], $b['description']),
        'rating' => static fn (array $a, array $b): int => $a['rating'] <=> $b['rating'],
        'stars' => static fn (array $a, array $b): int => $a['stars'] <=> $b['stars'],
    ];

    $compare = $comparators[$sort] ?? $comparators['date'];
    usort($apps, static function (array $a, array $b) use ($compare): int {
        $result = $compare($a, $b);
        if ($result === 0) {
            $result = strcasecmp($a['display_name'], $b['display_name']);
        }
        return $result;
    });

    if ($direction === 'desc') {
        $apps = array_reverse($apps);
    }

    return $apps;
}

function as_filter_apps(array $apps, string $query, string $category): array
{
    $query = trim($query);
    $category = trim($category);

    return array_values(array_filter($apps, static function (array $app) use ($query, $category): bool {
        if ($category !== '' && $category !== 'All' && $app['category'] !== $category) {
            return false;
        }
        if ($query === '') {
            return true;
        }

        $haystack = implode(' ', [
            $app['display_name'],
            $app['name'],
            $app['description'],
            $app['category'],
            $app['language'],
            implode(' ', $app['topics']),
        ]);

        return mb_stripos($haystack, $query, 0, 'UTF-8') !== false;
    }));
}

function as_app_payload(array $apps): array
{
    $payload = [];
    foreach ($apps as $app) {
        $payload[] = [
            'slug' => $app['slug'],
            'name' => $app['display_name'],
            'repo' => $app['name'],
            'description' => $app['description'],
            'category' => $app['category'],
            'categoryIcon' => $app['category_icon'],
            'rating' => $app['rating'],
            'stars' => $app['stars'],
            'forks' => $app['forks'],
            'language' => $app['language'],
            'topics' => $app['topics'],
            'initials' => $app['initials'],
            'gradient' => $app['gradient'],
            'updatedTs' => $app['updated_ts'],
            'updatedHuman' => $app['updated_human'],
            'updatedLabel' => $app['updated_label'],
            'createdLabel' => $app['created_label'],
            'isNew' => $app['is_new'],
            'repoUrl' => $app['repo_url'],
            'releasesUrl' => $app['releases_url'],
            'zipUrl' => $app['zip_url'],
            'homepage' => $app['homepage'],
            'apkUrl' => $app['apk_url'],
            'releaseTag' => $app['release_tag'],
            'size' => $app['size_label'],
            'license' => $app['license'],
        ];
    }
    return $payload;
}

/**
 * Tiny inline SVG icon helper. Keeping icons in one sprite avoids external
 * asset requests and lets the JS reuse the same symbols.
 */
function as_icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . as_e($class) . '" aria-hidden="true" focusable="false"><use href="#i-' . as_e($name) . '"></use></svg>';
}
