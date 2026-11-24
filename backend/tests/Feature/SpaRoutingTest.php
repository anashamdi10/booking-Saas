<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * In production one application serves both the API and the built Vue app
 * (see docs/DEPLOY_WASMER.md). The SPA fallback must never take precedence
 * over the API, and must only answer once the build output exists.
 */
class SpaRoutingTest extends TestCase
{
    public function test_api_routes_are_not_swallowed_by_the_spa_fallback(): void
    {
        $response = $this->getJson('/api/v1/definitely-not-a-route');

        $response->assertNotFound();
        $this->assertStringNotContainsString('<div id="app">', (string) $response->getContent());
    }

    public function test_the_fallback_reports_a_missing_build_instead_of_faking_it(): void
    {
        if (is_file(public_path('index.html'))) {
            $this->markTestSkipped('The SPA build is present, so the fallback serves it.');
        }

        $response = $this->get('/admin/plans');

        $response->assertNotFound();
        $this->assertStringContainsString('غير مبنية', (string) $response->getContent());
    }

    public function test_deep_links_are_served_by_the_spa_when_it_is_built(): void
    {
        if (! is_file(public_path('index.html'))) {
            $this->markTestSkipped('Run `npm run build:spa` inside frontend/ first.');
        }

        $response = $this->get('/admin/plans');

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<div id="app">', (string) $response->getContent());
    }
}
