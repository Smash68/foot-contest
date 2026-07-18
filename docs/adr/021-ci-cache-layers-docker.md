# 021. Cache des layers Docker en CI via GitHub Actions

Date: 2026-07-18
Status: Accepted

## Contexte

Suite à l'ADR 017 (CI rejoue `docker compose` en local), le build de l'image `app` représentait ~83% du temps de run (3m14s sur 3m52s au total) : chaque run recompilait Xdebug depuis les sources (`pecl install`) et réinstallait tous les paquets `apk` (`icu-dev`, `postgresql-dev`, `linux-headers`, `$PHPIZE_DEPS`) sans aucun cache entre les runs, `docker compose build` seul ne sachant pas persister de cache de layers entre exécutions d'un runner GitHub Actions (chaque run démarre sur une machine vierge).

## Décision

Remplacement de l'étape `docker compose build app` par `docker/setup-buildx-action` + `docker/build-push-action`, avec `cache-from: type=gha` / `cache-to: type=gha,mode=max` — persiste les layers de build dans le cache GitHub Actions entre les runs. `load: true` charge l'image buildée dans le daemon Docker local du runner, sous le tag exact que `docker compose` attend (`foot-contest-app:latest`, garanti par `COMPOSE_PROJECT_NAME: foot-contest` fixé explicitement au niveau du job plutôt que de dépendre du nom du dossier de checkout), pour que `docker compose up` la réutilise sans rebuild.

Alternative non retenue : publier l'image sur un registre (ghcr.io) et la `pull` en CI au lieu de la builder. Éliminerait le build dans le cas commun, mais demanderait un workflow de publication séparé et une stratégie de tag à maintenir — complexité non justifiée pour un projet de cette taille tant que le cache de layers suffit.

## Conséquences

Mesuré sur deux runs consécutifs après mise en place :

| | Build de l'image | Run total |
|---|---|---|
| Avant (ADR 017) | 3m14s | ~3m52s |
| 1er run après ajout du cache (cache froid) | 3m16s | ~3m49s |
| 2e run (cache chaud) | **28s** | **~1m06s** |

Le `Dockerfile` change rarement — la quasi-totalité des runs bénéficient donc du cache chaud. Aucun changement du `Dockerfile` lui-même ni de la logique de build : uniquement le mécanisme d'invocation en CI.
