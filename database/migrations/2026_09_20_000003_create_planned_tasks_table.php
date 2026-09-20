<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('kind')->default('other'); // relocation | migration | onsite | project | other
            $table->string('status')->default('planned'); // planned | in_progress | blocked | done | cancelled
            $table->string('priority')->default('normal'); // low | normal | high | urgent
            $table->date('due_on');
            $table->string('location_from')->nullable();
            $table->string('location_to')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index(['due_on', 'status']);
            $table->index('kind');
            $table->index('priority');
            $table->index('is_demo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_tasks');
    }
};
