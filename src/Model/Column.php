<?php

namespace Survos\ApiGrid\Model;

class Column {

    public function __construct(
        public string $name,
        public ?string $title=null,
        public ?string $twigTemplate = null,
        public ?string $route=null,
        public ?string $prefix=null,
        public ?array $actions=null,
        public bool $modal=false
    )
    {
        if (empty($this->title)) {
            $this->title = ucwords($this->name);
        }

    }

    public function __toString()
    {
        return $this->name;
    }
}
