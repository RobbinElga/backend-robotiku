<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Models\Article;
use App\Support\ImageStorage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $articles = Article::query()
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($articles, 'Daftar artikel.');
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);
        $data['created_by'] = $request->user()->id;
        $data['published_at'] = $data['status'] === 'publish' ? now() : null;

        if ($request->hasFile('cover')) {
            $data['cover_image'] = ImageStorage::storeWebp($request->file('cover'), 'articles', 'public');
        }
        unset($data['cover']);

        $article = Article::create($data);

        return $this->success($article, 'Artikel dibuat.', 201);
    }

    public function show(Article $article): JsonResponse
    {
        return $this->success($article, 'Detail artikel.');
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $article->id);
        }

        // set published_at saat berpindah ke publish pertama kali
        if (($data['status'] ?? null) === 'publish' && ! $article->published_at) {
            $data['published_at'] = now();
        }

        if ($request->hasFile('cover')) {
            $data['cover_image'] = ImageStorage::storeWebp($request->file('cover'), 'articles', 'public');
        }
        unset($data['cover']);

        $article->update($data);

        return $this->success($article->fresh(), 'Artikel diperbarui.');
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return $this->success(null, 'Artikel dihapus.');
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        $original = $slug;
        $i = 1;

        while (Article::where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $original . '-' . $i++;
        }

        return $slug;
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        $path = ImageStorage::storeWebp($request->file('image'), 'articles'); // folder publik

        return $this->success(['path' => $path, 'url' => '/api/v1/public-media/' . $path], 'Gambar terunggah.');
    }
}
