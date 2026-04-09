<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Ride;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;
use Psr\Log\LoggerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $mailerHost,
        private readonly int $mailerPort,
        private readonly string $mailerUsername,
        private readonly string $mailerPassword,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly string $mailerEncryption,
        private readonly bool $smsSimulation,
        private readonly string $appName,
        private readonly string $appBaseUrl,
    ) {}

    // ─── Trigger points ──────────────────────────────────────────────────────

    public function onRideCreated(Ride $ride): void
    {
        // 1. Email to admin(s)
        $admins = $this->em->getRepository(\App\Entity\User::class)->findActiveAdmins();
        foreach ($admins as $admin) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_CREATED,
                Notification::RECIPIENT_ADMIN,
                $admin->getEmail(),
                "Nouvelle course #{$ride->getReference()}",
                $this->buildAdminNewRideEmail($ride, $admin)
            );
        }

        // 2. Email to hotel
        if ($ride->getHotel()->getEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_CREATED,
                Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Confirmation de creation — Course #{$ride->getReference()}",
                $this->buildHotelRideCreatedEmail($ride)
            );
        }

        // 3. Confirmation email to client if email provided
        if ($ride->getClientEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_CREATED,
                Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Votre reservation de transport #{$ride->getReference()}",
                $this->buildClientConfirmationEmail($ride)
            );
        }
    }

    public function onDriverAssigned(Ride $ride): void
    {
        $driver = $ride->getDriver();
        if (!$driver) return;

        // 1. Email to driver
        if ($driver->getEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_DRIVER_ASSIGNED,
                Notification::RECIPIENT_DRIVER,
                $driver->getEmail(),
                "Nouvelle mission assignee — #{$ride->getReference()}",
                $this->buildDriverAssignmentEmail($ride)
            );
        }

        // 2. Email to hotel
        if ($ride->getHotel()->getEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_DRIVER_ASSIGNED,
                Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Chauffeur assigne — Course #{$ride->getReference()}",
                $this->buildHotelDriverAssignedEmail($ride)
            );
        }

        // 3. Update client if available
        if ($ride->getClientEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_DRIVER_ASSIGNED,
                Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Votre chauffeur a ete assigne — #{$ride->getReference()}",
                $this->buildClientDriverAssignedEmail($ride)
            );
        }
    }

    public function onRideConfirmed(Ride $ride): void
    {
        // Email to hotel
        if ($ride->getHotel()->getEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_CONFIRMED,
                Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Course confirmee par le chauffeur — #{$ride->getReference()}",
                $this->buildStatusUpdateEmail($ride, 'confirmée')
            );
        }
    }

    public function onRideStarted(Ride $ride): void
    {
        // Email to client
        if ($ride->getClientEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_STARTED,
                Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Votre chauffeur est en route — #{$ride->getReference()}",
                $this->buildClientRideStartedEmail($ride)
            );
        }

        // Email to hotel
        if ($ride->getHotel()->getEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_STARTED,
                Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Course demarree — #{$ride->getReference()}",
                $this->buildHotelRideStartedEmail($ride)
            );
        }
    }

    public function onRideCompleted(Ride $ride): void
    {
        // Email to hotel with commission details
        if ($ride->getHotel()->getEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_COMPLETED,
                Notification::RECIPIENT_HOTEL,
                $ride->getHotel()->getEmail(),
                "Course terminee — Commission #{$ride->getReference()}",
                $this->buildRideCompletedEmail($ride)
            );
        }

        // Email to client
        if ($ride->getClientEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_COMPLETED,
                Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Votre trajet est termine — #{$ride->getReference()}",
                $this->buildClientRideCompletedEmail($ride)
            );
        }
    }

    public function onRideCancelled(Ride $ride): void
    {
        // Email to driver if assigned
        if ($ride->getDriver() && $ride->getDriver()->getEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_CANCELLED,
                Notification::RECIPIENT_DRIVER,
                $ride->getDriver()->getEmail(),
                "Course annulee — #{$ride->getReference()}",
                $this->buildStatusUpdateEmail($ride, 'annulée')
            );
        }

        // Email to client
        if ($ride->getClientEmail()) {
            $this->sendEmail(
                $ride,
                Notification::TYPE_RIDE_CANCELLED,
                Notification::RECIPIENT_CLIENT,
                $ride->getClientEmail(),
                "Annulation de votre reservation #{$ride->getReference()}",
                $this->buildStatusUpdateEmail($ride, 'annulée')
            );
        }
    }

    public function sendMonthlyCommissionReport(Ride $ride, array $rides, float $total, \DateTimeInterface $month): void
    {
        if (!$ride->getHotel()->getEmail()) return;

        $this->sendEmail(
            $ride,
            Notification::TYPE_COMMISSION_REPORT,
            Notification::RECIPIENT_HOTEL,
            $ride->getHotel()->getEmail(),
            "Releve de commissions — " . $month->format('F Y'),
            $this->buildCommissionReportEmail($ride->getHotel(), $rides, $total, $month)
        );
    }

    // ─── Core send method ────────────────────────────────────────────────────

    private function sendEmail(
        ?Ride $ride,
        string $type,
        string $recipientType,
        string $recipientEmail,
        string $subject,
        string $htmlBody
    ): Notification {
        $notification = new Notification();
        $notification->setRide($ride);
        $notification->setType($type);
        $notification->setChannel(Notification::CHANNEL_EMAIL);
        $notification->setRecipientType($recipientType);
        $notification->setRecipientEmail($recipientEmail);
        $notification->setSubject($subject);
        $notification->setContent($htmlBody);

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $this->mailerHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->mailerUsername;
            $mail->Password   = $this->mailerPassword;
            $mail->SMTPSecure = $this->mailerEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->mailerPort;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 30;

            $mail->setFrom($this->mailerFromEmail, $this->mailerFromName);
            $mail->addAddress($recipientEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();

            $notification->setStatus(Notification::STATUS_SENT);
            $notification->setSentAt(new \DateTimeImmutable());
            $this->logger->info("Email sent: {$subject} → {$recipientEmail}");
        } catch (MailerException $e) {
            $notification->setStatus(Notification::STATUS_FAILED);
            $notification->setErrorMessage($e->getMessage());
            $this->logger->error("Email failed: {$subject} → {$recipientEmail}: " . $e->getMessage());
        }

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    // ─── Email templates ─────────────────────────────────────────────────────

    private function emailWrapper(string $title, string $body): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
<style>
  body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f0f4f8; margin: 0; padding: 20px; }
  .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
  .header { background: linear-gradient(135deg, #0d9488, #0f766e); padding: 32px; text-align: center; }
  .header h1 { color: white; margin: 0; font-size: 24px; font-weight: 700; }
  .header p { color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px; }
  .content { padding: 32px; }
  .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 16px 0; }
  .label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
  .value { font-size: 15px; color: #1e293b; margin-top: 2px; font-weight: 500; }
  .row { display: flex; justify-content: space-between; margin-bottom: 12px; }
  .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #dcfce7; color: #166534; }
  .btn { display: inline-block; padding: 12px 28px; background: #0d9488; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; margin: 16px 0; }
  .footer { background: #f8fafc; padding: 20px 32px; text-align: center; border-top: 1px solid #e2e8f0; }
  .footer p { color: #94a3b8; font-size: 12px; margin: 4px 0; }
  hr { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }
  .highlight { background: #f0fdf4; border-left: 4px solid #0d9488; padding: 12px 16px; border-radius: 4px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>{$this->appName}</h1>
    <p>Système de transport hôtelier haut de gamme</p>
  </div>
  <div class="content">
    {$body}
  </div>
  <div class="footer">
    <p>{$this->appName} — Plateforme de gestion de transport</p>
    <p>Cet email a été généré automatiquement, merci de ne pas répondre.</p>
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
<h2 style="color:#1e293b;margin-top:0">Nouvelle course à assigner</h2>
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
<a href="{$this->appBaseUrl}/admin/courses/{$ride->getId()}/assigner" class="btn">Assigner un chauffeur</a>
HTML;
        return $this->emailWrapper("Nouvelle course #{$ride->getReference()}", $body);
    }

    private function buildHotelRideCreatedEmail(Ride $ride): string
    {
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#1e293b;margin-top:0">Course créée avec succès</h2>
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
<a href="{$this->appBaseUrl}/hotel/courses/{$ride->getId()}" class="btn">Suivre la course</a>
HTML;
        return $this->emailWrapper("Course créée — #{$ride->getReference()}", $body);
    }

    private function buildDriverAssignmentEmail(Ride $ride): string
    {
        $driver = $ride->getDriver();
        $clientPhone = $ride->getClientPhone() ?? 'Non renseigné';
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#1e293b;margin-top:0">Nouvelle mission assignée</h2>
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
<h2 style="color:#1e293b;margin-top:0">Chauffeur assigné à votre course</h2>
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
<a href="{$this->appBaseUrl}/hotel/courses/{$ride->getId()}" class="btn">Suivre la course</a>
HTML;
        return $this->emailWrapper("Chauffeur assigné — #{$ride->getReference()}", $body);
    }

    private function buildClientConfirmationEmail(Ride $ride): string
    {
        $pickupDate = $ride->getPickupDatetime()->format('d/m/Y à H:i');
        $body = <<<HTML
<h2 style="color:#1e293b;margin-top:0">Votre réservation est confirmée !</h2>
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
<h2 style="color:#1e293b;margin-top:0">Votre chauffeur est prêt !</h2>
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
<h2 style="color:#1e293b;margin-top:0">Votre chauffeur est en route !</h2>
<p>Bonjour {$ride->getClientName()},</p>
<p>Votre chauffeur <strong>{$driverName}</strong> est en route vers le point de prise en charge.</p>
<div class="card">
  <div class="label">Chauffeur</div><div class="value">{$driverName}</div><br>
  <div class="label">Véhicule</div><div class="value">{$vehicleModel} — {$licensePlate}</div><br>
  <div class="label">Point de prise en charge</div><div class="value">{$ride->getPickupAddress()}</div><br>
  <div class="label">Destination</div><div class="value">{$ride->getDestinationAddress()}</div>
</div>
<div class="highlight">
  Référence de votre course : <strong>#{$ride->getReference()}</strong>
</div>
HTML;
        return $this->emailWrapper("Chauffeur en route — #{$ride->getReference()}", $body);
    }

    private function buildHotelRideStartedEmail(Ride $ride): string
    {
        $driverName = $ride->getDriver()?->getFullName() ?? 'Le chauffeur';
        $body = <<<HTML
<h2 style="color:#1e293b;margin-top:0">Course démarrée</h2>
<p>Bonjour,</p>
<p>La course <strong>#{$ride->getReference()}</strong> a été démarrée par <strong>{$driverName}</strong>.</p>
<div class="card">
  <div class="label">Référence</div><div class="value">#{$ride->getReference()}</div><br>
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Chauffeur</div><div class="value">{$driverName}</div><br>
  <div class="label">Trajet</div><div class="value">{$ride->getPickupAddress()} &rarr; {$ride->getDestinationAddress()}</div>
</div>
<a href="{$this->appBaseUrl}/hotel/courses/{$ride->getId()}" class="btn">Suivre la course</a>
HTML;
        return $this->emailWrapper("Course démarrée — #{$ride->getReference()}", $body);
    }

    private function buildClientRideCompletedEmail(Ride $ride): string
    {
        $body = <<<HTML
<h2 style="color:#1e293b;margin-top:0">Votre trajet est terminé</h2>
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
<h2 style="color:#1e293b;margin-top:0">Course {$statusFr}</h2>
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
<h2 style="color:#1e293b;margin-top:0">Course terminée avec succès</h2>
<p>La course <strong>#{$ride->getReference()}</strong> est terminée. Voici le récapitulatif financier :</p>
<div class="card">
  <div class="label">Client</div><div class="value">{$ride->getClientName()}</div><br>
  <div class="label">Trajet</div><div class="value">{$ride->getPickupAddress()} &rarr; {$ride->getDestinationAddress()}</div><br>
  <div class="label">Date</div><div class="value">{$pickupDate}</div>
</div>
<div class="card" style="background:#f0fdf4;border-color:#bbf7d0">
  <div class="label">Prix total</div><div class="value" style="font-size:20px;color:#0d9488;font-weight:700">{$ride->getPrice()} EUR</div><br>
  <div class="label">Votre commission ({$commissionRate}%)</div>
  <div class="value" style="font-size:18px;color:#166534;font-weight:700">{$ride->getHotelCommission()} EUR</div>
</div>
<a href="{$this->appBaseUrl}/hotel/commissions" class="btn">Voir mes commissions</a>
HTML;
        return $this->emailWrapper("Course terminée — Commission #{$ride->getReference()}", $body);
    }

    private function buildCommissionReportEmail(\App\Entity\Hotel $hotel, array $rides, float $total, \DateTimeInterface $month): string
    {
        $rows = '';
        foreach ($rides as $ride) {
            $rDate = $ride->getPickupDatetime()->format('d/m');
            $rows .= "<tr><td style='padding:8px;border-bottom:1px solid #e2e8f0'>#{$ride->getReference()}</td>
                <td style='padding:8px;border-bottom:1px solid #e2e8f0'>{$ride->getClientName()}</td>
                <td style='padding:8px;border-bottom:1px solid #e2e8f0'>{$rDate}</td>
                <td style='padding:8px;border-bottom:1px solid #e2e8f0;text-align:right'>{$ride->getPrice()} EUR</td>
                <td style='padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;color:#0d9488;font-weight:600'>{$ride->getHotelCommission()} EUR</td>
            </tr>";
        }

        $monthFr = $month->format('F Y');
        $commRate = $hotel->getCommissionRate();
        $body = <<<HTML
<h2 style="color:#1e293b;margin-top:0">Relevé de commissions — {$monthFr}</h2>
<p>Bonjour,</p>
<p>Voici votre relevé de commissions pour <strong>{$hotel->getName()}</strong> du mois de <strong>{$monthFr}</strong>.</p>
<div class="card" style="background:#f0fdf4;border-color:#bbf7d0;text-align:center">
  <div class="label">Total commissions ce mois</div>
  <div style="font-size:32px;font-weight:700;color:#0d9488;margin-top:8px">{$total} EUR</div>
  <div style="color:#64748b;font-size:13px;margin-top:4px">sur {$monthFr} — Taux {$commRate}%</div>
</div>
<table style="width:100%;border-collapse:collapse;font-size:13px">
  <thead>
    <tr style="background:#f8fafc">
      <th style="padding:8px;text-align:left;color:#64748b">Réf.</th>
      <th style="padding:8px;text-align:left;color:#64748b">Client</th>
      <th style="padding:8px;text-align:left;color:#64748b">Date</th>
      <th style="padding:8px;text-align:right;color:#64748b">Prix</th>
      <th style="padding:8px;text-align:right;color:#64748b">Commission</th>
    </tr>
  </thead>
  <tbody>{$rows}</tbody>
  <tfoot>
    <tr style="background:#f0fdf4">
      <td colspan="4" style="padding:12px;font-weight:700;color:#0d9488">Total</td>
      <td style="padding:12px;font-weight:700;color:#0d9488;text-align:right">{$total} EUR</td>
    </tr>
  </tfoot>
</table>
<a href="{$this->appBaseUrl}/hotel/commissions" class="btn">Voir l'historique complet</a>
HTML;
        return $this->emailWrapper("Relevé commissions {$monthFr} — {$hotel->getName()}", $body);
    }
}
