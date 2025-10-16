<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PromotionEvaluateEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('PromotionSetting')) {
            Schema::create('PromotionSetting', function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->string('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_evaluate_returns_expected_json(): void
    {
        $payload = [
            'stake' => 100,
            'sport' => 'football',
            'selections' => array_fill(0, 5, [
                'result' => 'lose',
                'market' => 'handicap',
                'period' => 'full_time',
                'odds' => 1.9,
            ]),
        ];

        $response = $this->postJson('/api/promotion/evaluate', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'eligible',
                'reasons',
                'selectionsCount',
                'multiplier',
                'stake',
                'computedRefund',
                'cappedRefund',
            ])
            ->assertJson([
                'eligible' => true,
                'selectionsCount' => 5,
            ]);
    }
}
