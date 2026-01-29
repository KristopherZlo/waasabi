<?php

$root = dirname(__DIR__);
$source = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . '_bootstrap.php';
$output = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'demo.php';

if (!file_exists($source)) {
    fwrite(STDERR, "Missing routes/partials/_bootstrap.php\n");
    exit(1);
}

$content = file_get_contents($source);
if ($content === false) {
    fwrite(STDERR, "Unable to read routes/partials/_bootstrap.php\n");
    exit(1);
}

$projects = extractArrayLiteral($content, '$projects');
$profile = extractArrayLiteral($content, '$profile');
$questions = extractArrayLiteral($content, '$qa_questions');

if ($projects === '' || $profile === '' || $questions === '') {
    fwrite(STDERR, "Unable to extract demo arrays\n");
    exit(1);
}

$payload = <<<PHP
<?php

return [
    'projects' => {$projects},
    'profile' => {$profile},
    'qa_questions' => {$questions},
];
PHP;

if (!is_dir(dirname($output))) {
    if (!mkdir(dirname($output), 0775, true) && !is_dir(dirname($output))) {
        fwrite(STDERR, "Unable to create resources/data\n");
        exit(1);
    }
}

if (file_put_contents($output, $payload) === false) {
    fwrite(STDERR, "Unable to write resources/data/demo.php\n");
    exit(1);
}

echo "Demo data extracted.\n";

function extractArrayLiteral(string $content, string $varName): string
{
    $needle = $varName . ' =';
    $pos = strpos($content, $needle);
    if ($pos === false) {
        return '';
    }
    $start = strpos($content, '[', $pos);
    if ($start === false) {
        return '';
    }

    $depth = 0;
    $inSingle = false;
    $inDouble = false;
    $escape = false;
    $length = strlen($content);
    $end = null;

    for ($i = $start; $i < $length; $i++) {
        $ch = $content[$i];
        if ($escape) {
            $escape = false;
            continue;
        }
        if (($inSingle || $inDouble) && $ch === '\\\\') {
            $escape = true;
            continue;
        }
        if (!$inDouble && $ch === "'") {
            $inSingle = !$inSingle;
            continue;
        }
        if (!$inSingle && $ch === '"') {
            $inDouble = !$inDouble;
            continue;
        }
        if ($inSingle || $inDouble) {
            continue;
        }
        if ($ch === '[') {
            $depth++;
            continue;
        }
        if ($ch === ']') {
            $depth--;
            if ($depth === 0) {
                $end = $i;
                break;
            }
        }
    }

    if ($end === null) {
        return '';
    }

    return trim(substr($content, $start, $end - $start + 1));
}
