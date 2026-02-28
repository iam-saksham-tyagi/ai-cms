<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    // Whitelist these columns for saving
    protected $fillable = ['title', 'slug', 'html_content', 'css_content', 'json_content'];

    // Tell Laravel to automatically handle the JSON conversion
    protected $casts = [
        'json_content' => 'array',
    ];
}
