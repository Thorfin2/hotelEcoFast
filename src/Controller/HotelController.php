<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Form\RideType;
use App\Repository\RideRepository;
use App\Repository\SettingRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/hotel', name: 'hotel_')]
class HotelController extends AbstractController
{
    private function getHotel(): \App\Entity\Hotel
    {
        /** @var User $user */
        $user = $this->getUser();
        $hotel = $user->getHotel();
        if (!$hotel) {
            throw $this->createAccessDeniedException('Aucun hôtel associé à ce compte.');
        }
        return $hotel;
    }

    #[Route('', name: 'dashboard')]
    public function dashboard(RideRepository $rideRepo): Response
    {
        $hotel = $this->getHotel();
        $stats = $rideRepo->getStatsForHotel($hotel);
        $upcomingRides = $rideRepo->findUpcoming();
        $recentRides = $rideRepo->findByHotel($hotel);
        $recentRides = array_slice($recentRides, 0, 5);

        return $this->render('hotel/dashboard.html.twig', [
            'hotel' => $hotel,
            'stats' => $stats,
            'upcomingRides' => $upcomingRides,
            'recentRides' => $recentRides,
        ]);
    }

    #[Route('/courses', name: 'rides')]
    public function rides(RideRepository $rideRepo, Request $request): Response
    {
        $hotel = $this->getHotel();
        $status = $request->query->get('status');
        $rides = $rideRepo->findByHotel($hotel, $status ?: null);

        return $this->render('hotel/rides.html.twig', [
            'hotel' => $hotel,
            'rides' => $rides,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/courses/nouvelle', name: 'new_ride')]
    public function newRide(
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notifications,
        SettingRepository $settingRepo
    ): Response {
        $hotel = $this->getHotel();

        $googleMapsApiKey = $this->getParameter('google_maps.api_key');
        $brackets = [
            'bracket_0_3_5' => (float) $settingRepo->getValue('bracket_0_3_5',  '20'),
            'bracket_3_5_5' => (float) $settingRepo->getValue('bracket_3_5_5',  '25'),
            'bracket_5_10'  => (float) $settingRepo->getValue('bracket_5_10',   '30'),
            'bracket_10_15' => (float) $settingRepo->getValue('bracket_10_15',  '40'),
            'bracket_15_17' => (float) $settingRepo->getValue('bracket_15_17',  '45'),
            'rate_per_km'   => (float) $settingRepo->getValue('rate_per_km',    '3'),
        ];

        $ride = new Ride();
        $ride->setHotel($hotel);
        $form = $this->createForm(RideType::class, $ride);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculate commissions
            if ($ride->getPrice()) {
                $ride->calculateCommissions((float) $hotel->getCommissionRate());
            }

            $em->persist($ride);
            $em->flush();

            // Send notifications (try-catch pour ne jamais bloquer)
            try { $notifications->onRideCreated($ride); } catch (\Throwable $e) {}

            $this->addFlash('success', "Course #{$ride->getReference()} créée avec succès.");
            return $this->redirectToRoute('hotel_ride_detail', ['id' => $ride->getId()]);
        }

        return $this->render('hotel/new_ride.html.twig', [
            'hotel'           => $hotel,
            'form'            => $form,
            'brackets'        => $brackets,
            'googleMapsApiKey'=> $googleMapsApiKey,
        ]);
    }

    #[Route('/courses/{id}', name: 'ride_detail')]
    public function rideDetail(int $id, EntityManagerInterface $em): Response
    {
        $hotel = $this->getHotel();
        $ride = $em->getRepository(Ride::class)->find($id);

        if (!$ride || $ride->getHotel() !== $hotel) {
            throw $this->createNotFoundException('Course introuvable.');
        }

        return $this->render('hotel/ride_detail.html.twig', [
            'hotel' => $hotel,
            'ride' => $ride,
        ]);
    }

    #[Route('/courses/{id}/annuler', name: 'cancel_ride', methods: ['POST'])]
    public function cancelRide(
        int $id,
        EntityManagerInterface $em,
        NotificationService $notifications
    ): Response {
        $hotel = $this->getHotel();
        $ride = $em->getRepository(Ride::class)->find($id);

        if (!$ride || $ride->getHotel() !== $hotel) {
            throw $this->createNotFoundException();
        }

        if (!in_array($ride->getStatus(), [Ride::STATUS_PENDING, Ride::STATUS_ASSIGNED])) {
            $this->addFlash('error', 'Impossible d\'annuler une course en cours ou terminée.');
            return $this->redirectToRoute('hotel_ride_detail', ['id' => $id]);
        }

        $ride->setStatus(Ride::STATUS_CANCELLED);
        $em->flush();

        try { $notifications->onRideCancelled($ride); } catch (\Throwable $e) {}
        $this->addFlash('success', "Course #{$ride->getReference()} annulée.");

        return $this->redirectToRoute('hotel_rides');
    }

    #[Route('/commissions', name: 'commissions')]
    public function commissions(
        Request $request,
        RideRepository $rideRepo,
        EntityManagerInterface $em,
        NotificationService $notifications
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_HOTEL');
        $hotel = $this->getHotel();

        $monthParam = $request->query->get('month', date('Y-m'));
        $month = \DateTime::createFromFormat('Y-m', $monthParam);
        if (!$month) {
            $month = new \DateTime();
        }

        $rides = $rideRepo->findForMonth($hotel, $month);
        $total = array_reduce($rides, fn($carry, $ride) => $carry + (float) $ride->getHotelCommission(), 0.0);

        // Generate and send report if requested
        if ($request->query->get('send_report') && !empty($rides)) {
            try { $notifications->sendMonthlyCommissionReport($rides[0], $rides, $total, $month); } catch (\Throwable $e) {}
            $this->addFlash('success', 'Le relevé de commissions a été envoyé par email.');
        }

        return $this->render('hotel/commissions.html.twig', [
            'hotel' => $hotel,
            'rides' => $rides,
            'total' => $total,
            'month' => $month,
        ]);
    }
}
