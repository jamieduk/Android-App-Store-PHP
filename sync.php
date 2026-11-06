<?php
declare(strict_types=1);

/**
 * Refresh data/apps.json from the GitHub API.
 *
 * CLI (recommended):
 *   GITHUB_TOKEN=ghp_xxx php sync.php
 *   php sync.php --owner=jnetai-clawbot --no-releases
 *
 * Web (only when SYNC_KEY is configured):
 *   https://example.com/sync.php?key=your-secret
 *
 * A token is optional for the repository list (2 requests) but strongly
 * recommended for the per-repository release lookup (1 request each).
 */

require __DIR__ . '/lib/appstore.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $expected = (string) (getenv('SYNC_KEY') ?: '');
    $provided = (string) ($_GET['key'] ?? '');
    if ($expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden. Run sync.php from the command line or configure SYNC_KEY.\n";
        exit(1);
    }
    header('Content-Type: text/plain; charset=utf-8');
}

if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

$options = getopt('', ['token::', 'owner::', 'no-releases', 'releases', 'help']);

if (isset($options['help'])) {
    echo <<<TXT
J~Net App Store sync

Usage:
  php sync.php [--owner=NAME] [--token=TOKEN] [--no-releases]

Options:
  --owner=NAME    GitHub user or organisation (default: jnetai-clawbot)
  --token=TOKEN   GitHub token (or set GITHUB_TOKEN in the environment)
  --no-releases   Only refresh repository metadata; keep existing APK links
  --help          Show this help

Environment:
  GITHUB_TOKEN    GitHub token used for API authentication
  SYNC_KEY        Required when running sync.php over HTTP

TXT;
    exit(0);
}

$owner = trim((string) ($options['owner'] ?? APP_STORE_OWNER));
$token = trim((string) ($options['token'] ?? getenv('GITHUB_TOKEN') ?: ''));
$fetchReleases = !isset($options['no-releases']);

if ($owner === '') {
    fwrite(STDERR, "Owner cannot be empty.\n");
    exit(1);
}

$apiBase = 'https://api.github.com';
$existing = [];
foreach (as_load_apps() as $app) {
    if (isset($app['name'])) {
        $existing[$app['name']] = $app;
    }
}

function log_line(string $message): void
{
    echo $message . PHP_EOL;
}

/**
 * @return array{status:int, body:mixed, error:string}
 */
function github_request(string $url, string $token): array
{
    $headers = [
        'User-Agent: JNet-App-Store-Sync/1.0',
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => null, 'error' => 'Could not initialise cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 30,
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        $error = '';

        $responseHeaders = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?: [])
            : ($http_response_header ?? []);

        if (is_array($responseHeaders)) {
            foreach ($responseHeaders as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches)) {
                    $status = (int) $matches[1];
                }
            }
        }

        if ($body === false) {
            $lastError = error_get_last();
            $error = is_array($lastError) ? (string) ($lastError['message'] ?? '') : 'HTTP request failed';
        }
    }

    $decoded = null;
    if (is_string($body) && $body !== '') {
        $decoded = json_decode($body, true);
    }

    return [
        'status' => $status,
        'body' => $decoded,
        'error' => $error,
    ];
}

function github_remaining(string $apiBase, string $token): ?int
{
    $result = github_request($apiBase . '/rate_limit', $token);
    if ($result['status'] !== 200 || !is_array($result['body'])) {
        return null;
    }
    $remaining = $result['body']['resources']['core']['remaining'] ?? null;
    return is_int($remaining) ? $remaining : null;
}

log_line('J~Net App Store sync');
log_line('Owner: ' . $owner);
log_line('Token: ' . ($token !== '' ? 'yes' : 'no (60 requests/hour)'));
log_line('Releases: ' . ($fetchReleases ? 'yes' : 'skipped'));
log_line('');

$repositories = [];
$page = 1;
$maxPages = 20;

do {
    $url = $apiBase . '/users/' . rawurlencode($owner) . '/repos?per_page=100&page=' . $page . '&sort=updated';
    $result = github_request($url, $token);

    if ($result['status'] !== 200) {
        fwrite(STDERR, 'Failed to fetch repositories (HTTP ' . $result['status'] . ')');
        if ($result['error'] !== '') {
            fwrite(STDERR, ': ' . $result['error']);
        }
        if (is_array($result['body']) && isset($result['body']['message'])) {
            fwrite(STDERR, ' — ' . $result['body']['message']);
        }
        fwrite(STDERR, PHP_EOL);
        exit(1);
    }

    if (!is_array($result['body'])) {
        fwrite(STDERR, "Unexpected repository response.\n");
        exit(1);
    }

    $batch = $result['body'];
    foreach ($batch as $repo) {
        if (is_array($repo) && isset($repo['name'])) {
            $repositories[$repo['name']] = $repo;
        }
    }

    log_line('Fetched page ' . $page . ' (' . count($batch) . ' repositories)');
    $page++;
} while (count($batch) === 100 && $page <= $maxPages);

$repositories = array_values($repositories);
log_line('Found ' . count($repositories) . ' public repositories.');
log_line('');

$releaseMap = [];
if ($fetchReleases && $repositories !== []) {
    $remaining = github_remaining($apiBase, $token);
    $needed = count($repositories) + 5;

    if ($remaining === null) {
        log_line('Could not read rate limit; release lookup will be attempted.');
    } elseif ($remaining < $needed) {
        log_line('Rate limit too low (' . $remaining . ' remaining, ~' . $needed . ' needed).');
        log_line('Keeping existing APK links. Use GITHUB_TOKEN for a full refresh.');
        $fetchReleases = false;
    } else {
        log_line('Rate limit remaining: ' . $remaining . '. Looking up latest releases…');
    }
}

if ($fetchReleases) {
    $index = 0;
    $total = count($repositories);

    foreach ($repositories as $repo) {
        $name = (string) $repo['name'];
        $index++;

        if ($index % 10 === 0 || $index === $total) {
            log_line('  release lookup ' . $index . '/' . $total . ' — ' . $name);
        }

        $url = $apiBase . '/repos/' . rawurlencode($owner) . '/' . rawurlencode($name) . '/releases/latest';
        $result = github_request($url, $token);

        if ($result['status'] === 404) {
            $releaseMap[$name] = [
                'apk_url' => null,
                'apk_name' => null,
                'apk_size' => null,
                'release_tag' => null,
                'release_date' => null,
            ];
            continue;
        }

        if ($result['status'] === 403 || $result['status'] === 429) {
            log_line('Rate limited while fetching releases; keeping remaining existing links.');
            break;
        }

        if ($result['status'] !== 200 || !is_array($result['body'])) {
            continue;
        }

        $release = $result['body'];
        $apkUrl = null;
        $apkName = null;
        $apkSize = null;

        foreach ((array) ($release['assets'] ?? []) as $asset) {
            $assetName = (string) ($asset['name'] ?? '');
            if (strtolower(pathinfo($assetName, PATHINFO_EXTENSION)) === 'apk') {
                $apkUrl = (string) ($asset['browser_download_url'] ?? '') ?: null;
                $apkName = $assetName;
                $apkSize = isset($asset['size']) ? (int) $asset['size'] : null;
                break;
            }
        }

        $releaseMap[$name] = [
            'apk_url' => $apkUrl,
            'apk_name' => $apkName,
            'apk_size' => $apkSize,
            'release_tag' => $release['tag_name'] ?? null,
            'release_date' => $release['published_at'] ?? null,
        ];
    }
}

$apps = [];
foreach ($repositories as $repo) {
    $name = (string) $repo['name'];
    $old = $existing[$name] ?? [];

    $release = $releaseMap[$name] ?? [
        'apk_url' => $old['apk_url'] ?? null,
        'apk_name' => $old['apk_name'] ?? null,
        'apk_size' => $old['apk_size'] ?? null,
        'release_tag' => $old['release_tag'] ?? null,
        'release_date' => $old['release_date'] ?? null,
    ];

    $apps[] = [
        'name' => $name,
        'full_name' => (string) ($repo['full_name'] ?? ($owner . '/' . $name)),
        'description' => $repo['description'] ?? null,
        'html_url' => (string) ($repo['html_url'] ?? ('https://github.com/' . $owner . '/' . $name)),
        'homepage' => $repo['homepage'] ?? null,
        'language' => $repo['language'] ?? null,
        'topics' => array_values((array) ($repo['topics'] ?? [])),
        'stars' => (int) ($repo['stargazers_count'] ?? 0),
        'forks' => (int) ($repo['forks_count'] ?? 0),
        'watchers' => (int) ($repo['watchers_count'] ?? 0),
        'open_issues' => (int) ($repo['open_issues_count'] ?? 0),
        'created_at' => $repo['created_at'] ?? null,
        'updated_at' => $repo['updated_at'] ?? null,
        'pushed_at' => $repo['pushed_at'] ?? null,
        'size' => (int) ($repo['size'] ?? 0),
        'default_branch' => $repo['default_branch'] ?? 'main',
        'archived' => (bool) ($repo['archived'] ?? false),
        'license' => $repo['license']['spdx_id'] ?? null,
        'owner_login' => $repo['owner']['login'] ?? $owner,
        'owner_avatar' => $repo['owner']['avatar_url'] ?? null,
        'apk_url' => $release['apk_url'],
        'apk_name' => $release['apk_name'],
        'apk_size' => $release['apk_size'],
        'release_tag' => $release['release_tag'],
        'release_date' => $release['release_date'],
    ];
}

usort($apps, static function (array $a, array $b): int {
    return strcmp((string) ($b['pushed_at'] ?? ''), (string) ($a['pushed_at'] ?? ''));
});

$json = json_encode($apps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "Could not encode catalogue JSON.\n");
    exit(1);
}

$dataFile = APP_STORE_DATA;
$directory = dirname($dataFile);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    fwrite(STDERR, "Could not create data directory.\n");
    exit(1);
}

$tempFile = $dataFile . '.tmp';
if (file_put_contents($tempFile, $json . PHP_EOL) === false) {
    fwrite(STDERR, "Could not write temporary catalogue file.\n");
    exit(1);
}

if (!rename($tempFile, $dataFile)) {
    @unlink($tempFile);
    fwrite(STDERR, "Could not replace catalogue file.\n");
    exit(1);
}

$withApk = count(array_filter($apps, static fn (array $app): bool => !empty($app['apk_url'])));

log_line('');
log_line('Saved ' . count($apps) . ' apps (' . $withApk . ' with direct APK downloads).');
log_line('Wrote ' . $dataFile);

$remainingAfter = github_remaining($apiBase, $token);
if ($remainingAfter !== null) {
    log_line('GitHub API requests remaining: ' . $remainingAfter);
}
