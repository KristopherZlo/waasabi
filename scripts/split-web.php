<?php

$root = dirname(__DIR__);
$source = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$partialsDir = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'partials';
$legacyBackup = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php.bak';
$backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'route-split';
$backup = $backupDir . DIRECTORY_SEPARATOR . 'web.php.bak';

if (!file_exists($source)) {
    fwrite(STDERR, "Missing routes/web.php\n");
    exit(1);
}

$sourceContentPath = file_exists($backup)
    ? $backup
    : (file_exists($legacyBackup) ? $legacyBackup : $source);
$content = file_get_contents($sourceContentPath);
if ($content === false) {
    fwrite(STDERR, "Unable to read routes/web.php source\n");
    exit(1);
}

$sections = [
    ['file' => 'feed.php', 'start' => "Route::get('/promos/"],
    ['file' => 'projects.php', 'start' => "Route::get('/projects/{slug}'"],
    ['file' => 'questions.php', 'start' => "Route::get('/questions/{slug}'"],
    ['file' => 'content-actions.php', 'start' => "Route::post('/reading-progress'"],
    ['file' => 'profile.php', 'start' => "Route::get('/profile'"],
    ['file' => 'support.php', 'start' => "Route::get('/showcase'"],
    ['file' => 'auth.php', 'start' => "Route::get('/locale/{"],
    ['file' => 'reading.php', 'start' => "Route::delete('/posts/{post}'"],
    ['file' => 'admin-moderation.php', 'start' => "Route::middleware(['auth', 'can:moderate'])"],
    ['file' => 'admin.php', 'start' => "Route::middleware(['auth', 'can:admin'])"],
    ['file' => 'legal.php', 'start' => "Route::prefix('legal')"],
    ['file' => 'fallback.php', 'start' => "Route::fallback("],
];

$positions = [];
foreach ($sections as $section) {
    $pos = strpos($content, $section['start']);
    if ($pos === false) {
        fwrite(STDERR, "Anchor not found: {$section['start']}\n");
        exit(1);
    }
    $positions[] = ['file' => $section['file'], 'pos' => $pos];
}

usort($positions, function ($a, $b) {
    return $a['pos'] <=> $b['pos'];
});

$firstRoutePos = $positions[0]['pos'];
$bootstrap = substr($content, 0, $firstRoutePos);
$imports = '';
if (preg_match_all('/^use\\s+[^;]+;\\r?$/m', $content, $matches)) {
    $imports = implode("\n", $matches[0]);
}

if (!is_dir($partialsDir)) {
    if (!mkdir($partialsDir, 0775, true) && !is_dir($partialsDir)) {
        fwrite(STDERR, "Unable to create routes/partials\n");
        exit(1);
    }
}

if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        fwrite(STDERR, "Unable to create storage/route-split\n");
        exit(1);
    }
}

if (!file_exists($backup)) {
    if (file_put_contents($backup, $content) === false) {
        fwrite(STDERR, "Unable to create storage/route-split/web.php.bak\n");
        exit(1);
    }
}

if (file_exists($legacyBackup) && $legacyBackup !== $backup) {
    @unlink($legacyBackup);
}

$bootstrapPath = $partialsDir . DIRECTORY_SEPARATOR . '_bootstrap.php';
if (file_put_contents($bootstrapPath, $bootstrap) === false) {
    fwrite(STDERR, "Unable to write _bootstrap.php\n");
    exit(1);
}

for ($i = 0; $i < count($positions); $i++) {
    $start = $positions[$i]['pos'];
    $end = ($i + 1 < count($positions)) ? $positions[$i + 1]['pos'] : strlen($content);
    $slice = substr($content, $start, $end - $start);
    $path = $partialsDir . DIRECTORY_SEPARATOR . $positions[$i]['file'];
    $payload = "<?php\n\n";
    if ($imports !== '') {
        $payload .= $imports . "\n\n";
    }
    $payload .= ltrim($slice);
    if (file_put_contents($path, $payload) === false) {
        fwrite(STDERR, "Unable to write {$positions[$i]['file']}\n");
        exit(1);
    }
}

$web = "<?php\n\n";
$web .= "require __DIR__ . '/partials/_bootstrap.php';\n";
foreach ($positions as $section) {
    $web .= "require __DIR__ . '/partials/{$section['file']}';\n";
}
$web .= "";

if (file_put_contents($source, $web) === false) {
    fwrite(STDERR, "Unable to write routes/web.php\n");
    exit(1);
}

echo "Split complete.\n";
