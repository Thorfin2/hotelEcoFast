<?php

declare(strict_types=1);

// Ancienne page de test : la config SMTP passe désormais par Symfony.
// Redirection vers l’outil admin (connexion requise).
header('Location: /admin/test-email', true, 302);
exit;
