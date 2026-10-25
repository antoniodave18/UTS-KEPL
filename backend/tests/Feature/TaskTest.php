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

    public function test_user_can_create_days_and_keep_tasks_separate_by_day(): void
    {
        $user = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret123']);
        Sanctum::actingAs($user);

        $firstDay = $this->postJson('/api/days', ['date' => '2026-10-24'])
            ->assertCreated()
            ->assertJsonPath('date', '2026-10-24')
            ->json();
        $secondDay = $this->postJson('/api/days', ['date' => '2026-10-25'])
            ->assertCreated()
            ->json();

        $this->postJson('/api/tasks', [
            'title' => 'Belajar hari ini',
            'task_day_id' => $firstDay['id'],
        ])->assertCreated();
        $this->postJson('/api/tasks', [
            'title' => 'Belajar besok',
            'task_day_id' => $secondDay['id'],
        ])->assertCreated();

        $this->getJson("/api/tasks?day_id={$firstDay['id']}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Belajar hari ini']);
        $this->getJson('/api/days')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_user_cannot_assign_task_to_another_users_day(): void
    {
        $user = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret123']);
        $other = User::create(['name' => 'B', 'email' => 'b@test.com', 'password' => 'secret123']);
        $otherDay = $other->taskDays()->create(['date' => '2026-10-24']);
        Sanctum::actingAs($user);

        $this->postJson('/api/tasks', [
            'title' => 'Tidak boleh',
            'task_day_id' => $otherDay->id,
        ])->assertUnprocessable();
    }
}