<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\OpenApiSpec\Facades\GeneratorFacade;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Database\Seeders\DatabaseSeeder;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;

/**
 * Many applications have endpoints that are not resource routes.
 *
 * A schema cannot describe these endpoints. Declare the class that the endpoint
 * returns instead. The description then stays next to the code. It also gives
 * nullability. An example cannot give nullability.
 */
class CustomRouteTest extends TestCase
{
    use RefreshDatabase;

    protected array $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->document = json_decode(GeneratorFacade::generate('v1', 'json'), true);
    }

    private function operation(): array
    {
        $this->assertArrayHasKey(
            '/posts/summary',
            $this->document['paths'],
            'The document must show a route that describes itself.',
        );

        return $this->document['paths']['/posts/summary']['get'];
    }

    public function test_a_self_describing_route_is_documented(): void
    {
        $this->assertEquals('Summarise posts', $this->operation()['summary']);
    }

    public function test_it_is_not_reported_as_undescribable(): void
    {
        $this->assertNotContains('v1.posts.summary', array_column(GeneratorFacade::skippedRoutes(), 'route'));
    }

    public function test_its_response_is_plain_json_without_the_jsonapi_envelope(): void
    {
        $content = $this->operation()['responses']['200']['content'];

        $this->assertArrayHasKey('application/json', $content);
        $this->assertArrayNotHasKey('application/vnd.api+json', $content);
        $this->assertArrayNotHasKey('jsonapi', $content['application/json']['schema']['properties']);
    }

    public function test_its_response_is_derived_from_the_declared_class(): void
    {
        $schema = $this->operation()['responses']['200']['content']['application/json']['schema'];

        $this->assertEquals('string', $schema['properties']['title']['type']);
        $this->assertEquals('integer', $schema['properties']['count']['type']);
    }

    public function test_its_response_carries_nullability(): void
    {
        $properties = $this->operation()['responses']['200']['content']['application/json']['schema']['properties'];

        $this->assertTrue($properties['note']['nullable']);
        $this->assertArrayNotHasKey('nullable', $properties['title']);
    }

    public function test_it_still_reports_routes_that_describe_nothing(): void
    {
        $skipped = array_column(GeneratorFacade::skippedRoutes(), 'route');

        $this->assertContains('v1.health', $skipped);
        $this->assertContains('v1.posts.stats', $skipped);
    }

    /**
     * OpenAPI refuses a path that holds a parameter it does not declare. A custom
     * route has no schema, so the declaration must come from the URI.
     */
    public function test_it_declares_the_path_parameters_of_a_custom_route(): void
    {
        $parameters = $this->document['paths']['/posts/{postId}/summary']['get']['parameters'];

        $this->assertEquals(['postId'], array_column($parameters, 'name'));
        $this->assertTrue($parameters[0]['required']);
    }

    /**
     * A numeric constraint on the route is the only place a custom route states a
     * type. The documented type follows it.
     */
    public function test_it_types_a_numeric_path_parameter_as_an_integer(): void
    {
        $parameters = $this->document['paths']['/posts/{postId}/summary']['get']['parameters'];

        $this->assertEquals('integer', $parameters[0]['schema']['type']);
    }

    public function test_a_custom_route_without_path_parameters_declares_none(): void
    {
        $this->assertEmpty($this->document['paths']['/posts/summary']['get']['parameters'] ?? []);
    }

    public function test_resource_routes_are_unaffected(): void
    {
        $paths = $this->document['paths'];

        $this->assertArrayHasKey('/posts', $paths);
        $this->assertArrayHasKey('/posts/{post}', $paths);
        $this->assertArrayHasKey(
            'application/vnd.api+json',
            $paths['/posts']['get']['responses']['200']['content'],
        );
    }
}
