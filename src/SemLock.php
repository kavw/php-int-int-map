<?php

declare(strict_types=1);

namespace Kavw\IntIntMap;

final class SemLock implements LockInterface
{
    private bool $lock = false;

    public function __construct(
        readonly private \SysvSemaphore $semaphore
    ) {
    }

    public function acquire(): void
    {
        if ($this->lock) {
            throw new \LogicException("The lock has been acquired already");
        }

        $this->lock = sem_acquire($this->semaphore);
        if (!$this->lock) {
            throw new \RuntimeException("Can't acquire the lock");
        }
    }

    public function release(): void
    {
        if (!$this->lock) {
            throw new \LogicException("The lock is released");
        }

        $res = sem_release($this->semaphore);
        if (!$res) {
            throw new \RuntimeException("Can't release the lock");
        }
        $this->lock = false;
    }

    public function __destruct()
    {
        if ($this->lock) {
            $this->release();
        }
    }
}
