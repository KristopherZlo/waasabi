<?php

namespace Tests\Unit;

use App\Services\ScribbleAvatar;
use PHPUnit\Framework\TestCase;

class ScribbleAvatarTest extends TestCase
{
    public function test_generated_avatar_has_a_deterministic_color_pair(): void
    {
        $svg = ScribbleAvatar::createSvgFromName('Robin');

        $this->assertSame($svg, ScribbleAvatar::createSvgFromName('Robin'));
        $this->assertMatchesRegularExpression('/fill="#(?:48243d|203d32|4a2f19|33275a|5a2726|16424a)"/', $svg);
        $this->assertMatchesRegularExpression('/stroke="#(?:ffb4cf|9aefc4|ffd08a|c9b9ff|ffb0a2|9ee8e8)"/', $svg);
    }
}
