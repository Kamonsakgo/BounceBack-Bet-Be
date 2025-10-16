<?php

namespace Tests\Unit;

use App\Services\PromotionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
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

    protected function tearDown(): void
    {
        // keep table for potential other tests, or drop if desired
        parent::tearDown();
    }

    public function test_uses_defaults_when_no_db_rows(): void
    {
        $service = new PromotionService();
        $result = $service->evaluate([
            'stake' => 100,
            'sport' => 'football',
            'selections' => array_fill(0, 5, [
                'result' => 'lose',
                'market' => 'handicap',
                'period' => 'full_time',
                'odds' => 1.9,
            ]),
        ]);

        $this->assertTrue($result['eligible']);
        $this->assertSame(5, $result['selectionsCount']);
        $this->assertSame(2.0, $result['multiplier']);
    }

    public function test_reads_min_selections_and_multipliers_from_db(): void
    {
        DB::table('PromotionSetting')->insert([
            ['key' => 'min_selections', 'value' => '6', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'multiplier_6', 'value' => '5', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = new PromotionService();

        // 5 selections should fail min_selections=6
        $fail = $service->evaluate([
            'stake' => 100,
            'sport' => 'football',
            'selections' => array_fill(0, 5, [
                'result' => 'lose',
                'market' => 'handicap',
                'period' => 'full_time',
                'odds' => 1.9,
            ]),
        ]);
        $this->assertFalse($fail['eligible']);

        // 6 selections should pass and use multiplier_6=5
        $ok = $service->evaluate([
            'stake' => 100,
            'sport' => 'football',
            'selections' => array_fill(0, 6, [
                'result' => 'lose',
                'market' => 'handicap',
                'period' => 'full_time',
                'odds' => 1.9,
            ]),
        ]);
        $this->assertTrue($ok['eligible']);
        $this->assertSame(6, $ok['selectionsCount']);
        $this->assertSame(5.0, $ok['multiplier']);
        $this->assertSame(500.0, $ok['computedRefund']);
    }
}


