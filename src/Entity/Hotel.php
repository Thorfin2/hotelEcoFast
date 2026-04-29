<?php

namespace App\Entity;

use App\Repository\HotelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HotelRepository::class)]
class Hotel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $stars = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $commissionRate = '10.00';

    #[ORM\Column]
    private bool $commissionEnabled = true;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(inversedBy: 'hotel', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'hotel', targetEntity: Ride::class)]
    private Collection $rides;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->rides = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(string $address): static { $this->address = $address; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(string $city): static { $this->city = $city; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getStars(): ?string { return $this->stars; }
    public function setStars(?string $stars): static { $this->stars = $stars; return $this; }

    public function getCommissionRate(): string { return $this->commissionRate; }
    public function setCommissionRate(string $commissionRate): static { $this->commissionRate = $commissionRate; return $this; }

    public function isCommissionEnabled(): bool { return $this->commissionEnabled; }
    public function setCommissionEnabled(bool $commissionEnabled): static { $this->commissionEnabled = $commissionEnabled; return $this; }
    /** True si la commission doit être affichée (activée ET taux > 0) */
    public function hasVisibleCommission(): bool { return $this->commissionEnabled && (float)$this->commissionRate > 0; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getRides(): Collection { return $this->rides; }

    public function getTotalCommissionForMonth(\DateTimeInterface $month): float
    {
        $total = 0;
        foreach ($this->rides as $ride) {
            if ($ride->getStatus() === Ride::STATUS_COMPLETED &&
                $ride->getPickupDatetime()->format('Y-m') === $month->format('Y-m')) {
                $total += (float) $ride->getHotelCommission();
            }
        }
        return $total;
    }

    public function __toString(): string { return $this->name ?? ''; }
}
