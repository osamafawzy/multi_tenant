<?php
namespace Modules\News\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\News\App\Services\NewsService;
use Modules\News\App\Models\News;

class NewsController extends Controller
{
    public function __construct(protected NewsService $newsService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->newsService->getAll());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $news = $this->newsService->create($validated);
        return response()->json($news, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->newsService->findById($id));
    }

    public function update(Request $request, News $news): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
        ]);

        return response()->json($this->newsService->update($news, $validated));
    }

    public function destroy(News $news): JsonResponse
    {
        $this->newsService->delete($news);
        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}
