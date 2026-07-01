<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticlePublicController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $articles = Article::where('status', 'publish')
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->category))
            ->orderByDesc('published_at')
            ->paginate($request->integer('per_page', 9));

        $articles->getCollection()->transform(fn($a) => $this->map($a));

        return $this->success($articles, 'Artikel publik.');
    }

    public function show(string $slug): JsonResponse
    {
        $article = Article::where('slug', $slug)->where('status', 'publish')->first();

        if (! $article) {
            return $this->error('Artikel tidak ditemukan.', 404);
        }

        return $this->success($this->map($article, full: true), 'Detail artikel.');
    }

    private function map(Article $a, bool $full = false): array
    {
        $data = [
            'id'         => $a->id,
            'title'      => $a->title,
            'slug'       => $a->slug,
            'category'   => $a->category,
            'cover_url' => $a->cover_image ? asset('storage/' . $a->cover_image) : null,
            'published_at' => $a->published_at,
        ];

        if ($full) {
            $data['content'] = $a->content;
        }

        return $data;
    }
}
