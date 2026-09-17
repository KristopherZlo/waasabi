<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    public function test_pagination_uses_application_markup(): void
    {
        app()->setLocale('en');

        $paginator = new LengthAwarePaginator(
            range(11, 20),
            30,
            10,
            2,
            ['path' => '/items'],
        );
        $html = (string) $paginator->links();

        $this->assertStringContainsString('class="pagination"', $html);
        $this->assertStringContainsString('Showing 11–20 of 30', $html);
        $this->assertStringContainsString('aria-current="page">2</span>', $html);
    }
}
