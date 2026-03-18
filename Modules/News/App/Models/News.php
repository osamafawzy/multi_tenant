<?php

namespace Modules\News\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class News extends Model
{
    protected $fillable = ['tenant_id', 'title', 'description'];

    protected static function booted(): void
    {
        // Auto-scope queries to current tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->has('currentTenant')) {
                $builder->where('tenant_id', app('currentTenant')->id);
            }
        });

        // Auto-fill tenant_id on create
        static::creating(function (News $news) {
            if (app()->has('currentTenant') && empty($news->tenant_id)) {
                $news->tenant_id = app('currentTenant')->id;
            }
        });
    }
}
