<?php

namespace App\Support;

use Illuminate\Support\Str;

class SummaryMarkdown
{
    public static function render(?string $text): ?string
    {
        return $text === null ? null : (string) Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
