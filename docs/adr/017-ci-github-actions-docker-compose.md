# 017. CI GitHub Actions : rejoue l'environnement Docker Compose local

Date: 2026-07-18
Status: Accepted

## Contexte

Aucune CI n'existait. Le repo tourne entièrement sous Docker en local depuis l'ADR 011 (objectif explicite : ne plus dépendre d'une config locale, un seul chemin d'exécution pour dev et tests).

## Décision

### 1. `docker compose` en CI, pas le setup natif GitHub Actions (`shivammathur/setup-php` + service Postgres)

`.github/workflows/ci.yml` reproduit exactement le flux local : build de l'image `app`, démarrage (`database` en dépendance via `depends_on`/healthcheck), création + migration de la base de test, `vendor/bin/phpunit`. Alternative rejetée : action PHP native + service Postgres GitHub Actions — plus rapide (pas de build d'image), mais crée un second chemin d'exécution indépendant du `Dockerfile` (risque de divergence de version PHP/extensions entre CI et local, exactement ce que le passage à Docker visait à éliminer).

### 2. Périmètre : tests uniquement, pas d'analyse statique

Aucun outil d'analyse statique (phpstan, psalm, php-cs-fixer) n'est configuré dans le projet à ce jour — les ajouter est un chantier à part entière, pas un sous-produit de la mise en place de la CI.

### 3. Attente explicite de `vendor/autoload.php`, pas de `sleep` fixe

`docker compose up -d app` retourne dès que le process PID 1 du container est lancé (`entrypoint.sh`), pas une fois `composer install` terminé (voir ADR 011 pour le pourquoi de ce `composer install` à chaque démarrage). Sur un runner CI sans cache, cet install est long (contrairement au poste de dev où `vendor/` existe déjà) — une commande exécutée trop tôt via `docker compose exec` échouerait sur un `vendor/` incomplet. Boucle `until ... test -f vendor/autoload.php` plutôt qu'un délai fixe, qui serait soit trop court (flaky) soit trop long (CI ralentie inutilement).

### 4. Cache `vendor/` via `actions/cache`, clé sur le hash de `composer.lock`

`vendor/` est bind-monté (pas un volume nommé, voir ADR 011) — le restaurer depuis le cache GitHub Actions avant `docker compose up` rend `composer install` de l'entrypoint quasi instantané (Composer ne réinstalle rien si le lock n'a pas changé), sans changement de mécanisme par rapport au poste de dev.

## Conséquences

- Un seul `Dockerfile` fait autorité pour l'environnement d'exécution, en CI comme en local — aucune version PHP/extension à synchroniser manuellement entre deux configs.
- `docker compose exec` utilise systématiquement `-T` (pas de TTY) en CI, requis en environnement non interactif.
- Vérifié en local en rejouant la séquence exacte du workflow (build → up → attente → migration → tests) avant de pousser.