# Foot Contest

Application SAAS multi-tenant de gestion de tournois de football, destinée aux entreprises, associations et mairies.

## Stack

- PHP 8.4 / Symfony 8.1
- Architecture hexagonale (DDD)
- TDD — PHPUnit 13

## Installation

Seul Docker est requis (pas de PHP/Composer local nécessaire) — voir ADR 011.

```bash
docker compose up -d app
```

## Lancer les tests

```bash
# Tous les tests
docker compose exec app vendor/bin/phpunit

# Un fichier spécifique
docker compose exec app vendor/bin/phpunit tests/Competition/Domain/SingleEliminationBracketGeneratorTest.php

# Un test spécifique
docker compose exec app vendor/bin/phpunit --filter testMethodName
```

## Raccourcis

Un `Makefile` raccourcit les commandes `docker compose` les plus courantes — `make help` liste les cibles disponibles (`make test`, `make stan`, `make cs-fix`, etc.).

## Documentation

- [`ROADMAP.md`](ROADMAP.md) — roadmap et plan d'implémentation
- [`docs/adr/`](docs/adr/) — décisions d'architecture (ADR)
- [`CLAUDE.md`](CLAUDE.md) — modèle de domaine, conventions, workflow

## Acteurs

| Rôle | Responsabilité |
|------|----------------|
| Organisateur | Crée et gère le tournoi |
| Capitaine | Inscrit et gère une équipe (est aussi joueur) |
| Joueur | Rejoint une équipe pour un tournoi |