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

## Structure

```
src/
└── Tournament/
    └── Domain/
        ├── Format/SingleElimination/   # Générateur de bracket en élimination directe
        ├── Model/                      # Agrégat Bracket + entités et value objects
        └── Service/                    # Interfaces de domaine (BracketGenerator)

tests/
└── Tournament/Domain/
```

## Documentation

- [`PROJET.md`](PROJET.md) — vision produit, modèle de domaine, plan d'implémentation
- [`docs/adr/`](docs/adr/) — décisions d'architecture (ADR)

## Acteurs

| Rôle | Responsabilité |
|------|----------------|
| Organisateur | Crée et gère le tournoi |
| Capitaine | Inscrit et gère une équipe (est aussi joueur) |
| Joueur | Rejoint une équipe pour un tournoi |