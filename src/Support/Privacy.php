<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Support;

final class Privacy
{
    public static function email(?string $email): ?string
    {
        if ($email === null || ! Config::boolean('privacy.mask_email_addresses')) {
            return $email;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, null);

        if ($local === '' || $domain === null || $domain === '') {
            return '***';
        }

        return mb_substr((string) $local, 0, 1).'***@'.$domain;
    }

    public static function subject(?string $subject): ?string
    {
        if ($subject === null || Config::boolean('privacy.show_subjects', true)) {
            return $subject;
        }

        return '[hidden]';
    }
}
