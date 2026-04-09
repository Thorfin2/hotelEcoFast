<?php

namespace App\Controller;

use App\Entity\Driver;
use App\Entity\Hotel;
use App\Entity\Ride;
use App\Entity\User;
use App\Form\DriverAssignType;
use App\Form\DriverType;
use App\Form\HotelType;
use App\Repository\DriverRepository;
use App\Repository\HotelRepository;
use App\Repository\NotificationRepository;
use App\Repository\RideRepository;
use App\Repository\SettingRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function dashboard(
        RideRepository $rideRepo,
        DriverRepository $driverRepo,
        HotelRepository $hotelRepo,
        NotificationRepository $notifRepo
    ): Response {
        $stats = $rideRepo->getStatsForAdmin();
        $pendingRides = $rideRepo->findPending();
        $upcomingRides = $rideRepo->findUpcoming();
        $availableDrivers = $driverRepo->findAvailable();
        $recentNotifications = $notifRepo->findRecent(10);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'pendingRides' => $pendingRides,
            'upcomingRides' => array_slice($upcomingRides, 0, 8),
            'availableDrivers' => $availableDrivers,
            'recentNotifications' => $recentNotifications,
        ]);
    }

    // ─── RIDES ────────────────────────────────────────────────────────────────

    #[Route('/courses', name: 'rides')]
    public function rides(RideRepository $rideRepo, Request $request): Response
    {
        $status = $request->query->get('status');
        $rides = $status
            ? $rideRepo->findBy(['status' => $status], ['pickupDatetime' => 'DESC'])
            : $rideRepo->findBy([], ['pickupDatetime' => 'DESC']);

        return $this->render('admin/rides.html.twig', [
            'rides' => $rides,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/courses/{id}', name: 'ride_detail')]
    public function rideDetail(int $id, EntityManagerInterface $em): Response
    {
        $ride = $em->getRepository(Ride::class)->find($id);
        if (!$ride) throw $this->createNotFoundException();

        return $this->render('admin/ride_detail.html.twig', ['ride' => $ride]);
    }

    #[Route('/courses/{id}/assigner', name: 'assign_driver')]
    public function assignDriver(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications
    ): Response {
        $ride = $em->getRepository(Ride::class)->find($id);
        if (!$ride) throw $this->createNotFoundException();

        $form = $this->createForm(DriverAssignType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $driver = $form->get('driver')->getData();

            // Unassign previous driver if any
            if ($ride->getDriver() && $ride->getDriver() !== $driver) {
                $ride->getDriver()->setStatus(Driver::STATUS_AVAILABLE);
            }

            $ride->setDriver($driver);
            $ride->setStatus(Ride::STATUS_ASSIGNED);
            $ride->setAssignedAt(new \DateTimeImmutable());
            $driver->setStatus(Driver::STATUS_BUSY);

            $em->flush();
            try { $notifications->onDriverAssigned($ride); } catch (\Throwable $e) {}

            $this->addFlash('success', "Chauffeur {$driver->getFullName()} assigné à la course #{$ride->getReference()}.");
            return $this->redirectToRoute('admin_ride_detail', ['id' => $id]);
        }

        return $this->render('admin/assign_driver.html.twig', [
            'ride' => $ride,
            'form' => $form,
        ]);
    }

    #[Route('/courses/{id}/statut/{status}', name: 'update_ride_status', methods: ['POST'])]
    public function updateRideStatus(
        int $id,
        string $status,
        EntityManagerInterface $em,
        NotificationService $notifications
    ): Response {
        $ride = $em->getRepository(Ride::class)->find($id);
        if (!$ride) throw $this->createNotFoundException();

        $allowed = [
            Ride::STATUS_CONFIRMED,
            Ride::STATUS_IN_PROGRESS,
            Ride::STATUS_COMPLETED,
            Ride::STATUS_CANCELLED,
        ];

        if (!in_array($status, $allowed)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('admin_ride_detail', ['id' => $id]);
        }

        $previousStatus = $ride->getStatus();
        $ride->setStatus($status);

        if ($status === Ride::STATUS_IN_PROGRESS) {
            $ride->setStartedAt(new \DateTimeImmutable());
        } elseif ($status === Ride::STATUS_COMPLETED) {
            $ride->setCompletedAt(new \DateTimeImmutable());
            if ($ride->getDriver()) {
                $ride->getDriver()->setStatus(Driver::STATUS_AVAILABLE);
            }
        } elseif ($status === Ride::STATUS_CANCELLED) {
            if ($ride->getDriver()) {
                $ride->getDriver()->setStatus(Driver::STATUS_AVAILABLE);
            }
        }

        $em->flush();

        try {
            match($status) {
                Ride::STATUS_COMPLETED => $notifications->onRideCompleted($ride),
                Ride::STATUS_CANCELLED => $notifications->onRideCancelled($ride),
                default => null,
            };
        } catch (\Throwable $e) {}

        $this->addFlash('success', "Statut mis à jour : {$ride->getStatusLabel()}");
        return $this->redirectToRoute('admin_ride_detail', ['id' => $id]);
    }

    // ─── HOTELS ───────────────────────────────────────────────────────────────

    #[Route('/hotels', name: 'hotels')]
    public function hotels(HotelRepository $hotelRepo): Response
    {
        return $this->render('admin/hotels.html.twig', [
            'hotels' => $hotelRepo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/hotels/{id}/modifier', name: 'hotel_edit')]
    public function editHotel(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $hotel = $em->getRepository(Hotel::class)->find($id);
        if (!$hotel) throw $this->createNotFoundException();

        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', "Hôtel {$hotel->getName()} modifié avec succès.");
            return $this->redirectToRoute('admin_hotels');
        }

        return $this->render('admin/hotel_edit.html.twig', ['form' => $form, 'hotel' => $hotel]);
    }

    #[Route('/hotels/{id}/activer', name: 'hotel_toggle', methods: ['POST'])]
    public function toggleHotel(int $id, EntityManagerInterface $em): Response
    {
        $hotel = $em->getRepository(Hotel::class)->find($id);
        if (!$hotel) throw $this->createNotFoundException();
        $hotel->setIsActive(!$hotel->isActive());
        $em->flush();
        $this->addFlash('success', "Hôtel " . ($hotel->isActive() ? 'activé' : 'désactivé') . ".");
        return $this->redirectToRoute('admin_hotels');
    }

    // ─── DRIVERS ─────────────────────────────────────────────────────────────

    #[Route('/chauffeurs', name: 'drivers')]
    public function drivers(DriverRepository $driverRepo): Response
    {
        return $this->render('admin/drivers.html.twig', [
            'drivers' => $driverRepo->findBy([], ['firstName' => 'ASC']),
        ]);
    }

    #[Route('/chauffeurs/nouveau', name: 'driver_new')]
    public function newDriver(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $driver = new Driver();
        $form = $this->createForm(DriverType::class, $driver);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Create associated user account
            $user = new User();
            $user->setFirstName($driver->getFirstName());
            $user->setLastName($driver->getLastName());
            $user->setEmail($driver->getEmail() ?? strtolower($driver->getFirstName() . '.' . $driver->getLastName() . '@ecofasthotel.com'));
            $user->setPhone($driver->getPhone());
            $user->setRoles(['ROLE_DRIVER']);
            $user->setPassword($hasher->hashPassword($user, 'ecofasthotel'));
            $driver->setUser($user);

            $em->persist($user);
            $em->persist($driver);
            $em->flush();

            $this->addFlash('success', "Chauffeur {$driver->getFullName()} créé. Login: {$user->getEmail()} / Mot de passe: ecofasthotel");
            return $this->redirectToRoute('admin_drivers');
        }

        return $this->render('admin/driver_new.html.twig', ['form' => $form]);
    }

    #[Route('/chauffeurs/{id}/modifier', name: 'driver_edit')]
    public function editDriver(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $driver = $em->getRepository(Driver::class)->find($id);
        if (!$driver) throw $this->createNotFoundException();

        $form = $this->createForm(DriverType::class, $driver);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', "Chauffeur {$driver->getFullName()} modifié.");
            return $this->redirectToRoute('admin_drivers');
        }

        return $this->render('admin/driver_edit.html.twig', ['form' => $form, 'driver' => $driver]);
    }

    #[Route('/chauffeurs/{id}/statut', name: 'driver_status', methods: ['POST'])]
    public function updateDriverStatus(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $driver = $em->getRepository(Driver::class)->find($id);
        if (!$driver) throw $this->createNotFoundException();

        $status = $request->request->get('status');
        if (in_array($status, [Driver::STATUS_AVAILABLE, Driver::STATUS_BUSY, Driver::STATUS_OFFLINE])) {
            $driver->setStatus($status);
            $em->flush();
        }

        return $this->redirectToRoute('admin_drivers');
    }

    // ─── COMMISSIONS ─────────────────────────────────────────────────────────

    #[Route('/commissions', name: 'commissions')]
    public function commissions(EntityManagerInterface $em, Request $request): Response
    {
        $monthParam = $request->query->get('month', date('Y-m'));
        $month = \DateTime::createFromFormat('Y-m', $monthParam) ?: new \DateTime();
        $hotels = $em->getRepository(Hotel::class)->findBy(['isActive' => true]);

        $data = [];
        foreach ($hotels as $hotel) {
            $rides = $em->getRepository(Ride::class)->findBy([
                'hotel' => $hotel,
                'status' => Ride::STATUS_COMPLETED,
            ]);
            $monthlyRides = array_filter($rides, fn($r) => $r->getPickupDatetime()->format('Y-m') === $month->format('Y-m'));
            $total = array_reduce($monthlyRides, fn($c, $r) => $c + (float) $r->getHotelCommission(), 0.0);
            $data[] = [
                'hotel' => $hotel,
                'rides' => array_values($monthlyRides),
                'total' => $total,
                'allTimeTotal' => array_reduce($rides, fn($c, $r) => $c + (float) $r->getHotelCommission(), 0.0),
            ];
        }

        return $this->render('admin/commissions.html.twig', [
            'data' => $data,
            'month' => $month,
        ]);
    }

    // ─── NOTIFICATIONS ────────────────────────────────────────────────────────

    #[Route('/notifications', name: 'notifications')]
    public function notifications(NotificationRepository $notifRepo): Response
    {
        return $this->render('admin/notifications.html.twig', [
            'notifications' => $notifRepo->findRecent(50),
        ]);
    }

    // ─── SETTINGS (Tarification) ─────────────────────────────────────────────

    #[Route('/parametres', name: 'settings')]
    public function settings(Request $request, SettingRepository $settingRepo): Response
    {
        $settings = $settingRepo->getAll();

        if ($request->isMethod('POST')) {
            $pricePerKm = $request->request->get('price_per_km', '2.50');
            $minimumPrice = $request->request->get('minimum_price', '25.00');

            $settingRepo->setValue('price_per_km', $pricePerKm, 'Prix par kilomètre (€)');
            $settingRepo->setValue('minimum_price', $minimumPrice, 'Prix minimum (€)');

            $this->addFlash('success', 'Paramètres de tarification mis à jour.');
            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings.html.twig', [
            'pricePerKm' => $settingRepo->getValue('price_per_km', '2.50'),
            'minimumPrice' => $settingRepo->getValue('minimum_price', '25.00'),
        ]);
    }

    // ─── TEST EMAIL ──────────────────────────────────────────────────────────

    #[Route('/test-email', name: 'test_email')]
    public function testEmail(Request $request): Response
    {
        $sent = false;
        $error = '';
        $to = '';

        if ($request->isMethod('POST')) {
            $to = $request->request->get('to', '');

            $consolePath = $this->getParameter('kernel.project_dir') . '/bin/console';
            $cmd = 'php ' . escapeshellarg($consolePath) . ' app:mail:test ' . escapeshellarg($to) . ' 2>&1';

            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);
            $debug = implode("\n", $output);

            if ($exitCode === 0) {
                $sent = true;
            } else {
                $error = $debug ?: 'Erreur inconnue';
            }
        }

        return $this->render('admin/test_email.html.twig', [
            'sent' => $sent,
            'error' => $error,
            'to' => $to,
            'mailerHost' => $this->getParameter('mailer.host'),
            'mailerPort' => $this->getParameter('mailer.port'),
            'mailerUsername' => $this->getParameter('mailer.username'),
            'mailerPassword' => $this->getParameter('mailer.password'),
            'mailerEncryption' => $this->getParameter('mailer.encryption'),
        ]);
    }

}
