<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ArticleGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleGeneratorController extends Controller
{
    public function generate(
        Request $request,
        ArticleGenerator $articleGenerator,
    ): JsonResponse {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        $article = $articleGenerator->prompt(
            'Buatkan artikel menarik tentang: '.$validated['topic'],
        );

        return response()->json([
            'data' => $article,
        ]);
    }
}
