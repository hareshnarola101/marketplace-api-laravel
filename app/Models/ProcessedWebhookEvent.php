<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'type', 'processed_at'])]
#[WithoutIncrementing]
#[WithoutTimestamps]
class ProcessedWebhookEvent extends Model
{
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
