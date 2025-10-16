@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-5 py-8">
    <h1 class="text-2xl font-medium mb-4 text-yellow-600">สร้างโปรโมชัน</h1>

    @if (session('status'))
        <div class="mb-4 px-4 py-2 bg-yellow-100 text-yellow-900 rounded-sm border border-yellow-300">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('promotions.store') }}" class="space-y-4">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium mb-1">ชื่อโปรโมชัน</label>
                <input name="name" required class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="โปรโมชันรับเครดิตคืนเมื่อแพ้หมด" />
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div></div>
            <div class="flex items-center gap-3 mt-6">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" /> เปิดใช้งาน
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_stackable" value="1" /> ซ้อนโปรได้
                </label>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">เวลาเริ่ม</label>
                <input type="datetime-local" name="starts_at" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">เวลาสิ้นสุด</label>
                <input type="datetime-local" name="ends_at" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">ลำดับความสำคัญ (เลขน้อยสำคัญกว่า)</label>
                <input type="number" name="priority" value="100" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">จำนวนสิทธิ์รวม (ทั้งระบบ)</label>
                <input type="number" name="global_quota" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">งบประมาณรวม (ทั้งระบบ)</label>
                <input type="number" step="0.01" name="global_budget" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">เพดานจ่ายต่อบิล</label>
                <input type="number" step="0.01" name="max_payout_per_bill" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">เพดานจ่ายต่อวัน</label>
                <input type="number" step="0.01" name="max_payout_per_day" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">เพดานจ่ายต่อผู้ใช้</label>
                <input type="number" step="0.01" name="max_payout_per_user" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
            </div>
        </div>

        <div class="mt-6 border border-yellow-300 dark:border-yellow-700 rounded-lg p-4 bg-[#fffbea] dark:bg-[#1D0002]">
            <h2 class="text-lg font-medium mb-3">ตารางวัน/เวลาเปิดโปรโมชัน (ไม่ระบุ = เปิดทุกวัน/ตลอดทั้งวัน)</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">เลือกวันในสัปดาห์</label>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="schedule_days[]" value="0" class="accent-yellow-600"> อาทิตย์</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="schedule_days[]" value="1" class="accent-yellow-600"> จันทร์</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="schedule_days[]" value="2" class="accent-yellow-600"> อังคาร</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="schedule_days[]" value="3" class="accent-yellow-600"> พุธ</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="schedule_days[]" value="4" class="accent-yellow-600"> พฤหัสบดี</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="schedule_days[]" value="5" class="accent-yellow-600"> ศุกร์</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="schedule_days[]" value="6" class="accent-yellow-600"> เสาร์</label>
                    </div>
                    <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mt-1">หากไม่เลือกวันเลย ระบบจะถือว่าเปิดทุกวัน</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">เวลาเริ่ม</label>
                        <input type="time" name="schedule_start_time" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">เวลาสิ้นสุด</label>
                        <input type="time" name="schedule_end_time" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500" />
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-[#706f6c] dark:text-[#A1A09A]">หากไม่ระบุเวลา ระบบจะถือว่าเปิดตลอดทั้งวัน</p>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">การตั้งค่า (JSON)</label>
            <textarea name="settings" rows="10" class="w-full px-3 py-2 border border-yellow-300 dark:border-yellow-700 rounded-sm focus:outline-none focus:ring-2 focus:ring-yellow-500">{
  "min_stake": 100,
  "min_odds": 1.85,
  "min_selections": 5,
  "allowed_markets": ["handicap","over_under","all"],
  "allowed_sports": ["football","mpy","all"],
  "required_period": "full_time",
  "multipliers": {"5":2, "6":5, "7":7, "8":10, "9":15, "10":30},
  "max_payout_per_day": 50000
}</textarea>
        </div>

        <button class="px-4 py-2 bg-black hover:bg-yellow-600 text-white rounded-sm border border-black hover:border-yellow-600 transition-colors">บันทึกโปรโมชัน</button>
    </form>
</div>
@endsection


