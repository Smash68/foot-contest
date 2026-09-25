# Foot Contest — Roadmap

Vision produit et acteurs : [`README.md`](README.md). Modèle de domaine et conventions : [`CLAUDE.md`](CLAUDE.md). Raisonnement derrière chaque décision structurante : [`docs/adr/`](docs/adr/).

## Priorité 1 — Avancement du bracket (suite) ✅ implémenté

#### 1a — Détection de fin de tournoi ✅ implémenté

Quand la finale est jouée (`recordResult()` sur le dernier encounter), le bracket est terminé. Exposé via `Bracket::isComplete(): bool` et `Bracket::getChampion(): TeamId` (`Bracket` référence les équipes par id depuis l'ADR 022, pas par `Team` complète).

#### 1b — Score enrichi ✅ implémenté

Voir ADR 005 (`EncounterResult` neutre, `Score` VO, `afterExtraTime()`, `afterPenalties()`).

## Priorité 2 — Match pour la 3e place ✅ implémenté

Optionnel, activé par composition (`BracketGeneratorWithThirdPlaceMatch` / `BracketWithThirdPlaceMatch`) — voir ADR 006.

## Priorité 3 — Inscription au tournoi ✅ implémenté (couche domaine)

Agrégat `Competition` : création (`TeamCapacity` valide min/max), inscription/désistement d'une équipe, clôture et génération du bracket comme deux actions manuelles et distinctes de l'organisateur — voir ADR 007. Modélisation corrigée depuis : `Registration` a disparu, `Team` porte directement `name`/`captainId: PlayerId`/`roster: PlayerId[]` — voir ADR 022.

Couche `Application/` volontairement reportée à la priorité 5 : sans repository, un use case n'aurait aucune orchestration réelle à faire au-delà d'un appel direct à l'agrégat.

## Priorité 4 — Autres formats de tournoi

- Double élimination
- Phase de poules + élimination directe
- Round-robin / championnat

## Priorité 5 — Infrastructure

#### 5a — Premier use case bout-en-bout : création d'une compétition ✅ implémenté

`POST /competitions` → contrôleur → Command CQRS dispatchée sur le bus Messenger → Handler (génère l'id via le repository, persiste, retourne l'id créé) → réponse 201. Bootstrap Symfony complet via `symfony/flex`. Voir ADR 008 (couche Application/CQRS) et ADR 009 (bootstrap infrastructure). Persistance actuelle : `InMemoryCompetitionRepository`.

#### 5b — Persistance réelle ✅ implémenté

Remplacer `InMemoryCompetitionRepository` par une vraie base de données. Choix retenu : Doctrine ORM + PostgreSQL, bootstrappé via Flex. Voir ADR 010 : mapping XML de `Competition` fait pour `id`/`name`/`capacity`/`closed` (`DoctrineCompetitionRepository`, testé par aller-retour réel sur PostgreSQL). `registrations` (ADR 022) et `bracket` (ADR 023) mappés dans un second temps — collection de VOs et polymorphisme d'agrégat, deux décisions à part entière.

Reste à faire :
- ✅ Environnement Docker (container `app` PHP-CLI + `database` PostgreSQL) — voir ADR 011
- ✅ Reset de la base de données entre chaque test (`DAMADoctrineTestBundle`, transaction + rollback automatique par test) — voir ADR 012
- ✅ Rebrancher `services.yaml` : `CompetitionRepository` pointe désormais vers `DoctrineCompetitionRepository`
- ✅ `services_test.yaml` : garde `InMemoryCompetitionRepository` pour `CreateCompetitionControllerTest` (test de contrat HTTP, pas de persistance — la couverture Doctrine vit déjà dans `DoctrineCompetitionRepositoryTest`) — voir ADR 013
- ✅ Smoke test e2e contre la stack réelle (`CreateCompetitionEndToEndTest`, un seul happy path — voir ADR 014)
- ✅ `CreateCompetitionControllerTest` couvre les 4 réponses de la route (201/422×3/400) ; violation de règle métier mappée en 422 JSON au lieu d'un 500 qui fuitait le message d'exception — voir ADR 015
- ✅ Persistance de `Player` en agrégat indépendant (table `id`/`name`, `PlayerRepository`) — un joueur existe indépendamment de toute compétition — voir ADR 022
- ✅ `Bracket` référence les équipes par `TeamId` (pas par `Team` complète) ; `Team` absorbe `Registration` (`name`/`captainId`/`roster`), reste une entité interne à `Competition` sans repository dédié — voir ADR 022 (corrige ADR 007 §2)
- ✅ Mapper `registrations` : colonne JSON auto-suffisante (`team_id`/`team_name`/`captain_id`), pas de lookup repository nécessaire à l'hydratation — voir ADR 022
- ✅ Mapper `bracket` (interface polymorphe `Bracket`/`SingleEliminationBracket`/décorateur) : colonne JSON auto-suffisante scopée à une compétition, discriminant de type, décorateur 3e place reconstruit par réflexion (première itération) — voir ADR 023. `Bracket` reste sans `BracketId`/repository propres (scission envisagée puis reportée faute de besoin réel, ADR 023 §5).

#### 5c bis — Outillage transverse

- ✅ Xdebug (couverture de tests + debug PhpStorm), désactivé par défaut — voir ADR 016
- ✅ CI GitHub Actions (`docker compose`, tests uniquement) — voir ADR 017
- ✅ PHPStan niveau max, `src/` propre, `tests/` en baseline, intégré à la CI — voir ADR 018
- ✅ PHP-CS-Fixer `@Symfony` (revue règle par règle), intégré à la CI en vérification seule — voir ADR 019
- Lisibilité des tests (en cours) : Test Data Builders fluides et immuables (`tests/Support/Builder/` — `aCompetition()->withTeam(...)->withBracketGenerated()->build()`, `aPlayer()`) et objets d'assertion fluides (`tests/Support/Assertion/`, ex. `EncounterSheetAssert`), pour réduire le boilerplate de construction (`Competition::create(` répété 96× dans 22 fichiers, `Team::create(` 66×) et rendre l'intention de chaque test lisible. Vocabulaire en anglais ; pas de trait ni de classe de base. Garde-fous : chaque méthode d'assertion porte un nom qui dit exactement ce qu'elle vérifie, un objet d'assertion par vue réellement réutilisée (pas de DSL générique) ; un builder ne masque jamais la précondition critique d'un test (ex. `->withRegistrationClosed()` reste visible) et n'enregistre rien dans un repository.
  - ✅ Fichier témoin : `GetEncounterHandlerTest` réécrit avec ces outils
  - Objectif : migrer **l'intégralité de la suite de tests** (modules `Competition` et `Organization`, toutes les couches), pas seulement le fichier témoin. Une famille à la fois, un commit `refactor(tests)` par famille, en enrichissant les builders à la demande (`withRegistrationClosed()`, `ownedBy()`, `withThirdPlaceMatch()`…). Ordre :
    - `Competition/Application` (13 dossiers de use cases, `GetEncounter` déjà fait)
    - `Competition/Infrastructure/Http` (contrôleurs)
    - `Competition/Infrastructure/Persistence/Doctrine`, `Security`, `Service`
    - `Competition/E2E`
    - `Competition/Domain` (y compris `Format/` et `Service/`), `CompetitionTest` en dernier : ses tests qui vérifient `Competition::create()` lui-même continuent de l'appeler directement, les autres passent par les builders
    - `Organization` (Application, Domain, Infrastructure), avec ses propres builders (`Organizer`, `Organization`, `CheckoutSession`)
  - ADR courte en fin de migration

#### 5c — API REST pour les autres use cases

- ✅ Inscription d'équipe (`RegisterTeam`) : `POST /competitions/{id}/teams` — CQRS (`RegisterTeamCommand`/`RegisterTeamHandler`), capitaine-seul (le roster complet se construit via le futur flux "rejoindre une équipe", Priorité 6). Le capitaine doit être un `Player` déjà persisté (échoue sinon). `TeamRepository` ajouté (port `nextIdentity()` uniquement, `Team` n'a pas de persistance propre — voir ADR 022) ; branché sur `InMemoryTeamRepository` en prod comme en test, pas de `DoctrineTeamRepository` nécessaire.
- ✅ Créer un `Player` (`RegisterPlayer`, ex-`CreatePlayer`) : `POST /players` — CQRS (`RegisterPlayerCommand`/`RegisterPlayerHandler`), premier endpoint permettant à une vraie personne (hors accès direct au repository) de faire exister son identité et donc de devenir capitaine via `RegisterTeam`. Réponse `201` identique qu'un email soit nouveau ou déjà pris, aucun profil existant jamais écrasé — voir ADR 024.
- ✅ Désistement d'équipe (`withdraw`) : `DELETE /competitions/{id}/teams/{teamId}` — CQRS (`WithdrawCommand`/`WithdrawHandler`), délègue entièrement la règle métier (inscription ouverte, équipe existante) à `Competition::withdraw()` déjà couverte côté domaine. Autorisation (capitaine ou organisateur) volontairement absente : aucune couche auth n'existe encore, question reportée en bloc à la Priorité 6 plutôt que de coder une règle non vérifiable aujourd'hui.
- ✅ Clôture de l'inscription (`closeRegistration`) : `POST /competitions/{id}/close-registration` — CQRS (`CloseRegistrationCommand`/`CloseRegistrationHandler`), délègue entièrement la règle métier (minimum de teams atteint) à `Competition::closeRegistration()` déjà couverte côté domaine. Route en verbe explicite plutôt que `PATCH` générique (convention Google AIP-136 / Microsoft REST Guidelines pour les actions sans forme CRUD naturelle). Handlers Messenger désormais taggés par un unique bloc `resource:` (pas un bloc par use case) — voir ADR 025.
- ✅ Génération du bracket (`generateBracket`) : `POST /competitions/{id}/generate-bracket` — CQRS (`GenerateBracketCommand`/`GenerateBracketHandler`), délègue entièrement la règle métier (inscription close, bracket pas déjà généré) à `Competition::generateBracket(BracketGeneratorFactory)` déjà couverte côté domaine. Le format (`CompetitionFormat`) et l'option 3e place sont choisis dès la création de la compétition (`BracketConfiguration`), pas à la génération — `CreateCompetition` rouvert pour exposer ce choix. `BracketGeneratorFactory` résout le générateur adapté sans `match` codé en dur (map format → générateur injectée en config) — voir ADR 026.
- ✅ Multi-tenancy (fondations) : nouveau bounded context `Organization` (`src/Organization/`, dépendance à sens unique vers `Competition`, imposée en CI par `deptrac`) — `Organizer` (compte organisateur, distinct de `Player`), `Organization` (le tenant, créée uniquement après paiement confirmé), paiement simulé par un flux initiation/confirmation asynchrone (`PaymentGateway`/`CheckoutSession`, webhook simulé, idempotent), auth par JWT (`LexikJWTAuthenticationBundle`, firewall scopé à `POST /organizations/checkout`). API HTTP complète : `POST /organizers`, `POST /login`, `POST /organizations/checkout`, `POST /organizations/checkout-webhook`. Voir ADR 027. Rattacher `Competition.organizationId` reste à faire — aucune compétition n'est encore liée à une organisation.
- ✅ Login `Player` : `PlayerId` devient un id généré, découplé de l'email (résout le compromis explicitement signalé par ADR 007 §3), `Player` gagne un mot de passe hashé. `POST /players/login` — CQRS (`LoginCommand`/`LoginHandler`), mirroir d'`Organization/Application/Login`. Ports `PasswordHasher`/`AccessTokenIssuer` dupliqués dans `Competition/Domain/Service` (pas de dépendance vers ceux d'`Organization`, conformément à ADR 027 "Conséquences"). Pas encore de `PlayerUserProvider`/firewall dédié : aucune route ne consomme encore le JWT émis (attend Priorité 6, capitaine authentifié dans `RegisterTeam`/`Withdraw`). SSO (Google/Apple OIDC) évalué et volontairement écarté pour l'instant. Voir ADR 028.
- ✅ Rattachement `Competition.organizationId` : `organizationId` explicite dans la requête de création (pas résolu implicitement depuis l'organizer — un organizer peut posséder plusieurs `Organization`), vérifié via un port d'autorisation étroit (`OrganizerOrganizationAuthorization::authorizes()`, 403 sinon). `POST /competitions` passe sous firewall JWT (mirroir `organization_checkout`). Frontière ACL tenue via une nouvelle Query CQRS côté `Organization/Application` (`IsOrganizerOwnerOfOrganization`, primitifs en entrée/sortie, dispatchée sur le même bus que les Commands) — premier appel réel inter-bounded-context du projet, zéro référence à `Organization/Domain` depuis `Competition`. Voir ADR 029.

## Priorité 6 — Gestion des équipes et des utilisateurs

Persistance de `Player` en agrégat indépendant avancée à la Priorité 5b (voir ADR 022) ; `Team` n'a pas d'existence hors d'une `Competition`, donc pas de CRUD indépendant à construire ici. Login `Player` (`PlayerId` généré, mot de passe, JWT) avancé avant cette priorité — voir ADR 028. Reste :

- Flux "rejoindre une équipe" ✅ **entièrement terminé** : un joueur crée une équipe (devient capitaine) ou demande à rejoindre une équipe déjà inscrite à la même compétition, validation par le capitaine requise — voir ADR 022
  - ✅ Couche Domain : `Team::requestToJoin()`/`approveJoinRequest()`/`rejectJoinRequest()` (état `pendingRequests`, invariants + idempotence — rejette une demande d'un joueur déjà au roster, idempotent si déjà en attente ou déjà approuvé, erreur explicite sur approve/reject sans demande en attente) ; `Competition` délègue (`requestToJoinTeam()`/`approveJoinRequest()`/`rejectJoinRequest()`, même garde "inscription ouverte" que `register()`/`withdraw()`) + expose `getTeamPendingRequests()`/`getTeamRoster()` en lecture. `Team.roster`/`pendingRequests` et `Competition.teams` refactorés en tableaux keyés (`PlayerId`/`TeamId::value`) plutôt qu'indexés, pour un accès direct.
  - ✅ `RequestToJoinTeam` bout en bout (Application + HTTP) : `POST /competitions/{id}/teams/{teamId}/join-requests`, demandeur authentifié via `#[CurrentUser] SecurityPlayer` (JWT), firewall dédié `request_to_join_team`.
  - ✅ `ApproveJoinRequest` bout en bout : autorisation capitaine-seul (acteur unique, pas de double acteur comme `Withdraw`), `POST /competitions/{id}/teams/{teamId}/join-requests/{playerId}/approve`, firewall dédié `approve_join_request`. Nouvelle exception 403 `NotAuthorizedToManageJoinRequestException` — l'ajout d'un 3e listener 403 quasi-identique aux deux existants (`Withdraw`/`CreateCompetition`) a déclenché un refactor : `NotAuthorizedException` (classe abstraite commune) + un unique `NotAuthorizedExceptionListener` remplacent les listeners par-exception — voir ADR 031.
  - ✅ `RejectJoinRequest` bout en bout : même patron d'autorisation qu'`ApproveJoinRequest`, réutilise `NotAuthorizedToManageJoinRequestException` (même règle d'autorisation, pas de nouvelle exception). `POST /competitions/{id}/teams/{teamId}/join-requests/{playerId}/reject`, firewall dédié `reject_join_request`.
- ✅ Règles de cohérence inter-équipes d'une même compétition : unicité du nom d'équipe (comparaison normalisée, casse et espaces ignorés) et un joueur ne peut appartenir qu'à une seule `Team` par `Competition` (roster confirmé seulement, pas les demandes en attente — un joueur peut candidater sur plusieurs équipes simultanément ; revérifié à l'approbation pour fermer la fenêtre de course entre deux candidatures concurrentes). Un retrait (`withdraw`) libère immédiatement nom et joueurs, sans nouvel état à modéliser. A aussi corrigé un gap préexistant : les `\LogicException` du domaine (dont ces nouvelles règles) ne remontaient jamais en HTTP proprement (500 brut) — `LogicExceptionListener` (mirroir `InvalidArgumentExceptionListener`) les mappe désormais en 409. Voir ADR 032.
- ✅ Flux "quitter une équipe" (`RemovePlayerFromTeam`) **entièrement terminé** : distinct de `Withdraw` (qui retire toute l'équipe de la compétition, pas un seul joueur). Capitaine non retirable (`\LogicException` explicite) — succession de capitaine hors périmètre, pas encore conçue. Autorisation à triple acteur (le joueur lui-même pour un départ volontaire, le capitaine, ou l'organisateur propriétaire pour une exclusion), extension du patron en cascade `||` d'ADR 030 (`Withdraw`, deux acteurs) sans tag de type ni `instanceof`. `DELETE /competitions/{id}/teams/{teamId}/players/{playerId}`, firewall dédié `remove_player_from_team` réutilisant le chain provider `players_or_organizers`. Nouvelle exception 403 `NotAuthorizedToRemoveTeamMemberException`, réutilise le listener unifié d'ADR 031. A aussi introduit `InMemoryOrganizerOrganizationAuthorization` (fake) en remplacement du stub PHPUnit précédemment dupliqué dans 3 tests Handler. Voir ADR 033.
- Gestion des utilisateurs : rôles et droits (organisateur / capitaine / joueur, cf. acteurs dans `README.md`)
  - ✅ `PlayerUserProvider`/firewall dédié : `PlayerUserProvider implements UserProviderInterface` (mirroir `OrganizerUserProvider`) résout un `Player` depuis le JWT via `PlayerRepository::ofId()`. Firewall `register_team` (`security.yaml`, mirroir `create_competition`) scopé à `POST /competitions/{id}/teams`.
  - ✅ Capitaine authentifié dans `RegisterTeam` : `RegisterTeamController` résout le capitaine via `#[CurrentUser] SecurityPlayer` au lieu d'un `captainId` dans le payload client — mirroir exact d'`organizerId` sur `CreateCompetitionController`.
  - ✅ Organisateur authentifié dans `CloseRegistration` : seul l'organisateur propriétaire de l'`Organization` rattachée peut clore l'inscription — mirroir exact de `CreateCompetition` (`OrganizerOrganizationAuthorization`, 403 sinon, firewall `close_registration`).
  - ✅ Autorisation à double acteur dans `Withdraw` : capitaine de l'équipe **ou** organisateur propriétaire de la compétition. Chain provider Symfony (`players_or_organizers`, premier du projet) sur un firewall unique ; `WithdrawCommand` ne porte qu'un `actorId` (pas de tag de type d'acteur), `WithdrawHandler` teste la comparaison capitaine (moins coûteuse) puis, si elle échoue, le port `OrganizerOrganizationAuthorization` — voir ADR 030.
- Effectif minimum du roster d'une équipe (cadrage validé, à implémenter **avant** `RecordEncounterResult`) : aujourd'hui aucune règle ne l'impose — `Team::create()` démarre avec le seul capitaine et `closeRegistration()` ne vérifie que `minTeams`, donc une équipe d'un seul joueur peut être tirée au sort et jouer. Décisions actées :
  - Porte sur le **roster** (remplaçants compris), pas sur le nombre de joueurs alignés sur le terrain (notion de feuille de match, inexistante dans le modèle)
  - Vérifiée dans `Competition::closeRegistration()`, mirroir de `minTeams` — ni à `register()` (inscription capitaine-seul, le roster se construit ensuite via le flux "rejoindre une équipe"), ni à `generateBracket()` (rosters gelés depuis la clôture, ADR 033 : une équipe incomplète n'aurait plus aucun moyen de se compléter)
  - Équipe incomplète à la clôture : la clôture est **refusée**, avec un message qui nomme les équipes concernées ; l'organisateur peut en retirer une via `Withdraw` (ADR 030). Exclusion automatique rejetée (silencieuse, fausserait le décompte `minTeams`)
  - Paramètre choisi à la création de la compétition (VO passé à `Competition::create()`, sur le modèle de `TeamCapacity`), pas une constante en dur : l'effectif diffère selon la discipline (foot à 5, à 7, à 11)
  - Maximum de roster : hors périmètre, décision distincte à prendre plus tard
  - Découpage TDD prévu : `closeRegistration()` avec effectif minimum (Domain) → paramètre à la création (Domain) → `CreateCompetitionCommand` + payload HTTP → mapping Doctrine + migration (valeur par défaut pour les lignes existantes) → ADR 036
  - Impact tests : `CompetitionBuilder::withBracketGenerated()` devra fabriquer des équipes complètes, et `EncounterSheetAssert::listsOnlyTheCaptainInEachTeam()` (qui décrit l'état devenu invalide) sera renommée ou revue

## Priorité 7 — Gestion de la compétition en cours

- ✅ Consultation du bracket (`GetBracket`) : `GET /competitions/{id}/bracket` — première Query CQRS du projet exposée à un vrai appelant externe (`IsOrganizerOwnerOfOrganization`, ADR 029, reste interne à l'ACL inter-modules). `GetBracketHandler` retourne un DTO de lecture dédié (`View\BracketView` et sa hiérarchie sous `Application/GetBracket/View/`), jamais le `Bracket` du domaine directement — celui-ci expose `recordResult()` (mutation), le retourner depuis une Query romprait la séparation lecture/écriture du CQRS. Mapping Domain → View concentré dans `BracketViewAssembler`, injecté au Handler (pas de méthode statique, pas de `fromDomain()` dispersé sur les DTOs de vue) : classes de vue `readonly` + `\JsonSerializable`, sans dépendance vers `Domain`. `404` si le bracket n'est pas encore généré (sous-ressource pas encore créée), `422` réservé à la compétition inconnue (ADR 015, inchangé). Voir ADR 034.
- ✅ Fiche d'un match (`GetEncounter`) : `GET /competitions/{id}/encounters/{encounterId}` — toutes les infos d'un encounter à l'instant T (équipes, joueurs, score si disponible). A fait émerger `Bracket::findEncounterById(EncounterId): ?Encounter` côté Domain (recherche à travers tous les rounds, jusqu'ici possible seulement en interne à l'agrégat, dupliquée à deux endroits) — nullable plutôt qu'un Null Object envisagé puis rejeté (pas de comportement "ne rien faire" sûr pour un encounter introuvable). Déduplication des deux recherches internes préexistantes faite en commit `refactor` séparé du `feat`, une fois la méthode publique disponible. `EncounterViewAssembler` (mirroir de `BracketViewAssembler`) ajoute une dépendance `PlayerRepository` pour résoudre le nom des joueurs du roster — confirme le point d'extension anticipé par ADR 034. `404` si le bracket n'est pas généré ou si l'encounter n'existe pas dans le bracket. Voir ADR 035.
- Mise à jour des scores / résultats d'un encounter (`RecordEncounterResult`, exposer `Bracket::recordResult()`, une Command cette fois) — cadrage fait, aucun code écrit, à démarrer après l'effectif minimum du roster (Priorité 6) :
  - Autorisation validée : **organisateur propriétaire seul** (acteur unique via le port `OrganizerOrganizationAuthorization`, ADR 029 ; `actorId` depuis le JWT). Nouvelle exception `NotAuthorizedToRecordResultException` (étend `NotAuthorizedException` → 403 via le listener unifié, ADR 031) ; firewall JWT dédié `record_encounter_result` avec le provider `organizers` (un JWT de joueur → 401)
  - Design recommandé (à confirmer en reprenant) : Command à primitifs (`competitionId`, `encounterId`, `actorId`, `regularTimeHome`/`Away`, puis dans des tranches suivantes `extraTimeHome`/`Away` et `penaltiesHome`/`Away` nullables). Le Handler construit `EncounterResult::regularTime()`/`afterExtraTime()`/`afterPenalties()` : ce mapping lui est propre et se teste à son niveau, mais les invariants de `EncounterResult` (prolongation seulement après un nul, etc.) sont déjà couverts côté Domain. Paires partielles validées dans le Request DTO HTTP
  - Ajouter `Competition::recordResult(EncounterId, EncounterResult)`, qui garde « bracket généré » et délègue, pour éviter que le Handler branche sur `getBracket() === null`. Bracket non généré → `\LogicException` (409) ; encounter introuvable → `InvalidArgumentException` (422, déjà porté par `SingleEliminationBracket::recordResult()`) ; encounter déjà joué ou participants encore en attente → `\LogicException` (409, déjà couvert Domain). Correction d'un résultat déjà saisi : hors périmètre
  - Route suggérée : `POST /competitions/{id}/encounters/{encounterId}/result`. Le match pour la 3e place passe par `BracketWithThirdPlaceMatch::recordResult()`, déjà géré côté Domain
  - Risque persistance à couvrir par un test de repository avant de considérer la feature terminée : le bracket est une colonne JSON (`BracketType`/`BracketNormalizer`) ; `DoctrineCompetitionRepositoryTest::it_persists_a_recorded_encounter_result` mute le bracket **avant** le premier `save()`, mais le cas réel (recharger une `Competition`, muter le bracket en place, `save()`) n'est pas testé — Doctrine pourrait ne pas détecter le changement sur la même instance d'objet
- Consultation des matchs et rounds au-delà du bracket complet (`getRound()`, `countEncounters()`, etc. — à évaluer si un besoin dépasse ce que `GetBracket` couvre déjà)

## Priorité 8 — Front

- Choix du front (Vue 3 ou Twig — à décider) — délibérément en toute dernière étape, une fois l'API stabilisée sur l'ensemble des priorités précédentes