<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoutePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'return_to_start' => ['sometimes', 'boolean'],
            'avoid_tolls' => ['sometimes', 'boolean'],
            'start' => ['required', 'array'],
            'start.address' => ['required', 'string', 'max:255'],
            'start.postal_code' => ['nullable', 'string', 'max:10'],
            'start.city' => ['nullable', 'string', 'max:100'],
            'start.region' => ['nullable', 'string', 'max:100'],
            'stops' => ['required', 'array', 'min:2', 'max:25'],
            'stops.*.address' => ['required', 'string', 'max:255'],
            'stops.*.postal_code' => ['nullable', 'string', 'max:10'],
            'stops.*.city' => ['nullable', 'string', 'max:100'],
            'stops.*.region' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'start.address.required' => 'Η διεύθυνση εκκίνησης είναι υποχρεωτική.',
            'stops.required' => 'Προσθέστε τουλάχιστον δύο στάσεις.',
            'stops.min' => 'Προσθέστε τουλάχιστον δύο στάσεις για τη βελτιστοποίηση της διαδρομής.',
            'stops.max' => 'Μπορείτε να προσθέσετε έως 25 στάσεις.',
            'stops.*.address.required' => 'Η διεύθυνση κάθε στάσης είναι υποχρεωτική.',
        ];
    }
}
