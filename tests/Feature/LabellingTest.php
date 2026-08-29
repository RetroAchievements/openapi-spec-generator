<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\OpenApiSpec\Facades\GeneratorFacade;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders\DatabaseSeeder;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;

class LabellingTest extends TestCase
{
    use RefreshDatabase;

    protected array $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->document = json_decode(GeneratorFacade::generate('v1', 'json'), true);
    }

    private function operation(string $path, string $method = 'get'): array
    {
        $this->assertArrayHasKey($path, $this->document['paths'], "Path [{$path}] is missing.");

        return $this->document['paths'][$path][$method];
    }

    private function parameter(string $path, string $name): array
    {
        return collect($this->operation($path)['parameters'])->firstWhere('name', $name);
    }

    public function test_page_size_carries_the_schemas_default(): void
    {
        $this->assertEquals(25, $this->parameter('/posts/{post}/tags', 'page[size]')['schema']['default']);
    }

    public function test_page_size_carries_no_default_when_the_schema_declares_none(): void
    {
        $this->assertArrayNotHasKey('default', $this->parameter('/posts', 'page[size]')['schema']);
    }

    public function test_the_page_size_default_is_resolved_against_the_inverse_schema(): void
    {
        $this->assertEquals(
            $this->parameter('/videos/{video}/tags', 'page[size]')['schema']['default'],
            $this->parameter('/posts/{post}/tags', 'page[size]')['schema']['default'],
        );
    }

    public function test_a_related_response_is_described_by_the_inverse_resource(): void
    {
        $description = $this->operation('/posts/{post}/tags')['responses']['200']['description'];

        $this->assertStringContainsString('tags', $description);
        $this->assertStringNotContainsString('ShowRelated', $description);
    }

    public function test_related_and_relationships_operations_are_distinguishable(): void
    {
        $related = $this->operation('/posts/{post}/tags')['summary'];
        $identifiers = $this->operation('/posts/{post}/relationships/tags')['summary'];

        $this->assertNotEquals($related, $identifiers);
        $this->assertStringContainsString('identifiers', $identifiers);
    }

    public function test_related_and_relationships_responses_are_distinguishable(): void
    {
        $this->assertNotEquals(
            $this->operation('/posts/{post}/tags')['responses']['200']['description'],
            $this->operation('/posts/{post}/relationships/tags')['responses']['200']['description'],
        );
    }

    public function test_non_relationship_descriptions_are_unchanged(): void
    {
        $this->assertEquals('Index posts', $this->operation('/posts')['responses']['200']['description']);
    }

    public function test_a_collection_summary_names_the_resource(): void
    {
        $this->assertEquals('List posts', $this->operation('/posts')['summary']);
    }

    public function test_a_single_resource_summary_carries_an_article(): void
    {
        $this->assertEquals('Get a post', $this->operation('/posts/{post}')['summary']);
    }

    public function test_a_related_summary_leads_with_the_relation(): void
    {
        $this->assertEquals('List tags for a post', $this->operation('/posts/{post}/tags')['summary']);
    }

    public function test_a_relationship_summary_leads_with_the_singular_relation(): void
    {
        $this->assertEquals(
            'List tag identifiers for a post',
            $this->operation('/posts/{post}/relationships/tags')['summary'],
        );
    }

    public function test_a_tag_reads_as_words(): void
    {
        $this->assertEquals(['Posts'], $this->operation('/posts')['tags']);
    }

    public function test_no_summary_leaks_a_raw_identifier(): void
    {
        foreach ($this->document['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $summary = $operation['summary'] ?? '';

                $this->assertDoesNotMatchRegularExpression(
                    '/[a-z][A-Z]|[a-z]-[a-z]/',
                    $summary,
                    "Summary for [{$method} {$path}] holds a raw identifier: {$summary}",
                );
            }
        }
    }
}
