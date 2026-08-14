<?php

declare(strict_types=1);

$word  = 'Hello';
$upper = $word->upper();

return [$upper, $word, is_string($word)];
