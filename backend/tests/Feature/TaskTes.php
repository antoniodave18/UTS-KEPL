<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_tasks(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }

    public function test_user_can_create_and_list_own_tasks(): void
    {
        $user = User::create([
            'name' => 'Budi', 'email' => 'budi@test.com', 'password' => 'secret123',
        ]);
        $other = User::create([
            'name' => 'Ani', 'email' => 'ani@test.com', 'password' => 'secret123',
        ]);
        Task::create(['user_id' => $other->id, 'title' => 'Milik Ani']);

        Sanctum::actingAs($user);

        $this->postJson('/api/tasks', ['title' => 'Belajar Laravel'])
            ->assertStatus(201)
            ->assertJsonFragment(['title' => 'Belajar Laravel']);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1)               // task Ani tidak ikut
            ->assertJsonMissing(['title' => 'Milik Ani']);
    }

    public function test_user_cannot_access_other_users_task(): void
    {
        $user = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret123']);
        $other = User::create(['name' => 'B', 'email' => 'b@test.com', 'password' => 'secret123']);
        $task = Task::create(['user_id' => $other->id, 'title' => 'Rahasia']);

        Sanctum::actingAs($user);

        $this->getJson("/api/tasks/{$task->id}")->assertStatus(404);
    }
}