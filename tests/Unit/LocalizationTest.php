<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

class LocalizationTest extends TestCase
{
    public function test_finnish_and_english_have_the_same_translation_keys(): void
    {
        $english = array_keys(Arr::dot(require __DIR__.'/../../resources/lang/en/ui.php'));
        $finnish = array_keys(Arr::dot(require __DIR__.'/../../resources/lang/fi/ui.php'));
        sort($english);
        sort($finnish);

        $this->assertSame([], array_values(array_diff($english, $finnish)), 'Finnish translations are missing keys.');
        $this->assertSame([], array_values(array_diff($finnish, $english)), 'Finnish translations contain unknown keys.');
    }

    public function test_translations_do_not_contain_mojibake(): void
    {
        foreach (['en', 'fi'] as $locale) {
            $translations = json_encode(
                require __DIR__.'/../../resources/lang/'.$locale.'/ui.php',
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );

            foreach (["\u{FFFD}", 'Ã', 'Â', 'Г', 'вЂ'] as $marker) {
                $this->assertStringNotContainsString($marker, $translations, "Mojibake found in {$locale} translations.");
            }
        }
    }

    public function test_typescript_translation_keys_exist(): void
    {
        $keys = [];
        $directory = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../resources/js'));
        foreach ($directory as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'ts') {
                continue;
            }
            preg_match_all(
                '/(?<![\w])t(?:Format)?\(\s*[\'\"]([a-z0-9_.-]+)[\'\"]\s*,/i',
                (string) file_get_contents($file->getPathname()),
                $matches,
            );
            $keys = array_merge($keys, $matches[1] ?? []);
        }

        $translations = require __DIR__.'/../../resources/lang/en/ui.php';
        $this->assertSame(
            [],
            array_values(array_diff(array_unique($keys), array_keys($translations['js']))),
            'TypeScript uses translation keys that are missing from ui.js.',
        );
    }

    public function test_every_badge_has_localized_catalog_text(): void
    {
        $badgeKeys = array_column(
            json_decode((string) file_get_contents(__DIR__.'/../../resources/data/badges.json'), true, flags: JSON_THROW_ON_ERROR),
            'key',
        );
        foreach (['en', 'fi'] as $locale) {
            $translations = require __DIR__.'/../../resources/lang/'.$locale.'/ui.php';
            $this->assertSame(
                [],
                array_values(array_diff($badgeKeys, array_keys($translations['badges']['catalog']))),
                "Badge catalog text is missing from {$locale} translations.",
            );
        }
    }
}
