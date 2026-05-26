<?php

namespace Tests\Feature;

use App\Models\WeightEntry;
use App\Services\WeightPredictionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightPredictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-26'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_prediction_with_insufficient_data(): void
    {
        $service = app(WeightPredictionService::class);
        $predictions = $service->calculatePredictions();

        $this->assertFalse($predictions['hasEnoughData']);
        $this->assertNull($predictions['nextMonthPrediction']);
        $this->assertNull($predictions['goalDate']);
        $this->assertNull($predictions['dailyWeightLoss']);
        $this->assertNull($predictions['allTimeDailyWeightLoss']);
        $this->assertEquals(0, $predictions['confidence']);
    }

    public function test_prediction_with_weight_loss_trend(): void
    {
        // Create sample weight entries showing weight loss
        WeightEntry::create(['date' => Carbon::now()->subDays(30), 'weight_kg' => 120.0]);
        WeightEntry::create(['date' => Carbon::now()->subDays(20), 'weight_kg' => 118.0]);
        WeightEntry::create(['date' => Carbon::now()->subDays(10), 'weight_kg' => 116.0]);
        WeightEntry::create(['date' => Carbon::now(), 'weight_kg' => 114.0]);

        $service = app(WeightPredictionService::class);
        $predictions = $service->calculatePredictions();

        $this->assertTrue($predictions['hasEnoughData']);
        $this->assertNotNull($predictions['nextMonthPrediction']);
        $this->assertEquals('losing', $predictions['trend']);
        $this->assertGreaterThan(0, $predictions['dailyWeightLoss']);
        $this->assertGreaterThan(0, $predictions['confidence']);

        // Should predict lower weight next month
        $this->assertLessThan(114.0, $predictions['nextMonthPrediction']);

        // Should have a goal date since weight is above 100kg and trending down
        $this->assertNotNull($predictions['goalDate']);
    }

    public function test_prediction_with_weight_gain_trend(): void
    {
        // Create sample weight entries showing weight gain
        WeightEntry::create(['date' => Carbon::now()->subDays(30), 'weight_kg' => 110.0]);
        WeightEntry::create(['date' => Carbon::now()->subDays(20), 'weight_kg' => 112.0]);
        WeightEntry::create(['date' => Carbon::now()->subDays(10), 'weight_kg' => 114.0]);
        WeightEntry::create(['date' => Carbon::now(), 'weight_kg' => 116.0]);

        $service = app(WeightPredictionService::class);
        $predictions = $service->calculatePredictions();

        $this->assertTrue($predictions['hasEnoughData']);
        $this->assertNotNull($predictions['nextMonthPrediction']);
        $this->assertEquals('gaining', $predictions['trend']);
        $this->assertGreaterThan(0, $predictions['dailyWeightLoss']); // absolute value
        $this->assertGreaterThan(0, $predictions['confidence']);

        // Should predict higher weight next month
        $this->assertGreaterThan(116.0, $predictions['nextMonthPrediction']);

        // Should not have a goal date since weight is trending up
        $this->assertNull($predictions['goalDate']);
    }

    public function test_short_term_prediction_uses_recent_trend_instead_of_stale_historical_loss(): void
    {
        WeightEntry::create(['date' => Carbon::now()->subDays(90), 'weight_kg' => 140.0]);
        WeightEntry::create(['date' => Carbon::now()->subDays(60), 'weight_kg' => 110.0]);

        WeightEntry::create(['date' => Carbon::now()->subDays(30), 'weight_kg' => 80.0]);
        WeightEntry::create(['date' => Carbon::now()->subDays(24), 'weight_kg' => 79.7]);
        WeightEntry::create(['date' => Carbon::now()->subDays(18), 'weight_kg' => 79.4]);
        WeightEntry::create(['date' => Carbon::now()->subDays(12), 'weight_kg' => 79.1]);
        WeightEntry::create(['date' => Carbon::now()->subDays(6), 'weight_kg' => 78.8]);
        WeightEntry::create(['date' => Carbon::now(), 'weight_kg' => 78.5]);

        $service = app(WeightPredictionService::class);
        $predictions = $service->calculatePredictions();

        $this->assertTrue($predictions['hasEnoughData']);
        $this->assertEquals('1 June 2026', $predictions['nextMonthDate']);
        $this->assertEquals(-0.05, $predictions['dailyWeightChange']);
        $this->assertEquals(0.05, $predictions['dailyWeightLoss']);
        $this->assertEquals(-0.6833, $predictions['allTimeDailyWeightChange']);
        $this->assertEquals(0.683, $predictions['allTimeDailyWeightLoss']);
        $this->assertEquals(78.2, $predictions['nextMonthPrediction']);
    }

    public function test_prediction_averages_multiple_entries_on_the_same_date(): void
    {
        WeightEntry::create(['date' => Carbon::now()->subDays(30), 'weight_kg' => 80.0]);
        WeightEntry::create(['date' => Carbon::now()->subDays(24), 'weight_kg' => 79.7]);
        WeightEntry::create(['date' => Carbon::now()->subDays(18), 'weight_kg' => 79.4]);
        WeightEntry::create(['date' => Carbon::now()->subDays(12), 'weight_kg' => 79.1]);
        WeightEntry::create(['date' => Carbon::now()->subDays(6), 'weight_kg' => 78.8]);
        WeightEntry::create(['date' => Carbon::now(), 'weight_kg' => 78.6]);
        WeightEntry::create(['date' => Carbon::now(), 'weight_kg' => 78.4]);

        $service = app(WeightPredictionService::class);
        $predictions = $service->calculatePredictions();

        $this->assertTrue($predictions['hasEnoughData']);
        $this->assertEquals(78.5, $predictions['latestEntryWeight']);
        $this->assertEquals(-0.05, $predictions['allTimeDailyWeightChange']);
        $this->assertEquals(78.2, $predictions['nextMonthPrediction']);
        $this->assertEquals(7, $predictions['entryCount']);
    }
}
