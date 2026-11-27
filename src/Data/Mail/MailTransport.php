<?php

declare(strict_types=1);

namespace SmartCircle\Data\Mail;

/** Sends one email now. Throws MailException when the mail server refuses or cannot be reached. */
interface MailTransport
{
    public function send(string $toEmail, string $toName, string $subject, string $text, string $html): void;
}
