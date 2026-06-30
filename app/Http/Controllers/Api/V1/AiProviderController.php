<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AiProviderService;

class AiProviderController extends Controller
{
    //
    public function chat(Request $request, AiProviderService $aiProviderService)
    {
        //validate request
        $request->validate([
            'prompt' => 'required|string',
            'content' => 'required|string',
        ]);

        return $aiProviderService->chat($request->input('prompt'), $request->input('content'));
    }
}
