<?php

namespace App\Services;

use Illuminate\Support\Arr;

class TextModerationService
{
    public function analyze(string $body, array $context = []): array
    {
        $config = (array) config('moderation.text', []);
        $enabled = (bool) ($config['enabled'] ?? true);
        if (!$enabled) {
            return $this->skipped('disabled');
        }

        $type = strtolower((string) ($context['type'] ?? 'post'));
        $typeConfig = (array) Arr::get($config, 'types.' . $type, []);
        if ($typeConfig === []) {
            $typeConfig = (array) Arr::get($config, 'types.post', []);
        }

        $text = $this->buildText($body, $context);
        $metrics = $this->computeMetrics($text);

        $signalsConfig = (array) ($config['signals'] ?? []);
        $score = 0.0;
        $signals = [];

        $minChars = max(0, (int) ($typeConfig['min_chars'] ?? 0));
        $minWords = max(0, (int) ($typeConfig['min_words'] ?? 0));
        $scoreThreshold = max(0.1, (float) ($typeConfig['score_threshold'] ?? 2.6));

        $tooShortWeight = (float) Arr::get($signalsConfig, 'too_short.weight', 1.5);
        if ($metrics['char_count'] < $minChars || $metrics['word_count'] < $minWords) {
            $charSeverity = $this->shortSeverity($metrics['char_count'], $minChars);
            $wordSeverity = $this->shortSeverity($metrics['word_count'], $minWords);
            $severity = max($charSeverity, $wordSeverity);
            $signals[] = $this->makeSignal(
                'too_short',
                $tooShortWeight,
                $severity,
                'Content is too short for a post.',
            );
        }

        $uniqueThreshold = (float) Arr::get($signalsConfig, 'low_unique_words_ratio.threshold', 0.32);
        $uniqueWeight = (float) Arr::get($signalsConfig, 'low_unique_words_ratio.weight', 1.2);
        if ($metrics['word_count'] >= 12 && $metrics['unique_word_ratio'] < $uniqueThreshold) {
            $severity = $this->ratioSeverity($metrics['unique_word_ratio'], $uniqueThreshold, true);
            $signals[] = $this->makeSignal(
                'low_unique_words_ratio',
                $uniqueWeight,
                $severity,
                'Very low unique word ratio suggests repetitive content.',
            );
        }

        $repeatThreshold = (float) Arr::get($signalsConfig, 'repeated_words_ratio.threshold', 0.28);
        $repeatWeight = (float) Arr::get($signalsConfig, 'repeated_words_ratio.weight', 1.1);
        $repeatMinWords = (int) Arr::get($signalsConfig, 'repeated_words_ratio.min_words', 20);
        if (
            $metrics['word_count'] >= $repeatMinWords
            && $metrics['top_word_ratio'] > $repeatThreshold
            && $metrics['top_word'] !== ''
        ) {
            $severity = $this->ratioSeverity($metrics['top_word_ratio'], $repeatThreshold, false);
            $signals[] = $this->makeSignal(
                'repeated_words_ratio',
                $repeatWeight,
                $severity,
                'A single word dominates the text too heavily.',
            );
        }

        $runLength = max(3, (int) Arr::get($signalsConfig, 'repeated_char_runs.run_length', 6));
        $runWeight = (float) Arr::get($signalsConfig, 'repeated_char_runs.weight', 1.3);
        $minRuns = max(1, (int) Arr::get($signalsConfig, 'repeated_char_runs.min_runs', 1));
        if ($metrics['repeated_char_runs'] >= $minRuns && $metrics['repeated_char_max_run'] >= $runLength) {
            $severity = min(2.0, max(1.0, $metrics['repeated_char_max_run'] / max(1, $runLength)));
            $signals[] = $this->makeSignal(
                'repeated_char_runs',
                $runWeight,
                $severity,
                'Repeated character runs look like keyboard spam.',
            );
        }

        $upperThreshold = (float) Arr::get($signalsConfig, 'uppercase_ratio.threshold', 0.55);
        $upperWeight = (float) Arr::get($signalsConfig, 'uppercase_ratio.weight', 0.6);
        $upperMinLetters = (int) Arr::get($signalsConfig, 'uppercase_ratio.min_letters', 30);
        if ($metrics['letter_count'] >= $upperMinLetters && $metrics['uppercase_ratio'] > $upperThreshold) {
            $severity = $this->ratioSeverity($metrics['uppercase_ratio'], $upperThreshold, false);
            $signals[] = $this->makeSignal(
                'uppercase_ratio',
                $upperWeight,
                $severity,
                'Excessive uppercase can indicate shouting or spam.',
            );
        }

        $symbolThreshold = (float) Arr::get($signalsConfig, 'symbol_ratio.threshold', 0.9);
        $symbolWeight = (float) Arr::get($signalsConfig, 'symbol_ratio.weight', 1.1);
        $symbolMinLetters = (int) Arr::get($signalsConfig, 'symbol_ratio.min_letters', 20);
        if ($metrics['letter_count'] >= $symbolMinLetters && $metrics['symbol_ratio'] > $symbolThreshold) {
            $severity = $this->ratioSeverity($metrics['symbol_ratio'], $symbolThreshold, false);
            $signals[] = $this->makeSignal(
                'symbol_ratio',
                $symbolWeight,
                $severity,
                'Too many symbols relative to letters suggests noise.',
            );
        }

        $linkThreshold = (int) Arr::get($signalsConfig, 'link_count.threshold', 4);
        $linkWeight = (float) Arr::get($signalsConfig, 'link_count.weight', 1.0);
        if ($metrics['link_count'] > $linkThreshold) {
            $severity = min(2.0, max(1.0, $metrics['link_count'] / max(1, $linkThreshold)));
            $signals[] = $this->makeSignal(
                'link_count',
                $linkWeight,
                $severity,
                'Too many links in a single post is a spam indicator.',
            );
        }

        $lineThreshold = (int) Arr::get($signalsConfig, 'longest_line.threshold', 420);
        $lineWeight = (float) Arr::get($signalsConfig, 'longest_line.weight', 0.6);
        if ($metrics['longest_line'] > $lineThreshold) {
            $severity = min(2.0, max(1.0, $metrics['longest_line'] / max(1, $lineThreshold)));
            $signals[] = $this->makeSignal(
                'longest_line',
                $lineWeight,
                $severity,
                'Extremely long unbroken lines are often garbage.',
            );
        }

        foreach ($signals as $signal) {
            $score += (float) ($signal['score'] ?? 0.0);
        }

        $flagged = $score >= $scoreThreshold && $signals !== [];
        $detailsLimit = max(1, (int) ($config['details_limit'] ?? 6));
        $details = array_slice(
            array_values(array_unique(array_map(static fn (array $signal) => (string) ($signal['detail'] ?? ''), $signals))),
            0,
            $detailsLimit,
        );

        $summary = $this->buildSummary($score, $scoreThreshold, $signals);

        return [
            'status' => $flagged ? 'flagged' : 'ok',
            'flagged' => $flagged,
            'score' => round($score, 3),
            'threshold' => round($scoreThreshold, 3),
            'signals' => $signals,
            'details' => $details,
            'summary' => $summary,
            'metrics' => $metrics,
            'type' => $type,
        ];
    }

    private function skipped(string $reason): array
    {
        return [
            'status' => 'skipped',
            'flagged' => false,
            'score' => 0.0,
            'threshold' => 0.0,
            'signals' => [],
            'details' => [$reason],
            'summary' => 'Text moderation skipped: ' . $reason . '.',
            'metrics' => [],
        ];
    }

    private function buildText(string $body, array $context): string
    {
        $title = trim((string) ($context['title'] ?? ''));
        $subtitle = trim((string) ($context['subtitle'] ?? ''));

        $parts = [];
        if ($title !== '') {
            $parts[] = $title;
        }
        if ($subtitle !== '') {
            $parts[] = $subtitle;
        }
        $parts[] = $body;

        return trim(implode("\n\n", array_filter($parts, static fn ($part) => trim((string) $part) !== '')));
    }

    private function computeMetrics(string $text): array
    {
        $charCount = mb_strlen($text);
        $letterCount = $this->countMatches('/\p{L}/u', $text);
        $uppercaseCount = $this->countMatches('/\p{Lu}/u', $text);
        $whitespaceCount = $this->countMatches('/\s/u', $text);
        $nonLetterCount = max(0, $charCount - $letterCount - $whitespaceCount);

        $uppercaseRatio = $letterCount > 0 ? ($uppercaseCount / $letterCount) : 0.0;
        $symbolRatio = $letterCount > 0 ? ($nonLetterCount / $letterCount) : 0.0;

        $linkCount = $this->countMatches('/https?:\/\/\S+|www\.\S+/iu', $text);

        $normalizedForWords = $this->normalizeForWords($text);
        $words = $this->extractWords($normalizedForWords);
        $wordCount = count($words);
        $uniqueWordCount = $wordCount > 0 ? count(array_unique($words)) : 0;
        $uniqueWordRatio = $wordCount > 0 ? ($uniqueWordCount / $wordCount) : 0.0;

        [$topWord, $topWordRatio] = $this->topWordStats($words);

        [$repeatedRuns, $maxRunLength] = $this->repeatedCharStats($text);

        $lines = preg_split('/\R/u', $text) ?: [];
        $longestLine = 0;
        foreach ($lines as $line) {
            $longestLine = max($longestLine, mb_strlen((string) $line));
        }

        return [
            'char_count' => $charCount,
            'letter_count' => $letterCount,
            'uppercase_count' => $uppercaseCount,
            'uppercase_ratio' => round($uppercaseRatio, 4),
            'non_letter_count' => $nonLetterCount,
            'symbol_ratio' => round($symbolRatio, 4),
            'link_count' => $linkCount,
            'word_count' => $wordCount,
            'unique_word_count' => $uniqueWordCount,
            'unique_word_ratio' => round($uniqueWordRatio, 4),
            'top_word' => $topWord,
            'top_word_ratio' => round($topWordRatio, 4),
            'repeated_char_runs' => $repeatedRuns,
            'repeated_char_max_run' => $maxRunLength,
            'longest_line' => $longestLine,
        ];
    }

    private function normalizeForWords(string $text): string
    {
        $text = preg_replace('/https?:\/\/\S+|www\.\S+/iu', ' ', $text) ?? $text;
        // Strip common markdown control characters so word metrics are not distorted.
        $text = preg_replace('/[`*_>#\[\]\(\)\{\}\|~=-]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        return $text;
    }

    private function extractWords(string $text): array
    {
        $lower = mb_strtolower($text);
        $parts = preg_split('/\s+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = [];
        foreach ($parts as $part) {
            $word = trim((string) $part);
            if ($word === '') {
                continue;
            }
            // Drop very short tokens to reduce noise.
            if (mb_strlen($word) < 2) {
                continue;
            }
            $words[] = $word;
        }
        return $words;
    }

    private function topWordStats(array $words): array
    {
        if ($words === []) {
            return ['', 0.0];
        }
        $counts = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 3) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }
        if ($counts === []) {
            return ['', 0.0];
        }
        arsort($counts);
        $topWord = array_key_first($counts);
        $topCount = (int) ($counts[$topWord] ?? 0);
        $ratio = $topCount > 0 ? ($topCount / max(1, count($words))) : 0.0;
        return [(string) $topWord, $ratio];
    }

    private function repeatedCharStats(string $text): array
    {
        $matches = [];
        preg_match_all('/(.)\1{4,}/u', $text, $matches);
        $runs = $matches[0] ?? [];
        if ($runs === []) {
            return [0, 0];
        }
        $maxRun = 0;
        foreach ($runs as $run) {
            $maxRun = max($maxRun, mb_strlen((string) $run));
        }
        return [count($runs), $maxRun];
    }

    private function countMatches(string $pattern, string $subject): int
    {
        return preg_match_all($pattern, $subject) ?: 0;
    }

    private function shortSeverity(int $value, int $minimum): float
    {
        if ($minimum <= 0) {
            return 1.0;
        }
        if ($value >= $minimum) {
            return 1.0;
        }
        $ratio = $value / max(1, $minimum);
        $severity = 1.0 + (1.0 - $ratio);
        return min(2.0, max(1.0, $severity));
    }

    private function ratioSeverity(float $value, float $threshold, bool $lowerIsWorse): float
    {
        if ($threshold <= 0) {
            return 1.0;
        }
        if ($lowerIsWorse) {
            $value = max(0.0001, $value);
            $ratio = $threshold / $value;
        } else {
            $ratio = $value / $threshold;
        }
        return min(2.0, max(1.0, $ratio));
    }

    private function makeSignal(string $key, float $weight, float $severity, string $detail): array
    {
        $score = $weight * $severity;
        return [
            'key' => $key,
            'weight' => round($weight, 3),
            'severity' => round($severity, 3),
            'score' => round($score, 3),
            'detail' => $detail,
        ];
    }

    private function buildSummary(float $score, float $threshold, array $signals): string
    {
        if ($signals === []) {
            return 'Text moderation: no issues detected.';
        }
        $keys = array_map(static fn (array $signal) => (string) ($signal['key'] ?? ''), $signals);
        $keys = array_values(array_filter($keys, static fn ($key) => $key !== ''));
        $keyList = $keys !== [] ? implode(', ', $keys) : 'signals';
        return sprintf(
            'Text moderation: score %.2f / %.2f; signals: %s.',
            $score,
            $threshold,
            $keyList,
        );
    }
}
