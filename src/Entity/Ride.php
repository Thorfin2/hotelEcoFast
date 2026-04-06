<?php

namespace App\Entity;

use App\Repository\RideRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RideRepository::class)]
class Ride
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_ASSIGNED   = 'assigned';
    public const STATUS_CONFIRMED  = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_CANCELLED  = 'cancelled';

    public const RIDE_TYPES = [
        'Aéroport → Hôtel' => 'airport_hotel',
        'Hôtel → Aéroport' => 'hotel_airport',
        'Hôtel → Gare' => 'hotel_station',
        'Gare → Hôtel' => 'station_hotel',
        'Transfert ville' => 'city_transfer',
        'Excursion journée' => 'day_excursion',
        'Mise à disposition' => 'disposal',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(inversedBy: 'rides')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Hotel $hotel = null;

    #[ORM\ManyToOne(inversedBy: 'rides')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Driver $driver = null;

    #[ORM\Column(length: 150)]
    private ?string $clientName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $clientPhone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $clientEmail = null;

    #[ORM\Column(length: 255)]
    private ?string $pickupAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $destinationAddress = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $pickupDatetime = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $rideType = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $driverAmount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $hotelCommission = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $distanceKm = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $passengers = 1;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $luggage = 0;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $flightNumber = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $assignedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'ride', targetEntity: Notification::class, cascade: ['persist', 'remove'])]
    private Collection $notifications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->notifications = new ArrayCollection();
        $this->reference = 'ECO-' . strtoupper(substr(uniqid(), -6));
    }

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = $reference; return $this; }

    public function getHotel(): ?Hotel { return $this->hotel; }
    public function setHotel(?Hotel $hotel): static { $this->hotel = $hotel; return $this; }

    public function getDriver(): ?Driver { return $this->driver; }
    public function setDriver(?Driver $driver): static { $this->driver = $driver; return $this; }

    public function getClientName(): ?string { return $this->clientName; }
    public function setClientName(string $clientName): static { $this->clientName = $clientName; return $this; }

    public function getClientPhone(): ?string { return $this->clientPhone; }
    public function setClientPhone(?string $clientPhone): static { $this->clientPhone = $clientPhone; return $this; }

    public function getClientEmail(): ?string { return $this->clientEmail; }
    public function setClientEmail(?string $clientEmail): static { $this->clientEmail = $clientEmail; return $this; }

    public function getPickupAddress(): ?string { return $this->pickupAddress; }
    public function setPickupAddress(string $pickupAddress): static { $this->pickupAddress = $pickupAddress; return $this; }

    public function getDestinationAddress(): ?string { return $this->destinationAddress; }
    public function setDestinationAddress(string $destinationAddress): static { $this->destinationAddress = $destinationAddress; return $this; }

    public function getPickupDatetime(): ?\DateTimeInterface { return $this->pickupDatetime; }
    public function setPickupDatetime(\DateTimeInterface $pickupDatetime): static { $this->pickupDatetime = $pickupDatetime; return $this; }

    public function getRideType(): ?string { return $this->rideType; }
    public function setRideType(?string $rideType): static { $this->rideType = $rideType; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(?string $price): static { $this->price = $price; return $this; }

    public function getDriverAmount(): ?string { return $this->driverAmount; }
    public function setDriverAmount(?string $driverAmount): static { $this->driverAmount = $driverAmount; return $this; }

    public function getHotelCommission(): ?string { return $this->hotelCommission; }
    public function setHotelCommission(?string $hotelCommission): static { $this->hotelCommission = $hotelCommission; return $this; }

    public function getDistanceKm(): ?string { return $this->distanceKm; }
    public function setDistanceKm(?string $distanceKm): static { $this->distanceKm = $distanceKm; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getPassengers(): ?int { return $this->passengers; }
    public function setPassengers(?int $passengers): static { $this->passengers = $passengers; return $this; }

    public function getLuggage(): ?int { return $this->luggage; }
    public function setLuggage(?int $luggage): static { $this->luggage = $luggage; return $this; }

    public function getFlightNumber(): ?string { return $this->flightNumber; }
    public function setFlightNumber(?string $flightNumber): static { $this->flightNumber = $flightNumber; return $this; }

    public function getAssignedAt(): ?\DateTimeImmutable { return $this->assignedAt; }
    public function setAssignedAt(?\DateTimeImmutable $assignedAt): static { $this->assignedAt = $assignedAt; return $this; }

    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(?\DateTimeImmutable $startedAt): static { $this->startedAt = $startedAt; return $this; }

    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeImmutable $completedAt): static { $this->completedAt = $completedAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getNotifications(): Collection { return $this->notifications; }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PENDING     => 'En attente',
            self::STATUS_ASSIGNED    => 'Chauffeur assigné',
            self::STATUS_CONFIRMED   => 'Confirmée',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_COMPLETED   => 'Terminée',
            self::STATUS_CANCELLED   => 'Annulée',
            default => 'Inconnu',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_PENDING     => 'amber',
            self::STATUS_ASSIGNED    => 'blue',
            self::STATUS_CONFIRMED   => 'indigo',
            self::STATUS_IN_PROGRESS => 'purple',
            self::STATUS_COMPLETED   => 'green',
            self::STATUS_CANCELLED   => 'red',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string
    {
        return match($this->status) {
            self::STATUS_PENDING     => '⏳',
            self::STATUS_ASSIGNED    => '👤',
            self::STATUS_CONFIRMED   => '✅',
            self::STATUS_IN_PROGRESS => '🚗',
            self::STATUS_COMPLETED   => '🏁',
            self::STATUS_CANCELLED   => '❌',
            default => '❓',
        };
    }

    public function calculateCommissions(float $commissionRate): void
    {
        $price = (float) $this->price;
        $commission = $price * ($commissionRate / 100);
        $this->hotelCommission = (string) round($commission, 2);
        $this->driverAmount = (string) round($price - $commission, 2);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ASSIGNED]);
    }
}
