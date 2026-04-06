<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function getValue(string $key, string $default = ''): string
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        return $setting ? $setting->getSettingValue() : $default;
    }

    public function setValue(string $key, string $value, ?string $label = null): void
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        if (!$setting) {
            $setting = new Setting();
            $setting->setSettingKey($key);
            if ($label) {
                $setting->setLabel($label);
            }
            $this->getEntityManager()->persist($setting);
        }
        $setting->setSettingValue($value);
        $this->getEntityManager()->flush();
    }

    public function getAll(): array
    {
        $settings = $this->findAll();
        $result = [];
        foreach ($settings as $s) {
            $result[$s->getSettingKey()] = $s;
        }
        return $result;
    }
}
