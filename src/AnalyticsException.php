<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use RuntimeException;

/** Only fixed, non-sensitive error codes may cross the provider boundary. */
final class AnalyticsException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Analytics request failed.');
    }

    public static function response(array $data, int $status): self
    {
        $error = $data['error'] ?? [];
        $reasons = is_array($error) ? array_merge(
            array_column($error['errors'] ?? [], 'reason'),
            array_column($error['details'] ?? [], 'reason'),
        ) : [$error];
        $reason = match (true) {
            count(array_intersect($reasons, ['accessNotConfigured', 'SERVICE_DISABLED'])) > 0 => 'apiDisabled',
            in_array('invalid_grant', $reasons, true), $status === 401 => 'reconnect',
            $status === 429, count(array_intersect($reasons, ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded', 'dailyLimitExceeded'])) > 0 => 'quota',
            $status === 403 => 'accessDenied',
            default => 'request',
        };
        return new self($reason);
    }
}
