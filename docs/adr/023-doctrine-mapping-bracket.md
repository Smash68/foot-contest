# 023. Mapping Doctrine de `bracket` : colonne JSON, discriminant de type, reconstitution du décorateur 3e place

Date: 2026-07-19
Status: Accepted

## Contexte

Priorité 5b (voir ROADMAP) : dernier champ non mappé de `Competition` après `registrations` (ADR 022). L'ADR 010 avait explicitement reporté cette décision : `bracket` est une interface polymorphe (`SingleEliminationBracket`, ou `BracketWithThirdPlaceMatch` qui la décore avec une `ThirdPlaceFixture` et un `Encounter` de 3e place construit paresseusement), portant un graphe imbriqué (`Round[]` → `Encounter[]` → `Participant` à 3 états → `?EncounterResult` à 3 variantes).

## Décision

### 1. Colonne JSON auto-suffisante, même patron que `registrations` (pas de mapping relationnel)

Toute lecture de l'état d'un bracket est scopée à une compétition déjà identifiée par son id — un organisateur ou un joueur consulte le tournoi qu'il connaît (Priorité 7 : `getRounds()`, `isComplete()`, `getChampion()`...), jamais une requête SQL transverse à plusieurs compétitions. Charger l'agrégat `Competition` par repository puis naviguer l'objet en mémoire suffit ; c'est d'ailleurs la frontière déjà imposée par l'ADR 004 (`Bracket` n'a pas de repository propre, aucune lecture/mutation hors de l'agrégat). Un `Doctrine\DBAL\Types\Type` custom (`BracketType`, colonne JSON) sérialise donc tout le graphe, sans dépendance à un repository à l'hydratation — même raisonnement que `RegistrationsType` (ADR 022).

Si un besoin de requêtes transverses multi-compétitions apparaît un jour (ex: dashboard admin), la réponse ne sera pas de rendre ce mapping d'écriture interrogeable en SQL (ça romprait la frontière d'agrégat), mais un modèle de lecture séparé — projection alimentée par les événements du domaine, cohérent avec le CQRS déjà choisi (ADR 008). Sujet distinct, non traité ici.

### 2. Discriminant `"type"` dans le JSON, scopé aux deux formats existants

Le JSON porte un champ `"type"` (`single_elimination` | `single_elimination_third_place`) pour savoir, à l'hydratation, s'il faut envelopper le résultat dans `BracketWithThirdPlaceMatch`. Ce discriminant ne généralise pas aux formats futurs (double élimination, poules — Priorité 4) : assumé, ce sera une décision à part entière au moment de leur introduction, pas anticipée ici.

### 3. Reconstruction via les constructeurs/factories publics du domaine pour tout sauf `thirdPlaceEncounter`

`BracketType::convertToPHPValue()` reconstruit le graphe via l'API publique déjà existante : `Participant::forTeam()/bye()/pendingWinnerOf()`, `new Round(...)`, `new Encounter(...)`, puis `Encounter::play($result)` pour réinjecter un résultat déjà enregistré sur un encounter dont les participants sont déjà résolus (ce que `play()` autorise nativement — pas de contournement d'invariant).

### 4. `thirdPlaceEncounter` reconstruit par réflexion, pas par une méthode de domaine dédiée à l'infrastructure

Le seul état qui ne se reconstruit pas par simple rejeu des constructeurs existants : `BracketWithThirdPlaceMatch` construit son `thirdPlaceEncounter` paresseusement (`buildThirdPlaceEncounterIfReady()`, méthode privée), déclenché uniquement par `recordResult()`. Deux approches écartées avant celle-ci :

- Une factory statique `reconstitute()` sur `BracketWithThirdPlaceMatch` — écartée : contrairement à `Team::create()` (ADR 022, `RegistrationsType`), qui sert un vrai usage métier (tests, flux d'inscription), une telle factory n'aurait jamais eu d'appelant en dehors de l'infrastructure. Une méthode publique du domaine dont le seul consommateur est Doctrine n'est pas une responsabilité du domaine.
- Déclencher `buildThirdPlaceEncounterIfReady()` depuis le constructeur métier — écartée : mélangerait une préoccupation d'hydratation avec la construction métier via `BracketGeneratorWithThirdPlaceMatch`, qui ne connaît jamais de `thirdPlaceEncounter` à la création.

Décision retenue (première itération, à réévaluer après implémentation) : `BracketType` construit `BracketWithThirdPlaceMatch` via son constructeur métier normal (`inner`, `fixture`), puis invoque `buildThirdPlaceEncounterIfReady()` par réflexion (`ReflectionMethod::invoke()`) — réutilise telle quelle la logique métier existante (pas de duplication), sans l'exposer publiquement. Si un résultat de 3e place était déjà enregistré, il est réinjecté via l'API publique (`getThirdPlaceEncounter()->play($result)`). Nécessite un accesseur `getFixture(): ThirdPlaceFixture` sur `BracketWithThirdPlaceMatch` (donnée de configuration déjà connue du constructeur, pas une méthode infra).

Une alternative a été identifiée en discussion — supprimer entièrement l'état caché en recalculant `thirdPlaceEncounter` à la demande à chaque appel de `getThirdPlaceEncounter()` (à partir de `inner` + un `?EncounterResult` stocké au lieu d'un `?Encounter`), ce qui aurait rendu le constructeur et `recordResult()` seuls suffisants pour l'hydratation, sans réflexion. Reportée : à réévaluer une fois la version par réflexion implémentée et vécue.

## Conséquences

- `BracketType` (Doctrine DBAL `Type`, JSON) + entrée dans `config/packages/doctrine_competition.yaml` + champ `bracket` (nullable) dans `Competition.orm.xml`.
- Testé par aller-retour réel sur PostgreSQL (`DoctrineCompetitionRepositoryTest`), même patron que `registrations` — pas de test unitaire isolé du `Type`.
- `BracketWithThirdPlaceMatch` gagne un accesseur public `getFixture(): ThirdPlaceFixture` ; aucun autre changement de comportement métier. L'hydratation s'appuie sur `ReflectionMethod::invoke()` pour déclencher `buildThirdPlaceEncounterIfReady()`, seule méthode privée concernée.
- Toute requête transverse multi-compétitions sur l'état des brackets (hors périmètre ici) sera un modèle de lecture séparé, pas un remaniement de ce mapping.