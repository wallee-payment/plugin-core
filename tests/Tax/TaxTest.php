<?php

declare(strict_types=1);

namespace Wallee\PluginCore\Tests\Tax;

use PHPUnit\Framework\TestCase;
use Wallee\PluginCore\Tax\Tax;

class TaxTest extends TestCase
{
    public function testValidTax(): void
    {
        $tax = new Tax('VAT', 19.0);
        $this->assertEquals('VAT', $tax->title);
        $this->assertEquals(19.0, $tax->rate);
    }

    public function testTitleTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax title must be between 2 and 40 characters');
        new Tax('A', 19.0);
    }

    public function testTitleTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax title must be between 2 and 40 characters');
        new Tax(str_repeat('A', 41), 19.0);
    }

    public function testTitleExactBoundaryHigh(): void
    {
        $title = str_repeat('A', 40);
        $tax = new Tax($title, 19.0);
        $this->assertEquals($title, $tax->title);
    }

    public function testTitleExactBoundaryLow(): void
    {
        $title = 'AB';
        $tax = new Tax($title, 19.0);
        $this->assertEquals($title, $tax->title);
    }
}
