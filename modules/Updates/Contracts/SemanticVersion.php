<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

use InvalidArgumentException;

final readonly class SemanticVersion
{
    /** @param list<string> $preRelease */
    private function __construct(
        public int $major,
        public int $minor,
        public int $patch,
        public array $preRelease,
    ) {}

    public static function parse(string $value): self
    {
        if (preg_match('/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/', $value, $m) !== 1) {
            throw new InvalidArgumentException("Not a semantic version: {$value}");
        }

        return new self((int) $m[1], (int) $m[2], (int) $m[3], isset($m[4]) ? explode('.', $m[4]) : []);
    }

    public static function tryParse(string $value): ?self
    {
        try {
            return self::parse($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function isStable(): bool
    {
        return $this->preRelease === [];
    }

    public function compareTo(self $other): int
    {
        foreach ([[$this->major, $other->major], [$this->minor, $other->minor], [$this->patch, $other->patch]] as [$a, $b]) {
            if ($a !== $b) {
                return $a <=> $b;
            }
        }

        if ($this->preRelease === [] || $other->preRelease === []) {
            return ($this->preRelease === [] ? 1 : 0) <=> ($other->preRelease === [] ? 1 : 0);
        }

        foreach ($this->preRelease as $index => $identifier) {
            $counterpart = $other->preRelease[$index] ?? null;

            if ($counterpart === null) {
                return 1;
            }

            $result = self::compareIdentifiers($identifier, $counterpart);

            if ($result !== 0) {
                return $result;
            }
        }

        return count($this->preRelease) <=> count($other->preRelease);
    }

    public function toString(): string
    {
        $version = "{$this->major}.{$this->minor}.{$this->patch}";

        return $this->preRelease === [] ? $version : $version.'-'.implode('.', $this->preRelease);
    }

    private static function compareIdentifiers(string $a, string $b): int
    {
        $aNumeric = ctype_digit($a);
        $bNumeric = ctype_digit($b);

        if ($aNumeric && $bNumeric) {
            return (int) $a <=> (int) $b;
        }

        if ($aNumeric !== $bNumeric) {
            return $aNumeric ? -1 : 1;
        }

        return strcmp($a, $b) <=> 0;
    }
}
