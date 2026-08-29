<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions\Relationship;

class Update extends Attach
{
    protected function summary(): string
    {
        return "Replace the {$this->humanize($this->route->relationName())} of {$this->withArticle($this->route->name(true))}";
    }
}
