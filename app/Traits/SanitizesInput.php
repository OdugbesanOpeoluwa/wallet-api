<?php

namespace App\Traits;

trait SanitizesInput
{
    protected function sanitize(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            }
            return $value;
        }, $data);
    }
}