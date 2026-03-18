<?php
namespace Modules\News\App\Services;

use Modules\News\App\Models\News;
use Illuminate\Pagination\LengthAwarePaginator;

class NewsService
{
    public function getAll()
    {
        if (app()->has('currentTenant')) {
            return News::latest()->paginate(15); // scoped by global scope
        }

        // Central domain — return all news without scope
        return News::withoutGlobalScope('tenant')->latest()->paginate(15);
    }

    public function findById(int $id): News
    {
        return News::findOrFail($id);
    }

    public function create(array $data): News
    {
        return News::create($data);
    }

    public function update(News $news, array $data): News
    {
        $news->update($data);
        return $news->fresh();
    }

    public function delete(News $news): void
    {
        $news->delete();
    }
}
