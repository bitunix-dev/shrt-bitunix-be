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
        'city',
        'region',
        'continent',
        'device',
        'browser'
    ];
    

    public $timestamps = false;
}