<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClickLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'url_id',
        'ip_address',
        'country',
        'country_flag',
        'city',
        'region',
        'continent',
        'device',
        'browser',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
    ];


    public $timestamps = false;
}