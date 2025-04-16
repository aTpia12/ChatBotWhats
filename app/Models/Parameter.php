<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue($key, $default = null)
    {
        return self::where('key', $key)->value('value') ?? $default;
    }
}
