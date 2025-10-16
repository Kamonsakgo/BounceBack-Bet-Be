<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stake' => ['required', 'numeric', 'min:1'],
            'sport' => ['nullable', 'string'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.result' => ['required', 'string', 'in:win,lose,void,cancelled,canceled'],
            // ปล่อยให้ค่าที่ส่งมาเป็นอะไรก็ได้แล้วไปตรวจเงื่อนไขใน Service เพื่อให้ตอบกลับเป็น JSON เสมอ
            'selections.*.market' => ['nullable', 'string'],
            'selections.*.market_type' => ['nullable', 'string'],
            'selections.*.period' => ['nullable', 'string'],
            'selections.*.odds' => ['required', 'numeric', 'min:1.0'],
            'selections.*.sport' => ['nullable', 'string'],
            'selections.*.status' => ['nullable', 'string', 'in:accept,cancel'],
        ];
    }
}


