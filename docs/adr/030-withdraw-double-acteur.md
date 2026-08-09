# 030. Autorisation à double acteur pour `Withdraw`

Date: 2026-08-09
Status: Accepted

## Contexte

Priorité 6 (authentifier réellement les actions sur `Competition`) a d'abord traité `RegisterTeam` (capitaine seul, mirroir direct d'`organizerId`/ADR 029) puis `CloseRegistration` (organisateur seul, mirroir direct de `CreateCompetition`). `Withdraw` est différent : la règle métier demandée est que le désistement d'une équipe soit autorisé **soit** par le capitaine de l'équipe (un `Player`), **soit** par l'organisateur propriétaire de la compétition (un `Organizer`) — deux types d'identité JWT distincts pour une seule action.

Aucun des deux mirroirs précédents ne s'applique tel quel : un firewall Symfony n'a qu'un seul `provider`, et les deux vérifications d'autorisation n'ont ni la même nature (comparaison directe de `PlayerId` vs requête cross-contexte via `OrganizerOrganizationAuthorization`, ADR 029) ni le même coût (la première ne fait aucun appel externe, la seconde traverse la frontière ACL vers `Organization`).

Trois questions couplées ont été tranchées dans ce chantier : comment une seule route HTTP accepte deux types de JWT, où vit la donnée "qui est capitaine", et comment le Handler représente "l'acteur" sans dupliquer un système de types que PHP fournit déjà.

## Décision

### 1. Chain provider Symfony plutôt qu'un parsing JWT manuel

`security.yaml` déclare `players_or_organizers: { chain: { providers: [players, organizers] } }`, essayé par le firewall dédié `withdraw` (mirroir des firewalls JWT précédents) scopé à `DELETE /competitions/{id}/teams/{teamId}`. Alternative rejetée : parser le JWT à la main dans le contrôleur en dehors du firewall, pour éviter la contrainte "un provider par firewall" — écarté immédiatement, à l'encontre de la discipline déjà actée de suivre le standard Symfony plutôt qu'une alternative maison (voir aussi ADR 009).

Le chain provider essaie `players` avant `organizers` : la vérification capitaine est la moins coûteuse (donnée déjà en mémoire), l'essai est donc ordonné en conséquence — sans conséquence fonctionnelle, seulement un detail de performance.

### 2. Pas de nouvelle règle métier sur `Competition`

Premier réflexe rejeté : `Competition::isTeamCaptain(TeamId, PlayerId): bool`, une méthode métier sur l'agrégat répondant à "qui a le droit d'agir". Écarté après confrontation aux règles déjà en place : `Competition::withdraw()`/`closeRegistration()` n'ont jamais porté de notion de "qui" — uniquement des règles d'état (inscription ouverte, capacité, existence). `CreateCompetitionHandler`/`CloseRegistrationHandler` placent déjà la décision d'autorisation dans le Handler, pas dans l'agrégat. Faire porter "qui est capitaine" par `Competition` aurait mélangé invariant métier et logique d'autorisation transverse — un anti-pattern DDD classique (les deux préoccupations ont des raisons de changer différentes).

Design retenu : `Competition::getTeamCaptainId(TeamId): PlayerId`, un accesseur de lecture pure (mirroir de `getOrganizationId()`), sans notion d'autorisation. Il lève `\InvalidArgumentException` si l'équipe n'est pas inscrite — mirroir exact du comportement déjà établi par `withdraw()` pour le même cas, plutôt qu'un retour nullable : une équipe absente est une erreur d'intégrité (422, déjà mappée globalement), pas un résultat métier normal à faire remonter en `null` et à re-vérifier chez chaque appelant.

### 3. Pas de tag `actorType`, un seul `actorId` testé contre les deux vérifications

Premier design de `WithdrawCommand` : un champ `actorType` (`'player'|'organizer'`) à côté d'`actorId`, branché via `match()` dans le Handler — un tag string simulant un système de types que PHP fournit déjà. Rejeté au profit d'un design plus direct : `PlayerId` et l'identifiant `Organizer` vivent dans des espaces d'identifiants disjoints (UUID générés indépendamment par deux repositories distincts), donc tenter les deux vérifications sur le même `actorId` brut ne crée aucune ambiguïté réelle.

`WithdrawCommand` ne porte plus que `competitionId`, `teamId`, `actorId` (primitif). `WithdrawHandler` teste `$competition->getTeamCaptainId($teamId)->equals(new PlayerId($command->actorId))` puis, seulement si faux, `$this->authorization->authorizes($command->actorId, $competition->getOrganizationId())` — court-circuit `||`, pas de branchement sur un type d'acteur. Conséquence en cascade côté contrôleur : `SecurityPlayer` et `SecurityOrganizer` exposant tous deux `getUserIdentifier()` via `UserInterface`, `WithdrawController` résout `#[CurrentUser] UserInterface $actor` sans jamais avoir besoin d'un `instanceof` — la distinction de type n'existe nulle part dans le code applicatif, seulement dans le choix du provider au niveau du firewall.

Alternative sérieusement envisagée et rejetée : deux Commands distinctes (`WithdrawAsCaptainCommand`/`WithdrawAsOrganizerCommand`), le contrôleur choisissant laquelle dispatcher via `instanceof` sur l'utilisateur résolu — la classe de la Command aurait alors porté la discrimination de type, sans tag runtime. Écartée au profit du design à `actorId` unique : la duplication de plomberie (charger la compétition, appeler `withdraw()`, sauvegarder) entre deux Handlers n'était pas justifiée par une réelle différence d'intention métier — "désister une équipe" reste une seule action, seule l'identité de l'acteur autorisé varie, et cette variation se résout entièrement par une expression booléenne dans un seul Handler.

Rejet d'autorisation unifié : `NotAuthorizedToWithdrawException` (mirroir `OrganizerNotAuthorizedForOrganizationException`), mappée en 403 par un listener dédié — pas de distinction de message selon la branche ayant échoué, l'appelant n'a pas à savoir laquelle des deux vérifications a été tentée en premier.

## Conséquences

- `security.yaml` gagne un provider `chain` (premier du projet) — pattern réutilisable si une future action nécessite le même besoin "plusieurs types d'acteurs autorisés sur une seule route".
- `Competition::getTeamCaptainId()` est la première méthode de lecture de l'agrégat qui lève une exception plutôt que de retourner un type nullable pour une donnée absente — cohérent avec `withdraw()`, établit la même convention pour de futurs accesseurs équivalents.
- Le design "un seul `actorId`, deux vérifications en cascade" suppose que les espaces d'identifiants `PlayerId`/`Organizer` restent disjoints en pratique (UUID v7 générés indépendamment). Aucune garantie formelle ne l'empêche en théorie ; à reconsidérer si un jour les deux repositories partagent un espace d'id commun.