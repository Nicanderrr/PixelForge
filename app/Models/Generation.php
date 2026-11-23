<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['request_payload', 'provider_key_fingerprint', 'request_key'];

    protected function casts(): array
    {
        return [
            'reference_images' => 'array', 'request_payload' => 'array',
            'estimated_rates' => 'array', 'rates' => 'array', 'usage' => 'array',
            'slot_reserved' => 'boolean',
            'estimated_provider_cost' => 'decimal:18', 'estimated_customer_price' => 'decimal:18',
            'maximum_customer_price' => 'decimal:18', 'actual_provider_cost' => 'decimal:18',
            'actual_customer_price' => 'decimal:18',
            'next_attempt_at' => 'datetime', 'last_checked_at' => 'datetime',
            'recovery_until' => 'datetime',
            'started_at' => 'datetime', 'completed_at' => 'datetime', 'image_url_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['succeeded', 'failed'], true);
    }
}
