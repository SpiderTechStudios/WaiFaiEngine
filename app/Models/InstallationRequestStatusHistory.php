<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationRequestStatusHistory extends Model
{
    protected $fillable = [
        'installation_request_id',
        'from_status',
        'to_status',
        'field',
        'actor_id',
        'note',
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
