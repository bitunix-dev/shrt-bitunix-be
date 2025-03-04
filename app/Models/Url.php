<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    use HasFactory;

    protected $fillable = [
        'destination_url',
        'short_link',
        'qr_code',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'referral',
        'clicks'
    ];

    protected $casts = [
        'clicks' => 'integer',
    ];

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order', function ($query) {
            $query->orderBy('created_at', 'desc');
        });
    }
}