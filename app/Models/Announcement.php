<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'audience', 'is_pinned', 'created_by', 'published_at'];
    protected $casts = ['is_pinned' => 'boolean', 'published_at' => 'datetime'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
