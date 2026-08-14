<?php

declare(strict_types=1);

return [1, 2, 3]->map(fn (int $x): int => $x * 2);
