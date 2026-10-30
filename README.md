# Radar Bons Plans

PWA qui centralise les bons plans courses en France (catalogues, offres de remboursement, bons de réduction,
Shopmium, codes promo, bons plans repérés sur TikTok), affiche leurs dates de validité, alerte avant expiration,
envoie un récap quotidien et suit les économies réalisées.

- **Backend** : Symfony 7.4 (PHP 8.3), API Platform 5 + JWT, Doctrine / PostgreSQL 16, Messenger + Scheduler,
  Notifier (e-mail, Telegram) et Web Push.
- **Frontend** : React 19, Vite, TypeScript, TanStack Query, PWA (service worker Workbox, notifications push).
- **Collecte** : un connecteur par source (`backend/src/Collecte/Connector`), robots.txt respecté, au plus une
  requête toutes les 2 s par domaine, cache HTTP, métadonnées uniquement (toujours un lien vers la source).
  TikTok n'est jamais lu : saisie manuelle via l'oEmbed public.

## Développement

Prérequis : Docker (ou OrbStack) et Node 24. PHP n'est pas nécessaire sur la machine.

Créer d'abord `backend/.env.local` (non versionné) avec vos propres secrets de développement :

```bash
printf 'APP_SECRET=%s\nJWT_PASSPHRASE=%s\n' "$(openssl rand -hex 16)" "$(openssl rand -hex 32)" > backend/.env.local
```

```bash
make up            # PostgreSQL
make migrate       # schéma + sources de référence
make jwt           # clés JWT (backend/config/jwt, jamais commitées)
make utilisateur EMAIL=moi@exemple.fr ADMIN=--admin
make collecte      # première collecte de toutes les sources
make front         # API http://localhost:8088 + PWA http://localhost:5180
make worker        # planning : collecte 6 h, purge 3 h, alertes 12 h, récap à l'heure de chacun
```

Qualité : `make qa` (PHPUnit, PHPStan niveau 8, ESLint, Prettier, Vitest, build). Les connecteurs sont testés
sur des pages **synthétiques** (`backend/tests/fixtures/`, structure des sites, contenus fictifs), toujours
exécutées en CI. Les pages réelles des sites ne sont pas publiées : capturez-les en local dans
`backend/tests/fixtures/reelles/` (`app:fixtures:capturer <url> reelles/<site>/<page>.html`) pour lancer aussi
les tests sur les vraies pages ; sans elles, ces tests sont ignorés.

Commandes utiles (`docker compose run --rm php bin/console …`) :

| Commande | Rôle |
|---|---|
| `app:collecte [source] [--dry-run]` | collecte d'une source ou de toutes |
| `app:alertes` | alertes J-3 / J-1 et « nouveau catalogue » |
| `app:recap:quotidien [email] [--apercu\|--forcer]` | récap quotidien |
| `app:push:generer-cles` | clés VAPID du Web Push |
| `app:telegram:discussions` | identifiants de discussion du bot Telegram |
| `app:utilisateur:creer email [--admin]` | créer un compte ou changer son mot de passe |

## Production (Coolify)

`compose.prod.yaml` décrit 5 services : **nginx** (PWA + relais `/api`), **php** (php-fpm ; applique les migrations
et crée les clés JWT au démarrage), **worker** (file Messenger), **scheduler** (tâches planifiées) et **postgres**.

1. Pousser le dépôt sur GitHub, puis dans Coolify : *New resource → Docker Compose*, fichier `compose.prod.yaml`.
2. Renseigner les variables (voir `.env.example`) ; obligatoires : `APP_SECRET`, `POSTGRES_PASSWORD`,
   `JWT_PASSPHRASE`, `APP_URL_FRONT`. Pour les alertes : `MAILER_DSN`, `TELEGRAM_DSN`, `VAPID_PUBLIC_KEY`,
   `VAPID_PRIVATE_KEY`, `ADMIN_EMAIL`.
3. Attribuer le domaine au service **nginx** (port 80) ; Coolify fournit le HTTPS (requis pour la PWA et le push).
4. Après le premier déploiement : créer le compte administrateur depuis le terminal du service **php** :
   `RADAR_MOT_DE_PASSE='…' bin/console app:utilisateur:creer moi@exemple.fr --admin`.
5. Surveillance : `GET /api/sante` (base joignable, date de la dernière collecte, retard éventuel) et la page
   **Admin** de l'application (état de chaque connecteur, relance manuelle).

Volumes à sauvegarder : `pgdata` (données) et `jwt` (clés ; les perdre déconnecte simplement les utilisateurs).

## Structure

```
backend/   Symfony : Collecte/ (connecteurs), Alerte/, Notification/, Economie/, Region/, ApiResource/, State/
frontend/  PWA React : pages/, components/, api/, lib/
docker/    images php (dev, prod) et nginx
```
