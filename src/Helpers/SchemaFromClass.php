<?php

namespace LaravelJsonApi\OpenApiSpec\Helpers;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema as OASchema;

/**
 * Makes an OpenAPI schema from the property types of a class.
 *
 * Some routes are not JSON:API resource routes. They have no schema. You must
 * write their documentation by hand, or make it from an example.
 *
 * An example is not sufficient. It shows one value. It cannot tell you if that
 * value can be null.
 *
 * A response class declares the type of each field. This class reads those
 * types. Thus one declaration controls the response and the documentation.
 *
 * This class uses only reflection. Any class with typed public properties works.
 */
class SchemaFromClass
{
    /**
     * The maximum number of nested objects to read.
     *
     * An object can point back to itself. This limit stops an endless loop.
     * Documentation becomes too deep to read before it gets to this limit.
     */
    private const MAX_DEPTH = 10;

    /**
     * @param  class-string  $class
     * @param  string[]  $ancestors  The classes above this one. Use them to find a cycle.
     */
    public static function generate(string $class, ?string $key = null, array $ancestors = []): OASchema
    {
        $schema = OASchema::object($key);

        if (in_array($class, $ancestors, true) || count($ancestors) >= self::MAX_DEPTH) {
            return $schema->description('Recursive structure, not expanded further.');
        }

        $reflection = new \ReflectionClass($class);
        $properties = [];
        $required = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $type = $property->getType();

            if (! $type instanceof \ReflectionNamedType) {
                // A union type or an intersection type has more than one shape.
                // Do not guess the shape. Give no type instead.
                $properties[] = OASchema::create($property->getName());

                continue;
            }

            $properties[] = self::forType(
                $type,
                $property->getName(),
                self::itemTypeFromDocBlock((string) $property->getDocComment(), $reflection),
                [...$ancestors, $class],
            );

            if (! $type->allowsNull()) {
                $required[] = $property->getName();
            }
        }

        $schema = $schema->properties(...$properties);

        return $required === [] ? $schema : $schema->required(...$required);
    }

    /**
     * @param  string[]  $ancestors
     */
    private static function forType(
        \ReflectionNamedType $type,
        string $key,
        ?string $itemType,
        array $ancestors,
    ): OASchema {
        $schema = self::forTypeName($type->getName(), $key, $itemType, $ancestors);

        // OpenAPI 3.0 has no null type. Put a flag next to the type instead.
        return $type->allowsNull() ? $schema->nullable(true) : $schema;
    }

    /**
     * @param  string[]  $ancestors
     */
    private static function forTypeName(
        string $name,
        string $key,
        ?string $itemType,
        array $ancestors,
    ): OASchema {
        $scalar = match ($name) {
            'int' => OASchema::integer($key),
            'float' => OASchema::number($key),
            'bool' => OASchema::boolean($key),
            'string' => OASchema::string($key),
            default => null,
        };

        if ($scalar !== null) {
            return $scalar;
        }

        if ($name === 'array' || $name === 'iterable') {
            return self::arrayOf($itemType, $key, $ancestors);
        }

        if ($name === 'mixed' || ! class_exists($name) && ! interface_exists($name)) {
            return OASchema::create($key);
        }

        if (enum_exists($name)) {
            return self::forEnum($name, $key);
        }

        if (is_a($name, \DateTimeInterface::class, true)) {
            return OASchema::string($key)->format(OASchema::FORMAT_DATE_TIME);
        }

        return self::generate($name, $key, $ancestors);
    }

    /**
     * @param  string[]  $ancestors
     */
    private static function arrayOf(?string $itemType, string $key, array $ancestors): OASchema
    {
        $schema = OASchema::array($key);

        if ($itemType === null) {
            return $schema;
        }

        return $schema->items(self::forTypeName($itemType, 'items', null, $ancestors));
    }

    /**
     * @param  class-string<\UnitEnum>  $name
     */
    private static function forEnum(string $name, string $key): OASchema
    {
        $cases = $name::cases();

        // A backed enum sends its value. A pure enum has only names.
        $values = array_map(
            static fn (\UnitEnum $case) => $case instanceof \BackedEnum ? $case->value : $case->name,
            $cases,
        );

        $schema = is_int($values[0] ?? null) ? OASchema::integer($key) : OASchema::string($key);

        return $schema->enum(...$values);
    }

    /**
     * Finds the element type of an array.
     *
     * A PHP type declaration cannot give this type. This method reads the
     * `Type[]`, `array<Type>`, and `array<Key, Type>` forms.
     */
    private static function itemTypeFromDocBlock(string $docBlock, \ReflectionClass $owner): ?string
    {
        $patterns = [
            '/@var\s+array<[^,>]+,\s*([^>\s]+)>/',
            '/@var\s+array<([^>\s]+)>/',
            '/@var\s+([^\s\[]+)\[\]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $docBlock, $matches)) {
                return self::qualify($matches[1], $owner);
            }
        }

        return null;
    }

    /**
     * Changes a doc block type name into a name that reflection can load.
     *
     * A doc block keeps the name that the developer wrote. This name is usually
     * a short alias. To find the full name, first read the imports of the file.
     * Then read the namespace of the file.
     */
    private static function qualify(string $type, \ReflectionClass $owner): string
    {
        $type = ltrim($type, '\\');

        if (self::isBuiltIn($type) || class_exists($type) || interface_exists($type)) {
            return $type;
        }

        $imported = self::imports($owner)[strtolower($type)] ?? null;

        if ($imported !== null) {
            return $imported;
        }

        $sameNamespace = $owner->getNamespaceName().'\\'.$type;

        return class_exists($sameNamespace) || interface_exists($sameNamespace) ? $sameNamespace : $type;
    }

    private static function isBuiltIn(string $type): bool
    {
        return in_array($type, ['int', 'float', 'bool', 'string', 'array', 'iterable', 'mixed', 'object'], true);
    }

    /**
     * Gets the `use` statements of the file.
     *
     * The key is the alias that each statement makes.
     *
     * @return array<string, class-string>
     */
    private static function imports(\ReflectionClass $owner): array
    {
        static $cache = [];

        $file = $owner->getFileName();

        if ($file === false) {
            return [];
        }

        if (isset($cache[$file])) {
            return $cache[$file];
        }

        $imports = [];

        preg_match_all(
            '/^use\s+([^\s;(]+?)(?:\s+as\s+([^\s;]+))?\s*;/mi',
            (string) file_get_contents($file),
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $fqn = ltrim($match[1], '\\');
            $alias = $match[2] ?? substr((string) strrchr('\\'.$fqn, '\\'), 1);
            $imports[strtolower($alias)] = $fqn;
        }

        return $cache[$file] = $imports;
    }
}
