<?php

namespace App\Services;

class Pricing
{
    public function __construct(private PixelForge $provider) {}

    public function estimate(string $prompt, string $size, string $quality, array $references): array
    {
        $pricing = $this->provider->pricing();
        $tokens = $pricing['tokens'];
        $divisor = (int) $tokens['characters_per_text_token'];
        $text = intdiv(mb_strlen($prompt) + $divisor - 1, $divisor);
        $range = $tokens['reasoning'][$quality];
        $usage = [
            'input_text_tokens' => $text,
            'input_image_tokens' => count($references) * $tokens['per_reference_image'],
            'output_tokens' => $tokens['output'][$quality][$size],
            'reasoning_tokens' => intdiv($range['min'] + $range['max'], 2),
        ];
        $cost = Money::cost($usage, $pricing['rates']);
        // JSON numeric variance is converted to a decimal string before arithmetic.
        $upperText = bcmul((string) $text, bcadd('1', (string) $tokens['text_token_variance'], 9), 9);
        $usage['input_text_tokens'] = (int) bcadd($upperText, '0.999999999', 0);
        $usage['reasoning_tokens'] = $range['max'];

        return [
            'provider_cost' => $cost, 'customer_price' => Money::charge($cost),
            'maximum_customer_price' => Money::charge(Money::cost($usage, $pricing['rates'])),
            'rates' => $pricing['rates'], 'currency' => 'USD',
        ];
    }
}
