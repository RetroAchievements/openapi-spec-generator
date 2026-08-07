<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use LaravelJsonApi\OpenApiSpec\Helpers\SchemaFromClass;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Responses\SampleNested;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Responses\SampleRecursive;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Responses\SampleResponse;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;

/**
 * Use the declared types to describe a response. Do not use an example.
 *
 * Only the declared types tell you if a value can be null. An example shows one
 * value. It cannot tell you which other values are permitted.
 */
class SchemaFromClassTest extends TestCase
{
    private array $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = SchemaFromClass::generate(SampleResponse::class, 'data')->toArray();
    }

    private function property(string $name): array
    {
        $this->assertArrayHasKey($name, $this->schema['properties'], "Property [{$name}] is missing.");

        return $this->schema['properties'][$name];
    }

    public function test_it_describes_the_class_as_an_object(): void
    {
        $this->assertEquals('object', $this->schema['type']);
    }

    public function test_it_maps_scalar_types(): void
    {
        $this->assertEquals('string', $this->property('title')['type']);
        $this->assertEquals('integer', $this->property('count')['type']);
        $this->assertEquals('number', $this->property('ratio')['type']);
        $this->assertEquals('boolean', $this->property('enabled')['type']);
    }

    public function test_it_marks_only_nullable_properties_as_nullable(): void
    {
        $this->assertTrue($this->property('note')['nullable']);
        $this->assertArrayNotHasKey('nullable', $this->property('title'));
    }

    public function test_it_requires_every_property_that_cannot_be_null(): void
    {
        $this->assertContains('title', $this->schema['required']);
        $this->assertNotContains('note', $this->schema['required']);
        $this->assertNotContains('resolvedAt', $this->schema['required']);
    }

    public function test_it_describes_dates_as_formatted_strings(): void
    {
        $occurredAt = $this->property('occurredAt');

        $this->assertEquals('string', $occurredAt['type']);
        $this->assertEquals('date-time', $occurredAt['format']);
    }

    public function test_it_carries_nullability_through_to_dates(): void
    {
        $this->assertTrue($this->property('resolvedAt')['nullable']);
    }

    public function test_it_lists_the_values_of_a_string_backed_enum(): void
    {
        $status = $this->property('status');

        $this->assertEquals('string', $status['type']);
        $this->assertEquals(['active', 'retired'], $status['enum']);
    }

    public function test_it_lists_the_values_of_an_integer_backed_enum(): void
    {
        $priority = $this->property('priority');

        $this->assertEquals('integer', $priority['type']);
        $this->assertEquals([1, 2], $priority['enum']);
    }

    public function test_it_falls_back_to_case_names_for_a_pure_enum(): void
    {
        $this->assertEquals(['Sweet', 'Salty'], $this->property('flavor')['enum']);
    }

    public function test_it_expands_a_nested_object(): void
    {
        $nested = $this->property('nested');

        $this->assertEquals('object', $nested['type']);
        $this->assertEquals('string', $nested['properties']['label']['type']);
        $this->assertTrue($nested['properties']['weight']['nullable']);
    }

    public function test_it_reads_the_element_type_of_an_array(): void
    {
        $ids = $this->property('ids');

        $this->assertEquals('array', $ids['type']);
        $this->assertEquals('integer', $ids['items']['type']);
    }

    public function test_it_expands_objects_inside_an_array(): void
    {
        $children = $this->property('children');

        $this->assertEquals('array', $children['type']);
        $this->assertEquals('object', $children['items']['type']);
        $this->assertArrayHasKey('label', $children['items']['properties']);
    }

    public function test_it_reads_the_value_type_of_a_keyed_array(): void
    {
        $this->assertEquals('object', $this->property('keyed')['items']['type']);
    }

    public function test_it_leaves_an_untyped_array_unconstrained(): void
    {
        $untyped = $this->property('untyped');

        $this->assertEquals('array', $untyped['type']);
        $this->assertArrayNotHasKey('items', $untyped);
    }

    public function test_it_stops_at_a_recursive_reference(): void
    {
        $schema = SchemaFromClass::generate(SampleRecursive::class, 'data')->toArray();

        $parent = $schema['properties']['parent'];

        $this->assertEquals('object', $parent['type']);
        $this->assertArrayNotHasKey('properties', $parent, 'Do not read a cycle forever.');
    }

    public function test_it_describes_a_class_with_no_nullable_properties(): void
    {
        $schema = SchemaFromClass::generate(SampleNested::class, 'data')->toArray();

        $this->assertEquals(['label'], $schema['required']);
    }
}
