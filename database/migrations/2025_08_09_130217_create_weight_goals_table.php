<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weight_goals', function (Blueprint $table) {
            $table->id();
            $table->decimal('target_weight', 5, 2);
            $table->date('target_date')->nullable();
            $table->enum('goal_type', ['lose', 'gain', 'maintain'])->default('lose');
            $table->enum('status', ['active', 'achieved', 'abandoned'])->default('active');
            $table->text('description')->nullable();
            $table->decimal('starting_weight', 5, 2)->nullable();
            $table->date('created_date');
            $table->timestamps();

            $table->index(['status', 'goal_type']);
            $table->index('target_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_goals');
    }
};
