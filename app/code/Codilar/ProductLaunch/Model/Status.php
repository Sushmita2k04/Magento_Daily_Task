<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Model;

/**
 * Calculated launch status. Never persisted: always derived from start/end date-times.
 */
class Status
{
    public const UPCOMING = 'upcoming';
    public const LIVE = 'live';
    public const COMPLETED = 'completed';

    /**
     * @param string|null $startAtUtc  Y-m-d H:i:s in UTC
     * @param string|null $endAtUtc    Y-m-d H:i:s in UTC, optional
     * @param \DateTimeInterface|null $now defaults to current time
     * @throws \InvalidArgumentException on malformed dates
     */
    public function calculate(?string $startAtUtc, ?string $endAtUtc, ?\DateTimeInterface $now = null): string
    {
        $nowTs = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp();
        $start = $this->toTimestamp($startAtUtc);
        if ($start === null) {
            throw new \InvalidArgumentException('Launch start date is missing or malformed.');
        }
        if ($nowTs < $start) {
            return self::UPCOMING;
        }
        $end = $this->toTimestamp($endAtUtc);
        if ($end !== null && $nowTs >= $end) {
            return self::COMPLETED;
        }
        return self::LIVE;
    }

    public function toTimestamp(?string $utcDateTime): ?int
    {
        if ($utcDateTime === null || trim($utcDateTime) === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', trim($utcDateTime), new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }
        return $date->getTimestamp();
    }

    /**
     * @return array<string,\Magento\Framework\Phrase>
     */
    public function getLabels(): array
    {
        return [
            self::UPCOMING => __('Upcoming'),
            self::LIVE => __('Live'),
            self::COMPLETED => __('Completed'),
        ];
    }
}
