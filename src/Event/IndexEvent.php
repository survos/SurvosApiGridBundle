<?php


namespace Survos\PixieBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class IndexEvent extends Event
{

    public function __construct(
        public string $pixieCode,
        public string $tableName,
        public ?int $numberOfObjects=null
        // @todo: all index counts, all stats?
    )
    {
    }
}
