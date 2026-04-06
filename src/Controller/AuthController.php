<?php

namespace App\Controller;

use App\Entity\Driver;
use App\Entity\Hotel;
use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\DriverRepository;
use App\Repository\HotelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_after_login');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_redirect_after_login');
        }
        return $this->redirectToRoute('app_login');
    }

    #[Route('/redirect', name: 'app_redirect_after_login')]
    public function redirectAfterLogin(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->hasRole('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }
        if ($user->hasRole('ROLE_HOTEL')) {
            return $this->redirectToRoute('hotel_dashboard');
        }
        if ($user->hasRole('ROLE_DRIVER')) {
            return $this->redirectToRoute('driver_dashboard');
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route('/admin/utilisateurs/nouveau', name: 'admin_user_new')]
    public function newUser(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('roles')->getData();
            $user->setRoles([$role]);
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));

            $em->persist($user);

            if ($role === 'ROLE_HOTEL') {
                $hotel = new Hotel();
                $hotel->setName($user->getFullName() . ' Hotel');
                $hotel->setAddress('À compléter');
                $hotel->setCity('Paris');
                $hotel->setUser($user);
                $em->persist($hotel);
            } elseif ($role === 'ROLE_DRIVER') {
                $driver = new Driver();
                $driver->setFirstName($user->getFirstName());
                $driver->setLastName($user->getLastName());
                $driver->setPhone($user->getPhone() ?? '');
                $driver->setVehicleModel('À compléter');
                $driver->setVehicleType('berline_premium');
                $driver->setLicensePlate('À compléter');
                $driver->setUser($user);
                $em->persist($driver);
            }

            $em->flush();
            $this->addFlash('success', "Utilisateur {$user->getFullName()} créé avec succès.");
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_new.html.twig', ['form' => $form]);
    }

    #[Route('/admin/utilisateurs', name: 'admin_users')]
    public function listUsers(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $users = $em->getRepository(User::class)->findAll();
        return $this->render('admin/users.html.twig', ['users' => $users]);
    }
}
