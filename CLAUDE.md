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
| `TeamId` | Value Object | Identifiant typé d'une équipe — `readonly class` avec `equals()` |
| `PlayerId` | Value Object | Identifiant typé d'un joueur — **valeur = email** (`readonly class`, valide via `FILTER_VALIDATE_EMAIL`) ; compromis assumé en l'absence de couche compte/auth, voir ADR 007 |
| `Player` | Entity | Joueur : `PlayerId` + `name` — même forme minimale que `Team` ; un capitaine **est** un `Player`, pas un type à part |
| `Registration` | Value Object | Inscription d'une équipe à un tournoi — `Team` + `Player` (nommé `captain` dans les signatures) |
| `TeamCapacity` | Value Object | Jauge min/max d'équipes d'un tournoi — `readonly class`, `TeamCapacity::of(int, int)`, valide `min >= 2` et `min <= max` |
| `TournamentId` | Value Object | Identifiant typé d'un tournoi — `readonly class` avec `equals()` |
| `Tournament` | **Aggregate Root** | Inscription au tournoi — mutable, distinct de `Bracket` (voir ADR 007). `create()`, `register()`/`withdraw()` (uniquement tant que l'inscription est ouverte), `closeRegistration()` (échoue sous `minTeams`), `generateBracket(BracketGenerator)` (action manuelle et distincte de la clôture, échoue si l'inscription est encore ouverte ou déjà générée) |

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

### Décisions d'architecture

Le raisonnement complet de chaque décision structurante (contexte, alternatives rejetées, conséquences) vit dans `docs/adr/` — ne pas le dupliquer ici, seulement y renvoyer :

- ADR 001 — `Encounter` est une Entity mutable, pas un Value Object
- ADR 002 — `Participant` à 3 états (`forTeam` / `bye` / `pendingWinnerOf`)
- ADR 003 — Les byes sont résolus à la génération du bracket, pas de `progressBye()`
- ADR 004 — `Bracket` est l'unique point de mutation de l'agrégat, pas de service séparé
- ADR 005 — `EncounterResult` est neutre vis-à-vis du format de tournoi ; `Score` VO
- ADR 006 — Match 3e place : `Bracket` devient une interface, règles optionnelles par décoration (pas flag, pas sous-classe, pas de 4e état sur `Participant`)
- ADR 007 — Inscription au tournoi : `Tournament` agrégat séparé de `Bracket`, `Player` distinct de `Team` (capitaine = joueur, pas un type à part), `PlayerId` = email, `TeamId` rétrofité en VO typé, clôture/génération manuelles et distinctes

## Workflow

- **TDD strict, pas test-first** : cycle red-green-refactor, un comportement à la fois. Écrire un test qui échoue pour la bonne raison avant tout code de production, le code minimal pour le faire passer, refactor avant le test suivant. Jamais de test écrit après coup ; jamais plusieurs comportements dans un seul test. Le nombre d'allers-retours peut être allégé sur les étapes triviales, mais le red-before-green n'est jamais sauté.
  - Un seul test à la fois : ne jamais écrire plusieurs méthodes de test dans le même fichier/edit avant d'avoir fait passer la précédente au vert. Si plusieurs comportements sont anticipés, les poser en liste (mentalement ou en commentaire de suivi), pas en code.
  - Après chaque vert, marquer explicitement le passage par l'étape refactor avant d'écrire le prochain test — même quand la réponse est « rien à changer » sur une étape triviale. La pause fait partie de la discipline, pas seulement son résultat.
  - Ce n'est pas une préférence de style : c'est une exigence non négociable du workflow, à appliquer sans qu'il soit nécessaire de le rappeler.
- **Tests comportementaux** : vérifier un comportement observable via l'API publique, jamais en miroir de l'implémentation interne. Pas de mock pour vérifier qu'une méthode interne a été appelée. Récupérer les valeurs générées dynamiquement (IDs, etc.) depuis l'API publique plutôt que de les coder en dur — un détail d'implémentation ne doit jamais faire échouer un test.
- **Design avant implémentation** : pour toute tâche architecturalement significative (nouvel agrégat, nouveau pattern, décision de modélisation DDD ambiguë), valider explicitement l'approche avec l'utilisateur avant le premier `Write`/`Edit`. Pas nécessaire pour les tâches mécaniques (bug fix évident, renommage local).
- **Challenge DDD, pas exécution passive** : le domaine est modélisé en DDD par choix, pas par défaut. Questionner activement le vocabulaire (ubiquitous language), la cohérence d'une règle métier proposée, et l'impact d'une demande sur les invariants du domaine — même quand l'implémentation semble simple à exécuter telle quelle. Signaler un terme sans sens métier ou une règle qui semble incomplète/contradictoire avant d'implémenter, pas après.
- **ADR pour les décisions impactantes** : toute décision qui change un contrat, introduit un pattern, ou avait une alternative sérieusement envisagée puis rejetée, est documentée dans `docs/adr/NNN-titre.md` (Contexte / Décision / Conséquences) — pas seulement actée en conversation ou résumée ici. Écrire l'ADR fait partie du travail, pas une étape à rattraper plus tard.
- **Commits** : format Conventional Commits (`<type>(<scope>): <description>`), toujours avec un corps en français qui explique le pourquoi. Types courants : `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.

## Prochaine étape prioritaire

Couche domaine de l'inscription au tournoi (`Tournament`) implémentée — voir ADR 007. La couche `Application/` associée est volontairement reportée à la priorité 5 (persistance), faute de repository à orchestrer. Prochaine étape : priorité 4 (autres formats de tournoi) ou priorité 5 (infrastructure), à trancher avec l'utilisateur. Le détail complet du plan (priorités 1 à 5) est dans `ROADMAP.md`.