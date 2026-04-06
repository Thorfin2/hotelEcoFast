<?php

namespace App\DataFixtures;

use App\Entity\Driver;
use App\Entity\Hotel;
use App\Entity\Notification;
use App\Entity\Ride;
use App\Entity\Setting;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ─── Settings ─────────────────────────────────────────────────────────
        $pricePerKm = new Setting();
        $pricePerKm->setSettingKey('price_per_km')->setSettingValue('2.50')->setLabel('Prix par kilomètre (€)');
        $manager->persist($pricePerKm);

        $minimumPrice = new Setting();
        $minimumPrice->setSettingKey('minimum_price')->setSettingValue('25.00')->setLabel('Prix minimum (€)');
        $manager->persist($minimumPrice);

        // ─── Admin ────────────────────────────────────────────────────────────
        $admin = new User();
        $admin->setFirstName('Marc')->setLastName('Dupont')
            ->setEmail('admin@ecofasthotel.com')->setPhone('+33 1 23 45 67 89')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->hasher->hashPassword($admin, 'ecofasthotel'));
        $manager->persist($admin);

        // ─── Hotels ───────────────────────────────────────────────────────────
        $hotelsData = [
            ['Le Ritz Paris', 'contact@ritz.com', '15 Place Vendôme', 'Paris', '5', '12.00', 'hotel@ecofasthotel.com', '+33 1 43 16 30 30'],
            ['George V', 'george@fourseasons.com', '31 Avenue George V', 'Paris', '5', '10.00', 'hotel2@ecofasthotel.com', '+33 1 49 52 70 00'],
            ['Hotel Barrière Le Fouquet\'s', 'contact@fouquets.com', '46 Avenue George V', 'Paris', '5', '8.00', 'hotel3@ecofasthotel.com', '+33 1 40 69 60 00'],
        ];

        $hotels = [];
        foreach ($hotelsData as $i => $data) {
            $userHotel = new User();
            $email = $i === 0 ? $data[6] : $data[6];
            $userHotel->setFirstName('Direction')->setLastName($data[0])
                ->setEmail($data[6])->setPhone($data[7])
                ->setRoles(['ROLE_HOTEL'])
                ->setPassword($this->hasher->hashPassword($userHotel, 'ecofasthotel'));
            $manager->persist($userHotel);

            $hotel = new Hotel();
            $hotel->setName($data[0])->setEmail($data[1])
                ->setAddress($data[2])->setCity($data[3])
                ->setStars($data[4])->setCommissionRate($data[5])
                ->setPhone($data[7])->setUser($userHotel);
            $manager->persist($hotel);
            $hotels[] = $hotel;
        }

        // ─── Drivers ──────────────────────────────────────────────────────────
        $driversData = [
            ['Ahmed', 'Benali', '+33 6 11 22 33 44', 'ahmed.benali@ecofasthotel.com', 'Mercedes Classe E', 'berline_premium', 'AA-123-BB'],
            ['Pierre', 'Martin', '+33 6 22 33 44 55', 'pierre.martin@ecofasthotel.com', 'BMW Série 7', 'berline_premium', 'CC-456-DD'],
            ['Karim', 'Hassan', '+33 6 33 44 55 66', 'karim.hassan@ecofasthotel.com', 'Mercedes Viano', 'van', 'EE-789-FF'],
            ['Jean-Luc', 'Moreau', '+33 6 44 55 66 77', 'jeanluc.moreau@ecofasthotel.com', 'Audi A8 L', 'berline_premium', 'GG-012-HH'],
        ];

        $drivers = [];
        $driverStatuses = [Driver::STATUS_AVAILABLE, Driver::STATUS_AVAILABLE, Driver::STATUS_BUSY, Driver::STATUS_OFFLINE];
        foreach ($driversData as $i => $data) {
            $userDriver = new User();
            $email = $i === 0 ? 'chauffeur@ecofasthotel.com' : $data[3];
            $userDriver->setFirstName($data[0])->setLastName($data[1])
                ->setEmail($email)->setPhone($data[2])
                ->setRoles(['ROLE_DRIVER'])
                ->setPassword($this->hasher->hashPassword($userDriver, 'ecofasthotel'));
            $manager->persist($userDriver);

            $driver = new Driver();
            $driver->setFirstName($data[0])->setLastName($data[1])
                ->setPhone($data[2])->setEmail($email)
                ->setVehicleModel($data[4])->setVehicleType($data[5])
                ->setLicensePlate($data[6])->setUser($userDriver)
                ->setStatus($driverStatuses[$i]);
            $manager->persist($driver);
            $drivers[] = $driver;
        }

        // Flush to get IDs
        $manager->flush();

        // ─── Rides ────────────────────────────────────────────────────────────
        $ridesData = [
            // Completed rides
            ['Sophie Laurent', '+33 6 55 66 77 88', 'sophie@email.com', 'Aéroport CDG Terminal 2E', 'Le Ritz Paris, 15 Place Vendôme', '-3 days 14:30', '3', 'airport_hotel', 'completed', '280.00', $hotels[0], $drivers[0]],
            ['James Wilson', '+44 20 7946 0958', 'james@corp.com', 'Gare de Lyon', 'George V, 31 Avenue George V', '-2 days 09:00', '2', 'station_hotel', 'completed', '185.00', $hotels[1], $drivers[1]],
            ['Maria Santos', '+34 91 123 4567', 'maria@travel.es', 'Aéroport Orly Terminal 4', 'Le Ritz Paris, 15 Place Vendôme', '-1 day 16:45', '4', 'airport_hotel', 'completed', '320.00', $hotels[0], $drivers[0]],
            ['François Petit', '+33 6 88 99 00 11', null, 'Le Ritz Paris', 'Aéroport CDG Terminal 1', '-5 days 07:00', '1', 'hotel_airport', 'completed', '260.00', $hotels[0], $drivers[1]],
            // Active / In progress
            ['Isabella Ferrari', '+39 02 1234 5678', 'isabella@italy.it', 'Aéroport CDG Terminal 2F', 'George V, 31 Avenue George V', '+2 hours', '2', 'airport_hotel', 'confirmed', '220.00', $hotels[1], $drivers[0]],
            ['David Chen', '+86 10 5678 9012', 'david@china.cn', 'Gare du Nord', 'Le Ritz Paris', '+4 hours', '1', 'station_hotel', 'assigned', '155.00', $hotels[0], $drivers[1]],
            // Pending
            ['Alexandra Dubois', '+33 6 12 34 56 78', 'alexandra@vip.fr', 'Aéroport CDG Terminal 3', 'Le Fouquet\'s, 46 Avenue George V', '+1 day 10:00', '3', 'airport_hotel', 'pending', '300.00', $hotels[2], null],
            ['Robert Johnson', '+1 212 555 0180', 'robert@newyork.com', 'Gare de Lyon', 'Le Ritz Paris', '+1 day 15:30', '2', 'station_hotel', 'pending', '175.00', $hotels[0], null],
            ['Yuki Tanaka', '+81 3 1234 5678', null, 'Aéroport Orly Terminal 1', 'George V', '+2 days 08:00', '1', 'airport_hotel', 'pending', '265.00', $hotels[1], null],
        ];

        foreach ($ridesData as $data) {
            $ride = new Ride();
            $ride->setClientName($data[0])->setClientPhone($data[1])->setClientEmail($data[2])
                ->setPickupAddress($data[3])->setDestinationAddress($data[4])
                ->setPickupDatetime(new \DateTime($data[5]))
                ->setPassengers((int)$data[6])->setRideType($data[7])
                ->setStatus($data[8])->setPrice($data[9])
                ->setHotel($data[10]);

            if ($data[11]) {
                $ride->setDriver($data[11]);
            }

            // Calculate commissions
            $commissionRate = (float) $data[10]->getCommissionRate();
            $ride->calculateCommissions($commissionRate);

            // Set timestamps for completed/active rides
            if ($data[8] === 'completed') {
                $ride->setAssignedAt(new \DateTimeImmutable($data[5] . ' -30 minutes'));
                $ride->setStartedAt(new \DateTimeImmutable($data[5]));
                $ride->setCompletedAt(new \DateTimeImmutable($data[5] . ' +45 minutes'));
            } elseif (in_array($data[8], ['assigned', 'confirmed', 'in_progress'])) {
                $ride->setAssignedAt(new \DateTimeImmutable('-1 hour'));
            }

            $manager->persist($ride);

            // Fake notifications for demo
            if ($data[8] === 'completed' || in_array($data[8], ['assigned', 'confirmed'])) {
                $notif = new Notification();
                $notif->setRide($ride)->setType(Notification::TYPE_RIDE_CREATED)
                    ->setChannel(Notification::CHANNEL_EMAIL)
                    ->setRecipientType(Notification::RECIPIENT_ADMIN)
                    ->setRecipientEmail('admin@ecofasthotel.com')
                    ->setSubject("Nouvelle course #{$ride->getReference()}")
                    ->setContent('Email notification content')
                    ->setStatus(Notification::STATUS_SENT)
                    ->setSentAt(new \DateTimeImmutable());
                $manager->persist($notif);

                if ($data[11]) {
                    $notif2 = new Notification();
                    $notif2->setRide($ride)->setType(Notification::TYPE_DRIVER_ASSIGNED)
                        ->setChannel(Notification::CHANNEL_SMS)
                        ->setRecipientType(Notification::RECIPIENT_DRIVER)
                        ->setRecipientPhone($data[11]->getPhone())
                        ->setSubject("SMS Mission #{$ride->getReference()}")
                        ->setContent("Mission assignée : {$ride->getClientName()}")
                        ->setStatus(Notification::STATUS_SENT)
                        ->setSentAt(new \DateTimeImmutable());
                    $manager->persist($notif2);
                }
            }
        }

        $manager->flush();

        echo "\n✅ Fixtures chargées avec succès !\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Comptes de démonstration :\n";
        echo "  Admin     : admin@ecofasthotel.com    / ecofasthotel\n";
        echo "  Hôtel     : hotel@ecofasthotel.com    / ecofasthotel\n";
        echo "  Chauffeur : chauffeur@ecofasthotel.com / ecofasthotel\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
}
