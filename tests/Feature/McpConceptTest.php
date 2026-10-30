<?php

namespace Tests\Feature;

use App\Support\McpConcept;
use App\Support\OpenApi;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class McpConceptTest extends TestCase
{
    public function test_every_api_operation_becomes_a_named_tool(): void
    {
        $operations = 0;
        foreach ((array) OpenApi::spec()['paths'] as $methods) {
            $operations += count(array_intersect(array_keys((array) $methods), ['get', 'post', 'patch', 'put', 'delete']));
        }
        $tools = McpConcept::tools();

        $this->assertCount($operations, $tools);
        $names = array_column($tools, 'name');
        $this->assertSame(count($names), count(array_unique($names)), 'Werkzeugnamen doppelt');
        foreach ($names as $name) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $name);
        }
    }

    public function test_only_reading_tools_in_first_phase_and_annotations_match(): void
    {
        foreach (McpConcept::tools() as $tool) {
            $this->assertSame($tool['method'] === 'GET', $tool['annotations']['readOnlyHint'], $tool['name']);
            $this->assertSame($tool['method'] === 'DELETE', $tool['annotations']['destructiveHint'], $tool['name']);
            if ($tool['phase'] === 1) {
                $this->assertSame('GET', $tool['method'], "{$tool['name']} darf nicht in Phase 1");
                $this->assertNotSame('Personen', $tool['tag'], "{$tool['name']}: Personendaten erst ab Phase 2");
            }
        }
    }

    public function test_concept_page_for_administration_only(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/mcp-konzept')->assertForbidden();

        $this->signInAs('admin');
        $this->get('/mcp-konzept')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('mcp-concept')
            ->has('tools', count(McpConcept::tools()))
            ->where('endpoint', fn ($url) => str_ends_with((string) $url, '/mcp')));
    }
}
