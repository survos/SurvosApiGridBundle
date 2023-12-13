<?php

namespace Survos\ApiGrid\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_PROPERTY)]
class Facet
{
    public function __construct(
        public ?string $label=null, // column label in sidebar
        public ?string $translationDomain=null
    ) {
    }
}
