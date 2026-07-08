<?php

namespace App\Http\Requests\API\Interaction;

class UpdateListeningSessionRequest extends Request
{
    public function rules(): array
    {
        return [
            'listened_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ];
    }

    public function messages(): array
    {
        return [
            'listened_seconds.required' => 'The listened time is required.',
            'listened_seconds.integer' => 'The listened time must be a whole number of seconds.',
            'listened_seconds.min' => 'The listened time cannot be negative.',
            'listened_seconds.max' => 'The listened time cannot exceed 24 hours.',
        ];
    }
}
