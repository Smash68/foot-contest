# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/Tournament/Domain/SingleEliminationBracketGeneratorTest.php

# Run a single test method
vendor/bin/phpunit --filter testMethodName
```

## Architecture

SaaS multi-tenant de gestion de tournois de football. PHP 8.4 / Symfony, architecture **hexagonale** (DDD), approche TDD.

L'application est structurée autour du namespace `Tournament`. Tout le code métier actuel vit dans la couche domaine — aucune couche application ni infrastructure n'existe encore.

### Couche domaine (`src/Tournament/Domain/`)

**Modèle** (`Model/`) — `strict_types=1` partout :

| Classe | Type DDD | Rôle |
|--------|----------|------|
| `Team` | Entity | Équipe : `id` + `name` |
| `EncounterId` | Value Object | Identifiant typé d'un encounter — `readonly class` avec `equals()` |
| `Participant` | Value Object | Participant à un encounter — 3 états : `forTeam`, `bye`, `pendingWinnerOf(EncounterId)` |
| `Encounter` | Entity (interne à l'agrégat) | Encounter mutable : `EncounterId $id`, `Participant $home/away`, `?EncounterResult $result` — lifecycle via `play()`, `resolveHome/Away()`, `getWinner()` |
| `Round` | Value Object (interne à l'agrégat) | Tour : numéro + liste d'`Encounter` — `findEncounterById()`, `resolveParticipant()` |
| `Bracket` | **Aggregate Root** | Tableau complet — **mutable**, liste de `Round` |
| `Side` | Enum | `Home` / `Away` — utilisé par `EncounterResult` |
| `EncounterResult` | Value Object | Score en temps réglementaire — `readonly class` avec named constructor `regularTime(int, int)` et `winner(): Side` |

`Participant` est le concept central : il représente indifféremment une équipe connue, un bye (avance automatique), ou un vainqueur en attente d'un encounter futur. Le terme `Slot` a été explicitement rejeté car sans sens métier dans le domaine football.

`Bracket` est l'agrégat racine — seule classe mutable. `Round` et `Participant` sont des value objects `readonly`. `Encounter` est une Entity interne mutable (elle porte son propre résultat).

**Interface de format** (`Service/BracketGenerator.php`) — contrat unique `generate(Team[] $teams): Bracket`. Tous les formats futurs (double élimination, poules, round-robin) implémentent cette interface.

**Format coupe simple** (`Format/SingleElimination/SingleEliminationBracketGenerator.php`) — seul format implémenté. Algorithme : validation → tirage aléatoire → calcul des byes (`2^n - totalTeams`) → appairage des participants en `Encounter` round par round → `nextRoundParticipants()` résout les byes **à la génération** (l'équipe qui avance est déjà connue, pas `pendingWinnerOf`) ; seuls les vrais encounters restent `pendingWinnerOf`.

### Conventions de nommage

- `Encounter` à la place de `Match` (mot-clé réservé PHP 8) et `Fixture` (sans sens métier)
- `Participant` à la place de `Slot` (sans sens métier dans le foot)
- `getEncounters()` / `countEncounters()` — jamais `getMatches()` / `countMatches()`
- Les IDs sont toujours des Value Objects typés (ex: `EncounterId`), jamais des `string` nus

### Décisions d'architecture notables

- `Bracket` est une **Entity mutable** (agrégat racine), pas un VO — elle évolue au fil des résultats
- `Participant` avec 3 états est le choix DDD correct : référencer un `EncounterId` (valeur) plutôt qu'un objet `Encounter` mutable (qui violerait l'immutabilité du VO)
- `Encounter` est une **Entity mutable** (pas un VO) — elle porte son propre `?EncounterResult`, lifecycle via `play()` / `resolveHome()` / `resolveAway()` ; getters explicites
- `EncounterId::equals()` pour toutes les comparaisons d'ID
- Les byes sont résolus **à la génération** dans `nextRoundParticipants()` — pas de `progressBye()` ; l'équipe avançant sur bye est immédiatement `forTeam` dans le round suivant
- `Bracket::recordResult()` utilise les références PHP : muter l'`Encounter` trouvé propage le changement sans reconstruire les rounds
- `Round::findEncounterById()` + `Round::resolveParticipant()` : le round gère lui-même sa recherche et sa résolution, `Bracket` orchestre sans tout connaître

## Prochaine étape prioritaire

`Bracket::isComplete()` — détection de fin de tournoi (toutes les encounters jouées). Ensuite : score enrichi (prolongations + tirs au but).

Le détail complet du plan (priorités 1 à 5) est dans `PROJET.md`.