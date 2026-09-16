<?php

namespace App\Health;

use Carbon\CarbonImmutable;

class WithingsMeasurements
{
    public function __construct(private WithingsClient $client) {}

    public function forDay(string $date): array
    {
        $start = CarbonImmutable::parse($date, config('health.timezone'))->startOfDay();
        $end = $start->addDay();
        $groups = $this->client->collection('measure', [
            'action' => 'getmeas', 'category' => 1, 'startdate' => $start->timestamp, 'enddate' => $end->timestamp - 1,
        ], 'measuregrps');

        return array_values(array_filter($groups, fn ($g) => ($g['category'] ?? null) === 1 && isset($g['date']) && $g['date'] >= $start->timestamp && $g['date'] < $end->timestamp));
    }

    public function minimum(array $groups): ?array
    {
        $candidates = [];
        foreach ($groups as $group) {
            foreach ($group['measures'] ?? [] as $measure) {
                if (($measure['type'] ?? null) !== 1) {
                    continue;
                }
                $weight = self::value($measure);
                if ($weight !== null && $weight > 0) {
                    $candidates[] = ['weight' => $weight, 'group' => $group];
                }
            }
        }
        usort($candidates, fn ($a, $b) => ($a['weight'] <=> $b['weight']) ?: ($b['group']['date'] <=> $a['group']['date']));

        return $candidates[0] ?? null;
    }

    public static function value(array $measure): ?float
    {
        if (! is_numeric($measure['value'] ?? null) || ! is_numeric($measure['unit'] ?? null)) {
            return null;
        }
        $value = $measure['value'] * (10 ** $measure['unit']);

        return is_finite($value) ? (float) $value : null;
    }
}
