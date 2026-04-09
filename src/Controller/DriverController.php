<?php

namespace App\Controller;

use App\Entity\Driver;
use App\Entity\Ride;
use App\Entity\User;
use App\Repository\RideRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chauffeur', name: 'driver_')]
class DriverController extends AbstractController
{
    private function getDriver(): Driver
    {
        /** @var User $user */
        $user = $this->getUser();
        $driver = $user->getDriver();
        if (!$driver) {
            throw $this->createAccessDeniedException('Aucun profil chauffeur associé.');
        }
        return $driver;
    }

    #[Route('', name: 'dashboard')]
    public function dashboard(RideRepository $rideRepo): Response
    {
        $driver = $this->getDriver();

        $activeMissions = $rideRepo->findByDriver($driver, Ride::STATUS_CONFIRMED);
        $assignedMissions = $rideRepo->findByDriver($driver, Ride::STATUS_ASSIGNED);
        $inProgressMissions = $rideRepo->findByDriver($driver, Ride::STATUS_IN_PROGRESS);
        $completedCount = count($rideRepo->findByDriver($driver, Ride::STATUS_COMPLETED));

        return $this->render('driver/dashboard.html.twig', [
            'driver' => $driver,
            'activeMissions' => array_merge($assignedMissions, $activeMissions, $inProgressMissions),
            'completedCount' => $completedCount,
        ]);
    }

    #[Route('/missions', name: 'missions')]
    public function missions(RideRepository $rideRepo, Request $request): Response
    {
        $driver = $this->getDriver();
        $status = $request->query->get('status');
        $rides = $rideRepo->findByDriver($driver, $status ?: null);

        return $this->render('driver/missions.html.twig', [
            'driver' => $driver,
            'rides' => $rides,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/missions/{id}', name: 'mission_detail')]
    public function missionDetail(int $id, EntityManagerInterface $em): Response
    {
        $driver = $this->getDriver();
        $ride = $em->getRepository(Ride::class)->find($id);

        if (!$ride || $ride->getDriver() !== $driver) {
            throw $this->createNotFoundException('Mission introuvable.');
        }

        return $this->render('driver/mission_detail.html.twig', [
            'driver' => $driver,
            'ride' => $ride,
        ]);
    }

    #[Route('/missions/{id}/confirmer', name: 'confirm_mission', methods: ['POST'])]
    public function confirmMission(
        int $id,
        EntityManagerInterface $em,
        NotificationService $notifications
    ): Response {
        $driver = $this->getDriver();
        $ride = $em->getRepository(Ride::class)->find($id);

        if (!$ride || $ride->getDriver() !== $driver || $ride->getStatus() !== Ride::STATUS_ASSIGNED) {
            $this->addFlash('error', 'Impossible de confirmer cette mission.');
            return $this->redirectToRoute('driver_missions');
        }

        $ride->setStatus(Ride::STATUS_CONFIRMED);
        $em->flush();

        try { $notifications->onRideConfirmed($ride); } catch (\Throwable $e) {}
        $this->addFlash('success', "Mission #{$ride->getReference()} confirmée.");

        return $this->redirectToRoute('driver_mission_detail', ['id' => $id]);
    }

    #[Route('/missions/{id}/demarrer', name: 'start_mission', methods: ['POST'])]
    public function startMission(
        int $id,
        EntityManagerInterface $em,
        NotificationService $notifications
    ): Response {
        $driver = $this->getDriver();
        $ride = $em->getRepository(Ride::class)->find($id);

        if (!$ride || $ride->getDriver() !== $driver || $ride->getStatus() !== Ride::STATUS_CONFIRMED) {
            $this->addFlash('error', 'Impossible de démarrer cette mission.');
            return $this->redirectToRoute('driver_missions');
        }

        $ride->setStatus(Ride::STATUS_IN_PROGRESS);
        $ride->setStartedAt(new \DateTimeImmutable());
        $em->flush();

        try { $notifications->onRideStarted($ride); } catch (\Throwable $e) {}
        $this->addFlash('success', "Mission #{$ride->getReference()} démarrée.");

        return $this->redirectToRoute('driver_mission_detail', ['id' => $id]);
    }

    #[Route('/missions/{id}/terminer', name: 'complete_mission', methods: ['POST'])]
    public function completeMission(
        int $id,
        EntityManagerInterface $em,
        NotificationService $notifications
    ): Response {
        $driver = $this->getDriver();
        $ride = $em->getRepository(Ride::class)->find($id);

        if (!$ride || $ride->getDriver() !== $driver || $ride->getStatus() !== Ride::STATUS_IN_PROGRESS) {
            $this->addFlash('error', 'Impossible de terminer cette mission.');
            return $this->redirectToRoute('driver_missions');
        }

        $ride->setStatus(Ride::STATUS_COMPLETED);
        $ride->setCompletedAt(new \DateTimeImmutable());
        $driver->setStatus(Driver::STATUS_AVAILABLE);
        $em->flush();

        try { $notifications->onRideCompleted($ride); } catch (\Throwable $e) {}
        $this->addFlash('success', "Mission #{$ride->getReference()} terminée avec succès.");

        return $this->redirectToRoute('driver_missions');
    }

    #[Route('/profil', name: 'profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        $driver = $this->getDriver();

        if ($request->isMethod('POST')) {
            $status = $request->request->get('status');
            if (in_array($status, [Driver::STATUS_AVAILABLE, Driver::STATUS_OFFLINE])) {
                $driver->setStatus($status);
                $em->flush();
                $this->addFlash('success', 'Statut mis à jour : ' . $driver->getStatusLabel());
            }
            return $this->redirectToRoute('driver_profile');
        }

        return $this->render('driver/profile.html.twig', ['driver' => $driver]);
    }
}
