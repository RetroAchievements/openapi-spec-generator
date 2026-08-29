<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions\Relationship;

use Illuminate\Support\Str;
use LaravelJsonApi\OpenApiSpec\Descriptors\Actions\ActionDescriptor;

class Fetch extends ActionDescriptor
{
    /**
     * A relationships endpoint returns only identifiers, so its summary must be
     * distinguishable from the related endpoint that returns the resources
     * themselves. Both otherwise render as the same sidebar entry.
     */
    protected function summary(): string
    {
        return "List {$this->humanize(Str::singular($this->route->relationName()))} identifiers for {$this->withArticle($this->route->name(true))}";
    }
}
