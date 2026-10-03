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

La paire de clés JWT (`config/jwt/*.pem`) n'est pas versionnée (voir `.gitignore`) — à générer une fois après le premier démarrage :

```bash
docker compose exec app php bin/console lexik:jwt:generate-keypair
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

- [Issues/Milestones GitHub](https://github.com/Smash68/foot-contest/issues) — avancement et prochaines features (voir ADR 037)
- [`docs/adr/`](docs/adr/) — décisions d'architecture (ADR)
- [`docs/architecture.md`](docs/architecture.md) — conventions d'architecture
- [`docs/glossary.md`](docs/glossary.md) — langage métier
- [`AGENTS.md`](AGENTS.md) — commandes et workflow de développement (instructions pour les agents de code et les contributeurs)

## Acteurs

| Rôle | Responsabilité |
|------|----------------|
| Organisateur | Crée et gère le tournoi |
| Capitaine | Inscrit et gère une équipe (est aussi joueur) |
| Joueur | Rejoint une équipe pour un tournoi |