# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/Competition/Domain/SingleEliminationBracketGeneratorTest.php

# Run a single test method
vendor/bin/phpunit --filter testMethodName
```

## Architecture

SaaS multi-tenant de gestion de tournois de football. PHP 8.4 / Symfony, architecture **hexagonale** (DDD), approche TDD.

L'application est structurée autour du namespace `Competition`, en trois couches : `Domain/`, `Application/` (use cases) et `Infrastructure/` (adapters).

### Couche domaine (`src/Competition/Domain/`)

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
| `CompetitionId` | Value Object | Identifiant typé d'une compétition — `readonly class` avec `equals()` |
| `Competition` | **Aggregate Root** | Inscription à une compétition — mutable, distinct de `Bracket` (voir ADR 007). `create()`, `register()`/`withdraw()` (uniquement tant que l'inscription est ouverte), `closeRegistration()` (échoue sous `minTeams`), `generateBracket(BracketGenerator)` (action manuelle et distincte de la clôture, échoue si l'inscription est encore ouverte ou déjà générée) |

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

### Couche application (`src/Competition/Application/`)

Un dossier par use case, en CQRS via `symfony/messenger` (voir ADR 008) :

- `CreateCompetition/CreateCompetitionCommand` — DTO immuable, ne porte que les données nécessaires à la création (`name`, `minTeams`, `maxTeams`) ; pas d'id, généré côté persistance (voir plus bas)
- `CreateCompetition/CreateCompetitionHandler` — orchestration : génère l'id via `CompetitionRepository::nextIdentity()`, construit l'agrégat (`Competition::create()`), le persiste (`repository->save()`), et **retourne** le `CompetitionId` créé — entorse assumée à "une Command ne retourne rien", nécessaire pour qu'un appelant synchrone (contrôleur HTTP) connaisse l'id généré. Le Handler est déclaré handler Messenger par tag en config (`services.yaml`), jamais par l'attribut `#[AsMessageHandler]`, pour rester 100% PHP sans dépendance au framework.

**Port repository** (`Domain/Repository/CompetitionRepository.php`) — contrat `nextIdentity(): CompetitionId`, `save(Competition): void`, `ofId(CompetitionId): ?Competition`.

### Couche infrastructure (`src/Competition/Infrastructure/`)

- `Persistence/InMemory/InMemoryCompetitionRepository` — implémente `CompetitionRepository`, stockage en `array` ; sert à la fois d'adapter réel (faute de vraie persistance) et de test double dans les tests d'Application, sans mock
- `Http/CreateCompetitionController` — route `POST /competitions` (attribut `#[Route]`), construit la Command depuis un DTO validé (`Http/CreateCompetitionRequest` + `#[MapRequestPayload]`), dispatch sur le bus, lit le résultat via `$envelope->last(HandledStamp::class)->getResult()`, répond 201 + id. Vit sous `Infrastructure/Http/` du module, pas `src/Controller/` — voir ADR 009 sur pourquoi l'auto-découverte des routes ne dépend pas d'un dossier fixe.

Bootstrap Symfony (Kernel, `config/`, `public/index.php`, `bin/console`) posé via `symfony/flex` et ses recipes officielles, pas assemblé à la main — voir ADR 009, notamment le scope de l'auto-discovery des services (`config/services.yaml`) et pourquoi il exclut tout le Domain.

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
- ADR 007 — Inscription au tournoi : `Competition` agrégat séparé de `Bracket` (nommage `Competition` plutôt que `Tournament`/`Contest` — voir ADR), `Player` distinct de `Team` (capitaine = joueur, pas un type à part), `PlayerId` = email, `TeamId` rétrofité en VO typé, clôture/génération manuelles et distinctes
- ADR 008 — Couche Application : CQRS via `symfony/messenger` (pas de bus fait main), Handler taggé en config (pas `#[AsMessageHandler]`), id généré par `CompetitionRepository::nextIdentity()` et retourné par le Handler (lu via `HandledStamp`)
- ADR 009 — Bootstrap infrastructure via `symfony/flex` et ses recipes officielles (pas de squelette fait main), auto-discovery des services scopée par module avec exclusion de tout `Domain/`, contrôleurs sous `Infrastructure/Http/` du module

## Workflow

- **TDD strict, pas test-first** : cycle red-green-refactor, un comportement à la fois. Écrire un test qui échoue pour la bonne raison avant tout code de production, le code minimal pour le faire passer, refactor avant le test suivant. Jamais de test écrit après coup ; jamais plusieurs comportements dans un seul test. Le nombre d'allers-retours peut être allégé sur les étapes triviales, mais le red-before-green n'est jamais sauté.
  - Un seul test à la fois : ne jamais écrire plusieurs méthodes de test dans le même fichier/edit avant d'avoir fait passer la précédente au vert. Si plusieurs comportements sont anticipés, les poser en liste (mentalement ou en commentaire de suivi), pas en code.
  - Après chaque vert, marquer explicitement le passage par l'étape refactor avant d'écrire le prochain test — même quand la réponse est « rien à changer » sur une étape triviale. La pause fait partie de la discipline, pas seulement son résultat.
  - Ce n'est pas une préférence de style : c'est une exigence non négociable du workflow, à appliquer sans qu'il soit nécessaire de le rappeler.
- **Tests comportementaux** : vérifier un comportement observable via l'API publique, jamais en miroir de l'implémentation interne. Pas de mock pour vérifier qu'une méthode interne a été appelée. Récupérer les valeurs générées dynamiquement (IDs, etc.) depuis l'API publique plutôt que de les coder en dur — un détail d'implémentation ne doit jamais faire échouer un test.
- **Design avant implémentation** : pour toute tâche architecturalement significative (nouvel agrégat, nouveau pattern, décision de modélisation DDD ambiguë), valider explicitement l'approche avec l'utilisateur avant le premier `Write`/`Edit`. Pas nécessaire pour les tâches mécaniques (bug fix évident, renommage local).
- **Challenge DDD, pas exécution passive** : le domaine est modélisé en DDD par choix, pas par défaut. Questionner activement le vocabulaire (ubiquitous language), la cohérence d'une règle métier proposée, et l'impact d'une demande sur les invariants du domaine — même quand l'implémentation semble simple à exécuter telle quelle. Signaler un terme sans sens métier ou une règle qui semble incomplète/contradictoire avant d'implémenter, pas après.
- **ADR pour les décisions impactantes** : toute décision qui change un contrat, introduit un pattern, ou avait une alternative sérieusement envisagée puis rejetée, est documentée dans `docs/adr/NNN-titre.md` (Contexte / Décision / Conséquences) — pas seulement actée en conversation ou résumée ici. Écrire l'ADR fait partie du travail, pas une étape à rattraper plus tard.
- **Petits incréments** : tout travail qui traverse plusieurs couches ou introduit un nouveau pattern se découpe en la plus petite tranche testable possible. Ne concevoir/trancher une étape que lorsque la précédente est terminée — ne pas figer à l'avance des décisions qui ne concernent pas encore l'étape en cours. Si une étape est difficile à committer proprement seule, c'est le signe qu'elle est mal découpée.
- **Commits** : format Conventional Commits (`<type>(<scope>): <description>`), toujours avec un corps en français qui explique le pourquoi. Types courants : `feat`, `fix`, `refactor`, `test`, `docs`, `chore`. Un commit par incrément vert — jamais plusieurs incréments regroupés dans un seul commit, jamais d'attente de la fin de session.

## Prochaine étape prioritaire

Priorité 5 (infrastructure) entamée : premier use case complet bout-en-bout, `POST /competitions` (voir ADR 008 et ADR 009). Persistance actuelle en mémoire (`InMemoryCompetitionRepository`). Prochaine étape : remplacer par une vraie persistance — choix Doctrine ou non encore à trancher. Le détail complet du plan (priorités 1 à 5) est dans `ROADMAP.md`.