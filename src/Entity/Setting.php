<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $settingKey = null;

    #[ORM\Column(length: 255)]
    private ?string $settingValue = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    public function getId(): ?int { return $this->id; }

    public function getSettingKey(): ?string { return $this->settingKey; }
    public function setSettingKey(string $settingKey): static { $this->settingKey = $settingKey; return $this; }

    public function getSettingValue(): ?string { return $this->settingValue; }
    public function setSettingValue(string $settingValue): static { $this->settingValue = $settingValue; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }
}
