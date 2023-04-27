<?php

declare(strict_types=1);

namespace Kavw\IntIntMap;

use Kavw\IntIntMap\Exceptions\CapacityIsOverException;

class IntIntMap implements IntIntMapInterface
{
    /**
     * @var array<int, string>
     */
    private static array $packPairFormat = [
        4 => 'ii',
        8 => 'qq'
    ];

    /**
     * @var array<int, string>
     */
    private static array $unpackPairFormat = [
        4 => 'ikey/ival',
        8 => 'qkey/qval'
    ];

    /**
     * @var array<int, string>
     */
    private static array $packIntFormat = [
        4 => 'i',
        8 => 'q'
    ];

    /**
     * @var array<int, string>
     */
    private static array $unpackIntFormat = [
        4 => 'ival',
        8 => 'qval'
    ];

    private int $itemSize;
    private int $capacity;


    public function __construct(
        readonly private \Shmop $shmId,
        readonly private ?LockInterface $lock = null
    ) {
        if (!isset(self::$packPairFormat[PHP_INT_SIZE])) {
            throw new \RuntimeException("Unsupported int size");
        }

        $memSize = shmop_size($shmId);

        $this->itemSize = PHP_INT_SIZE * 2;
        if ($memSize < $this->itemSize + PHP_INT_SIZE) {
            throw new \RuntimeException("Not enough memory");
        }

        $this->capacity = ((int) floor(($memSize - PHP_INT_SIZE) / $this->itemSize));
    }

    /**
     * @param int $key
     * @param int $value
     * @return int|null
     * @throws \Exception
     */
    public function set(int $key, int $value): ?int
    {
        try {
            $this->lock?->acquire();
            $this->testKey($key);
            $res = $this->findIndexToInsert($key);
            $this->writeItem($res['idx'], $key, $value);
            if ($res['val'] === null) {
                $this->writeSize($this->size() + 1);
            }

            return $res['val'];
        } finally {
            $this->lock?->release();
        }
    }

    public function get(int $key): ?int
    {
        $this->testKey($key);

        $index = $this->calcIndex($key);
        $ranges = [
            ['start' => $index, 'bound' => $this->capacity],
            ['start' => 0, 'bound' => $index],
        ];

        try {
            $this->lock?->acquire();

            foreach ($ranges as $range) {
                for ($i = $range['start']; $i < $range['bound']; $i++) {
                    $pair = $this->readItem($i);
                    if ($pair['val'] === null) {
                        return null;
                    }

                    if ($pair['key'] === $key) {
                        return $pair['val'];
                    }
                }
            }

            return null;
        } finally {
            $this->lock?->release();
        }
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function size(): int
    {
        return $this->readInt(0);
    }


    private function testKey(int $key): void
    {
        if ($key === PHP_INT_MIN) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Unable to use $key as a key. " .
                    "Available range is [%d, %d]",
                    $key + 1,
                    PHP_INT_MAX
                )
            );
        }
    }

    /**
     * @param int $key
     * @return array{idx: int, val: ?int}
     * @throws \Exception
     */
    private function findIndexToInsert(int $key): array
    {
        $index = $this->calcIndex($key);
        $ranges = [
            ['start' => $index, 'bound' => $this->capacity],
            ['start' => 0, 'bound' => $index],
        ];

        foreach ($ranges as $range) {
            for ($i = $range['start']; $i < $range['bound']; $i++) {
                $pair = $this->readItem($i);
                if ($pair['val'] === null) {
                    return ['idx' => $i, 'val' => null];
                }

                if ($pair['key'] === $key) {
                    return ['idx' => $i, 'val' => $pair['val']];
                }
            }
        }

        if ($this->size() === $this->capacity) {
            throw new CapacityIsOverException(
                "Can't insert key: {$key}. Map size capacity reached"
            );
        }

        throw new \LogicException(
            "Didn't found a place to insert a value for the key {$key}"
        );
    }

    private function writeItem(int $index, int $key, int $value): void
    {
        $key    = $key <= 0 ? $key - 1 : $key;
        $data   = pack(self::$packPairFormat[PHP_INT_SIZE], $key, $value);
        $offset = $this->calcOffset($index);
        $result = shmop_write($this->shmId, $data, $offset);

        if ($result !== $this->itemSize) {
            throw new \RuntimeException("Wrong write result {$result} expected {$this->itemSize}");
        }
    }

    /**
     * @param int $index
     * @return array{key: int, val: ?int}
     */
    private function readItem(int $index): array
    {
        $offset = $this->calcOffset($index);
        $data = shmop_read($this->shmId, $offset, $this->itemSize);
        if (strlen($data) < $this->itemSize) {
            throw new \RuntimeException("Unable to read data at offset: $offset");
        }

        $res = unpack(self::$unpackPairFormat[PHP_INT_SIZE], $data);
        if (!$res) {
            throw new \RuntimeException("Unable unpack data");
        }

        /** @var array{key: int, val: int} $res */
        if ($res['key'] === 0) {
            $res['val'] = null;
            return $res;
        }

        if ($res['key'] < 0) {
            $res['key'] = $res['key'] + 1;
        }

        return  $res;
    }

    private function calcIndex(int $key): int
    {
        return abs($key) % $this->capacity;
    }

    private function calcOffset(int $index): int
    {
        return $index * $this->itemSize + PHP_INT_SIZE;
    }

    private function writeSize(int $size): void
    {
        $data   = pack(self::$packIntFormat[PHP_INT_SIZE], $size);
        $result = shmop_write($this->shmId, $data, 0);

        if ($result !== PHP_INT_SIZE) {
            throw new \RuntimeException("Wrong write result {$result} expected {$this->itemSize}");
        }
    }


    private function readInt(int $offset): int
    {
        $data = shmop_read($this->shmId, $offset, PHP_INT_SIZE);
        if (strlen($data) < PHP_INT_SIZE) {
            throw new \RuntimeException("Unable to read data at offset: 0");
        }

        $res = unpack(self::$unpackIntFormat[PHP_INT_SIZE], $data);
        if (!$res) {
            throw new \RuntimeException("Unable unpack data");
        }

        return $res['val'];
    }
}
