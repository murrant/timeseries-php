<?php

namespace TimeSeriesPhp\Tests\Utils;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Utils\Convert;

class ConvertTests extends TestCase
{
    public function testNullInput(): void
    {
        $this->assertNull(Convert::toNumber(null));
    }

    public function testIntegerInput(): void
    {
        $this->assertSame(42, Convert::toNumber(42));
        $this->assertIsInt(Convert::toNumber(42));
    }

    public function testFloatInput(): void
    {
        $this->assertSame(42.5, Convert::toNumber(42.5));
        $this->assertIsFloat(Convert::toNumber(42.5));
    }

    public function testIntegerString(): void
    {
        $this->assertSame(123, Convert::toNumber("123"));
        $this->assertIsInt(Convert::toNumber("123"));
    }

    public function testFloatString(): void
    {
        $this->assertSame(123.45, Convert::toNumber("123.45"));
        $this->assertIsFloat(Convert::toNumber("123.45"));
    }

    public function testNonNumericString(): void
    {
        $this->assertNull(Convert::toNumber("abc"));
    }

    public function testWhitespaceString(): void
    {
        $this->assertSame(123, Convert::toNumber(" 123 "));
        $this->assertIsInt(Convert::toNumber(" 123 "));
    }

    public function testLargeNumber(): void
    {
        $large = (string)PHP_INT_MAX;
        $this->assertSame(PHP_INT_MAX, Convert::toNumber($large));
        $this->assertIsInt(Convert::toNumber($large));
    }

    public function testVeryLargeNumber(): void
    {
        $veryLarge = (string)(PHP_INT_MAX + 1);
        $this->assertIsFloat(Convert::toNumber($veryLarge));
    }
}
