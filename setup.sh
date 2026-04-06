#!/bin/bash
# ══════════════════════════════════════════════════════════════════════
#  EcoFast Hotel — Script d'installation automatique
# ══════════════════════════════════════════════════════════════════════

set -e

CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

echo ""
echo -e "${CYAN}${BOLD}══════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}${BOLD}  🚗  EcoFast Hotel — Installation                    ${NC}"
echo -e "${CYAN}${BOLD}══════════════════════════════════════════════════════${NC}"
echo ""

# Check PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP n'est pas installé. Installez PHP 8.1+${NC}"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo -e "${GREEN}✅ PHP ${PHP_VERSION} détecté${NC}"

# Check Composer
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}⚠️  Composer non trouvé. Installation...${NC}"
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi
echo -e "${GREEN}✅ Composer détecté${NC}"

# Check MySQL
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}⚠️  MySQL non trouvé. Assurez-vous que MySQL est installé et démarré.${NC}"
fi

echo ""
echo -e "${BOLD}━━━ Étape 1 : Configuration ━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Copy .env if needed
if [ ! -f .env.local ]; then
    cp .env .env.local
    echo -e "${GREEN}✅ Fichier .env.local créé${NC}"
fi

echo ""
echo -e "${YELLOW}📝 Configurez votre .env.local avec :${NC}"
echo -e "   DATABASE_URL=\"mysql://root:@127.0.0.1:3306/ecofasthotel\""
echo -e "   MAILER_USERNAME=votre.email@gmail.com"
echo -e "   MAILER_PASSWORD=votre_mot_de_passe_application"
echo ""
read -p "$(echo -e ${YELLOW})Appuyez sur ENTRÉE une fois .env.local configuré...$(echo -e ${NC})" -r

echo ""
echo -e "${BOLD}━━━ Étape 2 : Dépendances Composer ━━━━━━━━━━━━━━━━━━${NC}"
composer install --optimize-autoloader
echo -e "${GREEN}✅ Dépendances installées${NC}"

echo ""
echo -e "${BOLD}━━━ Étape 3 : Base de données ━━━━━━━━━━━━━━━━━━━━━━━${NC}"

php bin/console doctrine:database:create --if-not-exists
echo -e "${GREEN}✅ Base de données créée${NC}"

php bin/console doctrine:migrations:migrate --no-interaction
echo -e "${GREEN}✅ Migrations exécutées${NC}"

echo ""
echo -e "${BOLD}━━━ Étape 4 : Données de démonstration ━━━━━━━━━━━━━━${NC}"
php bin/console doctrine:fixtures:load --no-interaction
echo -e "${GREEN}✅ Fixtures chargées${NC}"

echo ""
echo -e "${BOLD}━━━ Étape 5 : Cache ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
php bin/console cache:clear
echo -e "${GREEN}✅ Cache vidé${NC}"

echo ""
echo -e "${CYAN}${BOLD}══════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}${BOLD}  ✅ Installation terminée !                          ${NC}"
echo -e "${CYAN}${BOLD}══════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${BOLD}🚀 Démarrer le serveur :${NC}"
echo -e "   ${CYAN}php -S localhost:8000 -t public/${NC}"
echo -e "   ou  ${CYAN}symfony server:start${NC}"
echo ""
echo -e "${BOLD}🔑 Comptes de démonstration :${NC}"
echo -e "   ${YELLOW}⚙️  Admin     :${NC} admin@ecofasthotel.com     / ecofasthotel"
echo -e "   ${YELLOW}🏨 Hôtel     :${NC} hotel@ecofasthotel.com     / ecofasthotel"
echo -e "   ${YELLOW}🚘 Chauffeur :${NC} chauffeur@ecofasthotel.com / ecofasthotel"
echo ""
echo -e "   🌐 URL : ${CYAN}http://localhost:8000${NC}"
echo ""
