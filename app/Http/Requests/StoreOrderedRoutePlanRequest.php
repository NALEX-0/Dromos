<?php

namespace App\Http\Requests;

class StoreOrderedRoutePlanRequest extends StoreRoutePlanRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'stops' => ['required', 'array', 'min:2', 'max:100'],
        ]);
    }

    public function messages(): array
    {
        return array_replace(parent::messages(), [
            'stops.max' => 'Μπορείτε να προσθέσετε έως 100 στάσεις στη σειριακή διαδρομή.',
        ]);
    }
}
