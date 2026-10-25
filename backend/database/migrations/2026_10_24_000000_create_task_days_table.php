<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_day_id')->nullable()->after('user_id')->constrained('task_days')->cascadeOnDelete();
        });

        DB::table('tasks')->orderBy('id')->chunkById(100, function ($tasks): void {
            foreach ($tasks as $task) {
                $date = $task->created_at
                    ? substr($task->created_at, 0, 10)
                    : now()->toDateString();
                $now = now();

                DB::table('task_days')->insertOrIgnore([
                    'user_id' => $task->user_id,
                    'date' => $date,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $dayId = DB::table('task_days')
                    ->where('user_id', $task->user_id)
                    ->where('date', $date)
                    ->value('id');

                DB::table('tasks')->where('id', $task->id)->update(['task_day_id' => $dayId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_day_id');
        });

        Schema::dropIfExists('task_days');
    }
};
