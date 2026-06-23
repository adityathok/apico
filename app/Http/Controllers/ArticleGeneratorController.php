<?php

namespace App\Http\Controllers;

use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleGeneratorController extends Controller
{
    public function generate(
        Request $request,
        AiProviderService $aiProviderService,
    ): JsonResponse {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        $article = $aiProviderService->article_generator($validated['topic']);

        return response()->json([
            'data' => $article,
        ]);
    }
}
