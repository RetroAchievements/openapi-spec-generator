<?php

namespace LaravelJsonApi\OpenApiSpec;

use GoldSpecDigital\ObjectOrientedOAS\Exceptions\ValidationException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class OpenApiGenerator
{
    /**
     * Routes belonging to the server that were left out of the last document.
     *
     * @var array<int, array{route: string, uri: string, reason: string}>
     */
    protected array $skippedRoutes = [];

    /**
     * @return array<int, array{route: string, uri: string, reason: string}>
     */
    public function skippedRoutes(): array
    {
        return $this->skippedRoutes;
    }

    /**
     * @throws ValidationException
     */
    public function generate(string $serverKey, string $format = 'yaml'): string
    {
        [$openapi, $this->skippedRoutes] = self::withoutDependencyDeprecations(
            static function () use ($serverKey): array {
                $generator = new Generator($serverKey);

                return [$generator->generate(), $generator->skippedRoutes()];
            },
        );

        $openapi->validate();

        $storageDisk = Storage::disk(config('openapi.filesystem_disk'));

        $fileName = $serverKey.'_openapi.'.$format;

        $document = $openapi->toArray();

        if (! ResourceContainer::examplesEnabled()) {
            $document = self::withoutExamples($document);
        }

        $document = self::withoutUnreachableComponents($document);

        if ($format === 'yaml') {
            $output = Yaml::dump($document);
        } elseif ($format === 'json') {
            $output = json_encode($document, JSON_PRETTY_PRINT);
        }

        $storageDisk->put($fileName, $output);

        return $output;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $build
     * @return T
     */
    private static function withoutDependencyDeprecations(callable $build): mixed
    {
        $reporting = error_reporting();
        error_reporting($reporting & ~E_DEPRECATED);

        try {
            return $build();
        } finally {
            error_reporting($reporting);
        }
    }

    /**
     * Component groups whose members are reached only through a `$ref`.
     *
     * Security schemes are left out on purpose. An operation names one by key in
     * its `security` block rather than by reference, so counting references
     * would report every scheme as dead.
     */
    private const REFERENCED_COMPONENT_GROUPS = ['schemas', 'responses', 'parameters', 'requestBodies'];

    /**
     * Drop components that nothing points at.
     *
     * The builders register a component whenever they may need one, so a document
     * whose routes never use a given response or schema still defines it. A dead
     * definition makes a client generator emit a model no endpoint can return.
     *
     * Removal repeats until nothing more falls out, because the only reference to
     * one component can live inside another that was itself just removed.
     *
     * @param  array<mixed>  $document
     * @return array<mixed>
     */
    private static function withoutUnreachableComponents(array $document): array
    {
        if (! isset($document['components']) || ! is_array($document['components'])) {
            return $document;
        }

        do {
            $referenced = self::referencedPaths($document);
            $removed = false;

            foreach (self::REFERENCED_COMPONENT_GROUPS as $group) {
                if (! isset($document['components'][$group]) || ! is_array($document['components'][$group])) {
                    continue;
                }

                foreach (array_keys($document['components'][$group]) as $name) {
                    if (! in_array("#/components/{$group}/{$name}", $referenced, true)) {
                        unset($document['components'][$group][$name]);
                        $removed = true;
                    }
                }

                if ($document['components'][$group] === []) {
                    unset($document['components'][$group]);
                }
            }
        } while ($removed);

        return $document;
    }

    /**
     * Every `$ref` target in the document, outside the components block itself.
     *
     * A reference that a component makes to another component counts only while
     * the referring component is still present, so the components block is read
     * again on each pass rather than once up front.
     *
     * @param  array<mixed>  $document
     * @return string[]
     */
    private static function referencedPaths(array $document): array
    {
        $found = [];

        array_walk_recursive($document, function ($value, $key) use (&$found): void {
            if ($key === '$ref' && is_string($value)) {
                $found[] = $value;
            }
        });

        return array_values(array_unique($found));
    }

    /**
     * Recursively remove every `example` and `examples` key.
     *
     * This runs at the single serialization point rather than at each of the many
     * call sites that can attach an example. Guarding the call sites individually
     * can silently miss one, and it would not catch the examples built from
     * constants rather than sampled data.
     *
     * @param  array<mixed>  $document
     * @return array<mixed>
     */
    private static function withoutExamples(array $document): array
    {
        $stripped = [];

        foreach ($document as $key => $value) {
            if ($key === 'example' || $key === 'examples') {
                continue;
            }

            $stripped[$key] = is_array($value) ? self::withoutExamples($value) : $value;
        }

        return $stripped;
    }
}
