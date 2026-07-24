<?php

use App\Models\Location;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function routerHeaders(Router $router): array
{
    return ['X-Router-ID' => $router->router_id, 'X-Router-Token' => $router->api_token];
}

function testRouter(): Router
{
    $suffix = (string) random_int(100000, 999999);
    $admin = User::factory()->create(['role' => 'admin']);
    $location = Location::create(['admin_id' => $admin->id, 'name' => "Test Location {$suffix}", 'slug' => "test-location-{$suffix}"]);

    return Router::create(['location_id' => $location->id, 'router_id' => "RTR-TEST-{$suffix}", 'api_token' => "test-router-secret-{$suffix}", 'name' => 'Test router']);
}

it('rejects a router request without valid credentials', function () {
    $this->getJson('/api/router/commands')->assertUnauthorized()->assertJson(['error' => 'Unauthorized router']);
});

it('accepts a heartbeat and only returns commands for the authenticated router', function () {
    $router = testRouter();
    $otherRouter = testRouter();
    $ownCommand = RouterCommand::create(['router_id' => $router->id, 'command_type' => 'CREATE_USER', 'payload' => ['username' => 'OY1']]);
    RouterCommand::create(['router_id' => $otherRouter->id, 'command_type' => 'CREATE_USER', 'payload' => ['username' => 'OY2']]);

    $this->postJson('/api/router/heartbeat', ['model' => 'hAP ax2'], routerHeaders($router))->assertOk()->assertJsonPath('status', 'success');
    expect($router->fresh()->status)->toBe('online')->and($router->fresh()->model)->toBe('hAP ax2');

    $this->getJson('/api/router/commands', routerHeaders($router))->assertOk()
        ->assertJsonPath('router_id', $router->router_id)
        ->assertJsonCount(1, 'commands')
        ->assertJsonPath('commands.0.id', $ownCommand->id);
});

it('only allows a router to acknowledge its own pending command with a valid status', function () {
    $router = testRouter();
    $command = RouterCommand::create(['router_id' => $router->id, 'command_type' => 'CREATE_USER', 'payload' => ['username' => 'OY1']]);

    $this->postJson("/api/router/commands/{$command->id}/ack", ['status' => 'completed'], routerHeaders($router))->assertOk();
    expect($command->fresh()->status)->toBe('completed')->and($command->fresh()->executed_at)->not->toBeNull();
    $this->postJson("/api/router/commands/{$command->id}/ack", ['status' => 'completed'], routerHeaders($router))->assertStatus(409);
});
