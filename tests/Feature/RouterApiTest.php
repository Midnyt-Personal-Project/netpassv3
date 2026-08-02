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

    $response = $this->get('/api/router/commands', routerHeaders($router))->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->toContain('# OYALO SYNC')
        ->and($response->getContent())->toContain($ownCommand->script)
        ->and($response->getContent())->toContain("/api/router/commands/{$ownCommand->id}/ack")
        ->and($response->getContent())->not->toContain('OY2');
});

it('generates executable mikrotik scripts for all router command types', function () {
    $router = testRouter();
    $profileCommand = RouterCommand::create([
        'router_id' => $router->id,
        'command_type' => 'CREATE_PROFILE',
        'payload' => ['name' => 'oyalo-test', 'speed_down' => '2M', 'speed_up' => '1M', 'share_users' => 1, 'duration_minutes' => 60],
    ]);
    expect($profileCommand->script)->toContain('/ip hotspot user profile add \\')
        ->and($profileCommand->script)->toContain('name="oyalo-test" \\')
        ->and($profileCommand->script)->toContain('session-timeout=1h \\')
        ->and($profileCommand->script)->toContain('rate-limit="2M/1M"');

    $userCommand = RouterCommand::create([
        'router_id' => $router->id,
        'command_type' => 'CREATE_USER',
        'payload' => ['username' => 'OY-TEST', 'password' => 'OY-TEST', 'profile' => 'oyalo-test', 'duration_minutes' => 60],
    ]);
    expect($userCommand->script)->toContain('/ip hotspot user add \\')
        ->and($userCommand->script)->toContain('name="OY-TEST" \\')
        ->and($userCommand->script)->toContain('password="OY-TEST" \\')
        ->and($userCommand->script)->toContain('limit-uptime=1h');

    $disableCommand = RouterCommand::create([
        'router_id' => $router->id,
        'command_type' => 'DISABLE_USER',
        'payload' => ['username' => 'OY-TEST'],
    ]);
    expect($disableCommand->script)->toContain('/ip hotspot user disable [find where name="OY-TEST"]')
        ->and($disableCommand->script)->toContain('/ip hotspot active remove [find where user="OY-TEST"]');
});

it('only allows a router to acknowledge its own pending command with a valid status', function () {
    $router = testRouter();
    $otherRouter = testRouter();
    $command = RouterCommand::create(['router_id' => $router->id, 'command_type' => 'CREATE_USER', 'payload' => ['username' => 'OY1']]);

    $this->post("/api/router/commands/{$command->id}/ack", [], routerHeaders($otherRouter))->assertNotFound();
    $this->postJson("/api/router/commands/{$command->id}/ack", ['status' => 'not-valid'], routerHeaders($router))->assertUnprocessable();
    $this->post("/api/router/commands/{$command->id}/ack", [], routerHeaders($router))->assertOk();
    expect($command->fresh()->status)->toBe('completed')->and($command->fresh()->executed_at)->not->toBeNull();
    $this->post("/api/router/commands/{$command->id}/ack", [], routerHeaders($router))->assertStatus(409);
});
