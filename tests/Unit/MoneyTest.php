<?php

namespace Tests\Unit;

use App\Services\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_documented_worked_example_and_markup_are_exact(): void
    {
        $usage = ['input_text_tokens' => 115, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 8000];
        $rates = ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'];
        $cost = Money::cost($usage, $rates);
        $this->assertSame('0.373875000000000000', $cost);
        $this->assertSame('0.467343750000000000', Money::charge($cost));
    }

    public function test_tiny_fractional_amounts_and_display_rounding(): void
    {
        $cost = Money::cost(array_fill_keys(Money::TOKENS, 1), array_fill_keys(Money::TOKENS, '0.000000001'));
        $this->assertSame('0.000000000000004000', $cost);
        $this->assertSame('0.000000000000005000', Money::charge($cost));
        $this->assertSame('0.000016', Money::display('0.000015625000000000'));
        $this->assertSame('0.467344', Money::display('0.467343750000000000'));
    }

    public function test_missing_or_float_rates_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::cost(array_fill_keys(Money::TOKENS, 1), array_fill_keys(Money::TOKENS, 5.0));
    }
}
