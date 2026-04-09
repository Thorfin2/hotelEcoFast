<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminEmailTestController extends AbstractController
{
    #[Route('/test-email', name: 'admin_test_email')]
    public function testEmail(
        Request $request,
        #[Autowire(service: 'app.mailer')]
        MailerInterface $mailer,
        #[Autowire('%mailer.host%')] string $mailerHost,
        #[Autowire('%mailer.port%')] int $mailerPort,
        #[Autowire('%mailer.username%')] string $mailerUsername,
        #[Autowire('%mailer.password%')] string $mailerPassword,
        #[Autowire('%mailer.from_email%')] string $mailerFromEmail,
        #[Autowire('%mailer.from_name%')] string $mailerFromName,
        #[Autowire('%mailer.encryption%')] string $mailerEncryption,
        #[Autowire('%env(bool:MAILER_ENABLED)%')] bool $mailerEnabled,
    ): Response {
        $to = '';
        $sent = false;
        $error = '';
        $transportDebug = '';

        if ($request->isMethod('POST')) {
            $to = (string) $request->request->get('to', '');
            if ($to === '') {
                $error = 'Adresse destinataire requise.';
            } elseif (!$mailerEnabled) {
                $error = 'MAILER_ENABLED est désactivé.';
            } elseif ($mailerPassword === '') {
                $error = 'MAILER_PASSWORD est vide (local: .env.local, prod: variable Railway).';
            } else {
                try {
                    $email = (new Email())
                        ->from(new Address($mailerFromEmail, $mailerFromName))
                        ->to($to)
                        ->subject('Test EcoFast — Symfony Mailer')
                        ->html($this->buildTestHtml($mailerHost, $mailerPort, $mailerFromEmail, $to))
                        ->text('Si vous lisez ce message, Symfony Mailer fonctionne.');

                    $mailer->send($email);
                    $sent = true;
                } catch (TransportExceptionInterface $e) {
                    $error = $e->getMessage();
                    if (method_exists($e, 'getDebug')) {
                        $d = $e->getDebug();
                        $transportDebug = $d !== null ? (string) $d : '';
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('admin/test_email.html.twig', [
            'to' => $to,
            'sent' => $sent,
            'error' => $error,
            'transport_debug' => $transportDebug,
            'mailer_enabled' => $mailerEnabled,
            'mailer_host' => $mailerHost,
            'mailer_port' => $mailerPort,
            'mailer_username' => $mailerUsername,
            'mailer_password_configured' => $mailerPassword !== '',
            'mailer_encryption' => $mailerEncryption,
            'mailer_from_email' => $mailerFromEmail,
        ]);
    }

    private function buildTestHtml(string $host, int $port, string $from, string $to): string
    {
        $h = htmlspecialchars($host, \ENT_QUOTES, 'UTF-8');
        $f = htmlspecialchars($from, \ENT_QUOTES, 'UTF-8');
        $t = htmlspecialchars($to, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto">
  <p><strong>Symfony Mailer</strong> — test réussi.</p>
  <p style="color:#64748b;font-size:13px">Host: {$h}<br>Port: {$port}<br>From: {$f}<br>To: {$t}</p>
</div>
HTML;
    }
}
