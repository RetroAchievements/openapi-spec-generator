<?php

declare(strict_types=1);

namespace LaravelJsonApi\OpenApiSpec\Eloquent\Fields;

use LaravelJsonApi\Eloquent\Fields\Number;

class Integer extends Number
{
    public static function make(string $fieldName, ?string $column = null): self
    {
        return new self($fieldName, $column);
    }
}
