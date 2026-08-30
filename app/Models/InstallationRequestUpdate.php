<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationRequestUpdate extends Model
{
    public const VISIBILITY_CUSTOMER = 'customer';

    public const VISIBILITY_INTERNAL = 'internal';

    protected $fillable = [
        'installation_request_id',
        'visibility',
        'body',
        'actor_id',
    ];

    public function installationRequest(): BelongsTo
    {
        return $this->belongsTo(InstallationRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
