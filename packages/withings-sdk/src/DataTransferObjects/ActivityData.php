<?php

namespace Filipac\Withings\DataTransferObjects;

use Illuminate\Support\Collection;

readonly class ActivityData
{
    public function __construct(
        public int $status = 0,
        public array $activities = [],
        public ?int $offset = null,
        public bool $more = false,
        array $data = []
    ) {
        if (!empty($data)) {
            $this->status = $data['status'] ?? 0;
            $this->activities = $data['body']['activities'] ?? [];
            $this->offset = $data['body']['offset'] ?? null;
            $this->more = (bool) ($data['body']['more'] ?? false);
        }
    }

    /**
     * Get activities as a Collection
     */
    public function getActivities(): Collection
    {
        return collect($this->activities);
    }

    /**
     * Get activities for a specific date
     */
    public function getActivitiesForDate(string $date): Collection
    {
        return $this->getActivities()
            ->filter(fn($activity) => isset($activity['date']) && $activity['date'] === $date);
    }

    /**
     * Get total steps across all activities
     */
    public function getTotalSteps(): int
    {
        return $this->getActivities()->sum('steps') ?? 0;
    }

    /**
     * Get total distance across all activities
     */
    public function getTotalDistance(): float
    {
        return $this->getActivities()->sum('distance') ?? 0.0;
    }

    /**
     * Get total calories across all activities
     */
    public function getTotalCalories(): float
    {
        return $this->getActivities()->sum('calories') ?? 0.0;
    }

    /**
     * Get activities grouped by date
     */
    public function getActivitiesGroupedByDate(): Collection
    {
        return $this->getActivities()->groupBy('date');
    }

    /**
     * Get average daily steps
     */
    public function getAverageDailySteps(): float
    {
        $dailySteps = $this->getActivitiesGroupedByDate()
            ->map(fn($activities) => $activities->sum('steps'));
        
        return $dailySteps->avg() ?? 0.0;
    }
}