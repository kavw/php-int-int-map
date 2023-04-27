<?php

declare(strict_types=1);

namespace Kavw\IntIntMap\Tests;

use Kavw\IntIntMap\IntIntMap;
use Kavw\IntIntMap\SemLock;
use Kavw\IntIntMap\Exceptions\CapacityIsOverException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IntIntMapTest extends TestCase
{
    private ?\Shmop $shmId = null;
    private ?\SysvSemaphore $semId = null;
    private ?int $capacity = null;

    private array $data = [];

    protected function setUp(): void
    {
        $this->data = [
            [-1, 1000],
            [-3, -1],
            [0, 2000],
            [3, 0],
            [1, -1000],
            [PHP_INT_MAX, 0],
            [PHP_INT_MIN + 1, 10],
        ];

        $this->capacity = \count($this->data);

        $shmKey = ftok(__FILE__, 't');
        $size = PHP_INT_SIZE + PHP_INT_SIZE * 2 * $this->capacity;
        $this->shmId = shmop_open($shmKey, "c", 0644, $size);
        $this->semId = sem_get($shmKey);
    }

    protected function tearDown(): void
    {
        shmop_delete($this->shmId);
        sem_remove($this->semId);
    }

    public static function lockFlagProvider(): array
    {
        return [
            [true],
            [false]
        ];
    }

    #[DataProvider('lockFlagProvider')]
    public function testBasicFunctionality(bool $useLock): void
    {
        $lock = $useLock ? new SemLock($this->semId) : null;
        $map = new IntIntMap($this->shmId, $lock);

        $this->assertEquals($this->capacity, $map->capacity(), "The map has expected capacity");

        $expectedSize = 0;
        $this->assertEquals($expectedSize, $map->size(), "The map is empty");

        foreach ($this->data as [$key, $val]) {
            $res = $map->get($key);
            $this->assertNull($res, "There are no data with the key {$key}");

            $res = $map->set($key, $val);
            $this->assertNull($res, "The previous value for the key {$key} is null");
            $this->assertEquals($expectedSize + 1, $map->size(), "Map size has been incremented");

            $res = $map->get($key);
            $this->assertEquals($val, $res, "Getting stored value for the key {$key}");

            $newVal = mt_rand(0, PHP_INT_MAX);
            $res = $map->set($key, $newVal);
            $this->assertEquals($val, $res, "Getting the previous value for the key {$key}");
            $this->assertEquals($expectedSize + 1, $map->size(), "Map size didn't change");

            $res = $map->get($key);
            $this->assertEquals($newVal, $res, "Getting the new value for the key {$key}");

            $expectedSize++;
        }

        $this->assertEquals($expectedSize, $this->capacity, "Map size reached the limit");

        $this->expectException(CapacityIsOverException::class);
        $map->set(PHP_INT_MIN + 10, 0);
    }

    public function testArgumentException(): void
    {
        $map = new IntIntMap($this->shmId);
        $this->expectException(\InvalidArgumentException::class);
        $map->set(PHP_INT_MIN, 1);
    }
}
