<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions\Relationship;

class Detach extends Attach
{
    protected function summary(): string
    {
        return "Remove {$this->humanize($this->route->relationName())} from {$this->withArticle($this->route->name(true))}";
    }
}
