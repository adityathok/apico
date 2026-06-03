<?php

namespace App\Http\Controllers;

use App\Http\Resources\WebsiteResource;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class WebsiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return WebsiteResource::collection(
            Website::query()
                ->latest()
                ->paginate(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): WebsiteResource
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'unique:websites,domain'],
            'ip_address' => ['nullable', 'ip'],
            'license_key' => ['required', 'string', 'max:255', 'unique:websites,license_key'],
            'status' => ['sometimes', Rule::in(['active', 'invalid'])],
            'theme_version' => ['nullable', 'string', 'max:255'],
            'plugin_version' => ['nullable', 'string', 'max:255'],
            'wp_version' => ['nullable', 'string', 'max:255'],
            'php_version' => ['nullable', 'string', 'max:255'],
        ]);

        $website = Website::create($validated);

        return WebsiteResource::make($website);
    }

    /**
     * Display the specified resource.
     */
    public function show(Website $website): WebsiteResource
    {
        return WebsiteResource::make($website);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Website $website): WebsiteResource
    {
        $validated = $request->validate([
            'domain' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('websites', 'domain')->ignore($website)],
            'ip_address' => ['nullable', 'ip'],
            'license_key' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('websites', 'license_key')->ignore($website)],
            'status' => ['sometimes', Rule::in(['active', 'invalid'])],
            'theme_version' => ['nullable', 'string', 'max:255'],
            'plugin_version' => ['nullable', 'string', 'max:255'],
            'wp_version' => ['nullable', 'string', 'max:255'],
            'php_version' => ['nullable', 'string', 'max:255'],
        ]);

        $website->update($validated);

        return WebsiteResource::make($website);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Website $website): Response
    {
        $website->delete();

        return response()->noContent();
    }
}
