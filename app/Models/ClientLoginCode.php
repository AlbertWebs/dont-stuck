<?php

namespace App\Models;

use Database\Factories\ClientLoginCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'code_hash', 'expires_at', 'attempts', 'consumed_at'])]
class ClientLoginCode extends Model
{
    /** @use HasFactory<ClientLoginCodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
