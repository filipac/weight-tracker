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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'streak', 'milestone', 'goal_achieved', 'weight_loss'
            $table->string('title');
            $table->text('description');
            $table->json('criteria'); // Store criteria like {days: 30} for streak or {weight_lost: 5} for milestone
            $table->date('earned_date');
            $table->integer('value')->nullable(); // streak count, weight lost, etc.
            $table->timestamps();

            $table->index(['type', 'earned_date']);
            $table->index('earned_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
