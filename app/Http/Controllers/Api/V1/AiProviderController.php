<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AiProviderService;
use Illuminate\Http\Request;

class AiProviderController extends Controller
{
    //
    public function chat(Request $request, AiProviderService $aiProviderService)
    {
        // validate request
        $request->validate([
            'prompt' => 'required|string',
            'content' => 'required|string',
        ]);

        return response()->json($aiProviderService->chat($request->input('prompt'), $request->input('content')));
    }
}
