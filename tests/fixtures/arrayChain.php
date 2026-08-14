<?php

declare(strict_types=1);

return [1, 2, 3, 4, 5, 6]->filter(fn (int $x): bool => $x % 2 === 0)->sum();
