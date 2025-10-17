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
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.result' => ['required', 'string', 'in:win,lose,void,cancelled,canceled,draw'],
            'selections.*.market' => ['nullable', 'string'],
            'selections.*.market_type' => ['nullable', 'string'],
            'selections.*.period' => ['nullable', 'string'],
            'selections.*.odds' => ['required', 'numeric', 'min:0.01'],
            'selections.*.sport' => ['required', 'string'],
            'selections.*.status' => ['nullable', 'string', 'in:accept,cancel'],
            'promotion_id' => ['nullable', 'integer'],
        ];
    }
}


