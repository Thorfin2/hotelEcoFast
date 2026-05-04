<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Ride;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $appName,
        private readonly string $appBaseUrl,
    ) {
    }

    /**
     * Lance l'envoi des emails en arrière-plan pour ne pas bloquer la requête HTTP.
     */
    private function dispatchEmailSending(): void
    {
        $phpBin     = PHP_BINARY; // chemin absolu vers l'exécutable PHP courant
        $consolePath = dirname(__DIR__, 2) . '/bin/console';
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($consolePath)
            . ' app:send-pending-emails > /dev/null 2>&1 &';
        @exec($cmd);
    }

    // ─── Trigger points ──────────────────────────────────────────────────────

    public function onRideCreated(Ride $ride): void
    {
        $admins = $this->em->getRepository(\App\Entity\User::class)->findActiveAdmins();
        foreach ($admins as $admin) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_CREATED, Notification::RECIPIENT_ADMIN,
                $admin->getEmail(),
                "Nouvelle course #{$ride->getReference()}",
                $this->buildAdminNewRideEmail($ride, $admin)
            );
        }

        if ($ride->getHotel()->getEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_CREATED, Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Confirmation de creation — Course #{$ride->getReference()}",
                $this->buildHotelRideCreatedEmail($ride)
            );
        }

        if ($ride->getClientEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_CREATED, Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Votre reservation de transport #{$ride->getReference()}",
                $this->buildClientConfirmationEmail($ride)
            );
        }

        $this->dispatchEmailSending();
    }

    public function onDriverAssigned(Ride $ride): void
    {
        $driver = $ride->getDriver();
        if (!$driver) return;

        if ($driver->getEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_DRIVER_ASSIGNED, Notification::RECIPIENT_DRIVER,
                $driver->getEmail(),
                "Nouvelle mission assignee — #{$ride->getReference()}",
                $this->buildDriverAssignmentEmail($ride)
            );
        }

        if ($ride->getHotel()->getEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_DRIVER_ASSIGNED, Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Chauffeur assigne — Course #{$ride->getReference()}",
                $this->buildHotelDriverAssignedEmail($ride)
            );
        }

        if ($ride->getClientEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_DRIVER_ASSIGNED, Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Votre chauffeur a ete assigne — #{$ride->getReference()}",
                $this->buildClientDriverAssignedEmail($ride)
            );
        }

        $this->dispatchEmailSending();
    }

    public function onRideConfirmed(Ride $ride): void
    {
        if ($ride->getHotel()->getEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_CONFIRMED, Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Course confirmee par le chauffeur — #{$ride->getReference()}",
                $this->buildStatusUpdateEmail($ride, 'confirmée')
            );
        }
        $this->dispatchEmailSending();
    }

    public function onRideStarted(Ride $ride): void
    {
        if ($ride->getClientEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_STARTED, Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Votre chauffeur est en route — #{$ride->getReference()}",
                $this->buildClientRideStartedEmail($ride)
            );
        }

        if ($ride->getHotel()->getEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_STARTED, Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Course demarree — #{$ride->getReference()}",
                $this->buildHotelRideStartedEmail($ride)
            );
        }
        $this->dispatchEmailSending();
    }

    public function onRideCompleted(Ride $ride): void
    {
        if ($ride->getHotel()->getEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_COMPLETED, Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Course terminee — Commission #{$ride->getReference()}",
                $this->buildRideCompletedEmail($ride)
            );
        }

        if ($ride->getClientEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_COMPLETED, Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Votre trajet est termine — #{$ride->getReference()}",
                $this->buildClientRideCompletedEmail($ride)
            );
        }
        $this->dispatchEmailSending();
    }

    public function onRideCancelled(Ride $ride): void
    {
        if ($ride->getDriver() && $ride->getDriver()->getEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_CANCELLED, Notification::RECIPIENT_DRIVER,
                $ride->getDriver()->getEmail(),
                "Course annulee — #{$ride->getReference()}",
                $this->buildStatusUpdateEmail($ride, 'annulée')
            );
        }

        if ($ride->getClientEmail()) {
            $this->saveNotification(
                $ride, Notification::TYPE_RIDE_CANCELLED, Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Annulation de votre reservation #{$ride->getReference()}",
                $this->buildStatusUpdateEmail($ride, 'annulée')
            );
        }
        $this->dispatchEmailSending();
    }

    public function sendMonthlyCommissionReport(Ride $ride, array $rides, float $total, \DateTimeInterface $month): void
    {
        if (!$ride->getHotel()->getEmail()) return;

        $this->saveNotification(
            $ride, Notification::TYPE_COMMISSION_REPORT, Notification::RECIPIENT_HOTEL,
            $ride->getHotel()->getEmail(),
            "Releve de commissions — " . $month->format('F Y'),
            $this->buildCommissionReportEmail($ride->getHotel(), $rides, $total, $month)
        );
        $this->dispatchEmailSending();
    }

    // ─── Core send method ────────────────────────────────────────────────────

    /**
     * Sauvegarde la notification en base (status: pending). L'envoi SMTP se fait en arrière-plan.
     */
    private function saveNotification(
        ?Ride $ride,
        string $type,
        string $recipientType,
        string $recipientEmail,
        string $subject,
        string $htmlBody
    ): void {
        $notification = new Notification();
        $notification->setRide($ride);
        $notification->setType($type);
        $notification->setChannel(Notification::CHANNEL_EMAIL);
        $notification->setRecipientType($recipientType);
        $notification->setRecipientEmail($recipientEmail);
        $notification->setSubject($subject);
        $notification->setContent($htmlBody);
        $notification->setStatus(Notification::STATUS_PENDING);

        $this->em->persist($notification);
        $this->em->flush();
    }

    // ─── Email templates ─────────────────────────────────────────────────────

    private function emailWrapper(string $title, string $body, string $accentColor = '#c4905a'): string
    {
        // Palette Cabsolu extraite du logo
        // Navy : #0D1B2E | Bronze-or : #C4905A | Or clair : #E2C08C | Or foncé : #8A5630
        return <<<HTML
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{$title}</title>
<style>
  /* Reset */
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; }
  a { color: inherit; }
  img { display: block; border: 0; }

  /* Layout */
  .email-wrapper { width: 100%; background: #f0f2f5; padding: 32px 16px; }
  .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 40px rgba(13,27,46,0.12); }

  /* Header navy + filet or */
  .header {
    background: linear-gradient(160deg, #0d1b2e 0%, #132233 60%, #1a3048 100%);
    padding: 0;
    text-align: center;
    position: relative;
  }
  .header-gold-bar {
    height: 3px;
    background: linear-gradient(90deg, transparent, #c4905a 25%, #e2c08c 50%, #c4905a 75%, transparent);
  }
  .header-inner { padding: 32px 32px 28px; }
  .header-logo-ring {
    display: inline-block;
    width: 72px; height: 72px;
    border-radius: 50%;
    border: 2px solid rgba(196,144,90,0.4);
    padding: 3px;
    margin-bottom: 16px;
  }
  .header-logo-ring img { width: 66px; height: 66px; border-radius: 50%; }
  .header-brand {
    font-size: 22px; font-weight: 800; color: #ffffff;
    letter-spacing: 0.06em; margin-bottom: 4px;
  }
  .header-brand span { color: #c4905a; }
  .header-subtitle {
    font-size: 11px; color: rgba(255,255,255,0.45);
    letter-spacing: 0.12em; text-transform: uppercase;
  }
  .header-title-bar {
    background: rgba(196,144,90,0.08);
    border-top: 1px solid rgba(196,144,90,0.15);
    padding: 14px 32px;
  }
  .header-title-bar h1 {
    font-size: 16px; font-weight: 600; color: #e2c08c;
    letter-spacing: 0.02em;
  }

  /* Content */
  .content { padding: 36px 32px; color: #1a1a1e; }
  .content h2 { font-size: 20px; font-weight: 700; color: #0d1b2e; margin-bottom: 8px; }
  .content p { font-size: 14px; color: #4d4e54; line-height: 1.65; margin-bottom: 12px; }

  /* Info card */
  .card {
    background: #f8f9fb;
    border: 1px solid #e8e9ec;
    border-radius: 12px;
    padding: 20px 24px;
    margin: 20px 0;
  }
  .card-row { padding: 8px 0; border-bottom: 1px solid #eff0f2; }
  .card-row:last-child { border-bottom: none; padding-bottom: 0; }
  .card-row:first-child { padding-top: 0; }
  .label {
    font-size: 10px; font-weight: 700; color: #868991;
    text-transform: uppercase; letter-spacing: 0.08em;
    margin-bottom: 2px;
  }
  .value { font-size: 14px; color: #1a1a1e; font-weight: 500; }

  /* Gold card (montants) */
  .card-gold {
    background: linear-gradient(135deg, #fdf6ee 0%, #fff 100%);
    border: 1px solid #f2cfa0;
    border-radius: 12px;
    padding: 20px 24px;
    margin: 20px 0;
    text-align: center;
  }
  .amount-label { font-size: 11px; font-weight: 600; color: #a67040; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
  .amount-value { font-size: 32px; font-weight: 800; color: #0d1b2e; }
  .amount-sub { font-size: 13px; color: #8a5630; margin-top: 4px; }

  /* Highlight info */
  .highlight {
    background: #fdf6ee;
    border-left: 3px solid #c4905a;
    border-radius: 0 8px 8px 0;
    padding: 12px 16px;
    margin: 16px 0;
    font-size: 13px;
    color: #8a5630;
    line-height: 1.6;
  }

  /* Status badge */
  .badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: #fdf6ee;
    color: #8a5630;
    border: 1px solid #f2cfa0;
  }

  /* CTA Button */
  .btn-wrap { text-align: center; margin: 28px 0 12px; }
  .btn {
    display: inline-block;
    padding: 14px 32px;
    background: linear-gradient(135deg, #c4905a, #a67040);
    color: #ffffff !important;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.02em;
    box-shadow: 0 4px 14px rgba(196,144,90,0.35);
  }

  /* Divider */
  .divider { height: 1px; background: #eff0f2; margin: 24px 0; }

  /* Footer */
  .footer {
    background: #0d1b2e;
    padding: 24px 32px;
    text-align: center;
  }
  .footer-brand { font-size: 13px; font-weight: 700; color: #c4905a; margin-bottom: 4px; letter-spacing: 0.04em; }
  .footer p { font-size: 11px; color: rgba(255,255,255,0.3); margin: 3px 0; line-height: 1.5; }
  .footer-bar { height: 2px; background: linear-gradient(90deg, transparent, #c4905a 40%, transparent); margin-bottom: 20px; }

  /* Table */
  .data-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 16px 0; }
  .data-table th { padding: 10px 12px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #868991; background: #f8f9fb; border-bottom: 2px solid #e8e9ec; }
  .data-table th:last-child { text-align: right; }
  .data-table td { padding: 10px 12px; border-bottom: 1px solid #f0f1f3; color: #1a1a1e; vertical-align: top; }
  .data-table td:last-child { text-align: right; }
  .data-table tr:last-child td { border-bottom: none; }
  .data-table tfoot td { padding: 12px; font-weight: 700; color: #0d1b2e; background: #fdf6ee; border-top: 2px solid #f2cfa0; }
  .data-table tfoot td:last-child { color: #c4905a; text-align: right; }
</style>
</head>
<body>
<div class="email-wrapper">
<div class="container">

  <!-- Header -->
  <div class="header">
    <div class="header-gold-bar"></div>
    <div class="header-inner">
      <div class="header-logo-ring">
        <img src="{$this->appBaseUrl}/images/logo.png" alt="Cabsolu" width="66" height="66">
      </div>
      <div class="header-brand">CAB<span>SOLU</span></div>
      <div class="header-subtitle">Solution Absolue pour vos Déplacements</div>
    </div>
    <div class="header-title-bar">
      <h1>{$title}</h1>
    </div>
  </div>

  <!-- Content -->
  <div class="content">
    {$body}
  </div>

  <!-- Footer -->
  <div class="footer">
    <div class="footer-bar"></div>
    <div class="footer-brand">CABSOLU</div>
    <p>Plateforme de transport hôtelier haut de gamme</p>
    <p style="margin-top:8px;">Cet email est généré automatiquement — merci de ne pas y répondre.</p>
    <p style="margin-top:6px;">© 2026 Cabsolu — Tous droits réservés</p>
  </div>

</div>
</div>
</body>
</html>
HTML;
    }

    private function buildAdminNewRideEmail(Ride $ride, User $admin): string
    {
        $hotelName = $ride->getHotel()->getName();
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Nouvelle course à assigner</h2>
<p>Bonjour {$admin->getFirstName()},</p>
<p>Une nouvelle course a été créée et nécessite l'assignation d'un chauffeur.</p>
<div class="card">
  <div class="label">Référence</div><div class="value">#{$ride->getReference()}</div><br>
  <div class="label">Hôtel demandeur</div><div class="value">{$hotelName}</div><br>
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Prise en charge</div><div class="value">{$ride->getPickupAddress()}</div><br>
  <div class="label">Destination</div><div class="value">{$ride->getDestinationAddress()}</div><br>
  <div class="label">Date & Heure</div><div class="value">{$pickupDate}</div>
</div>
<div class="highlight">
  Action requise : Connectez-vous pour assigner un chauffeur disponible.
</div>
<div class="btn-wrap"><a href="{$this->appBaseUrl}/admin/courses/{$ride->getId()}/assigner" class="btn">Assigner un chauffeur</a></div>
HTML;
        return $this->emailWrapper("Nouvelle course #{$ride->getReference()}", $body);
    }

    private function buildHotelRideCreatedEmail(Ride $ride): string
    {
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Course créée avec succès</h2>
<p>Bonjour,</p>
<p>La course <strong>#{$ride->getReference()}</strong> a bien été enregistrée pour votre client <strong>{$ride->getClientName()}</strong>.</p>
<div class="card">
  <div class="label">Référence</div><div class="value">#{$ride->getReference()}</div><br>
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Prise en charge</div><div class="value">{$ride->getPickupAddress()}</div><br>
  <div class="label">Destination</div><div class="value">{$ride->getDestinationAddress()}</div><br>
  <div class="label">Date & Heure</div><div class="value">{$pickupDate}</div>
</div>
<div class="highlight">
  Un chauffeur sera assigné prochainement. Vous recevrez un email de confirmation.
</div>
<div class="btn-wrap"><a href="{$this->appBaseUrl}/hotel/courses/{$ride->getId()}" class="btn">Suivre la course</a></div>
HTML;
        return $this->emailWrapper("Course créée — #{$ride->getReference()}", $body);
    }

    private function buildDriverAssignmentEmail(Ride $ride): string
    {
        $driver = $ride->getDriver();
        $clientPhone = $ride->getClientPhone() ?? 'Non renseigné';
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Nouvelle mission assignée</h2>
<p>Bonjour {$driver->getFirstName()},</p>
<p>Une nouvelle course vous a été assignée. Veuillez confirmer votre disponibilité.</p>
<div class="card">
  <div class="label">Référence</div><div class="value">#{$ride->getReference()}</div><br>
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Téléphone client</div><div class="value">{$clientPhone}</div><br>
  <div class="label">Prise en charge</div><div class="value">{$ride->getPickupAddress()}</div><br>
  <div class="label">Destination</div><div class="value">{$ride->getDestinationAddress()}</div><br>
  <div class="label">Date & Heure</div><div class="value">{$pickupDate}</div><br>
  <div class="label">Passagers</div><div class="value">{$ride->getPassengers()}</div><br>
  <div class="label">Bagages</div><div class="value">{$ride->getLuggage()}</div>
</div>
HTML;
        if ($ride->getNotes()) {
            $body .= "<div class=\"highlight\"><strong>Notes :</strong> {$ride->getNotes()}</div>";
        }
        $body .= "<a href=\"{$this->appBaseUrl}/chauffeur/missions/{$ride->getId()}\" class=\"btn\">Voir la mission</a>";
        return $this->emailWrapper("Mission #{$ride->getReference()}", $body);
    }

    private function buildHotelDriverAssignedEmail(Ride $ride): string
    {
        $driver = $ride->getDriver();
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Chauffeur assigné à votre course</h2>
<p>Bonjour,</p>
<p>Un chauffeur a été assigné à la course <strong>#{$ride->getReference()}</strong> pour votre client <strong>{$ride->getClientName()}</strong>.</p>
<div class="card">
  <div class="label">Chauffeur</div><div class="value">{$driver->getFullName()}</div><br>
  <div class="label">Téléphone</div><div class="value">{$driver->getPhone()}</div><br>
  <div class="label">Véhicule</div><div class="value">{$driver->getVehicleModel()} — {$driver->getLicensePlate()}</div>
</div>
<div class="card">
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Prise en charge</div><div class="value">{$pickupDate}</div><br>
  <div class="label">Départ</div><div class="value">{$ride->getPickupAddress()}</div><br>
  <div class="label">Destination</div><div class="value">{$ride->getDestinationAddress()}</div>
</div>
<div class="btn-wrap"><a href="{$this->appBaseUrl}/hotel/courses/{$ride->getId()}" class="btn">Suivre la course</a></div>
HTML;
        return $this->emailWrapper("Chauffeur assigné — #{$ride->getReference()}", $body);
    }

    private function buildClientConfirmationEmail(Ride $ride): string
    {
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Votre réservation est confirmée !</h2>
<p>Bonjour {$ride->getClientName()},</p>
<p>Votre réservation de transport a bien été enregistrée. Voici le récapitulatif :</p>
<div class="card">
  <div class="label">Référence</div><div class="value">#{$ride->getReference()}</div><br>
  <div class="label">Date & Heure</div><div class="value">{$pickupDate}</div><br>
  <div class="label">Adresse de départ</div><div class="value">{$ride->getPickupAddress()}</div><br>
  <div class="label">Destination</div><div class="value">{$ride->getDestinationAddress()}</div><br>
  <div class="label">Passagers</div><div class="value">{$ride->getPassengers()}</div>
</div>
<div class="highlight">
  Vous recevrez une notification dès qu'un chauffeur sera assigné à votre course.
</div>
HTML;
        return $this->emailWrapper("Confirmation de réservation #{$ride->getReference()}", $body);
    }

    private function buildClientDriverAssignedEmail(Ride $ride): string
    {
        $driver = $ride->getDriver();
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Votre chauffeur est prêt !</h2>
<p>Bonjour {$ride->getClientName()},</p>
<p>Votre chauffeur a été assigné pour votre trajet du <strong>{$pickupDate}</strong>.</p>
<div class="card">
  <div class="label">Votre chauffeur</div><div class="value">{$driver->getFullName()}</div><br>
  <div class="label">Contact</div><div class="value">{$driver->getPhone()}</div><br>
  <div class="label">Véhicule</div><div class="value">{$driver->getVehicleModel()}</div><br>
  <div class="label">Immatriculation</div><div class="value">{$driver->getLicensePlate()}</div>
</div>
<div class="highlight">
  Retrouvez votre chauffeur à : <strong>{$ride->getPickupAddress()}</strong>
</div>
HTML;
        return $this->emailWrapper("Votre chauffeur est assigné — #{$ride->getReference()}", $body);
    }

    private function buildClientRideStartedEmail(Ride $ride): string
    {
        $driverName = $ride->getDriver()?->getFullName() ?? 'Votre chauffeur';
        $licensePlate = $ride->getDriver()?->getLicensePlate() ?? '';
        $vehicleModel = $ride->getDriver()?->getVehicleModel() ?? '';
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Votre chauffeur est en route !</h2>
<p>Bonjour {$ride->getClientName()},</p>
<p>Votre chauffeur <strong>{$driverName}</strong> est en route vers le point de prise en charge.</p>
<div class="card">
  <div class="label">Chauffeur</div><div class="value">{$driverName}</div><br>
  <div class="label">Véhicule</div><div class="value">{$vehicleModel} — {$licensePlate}</div><br>
  <div class="label">Point de prise en charge</div><div class="value">{$ride->getPickupAddress()}</div><br>
  <div class="label">Destination</div><div class="value">{$ride->getDestinationAddress()}</div>
</div>
HTML;
        return $this->emailWrapper("Chauffeur en route — #{$ride->getReference()}", $body);
    }

    private function buildHotelRideStartedEmail(Ride $ride): string
    {
        $driverName = $ride->getDriver()?->getFullName() ?? 'Le chauffeur';
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Course démarrée</h2>
<p>Bonjour,</p>
<p>La course <strong>#{$ride->getReference()}</strong> a été démarrée par <strong>{$driverName}</strong>.</p>
<div class="card">
  <div class="label">Référence</div><div class="value">#{$ride->getReference()}</div><br>
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Chauffeur</div><div class="value">{$driverName}</div><br>
  <div class="label">Trajet</div><div class="value">{$ride->getPickupAddress()} &rarr; {$ride->getDestinationAddress()}</div>
</div>
<div class="btn-wrap"><a href="{$this->appBaseUrl}/hotel/courses/{$ride->getId()}" class="btn">Suivre la course</a></div>
HTML;
        return $this->emailWrapper("Course démarrée — #{$ride->getReference()}", $body);
    }

    private function buildClientRideCompletedEmail(Ride $ride): string
    {
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Votre trajet est terminé</h2>
<p>Bonjour {$ride->getClientName()},</p>
<p>Votre trajet est bien arrivé à destination. Merci de votre confiance !</p>
<div class="card">
  <div class="label">Référence</div><div class="value">#{$ride->getReference()}</div><br>
  <div class="label">Trajet</div><div class="value">{$ride->getPickupAddress()} &rarr; {$ride->getDestinationAddress()}</div><br>
  <div class="label">Date</div><div class="value">{$ride->getPickupDatetime()->format('d/m/Y')}</div>
</div>
<div class="highlight">
  Merci d'avoir choisi <strong>{$this->appName}</strong>. Nous espérons vous revoir bientôt !
</div>
HTML;
        return $this->emailWrapper("Trajet terminé — #{$ride->getReference()}", $body);
    }

    private function buildStatusUpdateEmail(Ride $ride, string $statusFr): string
    {
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Course {$statusFr}</h2>
<p>La course <strong>#{$ride->getReference()}</strong> a été <strong>{$statusFr}</strong>.</p>
<div class="card">
  <div class="label">Référence</div><div class="value">#{$ride->getReference()}</div><br>
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Statut</div><div class="value"><span class="badge">{$ride->getStatusLabel()}</span></div>
</div>
HTML;
        return $this->emailWrapper("Course {$statusFr} — #{$ride->getReference()}", $body);
    }

    private function buildRideCompletedEmail(Ride $ride): string
    {
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y');
        $commissionRate = $ride->getHotel()->getCommissionRate();
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Course terminée avec succès</h2>
<p>La course <strong>#{$ride->getReference()}</strong> est terminée. Voici le récapitulatif financier :</p>
<div class="card">
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Trajet</div><div class="value">{$ride->getPickupAddress()} &rarr; {$ride->getDestinationAddress()}</div><br>
  <div class="label">Date</div><div class="value">{$pickupDate}</div>
</div>
<div class="card-gold">
  <div class="amount-label">Prix total de la course</div>
  <div class="amount-value">{$ride->getPrice()} €</div>
  <div class="amount-sub">Commission hôtel ({$commissionRate}%) : <strong>{$ride->getHotelCommission()} €</strong></div>
</div>
<div class="btn-wrap"><a href="{$this->appBaseUrl}/hotel/commissions" class="btn">Voir mes commissions</a></div>
HTML;
        return $this->emailWrapper("Course terminée — Commission #{$ride->getReference()}", $body);
    }

    private function buildCommissionReportEmail(\App\Entity\Hotel $hotel, array $rides, float $total, \DateTimeInterface $month): string
    {
        $rows = '';
        foreach ($rides as $ride) {
            $rDate = $ride->getPickupDatetime()->format('d/m');
            $rows .= "<tr>
                <td>#{$ride->getReference()}</td>
                <td>{$ride->getClientName()}</td>
                <td>{$rDate}</td>
                <td>{$ride->getPrice()} €</td>
                <td style='color:#c4905a;font-weight:700'>{$ride->getHotelCommission()} €</td>
            </tr>";
        }

        $monthFr = $month->format('F Y');
        $commRate = $hotel->getCommissionRate();
        $body = <<<HTML
<h2 style="color:#0d1b2e;margin-top:0;font-size:19px;">Relevé de commissions — {$monthFr}</h2>
<p>Bonjour,</p>
<p>Voici votre relevé de commissions pour <strong>{$hotel->getName()}</strong> du mois de <strong>{$monthFr}</strong>.</p>

<div class="card-gold">
  <div class="amount-label">Total commissions — {$monthFr}</div>
  <div class="amount-value">{$total} €</div>
  <div class="amount-sub">Taux de commission : {$commRate}%</div>
</div>

<table class="data-table">
  <thead>
    <tr>
      <th>Référence</th>
      <th>Client</th>
      <th>Date</th>
      <th>Prix course</th>
      <th>Votre commission</th>
    </tr>
  </thead>
  <tbody>{$rows}</tbody>
  <tfoot>
    <tr>
      <td colspan="4">Total du mois</td>
      <td>{$total} €</td>
    </tr>
  </tfoot>
</table>

<div class="btn-wrap"><a href="{$this->appBaseUrl}/hotel/commissions" class="btn">Voir l'historique complet</a></div>
HTML;
        return $this->emailWrapper("Relevé commissions {$monthFr} — {$hotel->getName()}", $body);
    }
}
