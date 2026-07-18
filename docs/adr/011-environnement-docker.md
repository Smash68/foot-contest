# 011. Environnement Docker : container `app` CLI et gestion de `vendor/`

Date: 2026-07-18
Status: Accepted

## Contexte

Jusqu'ici, seul `compose.yaml` généré par la recipe Flex de `doctrine/doctrine-bundle` existait (service `database` PostgreSQL), tout le reste (PHP, Composer) dépendant de l'installation locale du poste de développement. Objectif : faire tourner toute l'app sous Docker pour ne plus dépendre de cette config locale — un poste avec seulement Docker installé doit pouvoir lancer les tests et l'app.

Deux questions se posaient : (1) le container applicatif doit-il déjà exposer un vrai serveur web, sachant que l'app est vouée à devenir une API exposée en continu (priorité 5c du ROADMAP) ? (2) comment gérer `vendor/` pour qu'aucune installation locale de PHP/Composer ne soit nécessaire, sans casser l'indexation PhpStorm (utilisé pour ce projet, voir `.idea/`) ?

## Décision

### 1. Container `app` en PHP-CLI seul, pas de serveur web dédié

`Dockerfile` : `php:8.4-cli-alpine` + extensions `pdo_pgsql`/`intl`/`opcache`, Composer copié depuis l'image officielle. Le container reste vivant via `CMD ["tail", "-f", "/dev/null"]` et sert de base à `docker compose exec` (tests, `bin/console`) ; l'exposition HTTP ponctuelle se fait à la demande via `php -S 0.0.0.0:8000 -t public` (port 8000 mappé dans `compose.yaml`).

Alternative rejetée : bootstrapper tout de suite FrankenPHP + Caddy (recipe Docker officielle Symfony). Écarté par choix de ne pas anticiper un besoin non encore présent — aucun front ni déploiement continu n'existe à ce stade du ROADMAP. La migration future est peu coûteuse : le `Dockerfile` CLI reste la base commune (runtime PHP, extensions, code), seul le `CMD`/entrypoint change pour invoquer un vrai serveur à la place de `php -S`. `database`, le réseau, les volumes et les variables d'env ne bougent pas.

### 2. `vendor/` bind-monté depuis l'hôte, `composer install` relancé à chaque démarrage via `entrypoint.sh`

`compose.yaml` bind-monte tout le repo (`.:/app`) dans le service `app`. `docker/entrypoint.sh` exécute `composer install --no-interaction --prefer-dist` avant toute commande (`exec "$@"`), à chaque démarrage du container.

Alternative rejetée : volume nommé Docker pour `vendor/`, peuplé une seule fois au build de l'image. Plus performant en I/O et plus proche d'une image immuable/reproductible, mais `vendor/` ne serait alors jamais visible sur le disque hôte — PhpStorm ne l'indexerait pas nativement (nécessiterait de configurer un interpréteur distant Docker Compose côté IDE). Le bind-mount du repo entier a été préféré pour préserver l'expérience IDE sans configuration supplémentaire.

Conséquence directe du bind-mount : `composer install` ne peut pas se limiter au build de l'image, car le bind-mount (repo hôte, sans `vendor/` au premier démarrage) masquerait au runtime le `vendor/` généré à l'image. D'où l'exécution à chaque démarrage plutôt qu'au build — léger coût de démarrage (Composer vérifie le hash de `composer.lock`, quasi instantané si rien n'a changé), mais cohérent avec l'objectif de zéro étape manuelle après un `git pull` qui changerait `composer.lock`.

### 3. `DATABASE_URL` du service `app` réécrite en variable d'environnement Compose, pas dans `.env`

`compose.yaml` fixe `DATABASE_URL` pour le service `app` en réutilisant les mêmes variables (`POSTGRES_USER`/`POSTGRES_PASSWORD`/`POSTGRES_DB`/`POSTGRES_VERSION`) que le service `database`, avec `database` (nom du service) comme host plutôt que `127.0.0.1`. Les variables d'environnement réelles gagnant sur les fichiers `.env` (comportement documenté par Symfony Dotenv en tête de `.env`), `.env` garde sa valeur `127.0.0.1` pour un usage local hors Docker sans avoir besoin d'être modifié.

## Conséquences

- `docker compose up -d app` est le point d'entrée unique pour développer : démarre `database` (health-checked) puis `app`, `composer install` s'exécute automatiquement.
- Commandes du quotidien via `docker compose exec app <commande>` (`vendor/bin/phpunit`, `bin/console ...`) — voir section Commands de `CLAUDE.md`.
- `.dockerignore` exclut `vendor/`, `var/`, `.git/`, `.idea/`, `.phpunit.cache/` du contexte de build (le `Dockerfile` ne copie pas le code applicatif, il repose entièrement sur le bind-mount).
- La base de données du service `database` est partagée entre l'usage Docker et un éventuel usage local historique (`127.0.0.1:5432` exposé via `compose.override.yaml`) — pas de double environnement PostgreSQL à maintenir.