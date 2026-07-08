<?php

namespace App\Http\Requests\API\Interaction;

class MonthlyListeningStatisticsRequest extends Request
{
    public function rules(): array
    {
        return [
            'month' => ['sometimes', 'date_format:Y-m'],
        ];
    }

    public function messages(): array
    {
        return [
            'month.date_format' => 'The month must use the YYYY-MM format.',
        ];
    }
}
