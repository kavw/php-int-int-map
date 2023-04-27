<?php

declare(strict_types=1);

namespace Kavw\IntIntMap;

/**
 * It needs to implement an IntIntMap class, which stores an arbitrary int value by an arbitrary int key
 * Important: All the data (including meta information) need to be stored in a prepared shared memory block.
 * To access the memory use functions `\shmop_read` and `\shmop_write`
 */
interface IntIntMapInterface
{
    /**
     * The method must have O(1) complexity if any collision doesn't exist
     * @param int $key an arbitrary key
     * @param int $value an arbitrary
     * @return int|null the previous value
     */
    public function set(int $key, int $value): ?int;

    /**
     * The method must have O(1) complexity if any collision doesn't exist
     * @param int $key
     * @return int|null
     */
    public function get(int $key): ?int;

    public function capacity(): int;

    public function size(): int;
}
