# Foot Contest — Roadmap

Vision produit et acteurs : [`README.md`](README.md). Modèle de domaine et conventions : [`CLAUDE.md`](CLAUDE.md). Raisonnement derrière chaque décision structurante : [`docs/adr/`](docs/adr/).

## Priorité 1 — Avancement du bracket (suite) ✅ implémenté

#### 1a — Détection de fin de tournoi ✅ implémenté

Quand la finale est jouée (`recordResult()` sur le dernier encounter), le bracket est terminé. Exposé via `Bracket::isComplete(): bool` et `Bracket::getChampion(): Team`.

#### 1b — Score enrichi ✅ implémenté

Voir ADR 005 (`EncounterResult` neutre, `Score` VO, `afterExtraTime()`, `afterPenalties()`).

## Priorité 2 — Match pour la 3e place ✅ implémenté

Optionnel, activé par composition (`BracketGeneratorWithThirdPlaceMatch` / `BracketWithThirdPlaceMatch`) — voir ADR 006.

## Priorité 3 — Inscription au tournoi ✅ implémenté (couche domaine)

Agrégat `Competition` : création (`TeamCapacity` valide min/max), inscription/désistement d'une équipe (`Registration` = `Team` + `Player` capitaine), clôture et génération du bracket comme deux actions manuelles et distinctes de l'organisateur — voir ADR 007.

Couche `Application/` volontairement reportée à la priorité 5 : sans repository, un use case n'aurait aucune orchestration réelle à faire au-delà d'un appel direct à l'agrégat.

## Priorité 4 — Autres formats de tournoi

- Double élimination
- Phase de poules + élimination directe
- Round-robin / championnat

## Priorité 5 — Infrastructure

#### 5a — Premier use case bout-en-bout : création d'une compétition ✅ implémenté

`POST /competitions` → contrôleur → Command CQRS dispatchée sur le bus Messenger → Handler (génère l'id via le repository, persiste, retourne l'id créé) → réponse 201. Bootstrap Symfony complet via `symfony/flex`. Voir ADR 008 (couche Application/CQRS) et ADR 009 (bootstrap infrastructure). Persistance actuelle : `InMemoryCompetitionRepository`.

#### 5b — Persistance réelle 🚧 en cours

Remplacer `InMemoryCompetitionRepository` par une vraie base de données. Choix retenu : Doctrine ORM + PostgreSQL, bootstrappé via Flex. Voir ADR 010 : mapping XML de `Competition` fait pour `id`/`name`/`capacity`/`closed` (`DoctrineCompetitionRepository`, testé par aller-retour réel sur PostgreSQL), `registrations` et `bracket` volontairement pas encore mappés (collection de VOs et polymorphisme d'agrégat, décisions à part entière).

Reste à faire :
- ✅ Environnement Docker (container `app` PHP-CLI + `database` PostgreSQL) — voir ADR 011
- ✅ Reset de la base de données entre chaque test (`DAMADoctrineTestBundle`, transaction + rollback automatique par test) — voir ADR 012
- ✅ Rebrancher `services.yaml` : `CompetitionRepository` pointe désormais vers `DoctrineCompetitionRepository`
- ✅ `services_test.yaml` : garde `InMemoryCompetitionRepository` pour `CreateCompetitionControllerTest` (test de contrat HTTP, pas de persistance — la couverture Doctrine vit déjà dans `DoctrineCompetitionRepositoryTest`) — voir ADR 013
- ✅ Smoke test e2e contre la stack réelle (`CreateCompetitionEndToEndTest`, un seul happy path — voir ADR 014)
- ✅ `CreateCompetitionControllerTest` couvre les 4 réponses de la route (201/422×3/400) ; violation de règle métier mappée en 422 JSON au lieu d'un 500 qui fuitait le message d'exception — voir ADR 015
- Mapper `registrations` (collection de `Registration` = `Team` + `Player`)
- Mapper `bracket` (interface polymorphe `Bracket`/`SingleEliminationBracket`/décorateur)

#### 5c bis — Outillage transverse

- ✅ Xdebug (couverture de tests + debug PhpStorm), désactivé par défaut — voir ADR 016
- ✅ CI GitHub Actions (`docker compose`, tests uniquement) — voir ADR 017

#### 5c — Reste à faire

- API REST ou GraphQL (autres use cases : inscription/désistement d'équipe, clôture, génération du bracket)
- Multi-tenancy
- Front (Vue 3 ou Twig — à décider)

## Priorité 6 — Gestion des équipes et des utilisateurs

- CRUD équipe (`Team`) : création, modification, suppression, consultation — indépendant de l'inscription à une compétition (`Registration`)
- Gestion des utilisateurs : rôles et droits (organisateur / capitaine / joueur, cf. acteurs dans `README.md`)

## Priorité 7 — Gestion de la compétition en cours

- Mise à jour des scores / résultats d'un encounter (exposer `Bracket::recordResult()`)
- Consultation des matchs et rounds (exposer `getRounds()`, `getRound()`, `countEncounters()`, `isComplete()`, `getChampion()`)