<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Processed webhook id. A second delivery with the same provider + event id is a no-op.
 *
 * @property string $provider
 * @property string $event_id
 */
class BillingWebhookEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('filament-billing-providers.persistence.events_table', 'billing_webhook_events');
    }
}
