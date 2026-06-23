<?php

use App\Models\Server;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('authenticated users can visit the admin servers page', function () {
    $this->get(route('servers'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Servers'));
});

test('server controller lists stores updates and deletes servers', function () {
    Server::factory()->count(2)->create();

    $this->getJson('/ajax/servers')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $response = $this->postJson('/ajax/servers', [
        'server_ip' => '192.168.10.50',
        'server_domain' => 'edge-api.test',
        'server_name' => 'Edge API Server',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.server_ip', '192.168.10.50')
        ->assertJsonPath('data.server_domain', 'edge-api.test')
        ->assertJsonPath('data.server_name', 'Edge API Server');

    $server = Server::where('server_domain', 'edge-api.test')->firstOrFail();

    $this->patchJson("/ajax/servers/{$server->id}", [
        'server_ip' => '192.168.10.60',
        'server_name' => 'Edge API Server Updated',
    ])
        ->assertOk()
        ->assertJsonPath('data.server_ip', '192.168.10.60')
        ->assertJsonPath('data.server_name', 'Edge API Server Updated');

    $this->deleteJson("/ajax/servers/{$server->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('servers', ['id' => $server->id]);
});
