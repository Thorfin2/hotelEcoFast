# 🚗 EcoFast Hotel — Système de Transport Hôtelier

Application complète de gestion de transport haut de gamme pour hôtels, chauffeurs et administrateurs, avec notifications automatiques par email (PHPMailer) et SMS simulé.

## Stack technique

- **Backend** : PHP 8.1 + Symfony 6.4 LTS
- **Base de données** : MySQL 8.0 (Doctrine ORM)
- **Frontend** : Tailwind CSS + Alpine.js (via CDN)
- **Emails** : PHPMailer 6.x (SMTP)
- **Authentification** : Symfony Security (3 rôles)

## Installation rapide

```bash
# 1. Configurer l'environnement
cp .env .env.local
# Éditez .env.local avec vos paramètres DB et SMTP

# 2. Lancer le script d'installation
chmod +x setup.sh && ./setup.sh
```

### Installation manuelle

```bash
# Dépendances
composer install

# Base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# Lancer le serveur
php -S localhost:8000 -t public/
```

## Comptes de démonstration

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| ⚙️ Administrateur | admin@ecofasthotel.com | ecofasthotel |
| 🏨 Hôtel (Ritz Paris) | hotel@ecofasthotel.com | ecofasthotel |
| 🚘 Chauffeur | chauffeur@ecofasthotel.com | ecofasthotel |

## Configuration email (PHPMailer)

Dans `.env.local` :
```env
MAILER_HOST=smtp.gmail.com
MAILER_PORT=587
MAILER_USERNAME=votre.email@gmail.com
MAILER_PASSWORD=votre_mot_de_passe_app   # Mot de passe d'application Google
MAILER_FROM_EMAIL=noreply@ecofasthotel.com
MAILER_FROM_NAME="EcoFast Hotel"
MAILER_ENCRYPTION=tls
```

> **Gmail** : Activez l'authentification à 2 facteurs, puis créez un [mot de passe d'application](https://myaccount.google.com/apppasswords).

## Fonctionnalités

### 🏨 Interface Hôtel
- Dashboard avec statistiques temps réel
- Création de courses (client, trajet, prix, passagers, vol)
- Suivi des courses par statut
- Commissions mensuelles avec envoi de relevé PDF par email

### ⚙️ Interface Admin
- Vue d'ensemble avec alertes (courses sans chauffeur)
- Assignation de chauffeurs
- Gestion des hôtels partenaires et taux de commission
- Gestion des chauffeurs (statut, véhicule)
- Historique complet des notifications

### 🚘 Application Chauffeur
- Liste des missions par statut
- Détail de la mission avec boutons d'action
- Confirmer → Démarrer → Terminer la course
- Gestion du statut (disponible/hors ligne)

### 📬 Notifications automatiques (PHPMailer)

| Déclencheur | Admin | Hôtel | Chauffeur | Client |
|-------------|-------|-------|-----------|--------|
| Course créée | ✉️ Email | 💬 SMS | — | ✉️ Email |
| Chauffeur assigné | — | ✉️ Email | ✉️ Email + 💬 SMS | ✉️ Email |
| Course confirmée | — | ✉️ Email + 💬 SMS | — | — |
| Course démarrée | — | 💬 SMS | — | 💬 SMS |
| Course terminée | — | ✉️ Email (commission) | — | 💬 SMS |
| Relevé mensuel | — | ✉️ Email PDF | — | — |

## Structure du projet

```
src/
├── Controller/
│   ├── AuthController.php      # Login, logout, redirection
│   ├── HotelController.php     # Interface hôtel
│   ├── AdminController.php     # Interface admin
│   └── DriverController.php    # Interface chauffeur
├── Entity/
│   ├── User.php                # Compte utilisateur (3 rôles)
│   ├── Hotel.php               # Hôtel partenaire
│   ├── Driver.php              # Chauffeur
│   ├── Ride.php                # Course (6 statuts)
│   └── Notification.php        # Log des notifications
├── Service/
│   └── NotificationService.php  # PHPMailer + templates email HTML
├── Form/                        # Formulaires Symfony
├── Repository/                  # Requêtes Doctrine
└── DataFixtures/
    └── AppFixtures.php          # Données de démonstration
```

## Cycle de vie d'une course

```
PENDING → ASSIGNED → CONFIRMED → IN_PROGRESS → COMPLETED
   ↓          ↓           ↓                        ↓
CANCELLED  CANCELLED   (hôtel notifié)      (commission calculée)
```
