# Foot Contest — État du projet

## Vision produit

Application **SAAS multi-tenant** de gestion de tournois de football, destinée aux entreprises, associations et mairies.

- Chaque organisation a son propre espace
- **Acteurs** : Organisateur (gère le tournoi) · Capitaine (inscrit et gère une équipe, est aussi joueur) · Joueur (rejoint une équipe pour un tournoi)
- Architecture **hexagonale** (DDD), back PHP 8.4 / Symfony, front à décider (Vue 3 ou Twig)
- Approche **TDD**, livraison de valeur incrémentale

---

## Décisions métier structurantes

### Format de tournoi

L'architecture est conçue pour supporter plusieurs formats. Le premier implémenté est la **coupe simple (élimination directe)**.

Interface `BracketGenerator` → les formats futurs (double élimination, poules + finale, round-robin) implémenteront la même interface.

### Règles de la coupe simple

| Règle | Décision |
|-------|----------|
| Nombre d'équipes | Min et max configurables par l'organisateur |
| Équipes en nombre impair | Byes assignés aléatoirement |
| Match nul | Impossible en élimination directe |
| Conditions de victoire | Portées par le format (configurable) |
| Score | **Score enrichi** : temps réglementaire + prolongations + tirs au but |
| Match pour la 3e place | Configurable par l'organisateur |

### Nommage

- `Encounter` a été préféré à `Match` (mot-clé réservé en PHP 8) et à `Fixture` (sans sens métier)
- `Participant` a été préféré à `Slot` (sans sens métier dans le football)
- Les IDs sont des Value Objects typés (`EncounterId`) — jamais des `string` nus

---

## Ce qui est codé

### Modèle de domaine (`src/Tournament/Domain/Model/`)

| Classe | Type DDD | Rôle |
|--------|----------|------|
| `Team` | Entity | Une équipe : `id` + `name` |
| `EncounterId` | Value Object | Identifiant typé — `readonly class`, `equals()` |
| `Participant` | Value Object | Un participant à un encounter — trois états possibles |
| `Encounter` | **Entity** interne agrégat | Encounter mutable : `EncounterId $id`, `Participant $home/away`, `?EncounterResult $result` — `play()`, `resolveHome/Away()`, `getWinner()` |
| `Round` | VO interne agrégat | Un tour : numéro + liste d'`Encounter` — `findEncounterById()`, `resolveParticipant()` |
| `Bracket` | **Aggregate Root** | Le tableau complet — mutable |
| `Score` | Value Object | Paire de buts — `Score::of(int, int)`, valide non-négatif, `isDraw()` |
| `Side` | Enum | `Home` / `Away` |
| `EncounterResult` | Value Object | Résultat complet, **neutre** : `regularTime(Score)`, `afterExtraTime(Score, Score)`, `afterPenalties(Score, Score, Score)` — `winner()` lève `LogicException` sur nul sans vainqueur |

#### Les trois états d'un `Participant`

```
Participant::forTeam($team)         → équipe connue
Participant::bye()                  → emplacement vide, avance automatique
Participant::pendingWinnerOf($id)   → vainqueur d'un encounter à venir (référence par EncounterId)
```

Le 3e état référence un `EncounterId` (valeur immuable) et non un objet `Encounter` mutable — choix DDD délibéré pour préserver l'immutabilité du Value Object.

#### `Bracket` est l'agrégat racine mutable

`Bracket` et `Encounter` sont des Entities (identité, état qui évolue). `Round`, `Participant`, `EncounterResult`, `EncounterId` sont des Value Objects `readonly`.

```php
$bracket->recordResult(EncounterId $id, EncounterResult $result): void
```

Enregistre le score sur l'`Encounter` via `play()`, puis résout le `Participant::pendingWinnerOf($id)` dans le round suivant en `Participant::forTeam($winner)` via `Round::resolveParticipant()`. Exploite les références PHP : muter l'`Encounter` trouvé propage le changement sans reconstruire les rounds.

### Interface de domaine (`src/Tournament/Domain/Service/`)

```php
interface BracketGenerator {
    public function generate(Team[] $teams): Bracket;
}
```

### Implémentation (`src/Tournament/Domain/Format/SingleElimination/`)

`SingleEliminationBracketGenerator` — génère un bracket en élimination directe.

**Algorithme :**

1. Validation (minimum 2 équipes)
2. Tirage au sort (`shuffle`)
3. Construction des participants du round 1 : `numByes` participants `bye` + un participant `forTeam` par équipe
4. Boucle uniforme sur tous les rounds : appairage des participants 2 par 2 → `Encounter` → `Round`
5. `nextRoundParticipants()` calcule les participants du round suivant : si un côté est `bye`, l'autre équipe est **immédiatement** `forTeam` (résolution à la génération) ; sinon `pendingWinnerOf($encounterId)`
6. Arrêt quand il ne reste qu'un participant (le champion)

**Propriétés :**
- Round 1 : toujours `totalParticipants / 2` encounters (y compris les encounters bye)
- Total encounters : `2^numRounds - 1`
- Les byes apparaissent explicitement en round 1 via `Participant::bye()`
- Les équipes avançant sur bye sont déjà `forTeam` dès la génération — aucune `progressBye()` nécessaire

```
Exemple — 5 équipes (numRounds = 3, totalParticipants = 8, numByes = 3) :

Round 1 : bye vs A,  bye vs B,  bye vs C,  D vs E      (4 encounters)
Round 2 : w1  vs w2, w3  vs w4                          (2 encounters)
Round 3 : w5  vs w6                                     (1 encounter — finale)
```

### Tests (`tests/Tournament/Domain/`)

- `SingleEliminationBracketGeneratorTest` — génération du bracket, byes, rounds, encounters
- `EncounterTest` — lifecycle d'un `Encounter` : `play()`, `getWinner()`, guards, `resolveHome/Away()`
- `ScoreTest` — `Score::of()`, validation non-négatif, `isDraw()`
- `EncounterResultTest` — `regularTime()`, `afterExtraTime()`, `afterPenalties()`, `winner()`, invariants métier
- `BracketProgressTest` — `recordResult()` : propagation du vainqueur
- `BracketIsCompleteTest` — `isComplete()`, `getChampion()`, cas avec byes

56 tests, 85 assertions — tout vert.

---

## Plan — ce qui reste à faire

### Priorité 1 — Avancement du bracket (suite)

#### 1a — Détection de fin de tournoi ✦ prochaine étape

Quand la finale est jouée (`recordResult()` sur le dernier encounter), le bracket est terminé. Exposer `Bracket::isComplete(): bool`.

#### 1b — Score enrichi ✅ implémenté

```
EncounterResult
  ├── regularTime(Score)                    → accepte nul (neutre vis-à-vis du format)
  ├── afterExtraTime(Score, Score)          → RT nul requis, ET doit avoir vainqueur
  └── afterPenalties(Score, Score, Score)   → RT nul + ET nul requis, penalties doit avoir vainqueur
```

`winner()` lève `LogicException` si nul sans ET/penalties — la règle "pas de nul" est portée par le format de tournoi (couche application), pas par `EncounterResult`.

### Priorité 2 — Match pour la 3e place

Configurable par l'organisateur. À intégrer dans le générateur ou dans une étape post-finale.

### Priorité 3 — Inscription au tournoi

- Création d'un tournoi par un organisateur (format, min/max équipes, règles)
- Inscription d'une équipe par un capitaine
- Déclenchement de la génération du bracket quand le nombre d'équipes est atteint

### Priorité 4 — Autres formats de tournoi

- Double élimination
- Phase de poules + élimination directe
- Round-robin / championnat

### Priorité 5 — Infrastructure

- Persistance (base de données)
- API REST ou GraphQL
- Multi-tenancy
- Front (Vue 3 ou Twig — à décider)

---

## Structure des fichiers

```
src/
└── Tournament/
    └── Domain/
        ├── Format/
        │   └── SingleElimination/
        │       └── SingleEliminationBracketGenerator.php
        ├── Model/
        │   ├── Bracket.php          ← Aggregate Root (mutable) — recordResult(), isComplete(), getChampion()
        │   ├── Encounter.php        ← Entity mutable — play(), resolveHome/Away(), getWinner()
        │   ├── EncounterId.php      ← Value Object avec equals()
        │   ├── EncounterResult.php  ← Value Object neutre — regularTime/afterExtraTime/afterPenalties
        │   ├── Participant.php      ← Value Object, 3 états
        │   ├── Round.php            ← findEncounterById(), resolveParticipant()
        │   ├── Score.php            ← Value Object — Score::of(int, int), isDraw()
        │   ├── Side.php             ← enum Home/Away
        │   └── Team.php
        └── Service/
            └── BracketGenerator.php  (interface)

tests/
└── Tournament/
    └── Domain/
        ├── BracketProgressTest.php
        ├── EncounterResultTest.php
        └── SingleEliminationBracketGeneratorTest.php
```