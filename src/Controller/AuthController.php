<?php

namespace App\Controller;

use App\Entity\Driver;
use App\Entity\Hotel;
use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        if ($user->hasRole('ROLE_HOTEL_EMPLOYEE')) {
            return $this->redirectToRoute('hotel_new_ride');
        }
        if ($user->hasRole('ROLE_DRIVER')) {
            return $this->redirectToRoute('driver_dashboard');
        }

        return $this->redirectToRoute('app_login');
    }

    // ─── MOT DE PASSE OUBLIÉ ─────────────────────────────────────────────────

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $em): Response
    {
        $sent = false;

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $user  = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user && $user->isActive()) {
                $token = bin2hex(random_bytes(32));
                $user->setResetToken($token);
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $em->flush();

                $resetUrl = $this->generateUrl(
                    'app_reset_password',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $this->sendResetEmail($email, $user->getFirstName(), $resetUrl);
            }

            // On affiche toujours succès (sécurité : ne pas révéler si l'email existe)
            $sent = true;
        }

        return $this->render('auth/forgot_password.html.twig', ['sent' => $sent]);
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = $em->getRepository(User::class)->findOneBy(['resetToken' => $token]);

        $invalid = !$user
            || !$user->getResetTokenExpiresAt()
            || $user->getResetTokenExpiresAt() < new \DateTimeImmutable();

        if ($invalid) {
            return $this->render('auth/reset_password.html.twig', [
                'token'   => $token,
                'invalid' => true,
                'success' => false,
            ]);
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('confirm', '');

            if (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== $confirm) {
                $error = 'Les deux mots de passe ne correspondent pas.';
            } else {
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $em->flush();

                return $this->render('auth/reset_password.html.twig', [
                    'token'   => $token,
                    'invalid' => false,
                    'success' => true,
                ]);
            }
        }

        return $this->render('auth/reset_password.html.twig', [
            'token'   => $token,
            'invalid' => false,
            'success' => false,
            'error'   => $error,
        ]);
    }

    // ─── CRÉATION D'UTILISATEUR (ADMIN) ──────────────────────────────────────

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

            if (in_array($role, ['ROLE_HOTEL', 'ROLE_HOTEL_EMPLOYEE'])) {
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

    #[Route('/admin/utilisateurs/{id}/modifier', name: 'admin_user_edit')]
    public function editUser(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $user->setFirstName($request->request->get('firstName', $user->getFirstName()));
            $user->setLastName($request->request->get('lastName', $user->getLastName()));
            $user->setPhone($request->request->get('phone', $user->getPhone()));

            $newEmail = trim($request->request->get('email', ''));
            if ($newEmail && $newEmail !== $user->getEmail()) {
                $existing = $em->getRepository(User::class)->findOneBy(['email' => $newEmail]);
                if ($existing && $existing->getId() !== $user->getId()) {
                    $error = "Cet email est déjà utilisé par un autre compte.";
                } else {
                    $user->setEmail($newEmail);
                }
            }

            $role = $request->request->get('role');
            if ($role && in_array($role, ['ROLE_ADMIN', 'ROLE_HOTEL', 'ROLE_HOTEL_EMPLOYEE', 'ROLE_DRIVER'])) {
                $user->setRoles([$role]);
            }

            $newPassword = $request->request->get('newPassword', '');
            if (!empty($newPassword)) {
                if (strlen($newPassword) < 8) {
                    $error = 'Le mot de passe doit contenir au moins 8 caractères.';
                } else {
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                }
            }

            if (!$error) {
                $em->flush();
                $this->addFlash('success', "Compte « {$user->getFullName()} » mis à jour.");
                return $this->redirectToRoute('admin_users');
            }
        }

        return $this->render('admin/user_edit.html.twig', [
            'user'  => $user,
            'error' => $error,
        ]);
    }

    #[Route('/admin/utilisateurs/{id}/supprimer', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(int $id, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        // Interdire la suppression de son propre compte
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }

        $name = $user->getFullName();
        $em->remove($user);
        $em->flush();

        $this->addFlash('success', "Compte « $name » supprimé.");
        return $this->redirectToRoute('admin_users');
    }

    // ─── ENVOI EMAIL DE RÉINITIALISATION ────────────────────────────────────

    private function sendResetEmail(string $to, string $firstName, string $resetUrl): void
    {
        $apiKey = $_ENV['RESEND_API_KEY'] ?? '';
        if (!$apiKey || $apiKey === 'change_me') {
            return;
        }

        $appName = 'Cabsolu';
        $subject = 'Réinitialisation de votre mot de passe';

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px">
          <div style="max-width:500px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
            <div style="background:#0d2233;padding:24px 32px">
              <h1 style="color:#5ab82e;margin:0;font-size:22px">$appName</h1>
            </div>
            <div style="padding:32px">
              <h2 style="margin:0 0 16px;color:#1a1a1a">Bonjour $firstName,</h2>
              <p style="color:#555;line-height:1.6">Vous avez demandé la réinitialisation de votre mot de passe.<br>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
              <div style="text-align:center;margin:32px 0">
                <a href="$resetUrl" style="background:#5ab82e;color:white;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px;display:inline-block">
                  Réinitialiser mon mot de passe
                </a>
              </div>
              <p style="color:#888;font-size:13px">Ce lien est valable <strong>1 heure</strong>.<br>Si vous n'avez pas fait cette demande, ignorez cet email.</p>
            </div>
            <div style="background:#f9f9f9;padding:16px 32px;border-top:1px solid #eee">
              <p style="color:#aaa;font-size:12px;margin:0">$appName · Votre service de transport premium</p>
            </div>
          </div>
        </body>
        </html>
        HTML;

        $fromDomain = $_ENV['MAILER_FROM_EMAIL'] ?? 'onboarding@resend.dev';

        $payload = json_encode([
            'from'    => "$appName <$fromDomain>",
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Fallback si domaine non vérifié
        if ($httpCode === 403 && $fromDomain !== 'onboarding@resend.dev') {
            $payload2 = json_encode([
                'from'    => "$appName <onboarding@resend.dev>",
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $html,
            ]);
            $ch2 = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload2,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch2);
            curl_close($ch2);
        }
    }
}
