<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions\Relationship;

use LaravelJsonApi\OpenApiSpec\Descriptors\Actions\ActionDescriptor;

class FetchRelated extends ActionDescriptor
{
    protected function summary(): string
    {
        return "List {$this->humanize($this->route->relationName())} for {$this->withArticle($this->route->name(true))}";
    }
}
