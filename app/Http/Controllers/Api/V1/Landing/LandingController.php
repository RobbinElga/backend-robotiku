<?php

namespace App\Http\Controllers\Api\V1\Landing;

use App\Http\Controllers\Controller;
use App\Models\LandingContent;
use App\Support\ImageStorage;
use App\Support\LandingSchema;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LandingController extends Controller
{
    use ApiResponse;

    /** Publik: semua section sebagai map { section: content }. */
    public function index(): JsonResponse
    {
        $all = LandingContent::pluck('content', 'section');

        return $this->success($all, 'Konten landing page.');
    }

    /** Publik: satu section. */
    public function show(string $section): JsonResponse
    {
        if (! in_array($section, LandingSchema::sections(), true)) {
            return $this->error('Section tidak dikenal.', 404);
        }

        $content = LandingContent::where('section', $section)->value('content') ?? new \stdClass();

        return $this->success(['section' => $section, 'content' => $content], 'Konten section.');
    }

    /** Super Admin / Admin: update satu section. */
    public function update(Request $request, string $section): JsonResponse
    {
        $rules = LandingSchema::rulesFor($section);
        if ($rules === null) {
            return $this->error('Section tidak dikenal.', 404);
        }

        // prefix 'content.' agar memvalidasi payload { content: {...} }
        $prefixed = collect($rules)->mapWithKeys(fn($r, $k) => ['content.' . $k => $r])->all();
        $prefixed['content'] = ['required', 'array'];
        $request->validate($prefixed);

        $lc = LandingContent::updateOrCreate(
            ['section' => $section],
            ['content' => $request->input('content'), 'updated_by' => $request->user()->id]
        );

        return $this->success(['section' => $lc->section, 'content' => $lc->content], 'Section diperbarui.');
    }

    /** Super Admin / Admin: upload gambar → balikin URL publik. */
    public function upload(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120']]);

        $path = ImageStorage::storeWebp($request->file('image'), 'landing', 'public');

        return $this->success(['url' => Storage::disk('public')->url($path), 'path' => $path], 'Gambar terunggah.');
    }
}
