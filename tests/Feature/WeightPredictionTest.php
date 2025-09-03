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

    public function test_prediction_with_insufficient_data(): void
    {
        $service = app(WeightPredictionService::class);
        $predictions = $service->calculatePredictions();

        $this->assertFalse($predictions['hasEnoughData']);
        $this->assertNull($predictions['nextMonthPrediction']);
        $this->assertNull($predictions['goalDate']);
        $this->assertNull($predictions['dailyWeightLoss']);
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
}
