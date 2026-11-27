<?php

declare(strict_types=1);

namespace SmartCircle\Data\Mail;

/**
 * A small SMTP client (no extensions beyond OpenSSL): SSL on 465 or STARTTLS on 587, AUTH LOGIN,
 * and a UTF-8 text + HTML message. One connection per email keeps each step short.
 */
final class SmtpTransport implements MailTransport
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,   // ssl | tls | none
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly int $timeout = 12,
    ) {
    }

    public function send(string $toEmail, string $toName, string $subject, string $text, string $html): void
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $toEmail . $toName . $subject)) {
            throw new MailException('Bad address or subject.');
        }
        try {
            $this->connect();
            $this->expect(220);
            $helo = 'smartcircle.local';
            $this->command('EHLO ' . $helo, 250);
            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new MailException('Could not start TLS.');
                }
                $this->command('EHLO ' . $helo, 250);
            }
            if ($this->username !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($this->username), 334);
                $this->command(base64_encode($this->password), 235, true);
            }
            $this->command('MAIL FROM:<' . $this->fromEmail . '>', 250);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', 354);
            $this->write(self::message($this->fromEmail, $this->fromName, $toEmail, $toName, $subject, $text, $html) . "\r\n.");
            $this->expect(250);
            $this->command('QUIT', 221);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    /** The full message with headers, dot-stuffed and ready for DATA. Pure. */
    public static function message(string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $text, string $html, ?string $boundary = null, ?int $time = null): string
    {
        $boundary ??= 'sc-' . bin2hex(random_bytes(12));
        $time ??= time();
        $domain = substr((string) strrchr($fromEmail, '@'), 1) ?: 'smartcircle.local';
        $headers = [
            'Date: ' . date('r', $time),
            'From: ' . self::address($fromEmail, $fromName),
            'To: ' . self::address($toEmail, $toName),
            'Subject: ' . self::header($subject),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $part = static fn (string $type, string $body): string => '--' . $boundary . "\r\n"
            . 'Content-Type: ' . $type . '; charset=UTF-8' . "\r\n" . 'Content-Transfer-Encoding: base64' . "\r\n\r\n"
            . rtrim(chunk_split(base64_encode($body), 76, "\r\n")) . "\r\n";
        $body = implode("\r\n", $headers) . "\r\n\r\n" . $part('text/plain', $text) . $part('text/html', $html) . '--' . $boundary . '--';

        // Lines starting with a dot are doubled (SMTP transparency).
        return (string) preg_replace('/^\./m', '..', $body);
    }

    private static function address(string $email, string $name): string
    {
        return $name === '' ? '<' . $email . '>' : self::header($name) . ' <' . $email . '>';
    }

    private static function header(string $value): string
    {
        return preg_match('/^[\x20-\x7E]*$/', $value) ? $value : '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function connect(): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $this->host]]);
        $socket = @stream_socket_client($scheme . $this->host . ':' . $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new MailException('Could not reach the mail server (' . $this->host . ':' . $this->port . '): ' . $errstr);
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    /** @param int|list<int> $ok */
    private function command(string $line, int|array $ok, bool $secret = false): void
    {
        $this->write($line);
        $this->expect($ok, $secret ? 'AUTH' : strtok($line, ' :'));
    }

    private function write(string $data): void
    {
        if (fwrite($this->socket, $data . "\r\n") === false) {
            throw new MailException('The mail server closed the connection.');
        }
    }

    /** @param int|list<int> $ok */
    private function expect(int|array $ok, string $step = 'greeting'): void
    {
        $reply = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $ok, true)) {
            throw new MailException('Mail server refused ' . $step . ': ' . trim(mb_substr($reply, 0, 200)));
        }
    }
}
