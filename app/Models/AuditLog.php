<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'action',
        'auditable_type',
        'auditable_id',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public static function record(string $action, Model $target): self
    {
        return self::create([
            'action' => $action,
            'auditable_type' => $target->getMorphClass(),
            'auditable_id' => $target->getKey(),
            'actor_user_id' => Auth::id(),
        ]);
    }
}
