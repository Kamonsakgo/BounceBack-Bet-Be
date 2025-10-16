<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Http\Requests\StorePromotionRequest;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(private PromotionService $service)
    {
    }

    public function evaluate(PromotionRequest $request): JsonResponse
    {
        $result = $this->service->evaluate($request->validated());
        return response()->json($result);
    }

    public function create(): View
    {
        return view('promotions.create');
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['settings'] = json_encode($data['settings']);
        $data['is_active'] = (bool)($data['is_active'] ?? false);
        $data['is_stackable'] = (bool)($data['is_stackable'] ?? false);
        $data['priority'] = (int)($data['priority'] ?? 100);

        // แยก schedule fields ออกมาก่อน
        $scheduleDays = $data['schedule_days'] ?? null;
        $scheduleStart = $data['schedule_start_time'] ?? null;
        $scheduleEnd = $data['schedule_end_time'] ?? null;
        unset($data['schedule_days'], $data['schedule_start_time'], $data['schedule_end_time']);

        // บันทึกโปรโมชัน
        DB::table('promotions')->updateOrInsert(
            ['name' => $data['name']],
            array_merge($data, [
                'updated_at' => now(),
                'created_at' => now(),
            ])
        );

        // ดึง id ล่าสุดของโปรนี้
        $promotion = DB::table('promotions')->where('name', $data['name'])->first();

        // ถ้ามีระบุวัน/เวลา ให้บันทึกลง promotion_schedules
        if ($promotion && (is_array($scheduleDays) || $scheduleStart || $scheduleEnd)) {
            // ลบของเก่าของโปรนี้ก่อน เพื่อความง่ายในการแก้ไข
            DB::table('promotion_schedules')->where('promotion_id', $promotion->id)->delete();

            $days = is_array($scheduleDays) && count($scheduleDays) > 0
                ? $scheduleDays
                : [0,1,2,3,4,5,6]; // ถ้าไม่ระบุวัน = ทุกวัน

            $start = $scheduleStart ?: '00:00'; // ไม่ระบุเวลา = ตลอดวัน
            $end = $scheduleEnd ?: '23:59:59';

            foreach ($days as $d) {
                DB::table('promotion_schedules')->insert([
                    'promotion_id' => $promotion->id,
                    'day_of_week' => (int)$d,
                    'start_time' => $start,
                    'end_time' => $end,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->back()->with('status', 'บันทึกโปรโมชันสำเร็จ');
    }
}


