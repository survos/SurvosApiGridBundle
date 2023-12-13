<?php

namespace Survos\ApiGrid\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Facets
{
    public function __construct(
        public array $groups = [],
        public array $properties = [],
//        public array $methods[]
    ) 
{
    if (empty($this->groups) && empty($this->properties)) {
        throw new \Exception("Define either groups or properties in Facets()");
    }
    }
}
