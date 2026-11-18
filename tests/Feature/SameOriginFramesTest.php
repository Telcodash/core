<?php

namespace Tests\Feature;

use Tests\TestCase;

class SameOriginFramesTest extends TestCase
{
    public function test_pages_can_only_be_framed_by_the_app_itself(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }
}
