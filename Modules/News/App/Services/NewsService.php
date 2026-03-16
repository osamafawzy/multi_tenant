<?php
namespace Modules\News\App\Services;

use Modules\News\App\Models\News;
use Illuminate\Pagination\LengthAwarePaginator;

class NewsService
{
    public function getAll(): LengthAwarePaginator
    {
        return News::latest()->paginate(15);
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
