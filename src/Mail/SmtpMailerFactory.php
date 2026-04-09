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
        $user = rawurlencode($username);
        $pass = rawurlencode($password);
        $enc = strtolower(trim($encryption));

        // Port 465 sans MAILER_ENCRYPTION explicite → souvent SMTPS
        if ($enc === '' && $port === 465) {
            $enc = 'ssl';
        }

        // Port 465 + SSL = TLS implicite (SMTPS). Avec Hostinger / beaucoup de SMTP,
        // "smtp://...?encryption=ssl" échoue ; il faut le schéma smtps:// (comme PHPMailer ENCRYPTION_SMTPS).
        if ($enc === 'ssl' || $enc === 'smtps') {
            return sprintf('smtps://%s:%s@%s:%d?timeout=30', $user, $pass, $host, $port);
        }

        // STARTTLS (souvent port 587)
        return sprintf('smtp://%s:%s@%s:%d?encryption=tls&timeout=30', $user, $pass, $host, $port);
    }
}
