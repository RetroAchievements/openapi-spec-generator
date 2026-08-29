<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions\Relationship;

use LaravelJsonApi\OpenApiSpec\Descriptors\Actions\ActionDescriptor;

class Attach extends ActionDescriptor
{
    protected function summary(): string
    {
        return "Add {$this->humanize($this->route->relationName())} to {$this->withArticle($this->route->name(true))}";
    }
}
