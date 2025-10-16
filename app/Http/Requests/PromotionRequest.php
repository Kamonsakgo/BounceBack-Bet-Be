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
            'stake' => ['required', 'numeric', 'min:100'],
            'sport' => ['nullable', 'string'],
            'selections' => ['required', 'array', 'min:5'],
            'selections.*.result' => ['required', 'string', 'in:win,lose,void,cancelled,canceled'],
            'selections.*.market' => ['required', 'string', 'in:handicap,over_under'],
            'selections.*.period' => ['required', 'string', 'in:full_time'],
            'selections.*.odds' => ['required', 'numeric', 'min:1.0'],
        ];
    }
}


