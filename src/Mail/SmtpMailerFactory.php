<?php

namespace App\Mail;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

/**
 * Construit un Mailer SMTP à partir des variables MAILER_* (évite MAILER_DSN et caractères spéciaux dans le mot de passe).
 */
final class SmtpMailerFactory
{
    public static function create(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption,
    ): MailerInterface {
        return new Mailer(Transport::fromDsn(self::buildDsn($host, $port, $username, $password, $encryption)));
    }

    public static function buildDsn(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption,
    ): string {
        $enc = strtolower($encryption) === 'ssl' ? 'ssl' : 'tls';

        return sprintf(
            'smtp://%s:%s@%s:%d?encryption=%s',
            rawurlencode($username),
            rawurlencode($password),
            $host,
            $port,
            $enc
        );
    }
}
