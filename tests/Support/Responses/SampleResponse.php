<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Support\Responses;

use Carbon\CarbonImmutable;

enum SampleStatus: string
{
    case Active = 'active';
    case Retired = 'retired';
}

enum SamplePriority: int
{
    case Low = 1;
    case High = 2;
}

enum SampleFlavor
{
    case Sweet;
    case Salty;
}

class SampleNested
{
    public function __construct(
        public string $label,
        public ?int $weight,
    ) {}
}

class SampleRecursive
{
    public function __construct(
        public string $name,
        public ?SampleRecursive $parent,
    ) {}
}

/**
 * Has one property for each shape that the converter must describe.
 */
class SampleResponse
{
    public function __construct(
        public string $title,
        public int $count,
        public float $ratio,
        public bool $enabled,
        public ?string $note,
        public CarbonImmutable $occurredAt,
        public ?CarbonImmutable $resolvedAt,
        public SampleStatus $status,
        public SamplePriority $priority,
        public SampleFlavor $flavor,
        public SampleNested $nested,

        /**
         * @var int[]
         */
        public array $ids,

        /**
         * @var array<SampleNested>
         */
        public array $children,

        /**
         * @var array<string, SampleNested>
         */
        public array $keyed,

        public array $untyped,
    ) {}
}
