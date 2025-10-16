@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-5 py-8">
    <h1 class="text-2xl font-medium mb-4">ทดสอบโปรโมชัน</h1>

    <form id="promo-form" class="space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">เลือกโปรโมชัน (ใส่ id หรือ name)</label>
            <div class="grid grid-cols-2 gap-3">
                <input type="number" name="promotion_id" placeholder="เช่น 1" class="px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]" />
                <input type="text" name="promotion_name" placeholder="เช่น โปรโมชันรับเครดิตคืนเมื่อแพ้หมด" class="px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]" />
            </div>
            <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] mt-1">เลือกด้วย ID หรือชื่อโปรโมชัน</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">ยอดแทง (Stake)</label>
            <input type="number" step="0.01" min="0" name="stake" value="100" class="w-full px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]" />
        </div>

        

        <div>
            <label class="block text-sm font-medium mb-1">รายการคู่ (Selections - JSON) — ระบุ sport ในแต่ละคู่</label>
            <textarea name="selections" rows="10" class="w-full px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">[
  {"sport":"football","result":"lose","market":"handicap","period":"full_time","odds":1.9},
  {"sport":"football","result":"lose","market":"over_under","period":"full_time","odds":2.0},
  {"sport":"mpy","result":"lose","market":"handicap","period":"full_time","odds":1.88},
  {"sport":"mpy","result":"lose","market":"over_under","period":"full_time","odds":1.92},
  {"sport":"football","result":"lose","market":"handicap","period":"full_time","odds":2.05}
]</textarea>
        </div>

        <button type="submit" class="px-4 py-2 bg-[#1b1b18] text-white rounded-sm">คำนวณสิทธิ์โปรโมชัน</button>
    </form>

    <div id="result" class="mt-6 text-sm"></div>
</div>

<script>
document.getElementById('promo-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const payload = {
        stake: parseFloat(form.stake.value || '0'),
        selections: JSON.parse(form.selections.value || '[]'),
        promotion_id: form.promotion_id.value ? parseInt(form.promotion_id.value, 10) : undefined,
        promotion_name: (form.promotion_name.value || '').trim(),
    };
    const res = await fetch('/api/promotion/evaluate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    document.getElementById('result').innerText = JSON.stringify(data, null, 2);
});
</script>
@endsection


