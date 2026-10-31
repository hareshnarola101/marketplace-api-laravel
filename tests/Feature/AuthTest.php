<?php

use App\Models\User;

it('registers a customer and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonStructure(['token']);
});

it('cannot register as an admin by sending a role', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'role' => 'admin',
    ])->assertCreated()->assertJsonPath('data.role', 'customer');
});

it('logs in with valid credentials and the token works', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $token = $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'password'])
        ->assertOk()
        ->json('token');

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 'jane@example.com');
});

it('rejects bad credentials with the standard error shape', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['email']]]);
});

it('rate limits login attempts', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong'])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'password'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'too_many_requests')
        ->assertHeader('Retry-After');
});

it('returns a JSON 401 for unauthenticated requests', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'unauthenticated', 'message' => 'Unauthenticated.', 'details' => null]]);
});

it('revokes the token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});
