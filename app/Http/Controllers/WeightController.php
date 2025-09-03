<?php

namespace App\Http\Controllers;

use App\Actions\GenerateWeightListAction;
use App\Actions\RefreshWithingsAction;
use App\Models\Achievement;
use App\Models\WeightEntry;
use App\Models\WeightGoal;
use App\Services\AchievementService;
use App\Services\NotesAppService;
use App\Services\WeightPredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class WeightController extends Controller
{
    public function __construct(
        private NotesAppService $notesAppService,
        private WeightPredictionService $predictionService,
        private AchievementService $achievementService
    ) {}

    public function index(GenerateWeightListAction $action)
    {
        $weightListWithIds = $action->execute(reverse: true, includeIds: true);

        // Get raw weight entries for chart data
        $chartData = WeightEntry::orderBy('date', 'asc')
            ->get()
            ->map(function ($entry) {
                return [
                    'date' => $entry->date->format('Y-m-d'),
                    'weight' => $entry->weight_kg,
                    'type' => 'entry',
                ];
            });

        // Get weight predictions
        $predictions = $this->predictionService->calculatePredictions();

        // Get active goals with progress
        $goals = WeightGoal::active()->orderBy('created_date', 'desc')->get()->map(function ($goal) {
            return [
                'id' => $goal->id,
                'target_weight' => $goal->target_weight,
                'target_date' => $goal->target_date?->format('j F Y'),
                'raw_target_date' => $goal->target_date?->format('Y-m-d'), // For form editing
                'goal_type' => $goal->goal_type,
                'description' => $goal->description,
                'starting_weight' => $goal->starting_weight,
                'progress' => round($goal->getCurrentProgress(), 1),
                'days_to_target' => $goal->getDaysToTarget(),
                'is_achieved' => $goal->isAchieved(),
            ];
        });

        // Get recent achievements
        $achievements = Achievement::recent(190)->orderBy('earned_date', 'desc')->get();

        // Get current streak and motivational message
        $currentStreak = $this->achievementService->getCurrentStreak();
        $motivationalMessage = $this->achievementService->getMotivationalMessage();

        return Inertia::render('WeightTracker', [
            'weightListWithIds' => $weightListWithIds,
            'chartData' => $chartData,
            'predictions' => $predictions,
            'goals' => $goals,
            'achievements' => $achievements,
            'currentStreak' => $currentStreak,
            'motivationalMessage' => $motivationalMessage,
            'weightFromWithings' => session()->get('weight'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'weight_kg' => 'required|numeric|min:30|max:300',
            'date' => 'required|date',
        ]);

        WeightEntry::create([
            'weight_kg' => $request->weight_kg,
            'date' => $request->date,
        ]);

        // Check for new achievements
        $this->achievementService->checkAndAwardAchievements();

        Artisan::call('notes:update-weight');

        return redirect()->back();
    }

    public function destroy($id)
    {
        $entry = WeightEntry::findOrFail($id);
        $entry->delete();

        Artisan::call('notes:update-weight');

        return redirect()->back();
    }

    public function sync()
    {
        $noteContent = $this->getNotesContent();
        $newEntries = $this->parseWeightEntries($noteContent);
        // dd($newEntries, $noteContent);

        $addedCount = 0;
        foreach ($newEntries as $entry) {
            // Use whereBetween for date comparison to handle datetime fields
            $exists = WeightEntry::whereBetween('date', [
                Carbon::parse($entry['date'])->startOfDay(),
                Carbon::parse($entry['date'])->endOfDay(),
            ])
                ->whereBetween('weight_kg', [
                    $entry['weight_kg'] - 0.001,
                    $entry['weight_kg'] + 0.001,
                ])
                ->exists();

            if (! $exists) {
                WeightEntry::create($entry);
                $addedCount++;
            }
        }

        Artisan::call('notes:update-weight');

        return redirect()->back()->with('message', "Synced {$addedCount} new entries from Notes.app");
    }

    private function getNotesContent()
    {
        $noteContent = $this->notesAppService->getNoteByName('weight');

        return $noteContent ?: '';
    }

    private function parseWeightEntries($content)
    {
        $entries = [];

        // Strip HTML tags and get plain text
        $plainText = strip_tags($content);
        $lines = explode("\n", $plainText);

        foreach ($lines as $line) {
            $line = trim($line);

            // Match pattern like "9 August - 112.00 = x" or "9 August - 112.00 - x" or "9 August - 112.00"
            if (preg_match('/^(\d{1,2})\s+(\w+)\s*-\s*([\d.]+)(?:\s*[-=]\s*[\d.]+)?/', $line, $matches)) {
                $day = intval($matches[1]);
                $monthName = $matches[2];
                $weight = floatval($matches[3]);

                // Convert month name to number
                $monthNumber = $this->getMonthNumber($monthName);
                if ($monthNumber === null) {
                    continue;
                }

                // Determine year (current year or previous year if future date)
                $currentYear = date('Y');
                $currentMonth = date('n');

                if ($monthNumber > $currentMonth) {
                    $year = $currentYear - 1;
                } else {
                    $year = $currentYear;
                }

                $date = sprintf('%04d-%02d-%02d', $year, $monthNumber, $day);

                // Validate weight range
                if ($weight >= 30 && $weight <= 300) {
                    $entries[] = [
                        'date' => $date,
                        'weight_kg' => $weight,
                    ];
                }
            }
        }

        return $entries;
    }

    private function getMonthNumber($monthName)
    {
        $months = [
            'january' => 1, 'jan' => 1,
            'february' => 2, 'feb' => 2,
            'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'may' => 5,
            'june' => 6, 'jun' => 6,
            'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9,
            'october' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'december' => 12, 'dec' => 12,
        ];

        return $months[strtolower($monthName)] ?? null;
    }

    public function storeGoal(Request $request)
    {
        $request->validate([
            'target_weight' => 'required|numeric|min:30|max:300',
            'target_date' => 'nullable|date|after:today',
            'goal_type' => 'required|in:lose,gain,maintain',
            'description' => 'nullable|string|max:500',
            'starting_weight' => 'nullable|numeric|min:30|max:300',
        ]);

        // Use provided starting weight or current weight as fallback
        $startingWeight = $request->starting_weight;
        if (! $startingWeight) {
            $currentWeight = WeightEntry::orderBy('date', 'desc')->first();
            $startingWeight = $currentWeight?->weight_kg;
        }

        WeightGoal::create([
            'target_weight' => $request->target_weight,
            'target_date' => $request->target_date,
            'goal_type' => $request->goal_type,
            'description' => $request->description,
            'starting_weight' => $startingWeight,
            'created_date' => today(),
        ]);

        return redirect()->back();
    }

    public function updateGoal(Request $request, $id)
    {
        $request->validate([
            'target_weight' => 'required|numeric|min:30|max:300',
            'target_date' => 'nullable|date',
            'goal_type' => 'required|in:lose,gain,maintain',
            'description' => 'nullable|string|max:500',
            'starting_weight' => 'nullable|numeric|min:30|max:300',
            'status' => 'sometimes|in:active,achieved,abandoned',
        ]);

        $goal = WeightGoal::findOrFail($id);
        $goal->update($request->only([
            'target_weight',
            'target_date',
            'goal_type',
            'description',
            'starting_weight',
            'status',
        ]));

        return redirect()->back();
    }

    public function destroyGoal($id)
    {
        $goal = WeightGoal::findOrFail($id);
        $goal->delete();

        return redirect()->back();
    }

    public function getFromWithings()
    {
        try {
            app(RefreshWithingsAction::class)->execute();
        } catch (\Exception|\Throwable|\Error $e) {

        }

        $accessToken = cache()->get('withings')['access_token'];

        $url = 'https://wbsapi.withings.net/measure';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])->post($url, [
            'action' => 'getmeas',
            'meastype' => 1,
            'category' => 1,
        ]);
        $resp = $response->json();

        $measuregrps = Arr::get(
            $resp,
            'body.measuregrps.0.measures.0',
            null
        );

        if (empty($measuregrps)) {
            session()->flash('message', 'No weight data found from Withings');

            return redirect()->back();
        }

        $weight = $measuregrps['value'];

        $resp = ['weight' => bcdiv($weight, 1000, 2)];

        return $resp;
    }
}
