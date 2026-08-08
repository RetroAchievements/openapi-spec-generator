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
