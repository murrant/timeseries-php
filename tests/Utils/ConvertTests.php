<?php

namespace TimeSeriesPhp\Tests\Utils;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Utils\Convert;

class ConvertTests extends TestCase
{
    public function test_null_input(): void
    {
        $this->assertNull(Convert::toNumber(null));
    }

    public function test_integer_input(): void
    {
        $this->assertSame(42, Convert::toNumber(42));
        $this->assertIsInt(Convert::toNumber(42));
    }

    public function test_float_input(): void
    {
        $this->assertSame(42.5, Convert::toNumber(42.5));
        $this->assertIsFloat(Convert::toNumber(42.5));
    }

    public function test_integer_string(): void
    {
        $this->assertSame(123, Convert::toNumber('123'));
        $this->assertIsInt(Convert::toNumber('123'));
    }

    public function test_float_string(): void
    {
        $this->assertSame(123.45, Convert::toNumber('123.45'));
        $this->assertIsFloat(Convert::toNumber('123.45'));
    }

    public function test_non_numeric_string(): void
    {
        $this->assertNull(Convert::toNumber('abc'));
    }

    public function test_whitespace_string(): void
    {
        $this->assertSame(123, Convert::toNumber(' 123 '));
        $this->assertIsInt(Convert::toNumber(' 123 '));
    }

    public function test_large_number(): void
    {
        $large = (string) PHP_INT_MAX;
        $this->assertSame(PHP_INT_MAX, Convert::toNumber($large));
        $this->assertIsInt(Convert::toNumber($large));
    }

    public function test_very_large_number(): void
    {
        $veryLarge = (string) (PHP_INT_MAX + 1);
        $this->assertIsFloat(Convert::toNumber($veryLarge));
    }
}
