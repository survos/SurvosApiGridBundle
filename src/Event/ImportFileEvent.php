<?php


namespace Survos\PixieBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ImportFileEvent extends Event
{

    public function __construct(
        public string $filename,
    )
    {
    }

    public function getType()
    {
        $ext = pathinfo($this->filename, PATHINFO_EXTENSION);
        return match ($ext) {
            'json' => 'json',
            'csv',
                'txt' => 'csv',
        };

    }
}
