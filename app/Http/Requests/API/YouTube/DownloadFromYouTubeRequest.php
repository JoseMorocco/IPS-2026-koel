<?php

namespace App\Http\Requests\API\YouTube;

use App\Http\Requests\Request;

class DownloadFromYouTubeRequest extends Request
{
    /** @inheritdoc */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                // Only allow standard YouTube watch URLs and short URLs
                'regex:/^https?:\/\/(www\.)?(youtube\.com\/watch\?.*v=|youtu\.be\/)[a-zA-Z0-9_\-]+/',
                // Reject playlist URLs to prevent bulk downloading
                'not_regex:/[?&]list=/',
            ],
        ];
    }

    /** @inheritdoc */
    public function messages(): array
    {
        return [
            'url.required' => 'A YouTube URL is required.',
            'url.url' => 'The provided value is not a valid URL.',
            'url.regex' => 'Only individual YouTube video URLs are supported (e.g. https://www.youtube.com/watch?v=...).',
            'url.not_regex' => 'Playlist URLs are not supported. Please provide a single video URL.',
        ];
    }
}
