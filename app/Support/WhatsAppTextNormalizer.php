<?php

namespace App\Support;

final class WhatsAppTextNormalizer
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ', 'ة'], ['ا', 'ا', 'ا', 'ا', 'ه'], $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    public static function matches(int $replyType, ?string $triggers, string $message): bool
    {
        if ($replyType === 4) {
            return true;
        }

        $normalizedMessage = self::normalize($message);
        $keywords = array_values(array_filter(array_map(
            self::normalize(...),
            explode(',', (string) $triggers)
        )));

        return $replyType === 1
            ? in_array($normalizedMessage, $keywords, true)
            : ($replyType === 2 && collect($keywords)->contains(
                fn (string $keyword): bool => str_contains($normalizedMessage, $keyword)
            ));
    }
}
