<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerResource;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return ServerResource::collection(
            Server::query()
                ->latest()
                ->paginate(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): ServerResource
    {
        $validated = $request->validate([
            'server_ip' => ['required', 'ip'],
            'server_domain' => ['required', 'string', 'max:255', 'unique:servers,server_domain'],
            'server_name' => ['required', 'string', 'max:255'],
        ]);

        $server = Server::create($validated);

        return ServerResource::make($server);
    }

    /**
     * Display the specified resource.
     */
    public function show(Server $server): ServerResource
    {
        return ServerResource::make($server);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Server $server): ServerResource
    {
        $validated = $request->validate([
            'server_ip' => ['sometimes', 'required', 'ip'],
            'server_domain' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('servers', 'server_domain')->ignore($server)],
            'server_name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $server->update($validated);

        return ServerResource::make($server);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Server $server): Response
    {
        $server->delete();

        return response()->noContent();
    }
}
