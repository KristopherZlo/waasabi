<?php

namespace App\Services;

class ScribbleAvatar
{
    private const TWO_POW_32 = 4294967296;

    private const RANGES = [
        'points' => ['min' => 500, 'max' => 12000],
        'step' => ['min' => 0.3, 'max' => 6.0],
        'jitterXY' => ['min' => 0.0, 'max' => 2.5],
        'curvature' => ['min' => 0.0, 'max' => 0.35],
        'inertia' => ['min' => 0.2, 'max' => 0.97],
        'centerPull' => ['min' => 0.0, 'max' => 0.02],
        'margin' => ['min' => 0, 'max' => 240],
        'lineWidth' => ['min' => 0.4, 'max' => 14.0],
        'alpha' => ['min' => 0.1, 'max' => 1.0],
        'smooth' => ['min' => 0.0, 'max' => 1.0],
        'paperNoise' => ['min' => 0, 'max' => 35],
    ];

    public static function createSvgFromName(string $name): string
    {
        $seed = self::seedFromName($name);
        return self::createSvgFromSeed($seed);
    }

    public static function createSvgFromSeed(int $seed, ?int $svgPointBudget = null): string
    {
        $params = self::paramsFromSeed($seed);
        $points = self::generatePoints($params, $seed);
        $budget = (int) self::clamp($svgPointBudget ?? 1200, 200, 4000);
        $points = self::decimate($points, $budget);
        $pathD = self::pointsToPathD($points, $params['smooth']);

        return self::buildSvg($params, $pathD, $seed);
    }

    public static function seedFromName(string $name): int
    {
        $trimmed = trim($name);
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($trimmed, 'UTF-8')
            : strtolower($trimmed);
        $hash = self::cyrb128($normalized);
        return $hash[0] & 0xFFFFFFFF;
    }

    private static function cyrb128(string $input): array
    {
        $h1 = 1779033703;
        $h2 = 3144134277;
        $h3 = 1013904242;
        $h4 = 2773480762;

        foreach (self::utf16CodeUnits($input) as $code) {
            $h1 = $h2 ^ self::imul($h1 ^ $code, 597399067);
            $h2 = $h3 ^ self::imul($h2 ^ $code, 2869860233);
            $h3 = $h4 ^ self::imul($h3 ^ $code, 951274213);
            $h4 = $h1 ^ self::imul($h4 ^ $code, 2716044179);
        }

        $h1 = self::imul($h3 ^ (($h1 & 0xFFFFFFFF) >> 18), 597399067);
        $h2 = self::imul($h4 ^ (($h2 & 0xFFFFFFFF) >> 22), 2869860233);
        $h3 = self::imul($h1 ^ (($h3 & 0xFFFFFFFF) >> 17), 951274213);
        $h4 = self::imul($h2 ^ (($h4 & 0xFFFFFFFF) >> 19), 2716044179);

        return [
            ($h1 ^ $h2 ^ $h3 ^ $h4) & 0xFFFFFFFF,
            $h2 & 0xFFFFFFFF,
            $h3 & 0xFFFFFFFF,
            $h4 & 0xFFFFFFFF,
        ];
    }

    private static function utf16CodeUnits(string $input): array
    {
        if (!function_exists('mb_convert_encoding')) {
            $bytes = array_map('ord', str_split($input));
            return $bytes;
        }

        $utf16 = mb_convert_encoding($input, 'UTF-16LE', 'UTF-8');
        $len = strlen($utf16);
        $units = [];
        for ($i = 0; $i < $len; $i += 2) {
            $lo = ord($utf16[$i]);
            $hi = ord($utf16[$i + 1] ?? "\x00");
            $units[] = $lo | ($hi << 8);
        }
        return $units;
    }

    private static function mulberry32(int $seed): callable
    {
        $t = $seed & 0xFFFFFFFF;
        return function () use (&$t) {
            $t = ($t + 0x6d2b79f5) & 0xFFFFFFFF;
            $x = self::imul($t ^ (($t & 0xFFFFFFFF) >> 15), 1 | $t);
            $x ^= ($x + self::imul($x ^ (($x & 0xFFFFFFFF) >> 7), 61 | $x)) & 0xFFFFFFFF;
            $x ^= ($x & 0xFFFFFFFF) >> 14;
            return (($x & 0xFFFFFFFF) / self::TWO_POW_32);
        };
    }

    private static function paramsFromSeed(int $seed): array
    {
        $rng = self::mulberry32($seed);

        $lengthPct = self::rangedPercent($rng, 35, 60);
        $points = (int) round(self::fromPercent(self::RANGES['points']['min'], self::RANGES['points']['max'], $lengthPct));

        $step = self::fromPercent(self::RANGES['step']['min'], self::RANGES['step']['max'], 65);

        $jitterPct = self::rangedPercent($rng, 0, 100);
        $jitterXY = self::fromPercent(self::RANGES['jitterXY']['min'], self::RANGES['jitterXY']['max'], $jitterPct);

        $curvaturePct = self::rangedPercent($rng, 13, 20);
        $curvature = self::fromPercent(self::RANGES['curvature']['min'], self::RANGES['curvature']['max'], $curvaturePct);

        $inertia = self::RANGES['inertia']['max'];

        $centerPullPct = self::rangedPercent($rng, 55, 70);
        $centerPull = self::fromPercent(self::RANGES['centerPull']['min'], self::RANGES['centerPull']['max'], $centerPullPct);

        $margin = 0;

        $lineWidth = self::fromPercent(self::RANGES['lineWidth']['min'], self::RANGES['lineWidth']['max'], 30);

        $alpha = self::RANGES['alpha']['max'];
        $smooth = self::RANGES['smooth']['max'];

        $turnEvery = (int) round(self::lerp(26, 58, $rng()));
        $turnJitter = self::lerp(0.03, 0.12, $rng());

        $paperNoise = 0;

        return [
            'width' => 512,
            'height' => 512,
            'points' => $points,
            'step' => $step,
            'jitterXY' => $jitterXY,
            'curvature' => $curvature,
            'inertia' => $inertia,
            'centerPull' => $centerPull,
            'margin' => $margin,
            'turnEvery' => $turnEvery,
            'turnJitter' => $turnJitter,
            'lineWidth' => $lineWidth,
            'alpha' => $alpha,
            'smooth' => $smooth,
            'roundCaps' => true,
            'invert' => false,
            'paperNoise' => $paperNoise,
        ];
    }

    private static function generatePoints(array $p, int $seed): array
    {
        $rng = self::mulberry32($seed);
        $cx = $p['width'] / 2;
        $cy = $p['height'] / 2;

        $x = $cx;
        $y = $cy;

        $ang = $rng() * M_PI * 2;

        $omegaTarget = ($rng() < 0.5 ? -1 : 1) * $p['curvature'];
        $omega = $omegaTarget;

        $points = [];
        $points[] = ['x' => $x, 'y' => $y];

        $turnEvery = max(1, (int) round($p['turnEvery']));
        $inert = self::clamp($p['inertia'], 0, 0.99);

        for ($i = 1; $i < $p['points']; $i++) {
            if ($i % $turnEvery === 0) {
                $dir = $rng() < 0.5 ? -1 : 1;
                $mag = $p['curvature'] * (0.55 + $rng() * 0.95);
                $omegaTarget = $dir * $mag;
            }

            $omega = $omega * $inert + $omegaTarget * (1 - $inert);
            $ang += $omega + ($rng() - 0.5) * $p['turnJitter'];

            $x += cos($ang) * $p['step'] + ($rng() - 0.5) * $p['jitterXY'];
            $y += sin($ang) * $p['step'] + ($rng() - 0.5) * $p['jitterXY'];

            $x += ($cx - $x) * $p['centerPull'];
            $y += ($cy - $y) * $p['centerPull'];

            $left = $p['margin'];
            $right = $p['width'] - $p['margin'];
            $top = $p['margin'];
            $bottom = $p['height'] - $p['margin'];

            if ($x < $left) {
                $x = $left + ($left - $x) * 0.35;
                $ang = M_PI - $ang;
                $omegaTarget = abs($omegaTarget);
            } elseif ($x > $right) {
                $x = $right - ($x - $right) * 0.35;
                $ang = M_PI - $ang;
                $omegaTarget = -abs($omegaTarget);
            }

            if ($y < $top) {
                $y = $top + ($top - $y) * 0.35;
                $ang = -$ang;
                $omegaTarget = -$omegaTarget;
            } elseif ($y > $bottom) {
                $y = $bottom - ($y - $bottom) * 0.35;
                $ang = -$ang;
                $omegaTarget = -$omegaTarget;
            }

            $points[] = ['x' => $x, 'y' => $y];
        }

        return $points;
    }

    private static function decimate(array $points, int $target): array
    {
        $count = count($points);
        if ($count <= $target) {
            return $points;
        }
        $step = $count / $target;
        $out = [];
        for ($i = 0; $i < $target; $i++) {
            $out[] = $points[(int) floor($i * $step)];
        }
        $out[$target - 1] = $points[$count - 1];
        return $out;
    }

    private static function pointsToPathD(array $points, float $smooth): string
    {
        if (count($points) < 2) {
            return '';
        }
        $s = self::clamp($smooth, 0, 1);
        if ($s <= 0) {
            $d = sprintf('M %.2f %.2f', $points[0]['x'], $points[0]['y']);
            for ($i = 1; $i < count($points); $i++) {
                $d .= sprintf(' L %.2f %.2f', $points[$i]['x'], $points[$i]['y']);
            }
            return $d;
        }

        $d = sprintf('M %.2f %.2f', $points[0]['x'], $points[0]['y']);
        $k = $s / 6;
        $last = count($points) - 1;

        for ($i = 0; $i < $last; $i++) {
            $p0 = $points[max(0, $i - 1)];
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $p3 = $points[min($last, $i + 2)];

            $cp1x = $p1['x'] + ($p2['x'] - $p0['x']) * $k;
            $cp1y = $p1['y'] + ($p2['y'] - $p0['y']) * $k;
            $cp2x = $p2['x'] - ($p3['x'] - $p1['x']) * $k;
            $cp2y = $p2['y'] - ($p3['y'] - $p1['y']) * $k;

            $d .= sprintf(
                ' C %.2f %.2f %.2f %.2f %.2f %.2f',
                $cp1x,
                $cp1y,
                $cp2x,
                $cp2y,
                $p2['x'],
                $p2['y']
            );
        }

        return $d;
    }

    private static function buildSvg(array $p, string $pathD, int $seed): string
    {
        $bg = $p['invert'] ? '#fff' : '#000';
        $stroke = $p['invert'] ? '#000' : '#fff';

        $opacity = self::clamp($p['alpha'], 0.05, 1);
        $cap = $p['roundCaps'] ? 'round' : 'butt';
        $join = $p['roundCaps'] ? 'round' : 'miter';

        $useNoise = $p['paperNoise'] > 0;
        $noiseAlpha = self::clamp($p['paperNoise'] / 120, 0, 0.55);

        $defs = '';
        $filterAttr = '';
        if ($useNoise) {
            $defs = '<filter id="paperNoise" x="-20%" y="-20%" width="140%" height="140%">'
                . '<feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="2" seed="' . $seed . '" result="n"/>'
                . '<feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 ' . $noiseAlpha . ' 0" in="n" result="na"/>'
                . '<feBlend mode="overlay" in="SourceGraphic" in2="na"/>'
                . '</filter>';
            $filterAttr = ' filter="url(#paperNoise)"';
        }

        $defsBlock = $defs !== '' ? "  <defs>\n$defs\n  </defs>\n" : '';

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $p['width'] . '" height="' . $p['height'] . '" viewBox="0 0 ' . $p['width'] . ' ' . $p['height'] . '">' . "\n"
            . $defsBlock
            . '  <rect width="100%" height="100%" fill="' . $bg . '"/>' . "\n"
            . '  <g' . $filterAttr . '>' . "\n"
            . '    <path d="' . $pathD . '" fill="none" stroke="' . $stroke . '" stroke-width="' . $p['lineWidth'] . '" stroke-opacity="' . $opacity . '"'
            . ' stroke-linecap="' . $cap . '" stroke-linejoin="' . $join . '"/>' . "\n"
            . "  </g>\n"
            . '</svg>';
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return min($max, max($min, $value));
    }

    private static function lerp(float $a, float $b, float $t): float
    {
        return $a + ($b - $a) * $t;
    }

    private static function fromPercent(float $min, float $max, float $percent): float
    {
        $t = self::clamp($percent / 100, 0, 1);
        return self::lerp($min, $max, $t);
    }

    private static function rangedPercent(callable $rng, float $a, float $b): float
    {
        return self::lerp($a, $b, $rng());
    }

    private static function imul(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;
        $aLow = $a & 0xFFFF;
        $aHigh = ($a >> 16) & 0xFFFF;
        $bLow = $b & 0xFFFF;
        $bHigh = ($b >> 16) & 0xFFFF;
        $low = $aLow * $bLow;
        $mid = ($aHigh * $bLow + $aLow * $bHigh) << 16;
        return ($low + $mid) & 0xFFFFFFFF;
    }
}
