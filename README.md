# Foot Contest

Application SAAS multi-tenant de gestion de tournois de football, destinée aux entreprises, associations et mairies.

## Stack

- PHP 8.4 / Symfony 8.1
- Architecture hexagonale (DDD)
- TDD — PHPUnit 13

## Installation

```bash
composer install
```

## Lancer les tests

```bash
# Tous les tests
vendor/bin/phpunit

# Un fichier spécifique
vendor/bin/phpunit tests/Tournament/Domain/SingleEliminationBracketGeneratorTest.php

# Un test spécifique
vendor/bin/phpunit --filter testMethodName
```

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