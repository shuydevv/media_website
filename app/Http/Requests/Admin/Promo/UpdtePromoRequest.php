<?php

namespace App\Http\Requests\Admin\Promo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $promoId = $this->route('promo')->id; // Route Model Binding

        return [
            'code'              => [
                'nullable','string','max:64',
                Rule::unique('promo_codes','code')->ignore($promoId),
            ],
            'course_id'         => ['nullable','integer','exists:courses,id'],

            'kind'              => ['required','in:access,discount'],

            // ACCESS
            'duration_days'     => ['required_if:kind,access','nullable','integer','min:1','max:365'],

            // DISCOUNT
            'discount_mode'         => ['required_if:kind,discount','nullable','in:percent,amount,fixed_price,free'],
            'discount_percent'      => ['required_if:discount_mode,percent','nullable','integer','min:1','max:100'],
            'discount_value_cents'  => ['required_if:discount_mode,amount,fixed_price','nullable','integer','min:0'],
            'currency'              => ['required_if:discount_mode,amount,fixed_price','nullable','string','size:3'],

            'starts_at'         => ['nullable','date'],
            'ends_at'           => ['nullable','date','after_or_equal:starts_at'],
            'max_uses'          => ['nullable','integer','min:1'],
            'is_active'         => ['sometimes','boolean'],
        ];
    }
}
