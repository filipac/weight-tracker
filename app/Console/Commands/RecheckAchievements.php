<?php

namespace App\Console\Commands;

use App\Services\AchievementService;
use Illuminate\Console\Command;

class RecheckAchievements extends Command
{
    protected $signature = 'achievements:recheck';

    protected $description = 'Recheck and award all achievements based on current weight data';

    public function handle(AchievementService $achievementService)
    {
        $this->info('Rechecking all achievements...');

        $currentStreak = $achievementService->getCurrentStreak();
        $this->info("Current streak: {$currentStreak} days");

        $achievementService->checkAndAwardAchievements();

        $this->info('✅ All achievements have been rechecked and awarded!');

        return Command::SUCCESS;
    }
}
