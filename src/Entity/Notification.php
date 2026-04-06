<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    public const TYPE_RIDE_CREATED    = 'ride_created';
    public const TYPE_DRIVER_ASSIGNED = 'driver_assigned';
    public const TYPE_RIDE_CONFIRMED  = 'ride_confirmed';
    public const TYPE_RIDE_STARTED    = 'ride_started';
    public const TYPE_RIDE_COMPLETED  = 'ride_completed';
    public const TYPE_RIDE_CANCELLED  = 'ride_cancelled';
    public const TYPE_COMMISSION_REPORT = 'commission_report';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS   = 'sms';

    public const RECIPIENT_ADMIN  = 'admin';
    public const RECIPIENT_HOTEL  = 'hotel';
    public const RECIPIENT_DRIVER = 'driver';
    public const RECIPIENT_CLIENT = 'client';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Ride $ride = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    private string $channel = self::CHANNEL_EMAIL;

    #[ORM\Column(length: 20)]
    private ?string $recipientType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recipientEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $recipientPhone = null;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getRide(): ?Ride { return $this->ride; }
    public function setRide(?Ride $ride): static { $this->ride = $ride; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $channel): static { $this->channel = $channel; return $this; }

    public function getRecipientType(): ?string { return $this->recipientType; }
    public function setRecipientType(string $recipientType): static { $this->recipientType = $recipientType; return $this; }

    public function getRecipientEmail(): ?string { return $this->recipientEmail; }
    public function setRecipientEmail(?string $recipientEmail): static { $this->recipientEmail = $recipientEmail; return $this; }

    public function getRecipientPhone(): ?string { return $this->recipientPhone; }
    public function setRecipientPhone(?string $recipientPhone): static { $this->recipientPhone = $recipientPhone; return $this; }

    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(string $subject): static { $this->subject = $subject; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(?\DateTimeImmutable $sentAt): static { $this->sentAt = $sentAt; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): static { $this->errorMessage = $errorMessage; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_RIDE_CREATED    => 'Course créée',
            self::TYPE_DRIVER_ASSIGNED => 'Chauffeur assigné',
            self::TYPE_RIDE_CONFIRMED  => 'Course confirmée',
            self::TYPE_RIDE_STARTED    => 'Course démarrée',
            self::TYPE_RIDE_COMPLETED  => 'Course terminée',
            self::TYPE_RIDE_CANCELLED  => 'Course annulée',
            self::TYPE_COMMISSION_REPORT => 'Relevé commissions',
            default => $this->type ?? 'Notification',
        };
    }

    public function getRecipientLabel(): string
    {
        return match($this->recipientType) {
            self::RECIPIENT_ADMIN  => 'Administrateur',
            self::RECIPIENT_HOTEL  => 'Hôtel',
            self::RECIPIENT_DRIVER => 'Chauffeur',
            self::RECIPIENT_CLIENT => 'Client',
            default => 'Destinataire',
        };
    }
}
