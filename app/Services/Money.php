<?php

namespace App\Services;

use InvalidArgumentException;

/** USD strings only. Rates have up to 9 fractional digits; /1M and x1.25 fit scale 18. */
final class Money
{
    public const SCALE = 18;

    public const TOKENS = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    public static function cost(array $usage, array $rates): string
    {
        $total = '0';
        foreach (self::TOKENS as $type) {
            $count = $usage[$type] ?? null;
            $rate = $rates[$type] ?? null;
            if (! is_int($count) || $count < 0 || ! is_string($rate) || ! preg_match('/^\d{1,9}(\.\d{1,9})?$/D', $rate)) {
                throw new InvalidArgumentException("Invalid token count or decimal rate for {$type}");
            }
            $total = bcadd($total, bcdiv(bcmul((string) $count, $rate, self::SCALE), '1000000', self::SCALE), self::SCALE);
        }

        return $total;
    }

    public static function charge(string $providerCost): string
    {
        return bcmul($providerCost, '1.25', self::SCALE);
    }

    public static function display(string $amount, int $places = 6): string
    {
        return bcadd($amount, '0.'.str_repeat('0', $places).'5', $places);
    }
}
