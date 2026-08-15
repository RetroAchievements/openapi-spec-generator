<?php

declare(strict_types=1);

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\OpenApiSpec\Facades\GeneratorFacade;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders\DatabaseSeeder;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;

class IntegerFieldTest extends TestCase
{
    use RefreshDatabase;

    protected array $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->document = json_decode(GeneratorFacade::generate('v1', 'json'), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function commentAttributes(): array
    {
        return $this->document['components']['schemas']['resources.comments.resource.fetch']['properties']['attributes']['properties'];
    }

    public function test_an_integer_field_is_documented_as_an_integer(): void
    {
        $this->assertEquals('integer', $this->commentAttributes()['score']['type']);
    }

    public function test_an_integer_field_carries_no_format(): void
    {
        $this->assertArrayNotHasKey('format', $this->commentAttributes()['score']);
    }

    public function test_a_plain_number_field_is_still_documented_as_a_number(): void
    {
        $this->assertEquals('number', $this->commentAttributes()['weight']['type']);
    }
}
