<?php

namespace NomadicSoft\LaravelIndexNow\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class RetryAfter
{
    public static function seconds(?string $value, ?DateTimeInterface $now = null): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (ctype_digit($value)) {
            return self::clamp((int) $value);
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return self::clamp(max(0, $date->getTimestamp() - $now->getTimestamp()));
    }

    private static function clamp(int $seconds): int
    {
        return max(1, min($seconds, 86400));
    }
}
