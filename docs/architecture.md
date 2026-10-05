# Architecture

SaaS multi-tenant de gestion de tournois de football. PHP 8.4 / Symfony, architecture hexagonale (DDD), développement en TDD. Le vocabulaire métier est défini dans le [glossaire](glossary.md) ; le raisonnement de chaque décision vit dans [`adr/`](adr/).

Ce document décrit les **conventions** à suivre, pas l'inventaire du code : il ne change que lorsqu'un pattern change, pas quand une classe ou un use case est ajouté. Pour savoir ce qui existe, lire le code (`src/`), le routeur (`bin/console debug:router`) et `config/packages/security.yaml`.

## Modules

Deux bounded contexts, chacun sous `src/<Module>/` :

- **`Competition`** : compétitions, inscription des équipes, effectifs, tableau et rencontres.
- **`Organization`** : comptes organisateurs, authentification, paiement et création du tenant ([ADR 027](adr/027-organization-bounded-context-auth-paiement.md)).

Dépendance **à sens unique**, imposée en CI par `deptrac` : `Competition` peut dépendre d'`Organization`, jamais l'inverse. Et même dans ce sens :

- `Competition` ne référence jamais `Organization/Domain`. Un appel inter-modules passe par une Query de la couche Application d'`Organization`, à primitifs en entrée et en sortie, dispatchée sur le bus Messenger ([ADR 029](adr/029-competition-organization-id.md)).
- Chaque module a ses propres identifiants (`OrganizationId` existe dans les deux) : anti-corruption layer, pas de Shared Kernel.
- Les ports techniques (`PasswordHasher`, `AccessTokenIssuer`) sont dupliqués par module plutôt que partagés ([ADR 028](adr/028-login-player-playerid-genere.md)).

## Couches

Chaque module est découpé en `Domain/`, `Application/` et `Infrastructure/`.

### Domain

- **PHP pur, sans framework** : ni attribut Doctrine (mapping XML en Infrastructure), ni interface Symfony Security (adapters en Infrastructure). `strict_types=1` partout.
- Exclu de l'autodiscovery des services ([ADR 009](adr/009-infrastructure-bootstrap-flex.md)) ; une classe du Domain n'est enregistrée explicitement dans `services.yaml` que lorsqu'elle doit être injectée (ex. un générateur de tableau dans la map de `BracketGeneratorFactory`, [ADR 026](adr/026-format-competition-et-bracket-generator-factory.md)).
- **Identifiants** : toujours un Value Object typé (`readonly`, `equals()`), jamais une `string` nue. Généré par le port repository (`nextIdentity()`), jamais dérivé d'une donnée métier comme l'email ([ADR 028](adr/028-login-player-playerid-genere.md)). Un identifiant utilisé comme clé primaire Doctrine implémente `Stringable` ([ADR 010](adr/010-doctrine-mapping-competition.md)).
- **Agrégats** : toute mutation passe par la racine d'agrégat, pas par un service séparé ([ADR 004](adr/004-bracket-aggregate-root-sans-service-separe.md)). Les règles optionnelles s'ajoutent par décoration, pas par flag ni sous-classe ([ADR 006](adr/006-match-3e-place-bracket-interface-et-decoration.md)).
- **Exceptions** : `\InvalidArgumentException` pour une donnée invalide ou une cible inexistante (équipe non inscrite, aucune demande en attente), `\LogicException` pour une opération en conflit avec l'état courant (compétition pleine, inscription close, nom déjà pris), sous-classe de `NotAuthorizedException` pour un refus d'autorisation. C'est ce choix qui détermine le code HTTP (voir [Erreurs HTTP](#erreurs-http)).
- **Enums** : une valeur brute venant de l'extérieur se valide par une méthode `fromValue()` qui lève `\InvalidArgumentException`. Le `from()` natif lève une `ValueError`, impossible à surcharger (méthode réservée par PHP), qui remonterait en 500 au lieu d'un 422.
- Une recherche qui peut ne rien trouver retourne un type nullable, pas un Null Object ([ADR 035](adr/035-get-encounter-find-encounter-by-id.md)).

### Application

CQRS via `symfony/messenger` ([ADR 008](adr/008-application-cqrs-messenger.md)). **Un dossier par use case**, autonome (rien n'est partagé entre deux dossiers de use case) :

- **Command / Query** : DTO immuable à **primitifs** ; les Value Objects sont construits dans le Handler.
- **Handler** : nommé `*Handler.php`, déclaré handler Messenger par un tag en configuration ciblant ce suffixe ([ADR 025](adr/025-tag-messenger-handlers-par-resource.md)), jamais par `#[AsMessageHandler]` — l'Application reste du PHP sans dépendance au framework.
- Un Handler de Command peut **retourner l'identifiant généré**, pour qu'un appelant synchrone le connaisse (ADR 008).
- Le Handler **délègue les règles métier à l'agrégat**. Il ne porte que l'orchestration : chargement (agrégat introuvable → `\InvalidArgumentException`), existence des dépendances, autorisation, persistance.
- **Lecture** : une Query retourne un modèle de lecture dédié (classes `readonly` + `\JsonSerializable` sous `<UseCase>/View/`), construit par un Assembler injecté — jamais un objet du Domain ([ADR 034](adr/034-get-bracket-query-read-model.md)). Un état légitimement absent (tableau pas encore généré) se traduit par `null`, pas par une erreur. Un modèle de lecture public n'expose jamais de donnée personnelle (l'email d'un joueur, notamment) : l'identifiant et le nom suffisent.

### Autorisation

- L'identité de l'acteur vient **toujours du JWT** (`#[CurrentUser]`), jamais du payload client.
- L'autorisation se vérifie **dans le Handler, hors de l'agrégat** ; l'agrégat n'expose que des accesseurs de lecture (ex. le capitaine d'une équipe) ([ADR 030](adr/030-withdraw-double-acteur.md)).
- Plusieurs acteurs possibles (joueur, capitaine, organisateur) : un seul `actorId`, testé en cascade `||` contre chaque règle — pas de tag de type d'acteur, pas d'`instanceof` ([ADR 030](adr/030-withdraw-double-acteur.md), [ADR 033](adr/033-remove-player-from-team-triple-acteur.md)).
- La propriété d'une compétition par un organisateur se vérifie via le port `OrganizerOrganizationAuthorization` ([ADR 029](adr/029-competition-organization-id.md)).
- Un refus lève une sous-classe de `NotAuthorizedException` ([ADR 031](adr/031-listener-not-authorized-unifie.md)).

### Infrastructure

- **Contrôleurs** sous `Infrastructure/Http/` du module (`#[Route]`), payload validé par un DTO de requête (`#[MapRequestPayload]`), dispatch sur le bus, résultat lu via `HandledStamp`.
- **Persistance** : Doctrine avec mapping XML et configuration par module (`config/packages/doctrine_<module>.yaml`) ; un Doctrine Type dédié par identifiant mappé, un embeddable pour un Value Object composite ([ADR 010](adr/010-doctrine-mapping-competition.md)) ; l'état interne d'un agrégat (équipes, tableau) est sérialisé via des Types dédiés plutôt que mappé en tables ([ADR 022](adr/022-bracket-teamid-et-team-absorbe-registration.md), [ADR 023](adr/023-doctrine-mapping-bracket.md)).
- **Implémentations InMemory** : fakes de test, préférés aux mocks PHPUnit ([ADR 033](adr/033-remove-player-from-team-triple-acteur.md)), et adapter de production quand aucune persistance n'est nécessaire ([ADR 022](adr/022-bracket-teamid-et-team-absorbe-registration.md)).
- **Sécurité** : JWT stateless (`LexikJWTAuthenticationBundle`), **un firewall par route authentifiée**, jamais un firewall global ; provider `chain` pour une route à plusieurs types d'acteurs ([ADR 030](adr/030-withdraw-double-acteur.md)). Les entités du Domain n'implémentent aucune interface Symfony : des adapters (`Security*`) sont construits depuis l'identifiant.

### Erreurs HTTP

Mappées par des listeners `kernel.exception` globaux, **jamais par un try/catch dans un contrôleur** ([ADR 015](adr/015-mapping-erreurs-metier-http.md)) :

| Exception | Statut | Décision |
|-----------|--------|----------|
| `\InvalidArgumentException` | 422 | [ADR 015](adr/015-mapping-erreurs-metier-http.md) |
| `\LogicException` | 409 | [ADR 032](adr/032-coherence-inter-equipes.md) |
| `NotAuthorizedException` (et sous-classes) | 403 | [ADR 031](adr/031-listener-not-authorized-unifie.md) |
| `InvalidCredentialsException` | 401 | [ADR 027](adr/027-organization-bounded-context-auth-paiement.md) |
| `IncompleteTeamsException` | 409 + `incompleteTeams` | [ADR 043](adr/043-effectif-minimum-par-equipe.md) |

Une exception rejoint toujours l'une de ces familles ; elle n'a son propre listener que si le format de réponse diffère. Ce listener dédié porte alors une priorité supérieure à celui de sa famille, et conserve la clé `error` commune à toutes les réponses d'erreur ([ADR 043](adr/043-effectif-minimum-par-equipe.md) §3).

En lecture, une sous-ressource pas encore créée (tableau non généré) répond **404**, distinct du 422 d'une compétition inconnue ([ADR 034](adr/034-get-bracket-query-read-model.md)).

## Tests

- Chaque couche teste sa propre API publique ; une règle métier se teste une seule fois, dans la couche qui la porte (voir le Workflow d'[`AGENTS.md`](../AGENTS.md#workflow)).
- Arrange via des Test Data Builders (`tests/Support/Builder/`, [ADR 036](adr/036-test-data-builders-arrange.md)) et des objets d'assertion fluides (`tests/Support/Assertion/`).
- Tests HTTP de contrôleur sur repositories InMemory ([ADR 013](adr/013-in-memory-tests-http-controleur.md)), client et repository créés dans `setUp()`, acteur authentifié via les objets de support `tests/Support/Http/` ([ADR 041](adr/041-objets-support-authentification-tests-http.md)) ; persistance testée contre une vraie base, remise à zéro par transaction ([ADR 012](adr/012-reset-base-tests-dama.md)).
- PHPStan au niveau `max` sur `src/` et `tests/`, sans baseline ; idiomes de narrowing dans les tests décrits dans [ADR 038](adr/038-phpstan-baseline-resorption.md).