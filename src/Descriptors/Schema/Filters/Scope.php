<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema as OASchema;

class Scope extends FilterDescriptor
{
    public function filter(): array
    {
        return [
            Parameter::query()
                ->name("filter[{$this->filter->key()}]")
                ->description($this->description())
                ->required(false)
                ->allowEmptyValue(false)
                ->schema($this->valueSchema()),
        ];
    }

    /**
     * Infer the accepted type from how the filter converts an incoming value.
     */
    protected function valueSchema(): OASchema
    {
        $probe = '1';

        try {
            $deserialize = new \ReflectionMethod($this->filter, 'deserialize');
            $converted = $deserialize->invoke($this->filter, $probe);
        } catch (\Throwable) {
            return OASchema::string();
        }

        return match (true) {
            is_bool($converted) => OASchema::boolean(),
            is_int($converted) => OASchema::integer(),
            default => OASchema::string(),
        };
    }

    protected function description(): string
    {
        return "Applies the {$this->filter->key()} scope.";
    }
}
