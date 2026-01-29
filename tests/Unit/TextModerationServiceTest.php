<?php

namespace Tests\Unit;

use App\Services\TextModerationService;
use Tests\TestCase;

class TextModerationServiceTest extends TestCase
{
    public function test_text_moderation_flags_short_content(): void
    {
        config([
            'moderation.text.enabled' => true,
            'moderation.text.types.post.min_chars' => 10,
            'moderation.text.types.post.min_words' => 2,
            'moderation.text.types.post.score_threshold' => 0.1,
        ]);

        $service = new TextModerationService();
        $result = $service->analyze('Hi', ['type' => 'post']);

        $this->assertTrue($result['flagged']);
        $this->assertSame('flagged', $result['status']);
    }

    public function test_text_moderation_can_be_disabled(): void
    {
        config(['moderation.text.enabled' => false]);

        $service = new TextModerationService();
        $result = $service->analyze('Anything', ['type' => 'post']);

        $this->assertFalse($result['flagged']);
        $this->assertSame('skipped', $result['status']);
    }
}
