<?php

declare(strict_types=1);

namespace SmartCircle\Application\Jobs;

use SmartCircle\Application\Mail\MailService;
use SmartCircle\Application\Students\DigestService;
use SmartCircle\Application\Students\SeatCheckService;
use SmartCircle\Data\Repositories\MailRepository;

/**
 * Scheduled work without cron: after a page has been sent, whichever job is due runs once
 * (one request wins the claim). The digest goes out on Mondays; seat checks once a day; email every few minutes.
 */
final class JobRunner
{
    public function __construct(
        private readonly MailRepository $jobs,
        private readonly SeatCheckService $seats,
        private readonly DigestService $digest,
        private readonly MailService $mail,
    ) {
    }

    public function runDue(?int $now = null): void
    {
        $now ??= time();
        if (date('N', $now) === '1' && (int) date('G', $now) >= 6 && $this->jobs->claimJob('digest')) {
            $r = $this->digest->send();
            $this->jobs->jobResult('digest', 'Sent to ' . $r['students'] . ' students and the tutor');
        }
        if ($this->jobs->claimJob('seat_checks')) {
            $n = $this->seats->autoNudge($now);
            $this->jobs->jobResult('seat_checks', 'Nudged ' . $n . ' quiet student' . ($n === 1 ? '' : 's'));
        }
        $this->mail->runIfDue();
    }

    /** @return array<string, mixed>|null */
    public function job(string $name): ?array
    {
        return $this->jobs->job($name);
    }

    /** "Send the digest now" from the admin page. */
    public function digestNow(): int
    {
        $r = $this->digest->send();
        $this->jobs->touchJob('digest');
        $this->jobs->jobResult('digest', 'Sent to ' . $r['students'] . ' students and the tutor (by hand)');

        return $r['students'];
    }
}
