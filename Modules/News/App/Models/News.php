<?php

namespace Modules\News\App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class News extends Model
{
    use BelongsToTenant;

    protected $fillable = ['title', 'description'];
}
