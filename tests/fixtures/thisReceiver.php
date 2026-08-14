<?php

declare(strict_types=1);

class ThisReceiverProbe
{
    public function run(): string
    {
        return $this->word()->upper();
    }

    private function word(): string
    {
        return 'ok';
    }
}

return new ThisReceiverProbe()->run();
