# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

L'app tourne sous Docker (voir ADR 011) — aucune dépendance locale à PHP/Composer requise, seulement Docker. `docker compose up -d app` démarre le container `app` (PHP 8.4 CLI + extensions) et sa dépendance `database` (PostgreSQL), et exécute `composer install` à chaque démarrage.

Un `Makefile` raccourcit les commandes ci-dessous (`make test`, `make stan`, `make cs-fix`, etc.) — `make help` liste les cibles disponibles. Les commandes `docker compose` complètes restent valides, le Makefile n'est qu'un alias.

```bash
# Démarrer l'environnement (app + base de données)
docker compose up -d app

# Run all tests
docker compose exec app vendor/bin/phpunit

# Run a single test file
docker compose exec app vendor/bin/phpunit tests/Competition/Domain/SingleEliminationBracketGeneratorTest.php

# Run a single test method
docker compose exec app vendor/bin/phpunit --filter testMethodName

# Couverture de tests (Xdebug désactivé par défaut pour un run rapide — voir ADR 016)
docker compose exec app composer test:coverage

# Analyse statique (PHPStan niveau max — voir ADR 018)
docker compose exec app composer stan

# Style de code (PHP-CS-Fixer @Symfony — voir ADR 019)
docker compose exec app composer cs-check   # vérifie seulement
docker compose exec app composer cs-fix     # applique

# Console Symfony (ex: migrations)
docker compose exec app php bin/console doctrine:migrations:migrate

# Servir l'app en HTTP à la demande (http://localhost:8000)
docker compose exec -d app php -S 0.0.0.0:8000 -t public
```

## Architecture

SaaS multi-tenant de gestion de tournois de football. PHP 8.4 / Symfony, architecture **hexagonale** (DDD), approche TDD.

L'application est structurée en modules (bounded contexts) — `Competition` et `Organization` — chacun en trois couches : `Domain/`, `Application/` (use cases) et `Infrastructure/` (adapters). Dépendance à sens unique imposée en CI par `deptrac` : `Competition` peut dépendre d'`Organization`, jamais l'inverse — voir ADR 027.

### Couche domaine (`src/Competition/Domain/`)

**Modèle** (`Model/`) — `strict_types=1` partout :

| Classe | Type DDD | Rôle |
|--------|----------|------|
| `Team` | Entity | Équipe : `id` + `name` + `captainId: PlayerId` + `roster`/`pendingRequests: array<string, PlayerId>` (clé = `PlayerId::value`, voir ADR 022) — `requestToJoin()`/`approveJoinRequest()`/`rejectJoinRequest()` pour le flux d'adhésion : rejette une demande d'un joueur déjà au roster (`LogicException`), idempotent si déjà en attente ou déjà approuvé, `InvalidArgumentException` sur approve/reject sans demande en attente |
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
| `PlayerId` | Value Object | Identifiant typé d'un joueur — id **généré** (`readonly class`, simple string non-vide, mirroir `OrganizerId`) ; découplé de l'email depuis ADR 028, qui résout le compromis assumé par ADR 007 §3 |
| `Player` | Entity | Joueur : `PlayerId` + `name` + `email` + mot de passe hashé (`Player::register()`, mirroir `Organizer::register()`) ; un capitaine **est** un `Player`, pas un type à part |
| `Registration` | Value Object | Inscription d'une équipe à un tournoi — `Team` + `Player` (nommé `captain` dans les signatures) |
| `TeamCapacity` | Value Object | Jauge min/max d'équipes d'un tournoi — `readonly class`, `TeamCapacity::of(int, int)`, valide `min >= 2` et `min <= max` |
| `CompetitionId` | Value Object | Identifiant typé d'une compétition — `readonly class` avec `equals()` |
| `CompetitionFormat` | Enum | Format structurel du tournoi — un seul case aujourd'hui, `SingleElimination`. Pure donnée, sans comportement (pas de résolution vers un générateur) ; `fromValue(string): self` valide une valeur brute et lève `InvalidArgumentException` (le `from()` natif de l'enum lève `ValueError`, impossible à surcharger — réservé par PHP) — voir ADR 026 |
| `BracketConfiguration` | Value Object | Regroupe `format: CompetitionFormat` et `includeThirdPlaceMatch: bool` — data clump toujours consommé ensemble par `BracketGeneratorFactory::forConfiguration()` ; `Competition` expose `getFormat()`/`includesThirdPlaceMatch()` en accesseurs distincts, le regroupement reste un détail de construction interne — voir ADR 026 |
| `OrganizationId` | Value Object | Identifiant typé de l'`Organization` propriétaire d'une compétition — `readonly class` avec `equals()`, **local à `Competition`** (ACL, pas de Shared Kernel avec `Organization`, même pattern qu'ADR 027 §5) |
| `Competition` | **Aggregate Root** | Inscription à une compétition — mutable, distinct de `Bracket` (voir ADR 007). `create()` (avec `BracketConfiguration`, choisie une fois pour toutes à la création, et `OrganizationId`, voir ADR 029), `register()`/`withdraw()` (uniquement tant que l'inscription est ouverte), `closeRegistration()` (échoue sous `minTeams`), `generateBracket(BracketGeneratorFactory)` (résout son propre générateur via `$factory->forConfiguration($this->bracketConfiguration)` — protège l'invariant format/générateur, action manuelle et distincte de la clôture, échoue si l'inscription est encore ouverte ou déjà générée) — voir ADR 026. `requestToJoinTeam()`/`approveJoinRequest()`/`rejectJoinRequest()` (même garde "inscription ouverte" que `register()`/`withdraw()`, déléguent à `Team`) + `getTeamPendingRequests()`/`getTeamRoster()` en lecture, pour le flux d'adhésion à une équipe. `$teams` est un `array<string, Team>` keyé par `TeamId::value` (accès direct, pas de scan linéaire) |

`Participant` est le concept central : il représente indifféremment une équipe connue, un bye (avance automatique), ou un vainqueur en attente d'un encounter futur. Le terme `Slot` a été explicitement rejeté car sans sens métier dans le domaine football. Il compte volontairement **3 états, pas plus** — voir plus bas pourquoi le match pour la 3e place n'en a pas ajouté un 4e.

`Bracket` est une interface — le contrat de l'agrégat racine. `SingleEliminationBracket` en est l'implémentation concrète et mutable. `Round` et `Participant` sont des value objects `readonly`. `Encounter` est une Entity interne mutable (elle porte son propre résultat).

**Interface de format** (`Service/BracketGenerator.php`) — contrat unique `generate(Team[] $teams): Bracket`. Tous les formats futurs (double élimination, poules, round-robin) implémentent cette interface.

**`BracketGeneratorFactory`** (`Service/BracketGeneratorFactory.php`) — résout le `BracketGenerator` adapté à une `BracketConfiguration` : `forConfiguration(BracketConfiguration): BracketGenerator`. Map `format => générateur` injectée depuis `services.yaml` (pas de `match` codé en dur sur `CompetitionFormat`) ; décore automatiquement en `BracketGeneratorWithThirdPlaceMatch` si `includeThirdPlaceMatch` est vrai. Ajouter un format futur = une classe générateur + une ligne de config, la factory elle-même n'est jamais modifiée (OCP) — voir ADR 026.

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

- `CreateCompetition/CreateCompetitionCommand` — DTO immuable, ne porte que les données nécessaires à la création (`name`, `minTeams`, `maxTeams`, `format`, `includeThirdPlaceMatch`, `organizerId`, `organizationId`) ; `format` en primitif (`string`, valeur brute de `CompetitionFormat`), cohérent avec le pattern déjà établi pour `minTeams`/`maxTeams` (primitifs sur le DTO, VO construit dans le Handler) ; pas d'id, généré côté persistance (voir plus bas). `organizerId` vient du JWT (`#[CurrentUser]`, jamais du payload client), `organizationId` est explicite dans le payload — un organizer peut posséder plusieurs `Organization`, voir ADR 029.
- `CreateCompetition/CreateCompetitionHandler` — orchestration : vérifie l'autorisation via `OrganizerOrganizationAuthorization::authorizes()` (échoue en `OrganizerNotAuthorizedForOrganizationException`, 403), génère l'id via `CompetitionRepository::nextIdentity()`, construit `BracketConfiguration(CompetitionFormat::fromValue($command->format), $command->includeThirdPlaceMatch)` puis l'agrégat (`Competition::create()`), le persiste (`repository->save()`), et **retourne** le `CompetitionId` créé — entorse assumée à "une Command ne retourne rien", nécessaire pour qu'un appelant synchrone (contrôleur HTTP) connaisse l'id généré. Le Handler est déclaré handler Messenger par tag en config (`services.yaml`), jamais par l'attribut `#[AsMessageHandler]`, pour rester 100% PHP sans dépendance au framework. Format et option 3e place choisis dès la création (pas à la génération du bracket) — voir ADR 026.

**Port d'autorisation** (`Domain/Service/OrganizerOrganizationAuthorization.php`) — contrat unique `authorizes(organizerId: string, OrganizationId): bool`. Implémenté par `Infrastructure/Service/OrganizationOwnershipAuthorization`, qui dispatche une Query (`Organization/Application/IsOrganizerOwnerOfOrganization`, primitifs en entrée/sortie) sur le même bus Messenger que les Commands — premier appel réel inter-bounded-context du projet et première Query CQRS (jusqu'ici uniquement des Commands). Zéro référence à `Organization/Domain` depuis `Competition` : la frontière ACL passe par la couche Application d'`Organization`, jamais par ses classes Domain directement — voir ADR 029.

**Port repository** (`Domain/Repository/CompetitionRepository.php`) — contrat `nextIdentity(): CompetitionId`, `save(Competition): void`, `ofId(CompetitionId): ?Competition`.

- `RegisterTeam/RegisterTeamCommand` — DTO immuable (`competitionId`, `teamName`, `captainId`) ; inscription **capitaine-seul**, le roster complet se construit via un futur flux "rejoindre une équipe" (Priorité 6, validation par le capitaine) — enregistrer une équipe déjà complète court-circuiterait cette validation. `captainId` vient du JWT (`#[CurrentUser]`, jamais du payload client) — un joueur authentifié s'inscrit lui-même comme capitaine, mirroir exact d'`organizerId` sur `CreateCompetitionCommand`.
- `RegisterTeam/RegisterTeamHandler` — vérifie l'existence de la `Competition` (`CompetitionRepository::ofId()`) et du `Player` capitaine (`PlayerRepository::ofId()`, doit déjà être persisté — échoue sinon, pas d'upsert) avant d'écrire ; construit l'équipe (`Team::create()`, id généré via `TeamRepository::nextIdentity()`), l'enregistre sur l'agrégat (`Competition::register()`), persiste, et retourne le `TeamId` créé

**Port repository** (`Domain/Repository/TeamRepository.php`) — contrat réduit à `nextIdentity(): TeamId` : `Team` n'a pas de persistance propre (voir ADR 022), ce port ne sert qu'à générer l'identifiant. Branché sur `InMemoryTeamRepository` en production comme en test, pas de `DoctrineTeamRepository` nécessaire.

- `RegisterPlayer/RegisterPlayerCommand` (ex-`CreatePlayer`) — DTO immuable (`name`, `email`, `plainPassword`) ; `PlayerId` est désormais un id généré (`PlayerRepository::nextIdentity()`), découplé de l'email — voir ADR 028, qui résout le compromis explicitement signalé par ADR 007 §3
- `RegisterPlayer/RegisterPlayerHandler` — vérifie l'existence du `Player` via `PlayerRepository::ofEmail()` avant d'écrire : crée et persiste si absent (id généré, mot de passe hashé via `PasswordHasher`), ne touche à rien si déjà présent (ni nom, ni mot de passe écrasés) ; retourne toujours le même `PlayerId` dans les deux cas. Réponse HTTP identique (201) qu'il y ait eu création ou non — anti-énumération d'utilisateurs, voir ADR 024. Auto-inscription confirmée (le joueur crée son propre compte) — distinct d'un futur `AddPlayerToTeam` où un capitaine ajouterait des coéquipiers, hors périmètre.
- `Login/LoginCommand` (`Competition`) — DTO immuable (`email`, `plainPassword`), mirroir exact d'`Organization/Application/Login`
- `Login/LoginHandler` (`Competition`) — vérifie les identifiants (`PlayerRepository::ofEmail()` + `PasswordHasher::verify()`), lève `InvalidCredentialsException` sinon (401, pas 422), émet le JWT via `AccessTokenIssuer` en cas de succès. Ports `PasswordHasher`/`AccessTokenIssuer` dupliqués dans `Competition/Domain/Service` (pas de dépendance vers ceux d'`Organization`, même si `deptrac` l'autoriserait) — voir ADR 027 "Conséquences" et ADR 028. Exposé en HTTP via `POST /players/login` (`/login` déjà pris par `Organizer`). `PlayerUserProvider implements UserProviderInterface` (mirroir `OrganizerUserProvider`) résout un `Player` depuis le claim JWT via `PlayerRepository::ofId()`, enveloppé dans `SecurityPlayer implements UserInterface`. Firewall JWT dédié `register_team` (`security.yaml`, mirroir `create_competition`) scopé à `POST /competitions/{id}/teams` : `RegisterTeamController` résout le capitaine via `#[CurrentUser] SecurityPlayer`, plus de `captainId` dans le payload client. `Withdraw`/`CloseRegistration` restent sans authentification — question d'autorisation (capitaine seul ? organisateur seul ? les deux ?) à trancher avant d'y toucher.
- `Withdraw/WithdrawCommand` — DTO immuable (`competitionId`, `teamId`, `actorId`) ; `actorId` vient du JWT (`#[CurrentUser] UserInterface`, jamais du payload client), sans distinction de type d'acteur — voir ADR 030.
- `Withdraw/WithdrawHandler` — délègue la règle d'état (inscription ouverte, équipe existante) à `Competition::withdraw()`, déjà couverte côté domaine. Autorisation à double acteur (capitaine **ou** organisateur propriétaire) : compare `actorId` au capitaine de l'équipe (`Competition::getTeamCaptainId()`) puis, seulement si faux, au port `OrganizerOrganizationAuthorization` (ADR 029) — court-circuit `||`, pas de tag `actorType`, aucun `instanceof` nulle part dans le code applicatif (`SecurityPlayer`/`SecurityOrganizer` exposent tous deux `getUserIdentifier()` via `UserInterface`). Rejet unifié via `NotAuthorizedToWithdrawException` (403). Firewall JWT dédié `withdraw` (`security.yaml`) avec un provider `chain` (`players_or_organizers`, premier du projet) scopé à `DELETE /competitions/{id}/teams/{teamId}`. Voir ADR 030.
- `CloseRegistration/CloseRegistrationCommand` — DTO immuable (`competitionId`)
- `CloseRegistration/CloseRegistrationHandler` — orchestration minimale, même patron que `Withdraw` : délègue entièrement la règle métier (minimum de teams atteint) à `Competition::closeRegistration()`, déjà couverte côté domaine (`CompetitionTest`) ; ne teste à son niveau que ce qu'il possède en propre (compétition inconnue). Exposé en HTTP via `POST /competitions/{id}/close-registration` — voir ADR 025 pour le tag Messenger (bloc `resource:` unique, plus de bloc par use case).
- `GenerateBracket/GenerateBracketCommand` — DTO immuable (`competitionId`)
- `GenerateBracket/GenerateBracketHandler` — orchestration minimale, même patron que `CloseRegistration`/`Withdraw` : injecte `BracketGeneratorFactory` (autowired), délègue entièrement la règle métier (inscription close, bracket pas déjà généré) à `Competition::generateBracket($factory)`, déjà couverte côté domaine (`CompetitionTest`) ; ne teste à son niveau que ce qu'il possède en propre (compétition inconnue). Exposé en HTTP via `POST /competitions/{id}/generate-bracket` — voir ADR 026.
- `RequestToJoinTeam/RequestToJoinTeamCommand` — DTO immuable (`competitionId`, `teamId`, `playerId`) ; `playerId` vient du JWT (`#[CurrentUser] SecurityPlayer`, jamais du payload client), mirroir exact de `captainId` sur `RegisterTeamCommand` — un joueur authentifié demande lui-même à rejoindre une équipe. Premier use case du flux "rejoindre une équipe" (Priorité 6, voir ROADMAP) ; reste à faire : `ApproveJoinRequest`/`RejectJoinRequest` (couche Domain déjà prête sur `Team`/`Competition`).
- `RequestToJoinTeam/RequestToJoinTeamHandler` — vérifie l'existence de la `Competition` et du `Player` demandeur (mêmes gardes que `RegisterTeamHandler`) avant de déléguer entièrement la règle métier (inscription ouverte, équipe existante, pas déjà au roster, idempotent si déjà en attente) à `Competition::requestToJoinTeam()`, déjà couverte côté domaine ; ne teste à son niveau que ce qu'il possède en propre (compétition/joueur inconnus). Exposé en HTTP via `POST /competitions/{id}/teams/{teamId}/join-requests`, firewall JWT dédié `request_to_join_team` (`security.yaml`, mirroir `register_team`).
- `ApproveJoinRequest/ApproveJoinRequestCommand` — DTO immuable (`competitionId`, `teamId`, `playerId`, `actorId`) ; `playerId` identifie le demandeur dont la requête est approuvée, `actorId` vient du JWT (`#[CurrentUser] SecurityPlayer`, jamais du payload client) et doit être le capitaine de l'équipe — autorisation à **acteur unique**, contrairement à `Withdraw` (ADR 030) : "c'est uniquement le capitaine qui gère son équipe", pas de fallback organisateur.
- `ApproveJoinRequest/ApproveJoinRequestHandler` — vérifie l'existence de la `Competition` (même garde que les autres Handlers) puis compare `actorId` au capitaine de l'équipe (`Competition::getTeamCaptainId()`) avant de déléguer entièrement la règle métier (demande en attente, idempotent si déjà au roster) à `Competition::approveJoinRequest()`, déjà couverte côté domaine. Rejet d'autorisation via `NotAuthorizedToManageJoinRequestException` (403, voir ADR 031). Exposé en HTTP via `POST /competitions/{id}/teams/{teamId}/join-requests/{playerId}/approve`, firewall JWT dédié `approve_join_request` (`security.yaml`, provider `players` seul).
- `RejectJoinRequest/RejectJoinRequestCommand`/`RejectJoinRequestHandler` — même patron exact qu'`ApproveJoinRequest` (autorisation capitaine-seul, même exception `NotAuthorizedToManageJoinRequestException` réutilisée — c'est la même règle d'autorisation, pas une nouvelle), délègue à `Competition::rejectJoinRequest()` déjà couverte côté domaine (pas d'idempotence ici, contrairement à `approveJoinRequest()` — rejeter un membre déjà confirmé n'est pas l'état déjà atteint). Exposé en HTTP via `POST /competitions/{id}/teams/{teamId}/join-requests/{playerId}/reject`, firewall JWT dédié `reject_join_request`.

### Couche infrastructure (`src/Competition/Infrastructure/`)

- `Persistence/InMemory/InMemoryCompetitionRepository` — implémente `CompetitionRepository`, stockage en `array` ; sert à la fois d'adapter réel (faute de vraie persistance) et de test double dans les tests d'Application, sans mock
- `Http/CreateCompetitionController` — route `POST /competitions` (attribut `#[Route]`), construit la Command depuis un DTO validé (`Http/CreateCompetitionRequest` + `#[MapRequestPayload]`), dispatch sur le bus, lit le résultat via `$envelope->last(HandledStamp::class)->getResult()`, répond 201 + id. Vit sous `Infrastructure/Http/` du module, pas `src/Controller/` — voir ADR 009 sur pourquoi l'auto-découverte des routes ne dépend pas d'un dossier fixe.
- `Http/InvalidArgumentExceptionListener` — écouteur `kernel.exception` global du module : désencapsule `HandlerFailedException` (Messenger), mappe toute `\InvalidArgumentException` du domaine en `JsonResponse(['error' => ...], 422)`. Voir ADR 015.
- `Http/NotAuthorizedExceptionListener` — même patron, mappe toute `Domain\Exception\NotAuthorizedException` (classe abstraite, base commune à `NotAuthorizedToWithdrawException`/`OrganizerNotAuthorizedForOrganizationException`/`NotAuthorizedToManageJoinRequestException`) en `JsonResponse(['error' => ...], 403)` — un seul listener pour toute règle d'autorisation, pas un par exception concrète. Voir ADR 031.

Bootstrap Symfony (Kernel, `config/`, `public/index.php`, `bin/console`) posé via `symfony/flex` et ses recipes officielles, pas assemblé à la main — voir ADR 009, notamment le scope de l'auto-discovery des services (`config/services.yaml`) et pourquoi il exclut tout le Domain.

### Conventions de nommage

- `Encounter` à la place de `Match` (mot-clé réservé PHP 8) et `Fixture` (sans sens métier)
- `Participant` à la place de `Slot` (sans sens métier dans le foot)
- `getEncounters()` / `countEncounters()` — jamais `getMatches()` / `countMatches()`
- Les IDs sont toujours des Value Objects typés (ex: `EncounterId`), jamais des `string` nus

### Module `Organization`

Bounded context distinct, fondations multi-tenant : auth organisateur et paiement simulé (`src/Organization/{Domain,Application,Infrastructure}`) — voir ADR 027 pour le raisonnement complet.

**Domaine** (`Domain/Model/`) :

| Classe | Type DDD | Rôle |
|--------|----------|------|
| `OrganizerId` | Value Object | Identifiant typé, généré (`nextIdentity()`) — contrairement à `PlayerId` (email), voir ADR 027 §2 |
| `Organizer` | Entity | Compte organisateur : email + mot de passe hashé, `register()` valide l'email — distinct de `Player` |
| `OrganizationId` | Value Object | Identifiant typé du tenant — local au module (ACL, pas Shared Kernel avec `Competition`) |
| `Organization` | **Aggregate Root** | Le tenant : `id` + `name` + `ownerId: OrganizerId` — `create()` uniquement, jamais d'état "pending" |
| `CheckoutSessionId` / `CheckoutReference` | Value Object | Identifiant typé de session / référence opaque retournée par `PaymentGateway::initiateCheckout()` |
| `CheckoutSessionStatus` | Enum (backé string) | `Pending` / `Completed` / `Failed` — backé pour permettre le mapping Doctrine `enum-type` (mirroir `CompetitionFormat`) |
| `CheckoutSession` | Aggregate | État intermédiaire d'un paiement en cours : `initiate()`, `complete(OrganizationId)`/`fail()` (protègent l'invariant "pending seulement", `\LogicException` sinon) |

**Services Domain** (`Domain/Service/`) : `PasswordHasher` (`hash()`/`verify()`), `PaymentGateway` (`initiateCheckout(): CheckoutReference`), `AccessTokenIssuer` (`issue(OrganizerId): string`, émet un JWT) — tous des ports, implémentés en Infrastructure (`NativePasswordHasher`, `FakePaymentGateway`, `LexikAccessTokenIssuer`).

**Application** (un dossier par use case, même patron CQRS que `Competition`) :

- `RegisterOrganizer` — crée l'`Organizer`, échoue explicitement si email déjà pris (pas d'anti-énumération ici, contrairement à `RegisterPlayer`/ADR 024 — voir ADR 027 §7)
- `Login` — vérifie les identifiants (`OrganizerRepository::ofEmail()` + `PasswordHasher::verify()`), lève `InvalidCredentialsException` sinon (401, pas 422), émet le JWT via `AccessTokenIssuer` en cas de succès
- `InitiateOrganizationCheckout` — crée une `CheckoutSession` `Pending`, retourne la `CheckoutReference`
- `ConfirmOrganizationCheckout` — reçoit la référence + le résultat (webhook simulé), **idempotent** (rejouer sur une session déjà terminée renvoie le résultat existant), crée l'`Organization` seulement si succès
- `IsOrganizerOwnerOfOrganization` — première **Query** CQRS du projet (jusqu'ici uniquement des Commands) : `IsOrganizerOwnerOfOrganizationQuery` (primitifs `organizerId`/`organizationId`) / `Handler` (bool). Sert exclusivement de frontière ACL pour `Competition` (voir ADR 029) — dispatchée sur le même bus Messenger que les Commands, jamais appelée par accès direct aux classes `Domain` de ce module.

**Infrastructure** :

- Persistance Doctrine des 3 agrégats (mapping XML, `config/packages/doctrine_organization.yaml`), même discipline qu'ADR 010 — `OrganizerIdType`/`OrganizationIdType`/`CheckoutSessionIdType`/`CheckoutReferenceType`
- `SecurityOrganizer implements UserInterface` — adapter minimal construit à la volée depuis un `OrganizerId`, jamais porté par `Organizer` lui-même (zéro dépendance Symfony Security dans le Domain, même discipline que le mapping Doctrine)
- `OrganizerUserProvider implements UserProviderInterface` — recharge l'`Organizer` depuis le claim JWT via `OrganizerRepository::ofId()`
- `LexikJWTAuthenticationBundle` : firewall `organization_checkout` (`security.yaml`) scopé au seul `POST /organizations/checkout`, pas un firewall JWT global
- Endpoints HTTP : `POST /organizers`, `POST /login`, `POST /organizations/checkout` (authentifiée via `#[CurrentUser]`), `POST /organizations/checkout-webhook` (publique, simule le webhook fournisseur) — même patron `#[MapRequestPayload]` + dispatch CQRS que `Competition/Infrastructure/Http/*`
- `InvalidCredentialsExceptionListener` (mirroir `InvalidArgumentExceptionListener`) mappe `InvalidCredentialsException` en 401 JSON

Rattacher `Competition` à une `Organization` (`organizationId`) reste à faire — hors périmètre de ce chantier, voir ADR 027 "Conséquences".

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
- ADR 010 — Mapping Doctrine de `Competition` : périmètre du premier mapping (`id`/`name`/`capacity`/`closed`, pas `registrations` ni `bracket`), VOs identifiants via Doctrine Type dédié (pas embeddable) + `Stringable` requis sur tout VO identifiant, `TeamCapacity` en embeddable, config Doctrine scopée par module (`config/packages/doctrine_competition.yaml`), convention de nommage des fichiers XML de mapping
- ADR 011 — Environnement Docker : container `app` PHP-CLI seul (pas de serveur web dédié type FrankenPHP tant qu'aucun besoin d'exposition continue), bind-mount du repo entier + `composer install` relancé à chaque démarrage via `entrypoint.sh` (plutôt qu'un volume nommé pour `vendor/`) pour que `vendor/` reste indexable par l'IDE sans dépendance locale à PHP/Composer
- ADR 012 — Reset de la base entre chaque test : `DAMADoctrineTestBundle` (transaction + rollback automatique par test, actif uniquement en env `test`) plutôt qu'un truncate/delete manuel en `tearDown()`
- ADR 013 — `services_test.yaml` réécrit `CompetitionRepository` vers `InMemoryCompetitionRepository` pour les tests HTTP contrôleur (contrat HTTP seulement, pas de persistance à re-tester — déjà couverte par `DoctrineCompetitionRepositoryTest`)
- ADR 014 — Smoke test e2e : un seul happy path (les codes d'erreur HTTP restent dans `CreateCompetitionControllerTest`, pas de signal supplémentaire à les re-tester contre une vraie base), surcharge locale de `CompetitionRepository` via `self::getContainer()->set()` plutôt que `services_test.yaml`
- ADR 015 — Violations de règles métier (`\InvalidArgumentException`) mappées en 422 JSON via un listener `kernel.exception` global (`InvalidArgumentExceptionListener`), pas de try/catch par contrôleur ; `\LogicException` volontairement pas encore intercepté (pas encore atteignable en HTTP)
- ADR 016 — Xdebug (pas PCOV) pour la couverture de tests + debug PhpStorm, désactivé par défaut (`XDEBUG_MODE=off`) pour ne pas ralentir le run quotidien ; pas de rapport de couverture déclaré dans `phpunit.dist.xml` (rendrait un driver obligatoire pour tout run) ; script `composer test:coverage` pour l'usage ponctuel
- ADR 017 — CI GitHub Actions rejoue `docker compose` (pas de setup PHP natif GitHub Actions) pour un seul chemin d'exécution CI/local ; tests uniquement pour l'instant (pas d'analyse statique) ; attente explicite de `vendor/autoload.php` plutôt qu'un `sleep` fixe ; cache `vendor/` via `actions/cache`
- ADR 018 — PHPStan niveau `max` (extensions Symfony/Doctrine) ; les 6 erreurs réelles dans `src/` corrigées (`assert()` pour narrower les frontières framework/Doctrine, un override ne peut pas restreindre le type `mixed` hérité de `Doctrine\DBAL\Types\Type`) ; les 43 erreurs de `tests/` (types génériques non affinables) mises en `phpstan-baseline.neon` plutôt que de réécrire les tests dans cet incrément
- ADR 019 — PHP-CS-Fixer `@Symfony` (pas `:risky`, formatage seulement) ; `php_unit_method_casing` forcé en `snake_case` (convention déjà établie pour les noms de tests) ; `yoda_style`/`concat_space`/`increment_style` désactivées par préférence explicite après revue règle par règle ; CI en vérification seule (`cs-check`), jamais d'auto-fix
- ADR 020 — `Makefile` (pas `just`, pas d'alias shell personnels) pour raccourcir les commandes `docker compose` déjà documentées ; `make help` liste les cibles
- ADR 021 — CI : cache des layers Docker via `docker/build-push-action` (`type=gha`) plutôt que `docker compose build` seul (aucun cache entre runs) ; build de l'image 3m14s → 28s une fois le cache chaud
- ADR 025 — Handlers Messenger taggés par un unique bloc `resource:` ciblant `*Handler.php` (pas un bloc par use case, pas de split de `services.yaml`) ; ajouter un use case n'implique plus de toucher la config tant que son Handler suit la convention de nommage établie
- ADR 026 — Format de compétition choisi à la création (`CompetitionFormat`/`BracketConfiguration` sur `Competition::create()`, pas au moment de générer le bracket) ; `includeThirdPlaceMatch` orthogonal au format, pas un 2e case d'enum ; `BracketGeneratorFactory` (map format → générateur injectée en config, pas de `match` codé en dur) résout le générateur, `Competition::generateBracket(BracketGeneratorFactory)` protège l'invariant format/générateur en interne à l'agrégat
- ADR 027 — Bounded context `Organization` (dépendance à sens unique vers/depuis `Competition` imposée par `deptrac`) : `Organizer` distinct de `Player` (id généré, pas email) ; `Organization` créée uniquement après paiement confirmé (pas d'état "pending" sur l'agrégat) ; paiement par flux initiation/confirmation asynchrone (`PaymentGateway`/`CheckoutSession`, webhook simulé idempotent), jamais de `charge()` synchrone ; `OrganizationId` en ACL (pas de Shared Kernel) ; auth par JWT stateless (`LexikJWTAuthenticationBundle`, pas de token opaque persisté, pas de révocation en v1) avec `SecurityOrganizer`/`OrganizerUserProvider` en adapters Infrastructure (zéro interface Symfony Security sur `Organizer`) ; email dupliqué à l'inscription en erreur explicite (pas anti-énumération, contexte différent de `RegisterPlayer`/ADR 024)
- ADR 028 — Login `Player` : `PlayerId` devient un id généré, découplé de l'email (résout le compromis assumé par ADR 007 §3) ; `CreatePlayer` renommé `RegisterPlayer` une fois confirmée la sémantique d'auto-inscription (pas un tiers qui construit un roster) ; ports `PasswordHasher`/`AccessTokenIssuer` dupliqués dans `Competition/Domain/Service`, jamais réutilisés depuis `Organization` malgré la dépendance `deptrac` autorisée (ADR 027 "Conséquences") ; `POST /players/login` sans `PlayerUserProvider`/firewall dédié tant qu'aucune route ne consomme le JWT émis ; SSO (Google/Apple OIDC) évalué et volontairement écarté
- ADR 029 — Rattachement `Competition.organizationId` : `organizationId` explicite dans la requête de création (pas résolu implicitement depuis l'organizer — un organizer peut posséder plusieurs `Organization`, contrairement à un premier design `OrganizerOrganizationResolver` rejeté) ; port d'autorisation étroit `OrganizerOrganizationAuthorization::authorizes()` plutôt qu'une résolution implicite, 403 (pas 422) si l'organizer n'est pas propriétaire ; frontière ACL tenue via une nouvelle Query CQRS côté `Organization/Application` (`IsOrganizerOwnerOfOrganization`), jamais via un accès direct à `Organization/Domain` depuis `Competition` — Conformist (accès direct au Domain d'`Organization`) et simulation d'une frontière réseau (sous-requête HTTP/client loopback, anticipant un futur split microservices) tous deux sérieusement envisagés puis rejetés
- ADR 030 — Autorisation à double acteur pour `Withdraw` (capitaine **ou** organisateur propriétaire) : chain provider Symfony (`players_or_organizers`) plutôt qu'un parsing JWT manuel, un seul firewall pour les deux types d'identité ; pas de nouvelle règle métier sur `Competition` (rejeté `isTeamCaptain()`, autorisation reste hors agrégat comme pour `CreateCompetition`/`CloseRegistration`), juste un accesseur de lecture `getTeamCaptainId()` qui lève plutôt que de retourner `null` (mirroir `withdraw()`) ; pas de tag `actorType` sur la Command (rejeté, tag string simulant un typage que PHP fournit déjà) ni de split en deux Commands par type d'acteur (rejeté, duplication de plomberie non justifiée) — un seul `actorId` testé en cascade (`||`) contre les deux vérifications, `PlayerId`/`Organizer` vivant dans des espaces d'id disjoints
- ADR 031 — Listener HTTP unique pour les exceptions "non autorisé" : `NotAuthorizedException` (classe abstraite, marqueur de famille) remplace l'étage `\RuntimeException` direct sur `NotAuthorizedToWithdrawException`/`OrganizerNotAuthorizedForOrganizationException`/`NotAuthorizedToManageJoinRequestException` ; un seul `NotAuthorizedExceptionListener` catch ce type de base et répond 403 (mirroir `InvalidArgumentExceptionListener`/422, ADR 015), remplace les deux listeners par-exception précédents (déclenché en ajoutant `ApproveJoinRequest`, où un 3e clone serait devenu la duplication de trop) ; interface marqueur envisagée puis rejetée, aucun besoin d'un parent autre que `\RuntimeException` aujourd'hui

## Workflow

- **TDD strict, pas test-first** : cycle red-green-refactor, un comportement à la fois. Écrire un test qui échoue pour la bonne raison avant tout code de production, le code minimal pour le faire passer, refactor avant le test suivant. Jamais de test écrit après coup ; jamais plusieurs comportements dans un seul test. Le nombre d'allers-retours peut être allégé sur les étapes triviales, mais le red-before-green n'est jamais sauté.
  - Un seul test à la fois : ne jamais écrire plusieurs méthodes de test dans le même fichier/edit avant d'avoir fait passer la précédente au vert. Si plusieurs comportements sont anticipés, les poser en liste (mentalement ou en commentaire de suivi), pas en code.
  - Après chaque vert, marquer explicitement le passage par l'étape refactor avant d'écrire le prochain test — même quand la réponse est « rien à changer » sur une étape triviale. La pause fait partie de la discipline, pas seulement son résultat.
  - Ce n'est pas une préférence de style : c'est une exigence non négociable du workflow, à appliquer sans qu'il soit nécessaire de le rappeler.
- **Chaque couche teste sa propre API publique, jamais la couche qu'elle délègue** : une règle métier se teste dans la couche qui la porte, une seule fois. `Domain` teste les invariants et règles métier de l'agrégat via son API publique. `Application` ne re-teste jamais une règle métier déjà couverte côté `Domain` — un Handler qui délègue intégralement une règle à l'agrégat (ex: `WithdrawHandler`/`CloseRegistrationHandler` qui appellent `Competition::withdraw()`/`closeRegistration()`) n'a pas à la retester, seulement ce qui lui est propre : orchestration, résolution de dépendances externes (repository qui retourne `null`), tout traitement additionnel que le use case effectue réellement au-delà de la pure délégation. Même principe en cascade pour `Infrastructure`/HTTP : un contrôleur ne re-teste pas les règles déjà couvertes en `Application`, seulement le contrat HTTP (statuts, payloads, routing). Avant d'ajouter un test dans une couche, vérifier si le comportement est déjà couvert par la couche qu'elle délègue (grep/lecture du test correspondant) — si oui, ne pas dupliquer.
- **Tests comportementaux** : vérifier un comportement observable via l'API publique, jamais en miroir de l'implémentation interne. Pas de mock pour vérifier qu'une méthode interne a été appelée. Récupérer les valeurs générées dynamiquement (IDs, etc.) depuis l'API publique plutôt que de les coder en dur — un détail d'implémentation ne doit jamais faire échouer un test.
- **Tests HTTP : arranger les préconditions via repository, jamais en chaînant une requête HTTP de setup** : quand un test de contrôleur a besoin d'un état préexistant (ex: une `Competition` déjà créée avant de tester l'inscription d'une équipe), construire cet état directement via le repository/domaine (`Competition::create()` + `repository->save()`) puis faire un seul `$client->request()` — celui qui teste réellement l'endpoint visé. Chaîner plusieurs appels HTTP dans un même test couple sa réussite à la correction d'un autre contrôleur, et force à désactiver le reboot du kernel entre requêtes (`$client->disableReboot()`) — un garde-fou qui détecte justement les bugs d'état statique/container, à ne pas désactiver pour la requête sous test elle-même.
- **Design avant implémentation** : pour toute tâche architecturalement significative (nouvel agrégat, nouveau pattern, décision de modélisation DDD ambiguë), valider explicitement l'approche avec l'utilisateur avant le premier `Write`/`Edit`. Pas nécessaire pour les tâches mécaniques (bug fix évident, renommage local).
- **Challenge DDD, pas exécution passive** : le domaine est modélisé en DDD par choix, pas par défaut. Questionner activement le vocabulaire (ubiquitous language), la cohérence d'une règle métier proposée, et l'impact d'une demande sur les invariants du domaine — même quand l'implémentation semble simple à exécuter telle quelle. Signaler un terme sans sens métier ou une règle qui semble incomplète/contradictoire avant d'implémenter, pas après.
- **Confronter une nouvelle décision aux ADR existantes avant d'implémenter** : ne pas résoudre les questions de modélisation une par une, en laissant chaque implémentation faire remonter la question adjacente suivante par ricochet. Avant d'écrire du code, mapper explicitement la décision envisagée contre toutes les ADR déjà actées sur les entités concernées (`docs/adr/`, tableau de modèle de ce fichier) pour détecter le couplage en amont. Si une nouvelle question remet en cause une décision qui vient d'être prise dans la même conversation, c'est le signal d'arrêter complètement l'implémentation et de faire une seule passe de cadrage sur toutes les questions couplées ouvertes — pas de patcher localement et continuer. Dans ces discussions, formuler une recommandation concrète ancrée dans la vision produit/les ADR existantes plutôt que de se limiter à poser des questions ouvertes — challenger activement en expert métier, pas seulement exécuter la première réponse venue.
- **ADR pour les décisions impactantes** : toute décision qui change un contrat, introduit un pattern, ou avait une alternative sérieusement envisagée puis rejetée, est documentée dans `docs/adr/NNN-titre.md` (Contexte / Décision / Conséquences) — pas seulement actée en conversation ou résumée ici. Écrire l'ADR fait partie du travail, pas une étape à rattraper plus tard.
- **Petits incréments** : tout travail qui traverse plusieurs couches ou introduit un nouveau pattern se découpe en la plus petite tranche testable possible. Ne concevoir/trancher une étape que lorsque la précédente est terminée — ne pas figer à l'avance des décisions qui ne concernent pas encore l'étape en cours. Si une étape est difficile à committer proprement seule, c'est le signe qu'elle est mal découpée.
- **Commits** : format Conventional Commits (`<type>(<scope>): <description>`), toujours avec un corps en français qui explique le pourquoi. Types courants : `feat`, `fix`, `refactor`, `test`, `docs`, `chore`. Un commit par incrément vert — jamais plusieurs incréments regroupés dans un seul commit, jamais d'attente de la fin de session.
- **Documentation à jour dès qu'une feature est terminée** : quand un use case est fait bout en bout (Application + HTTP), mettre à jour dans la foulée — pas en fin de session, pas à la prochaine relance — `ROADMAP.md` (case cochée + détail), la section Application de ce fichier (nouvelle entrée), et "Prochaine étape prioritaire" ci-dessous. Commit `docs` séparé du/des commit(s) `feat` de la feature elle-même. Une session qui se termine sans cette mise à jour laisse la doc désynchronisée du code pour la session suivante.

## Prochaine étape prioritaire

Priorité 5b (persistance réelle) ✅ implémentée. Priorité 5c (API REST pour les autres use cases) ✅ **entièrement terminée** : `RegisterTeam`, `RegisterPlayer` (ex-`CreatePlayer`, ADR 024), `Withdraw`, `CloseRegistration`, `GenerateBracket` (ADR 026), multi-tenancy (`Organization`, ADR 027), login `Player` (ADR 028) et rattachement `Competition.organizationId` (ADR 029, dernier point laissé ouvert par ADR 027) faits bout en bout (Domain/Application/Infrastructure + HTTP + Security). Priorité 6 : gestion des utilisateurs (rôles/droits) ✅ **entièrement terminée** — `PlayerUserProvider`/firewall dédié, capitaine authentifié dans `RegisterTeam` (mirroir `organizerId`), organisateur authentifié dans `CloseRegistration` (mirroir `CreateCompetition`), autorisation à double acteur (capitaine ou organisateur) dans `Withdraw` via chain provider (ADR 030).

**Flux "rejoindre une équipe" (Priorité 6) ✅ entièrement terminé** : couche Domain (`Team`/`Competition`), `RequestToJoinTeam`, `ApproveJoinRequest` et `RejectJoinRequest` (Application + HTTP) faits bout en bout — autorisation capitaine-seul (acteur unique, pas de double acteur comme `Withdraw`) pour approve/reject, rejet via `NotAuthorizedToManageJoinRequestException`/`NotAuthorizedExceptionListener` unifié (ADR 031).

**Prochaine étape concrète en reprenant (reste de Priorité 6)** : règles de cohérence inter-équipes (unicité du nom d'équipe, un joueur ne peut appartenir qu'à une seule `Team` par `Competition` — incrément dédié, nuances à trancher avec l'utilisateur avant d'implémenter : casse/normalisation du nom, un `withdraw` libère-t-il immédiatement nom/joueurs) et flux "quitter une équipe" (départ volontaire/exclusion d'*un seul joueur* du roster, distinct de `Withdraw` qui retire toute l'équipe — cas du capitaine qui part, désignation d'un successeur, pas encore conçu). Voir détail dans `ROADMAP.md` (Priorité 6).

Après ça : choix du front (Vue 3 ou Twig) déplacé en toute dernière étape (Priorité 8), une fois l'API stabilisée. Le détail complet du plan est dans `ROADMAP.md`.