<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'code',
        'context',
        'expires_at',
        'is_used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
        'user_id' => 'integer',
    ];

    /**
     * Get the user that owns the verification code
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if verification code is expired
     */
    public function isExpired()
    {
        return $this->expires_at < now();
    }

    /**
     * Check if verification code is valid (not used and not expired)
     */
    public function isValid()
    {
        return !$this->is_used && !$this->isExpired();
    }
}
