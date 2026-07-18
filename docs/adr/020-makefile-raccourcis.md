# 020. Makefile pour raccourcir les commandes Docker

Date: 2026-07-18
Status: Accepted

## Contexte

Les commandes quotidiennes (`docker compose exec app vendor/bin/phpunit`, `composer stan`, `composer cs-fix`...) sont verbeuses à taper depuis le terminal.

## Décision

`Makefile` à la racine plutôt que `just` (nécessiterait `brew install just`, une dépendance locale de plus alors que l'objectif du passage à Docker était justement de n'en avoir aucune au-delà de Docker lui-même) ou des alias shell personnels (non versionnés, invisibles pour une autre session ou un futur contributeur). `make` est disponible nativement sur macOS et la quasi-totalité des distributions Linux.

Cibles = alias directs des commandes déjà documentées dans `CLAUDE.md` (`up`, `test`, `test-coverage`, `stan`, `cs-check`, `cs-fix`, `migrate`, `serve`, `shell`, `down`), rien de nouveau en soi. `make help` (cible par défaut) liste les cibles disponibles avec leur description.

## Conséquences

- `make cs-fix` au lieu de `docker compose exec app composer cs-fix` — les commandes `docker compose` complètes restent valides, le Makefile n'est qu'un raccourci, pas un remplacement.
- Un futur ajout de commande (nouveau script Composer, nouvelle commande `bin/console` fréquente) doit aussi ajouter une cible ici pour rester utile.