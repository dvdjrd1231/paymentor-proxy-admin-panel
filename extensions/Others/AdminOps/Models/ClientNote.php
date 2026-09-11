<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One staff note against a client, for the reference's Notes tab. */
class ClientNote extends Model
{
    protected $table = 'ext_ao_client_notes';

    protected $fillable = ['user_id', 'admin_id', 'note', 'sticky'];

    protected $casts = ['sticky' => 'boolean'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
