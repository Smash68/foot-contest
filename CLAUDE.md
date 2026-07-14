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
| `Encounter` | Entity (interne à l'agrégat) | Encounter mutable : `EncounterId $id`, `Participant $home/away`, `?EncounterResult $result` — lifecycle via `play()`, `resolveHome/Away()`, `getWinner()`, `getLoser()` |
| `Round` | Value Object (interne à l'agrégat) | Tour : numéro + liste d'`Encounter` — `findEncounterById()`, `resolveParticipant()` |
| `Bracket` | Interface (contrat d'agrégat) | `getRounds()`, `countRounds()`, `countEncounters()`, `getRound()`, `isComplete()`, `getChampion()`, `recordResult()` |
| `SingleEliminationBracket` | **Aggregate Root** (implémente `Bracket`) | Tableau complet — **mutable**, liste de `Round` |
| `Score` | Value Object | Paire de buts `home`/`away` — `readonly class`, `Score::of(int, int)`, valide non-négatif, expose `isDraw()` |
| `Side` | Enum | `Home` / `Away` — utilisé par `EncounterResult` |
| `EncounterResult` | Value Object | Résultat complet d'un encounter — **neutre vis-à-vis du format** : `regularTime(Score)`, `afterExtraTime(Score, Score)`, `afterPenalties(Score, Score, Score)` ; `winner()` lève `LogicException` sur un nul sans ET/penalties |

`Participant` est le concept central : il représente indifféremment une équipe connue, un bye (avance automatique), ou un vainqueur en attente d'un encounter futur. Le terme `Slot` a été explicitement rejeté car sans sens métier dans le domaine football. Il compte volontairement **3 états, pas plus** — voir plus bas pourquoi le match pour la 3e place n'en a pas ajouté un 4e.

`Bracket` est une interface — le contrat de l'agrégat racine. `SingleEliminationBracket` en est l'implémentation concrète et mutable. `Round` et `Participant` sont des value objects `readonly`. `Encounter` est une Entity interne mutable (elle porte son propre résultat).

**Interface de format** (`Service/BracketGenerator.php`) — contrat unique `generate(Team[] $teams): Bracket`. Tous les formats futurs (double élimination, poules, round-robin) implémentent cette interface.

**Format coupe simple** (`Format/SingleElimination/SingleEliminationBracketGenerator.php`) — seul format implémenté. Algorithme : validation → tirage aléatoire → calcul des byes (`2^n - totalTeams`) → appairage des participants en `Encounter` round par round → `nextRoundParticipants()` résout les byes **à la génération** (l'équipe qui avance est déjà connue, pas `pendingWinnerOf`) ; seuls les vrais encounters restent `pendingWinnerOf`.

**Match pour la 3e place** (`Format/SingleElimination/`) — option **composée**, pas configurée par flag :

```php
new BracketGeneratorWithThirdPlaceMatch(new SingleEliminationBracketGenerator())
```

- `ThirdPlaceFixture` — VO de câblage : 3 `EncounterId` (celui du futur match + les deux demi-finales sources), aucune donnée de jeu
- `BracketGeneratorWithThirdPlaceMatch` — décore `BracketGenerator` : repère le round avant la finale, valide l'éligibilité (exactement 2 encounters, aucun bye), lève `InvalidArgumentException` sinon
- `BracketWithThirdPlaceMatch` — décore `Bracket` : délègue toutes les méthodes du contrat à l'agrégat enveloppé ; construit **paresseusement** l'`Encounter` de 3e place (avec deux `Participant::forTeam()` concrets, jamais de nouvel état pending) dès que les deux demi-finales référencées sont `isCompleted()` ; expose `getThirdPlaceEncounter(): ?Encounter`

### Conventions de nommage

- `Encounter` à la place de `Match` (mot-clé réservé PHP 8) et `Fixture` (sans sens métier)
- `Participant` à la place de `Slot` (sans sens métier dans le foot)
- `getEncounters()` / `countEncounters()` — jamais `getMatches()` / `countMatches()`
- Les IDs sont toujours des Value Objects typés (ex: `EncounterId`), jamais des `string` nus

### Décisions d'architecture notables

- `Bracket` est une **Entity mutable** (agrégat racine), pas un VO — elle évolue au fil des résultats. `Bracket` est une interface (contrat), `SingleEliminationBracket` son implémentation concrète — extraction motivée par le besoin de décorer l'agrégat (voir match 3e place) sans modifier son constructeur
- Les règles optionnelles du format élimination directe (ex: match pour la 3e place) sont ajoutées par **décoration** (`BracketGeneratorWithThirdPlaceMatch` / `BracketWithThirdPlaceMatch`), pas par flag booléen ni sous-classe : un flag grossit indéfiniment à chaque nouvelle règle, une sous-classe explose combinatoirement dès que deux règles doivent se cumuler (`WithThirdPlaceAndX`...). La décoration compose : `new WithRegleA(new WithRegleB($bracket))`, un nombre de classes linéaire au nombre de règles, jamais aux combinaisons. Un système d'extensions/hooks a été envisagé (liste injectée dans `Bracket`) mais écarté pour l'instant : une seule règle optionnelle existe aujourd'hui, construire ce mécanisme maintenant serait de la généralité spéculative — à réévaluer si une 2e règle apparaît et que le passe-plat des décorateurs devient réellement pénible
- Le match pour la 3e place ne référence **jamais** les perdants de demi-finale via un 4e état de `Participant` (type `pendingLoserOf`) — ça aurait pollué un concept central déjà stable à 3 états pour un besoin très localisé. À la place, `BracketWithThirdPlaceMatch` construit l'`Encounter` de 3e place **paresseusement**, seulement une fois les deux perdants réellement connus (`Encounter::getLoser()`), avec des `Participant::forTeam()` concrets dès la construction
- `Participant` avec 3 états est le choix DDD correct : référencer un `EncounterId` (valeur) plutôt qu'un objet `Encounter` mutable (qui violerait l'immutabilité du VO)
- `Encounter` est une **Entity mutable** (pas un VO) — elle porte son propre `?EncounterResult`, lifecycle via `play()` / `resolveHome()` / `resolveAway()` ; getters explicites
- `EncounterId::equals()` pour toutes les comparaisons d'ID
- Les byes sont résolus **à la génération** dans `nextRoundParticipants()` — pas de `progressBye()` ; l'équipe avançant sur bye est immédiatement `forTeam` dans le round suivant
- `Bracket::recordResult()` utilise les références PHP : muter l'`Encounter` trouvé propage le changement sans reconstruire les rounds
- `Round::findEncounterById()` + `Round::resolveParticipant()` : le round gère lui-même sa recherche et sa résolution, `Bracket` orchestre sans tout connaître
- `EncounterResult` est **neutre vis-à-vis du format** — il n'interdit pas les nuls ; `winner()` lève `LogicException` si nul sans vainqueur désigné ; la règle "pas de nul" est une contrainte du format de tournoi, portée par la couche application
- `Score` est un VO léger qui valide le non-négatif et expose `isDraw()` — utilisé comme paramètre de tous les named constructors d'`EncounterResult`

## Prochaine étape prioritaire

Inscription au tournoi (couche application) — création d'un tournoi par un organisateur, inscription d'une équipe par un capitaine, déclenchement de la génération du bracket. Le détail complet du plan (priorités 1 à 5) est dans `PROJET.md`.