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
    protected $appends = ['mixed_url'];
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
    public function getMixedUrlAttribute()
    {
        $utmParams = collect([
            'utm_source' => $this->source,
            'utm_medium' => $this->medium,
            'utm_campaign' => $this->campaign,
            'utm_term' => $this->term,
            'utm_content' => $this->content,
            'ref' => $this->referral,
        ])->filter()->toArray();

        $destinationUrl = $this->destination_url;
        if (!empty($utmParams)) {
            $separator = (str_contains($destinationUrl, '?') ? '&' : '?');
            $destinationUrl .= $separator . http_build_query($utmParams);
        }

        return $destinationUrl;
    }
}