<?php

namespace Survos\ApiGridBundle\Attribute;

use \Attribute;

#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_PROPERTY)]
class MeiliId
{
    public function __construct(
    ) {
    }
}
