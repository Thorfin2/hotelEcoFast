<?php

namespace App\Entity;

use App\Repository\DriverRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DriverRepository::class)]
class Driver
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_BUSY = 'busy';
    public const STATUS_OFFLINE = 'offline';

    public const VEHICLE_TYPES = [
        'Berline Premium' => 'berline_premium',
        'SUV' => 'suv',
        'Van' => 'van',
        'Limousine' => 'limousine',
        'Minibus' => 'minibus',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    private ?string $vehicleModel = null;

    #[ORM\Column(length: 20)]
    private ?string $vehicleType = null;

    #[ORM\Column(length: 20)]
    private ?string $licensePlate = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_AVAILABLE;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $licenseNumber = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $licenseExpiry = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(inversedBy: 'driver', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'driver', targetEntity: Ride::class)]
    private Collection $rides;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->rides = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): static { $this->lastName = $lastName; return $this; }

    public function getFullName(): string { return $this->firstName . ' ' . $this->lastName; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(string $phone): static { $this->phone = $phone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getVehicleModel(): ?string { return $this->vehicleModel; }
    public function setVehicleModel(string $vehicleModel): static { $this->vehicleModel = $vehicleModel; return $this; }

    public function getVehicleType(): ?string { return $this->vehicleType; }
    public function setVehicleType(string $vehicleType): static { $this->vehicleType = $vehicleType; return $this; }

    public function getLicensePlate(): ?string { return $this->licensePlate; }
    public function setLicensePlate(string $licensePlate): static { $this->licensePlate = $licensePlate; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getLicenseNumber(): ?string { return $this->licenseNumber; }
    public function setLicenseNumber(?string $licenseNumber): static { $this->licenseNumber = $licenseNumber; return $this; }

    public function getLicenseExpiry(): ?\DateTimeImmutable { return $this->licenseExpiry; }
    public function setLicenseExpiry(?\DateTimeImmutable $licenseExpiry): static { $this->licenseExpiry = $licenseExpiry; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getRides(): Collection { return $this->rides; }

    public function isAvailable(): bool { return $this->status === self::STATUS_AVAILABLE; }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_AVAILABLE => 'Disponible',
            self::STATUS_BUSY => 'En mission',
            self::STATUS_OFFLINE => 'Hors ligne',
            default => 'Inconnu',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_AVAILABLE => 'green',
            self::STATUS_BUSY => 'amber',
            self::STATUS_OFFLINE => 'gray',
            default => 'gray',
        };
    }

    public function __toString(): string { return $this->getFullName(); }
}
