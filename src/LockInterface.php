<?php

declare(strict_types=1);

namespace Kavw\IntIntMap;

interface LockInterface
{
    public function acquire(): void;

    public function release(): void;
}
