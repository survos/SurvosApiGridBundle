<?php

namespace Survos\ApiGrid\Model;

class Column
{
    public function __construct(
        public string $name,
        public ?string $block = null, // reuse the blocks even if the data changes
        public ?string $title = null,
        public ?string $twigTemplate = null,
        public ?string $route = null,
        public ?string $type = null, // this is used for searchBuilder
        public ?string $prefix = null,
        public bool $searchable = false,
        public bool $browsable = false,
        public bool $sortable = false,
        public ?array $actions = null,
        public bool $modal = false,
        public bool|string $locale = false,
    ) {
        if (empty($this->title)) {
            $this->title = ucwords($this->name);
        }
    }

    public function __toString()
    {
        return $this->name;
    }
}
