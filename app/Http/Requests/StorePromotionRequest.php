<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            // type removed
            'is_active' => ['boolean'],
            'starts_at' => ['nullable','date'],
            'ends_at' => ['nullable','date','after_or_equal:starts_at'],
            'priority' => ['nullable','integer','min:0'],
            'is_stackable' => ['boolean'],
            'user_limit_total' => ['nullable','integer','min:0'],
            'user_limit_per_day' => ['nullable','integer','min:0'],
            'global_quota' => ['nullable','integer','min:0'],
            'global_budget' => ['nullable','numeric','min:0'],
            'max_payout_per_bill' => ['nullable','numeric','min:0'],
            'max_payout_per_day' => ['nullable','numeric','min:0'],
            'max_payout_per_user' => ['nullable','numeric','min:0'],
            // อนุญาตทั้งแบบ array (จาก JS) หรือ string JSON (จาก textarea)
            'settings' => ['required'],
            // schedule (optional)
            'schedule_days' => ['sometimes','array'],
            'schedule_days.*' => ['integer','between:0,6'],
            'schedule_start_time' => ['nullable','date_format:H:i'],
            'schedule_end_time' => ['nullable','date_format:H:i','after:schedule_start_time'],
        ];
    }
}


