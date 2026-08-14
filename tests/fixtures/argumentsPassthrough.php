<?php

declare(strict_types=1);

$spread = "a-b"->replace(...['-', '+']);
$named  = "a-b"->replace(replace: '+', search: '-');

return [$spread, $named];
