# 029. Rattachement de `Competition` à une `Organization`

Date: 2026-08-07
Status: Accepted

## Contexte

Dernier point laissé ouvert par ADR 027 §"Conséquences" : `Competition.organizationId` restait à implémenter, avec deux questions couplées à trancher — comment le renseigner à la création, et où vit l'autorisation "cet organisateur peut-il agir sur cette compétition".

Deux designs ont été envisagés puis rejetés en cours de chantier, chacun après une remise en question explicite plutôt qu'une intuition de départ.

## Décision

### 1. `organizationId` explicite dans la requête de création, pas résolu implicitement depuis l'organizer

Premier design envisagé : un port `OrganizerOrganizationResolver::resolve(organizerId): ?OrganizationId`, résolvant "l'" `Organization` d'un organizer via une méthode `OrganizationRepository::ofOwnerId()`. Rejeté : rien dans `InitiateOrganizationCheckoutHandler`/`ConfirmOrganizationCheckoutHandler` n'empêche un organizer de compléter plusieurs checkouts et de posséder plusieurs `Organization` — `ofOwnerId(): ?Organization` (singulier) aurait silencieusement verrouillé un invariant "un organizer = une organization" jamais décidé nulle part.

`CreateCompetitionCommand`/`CreateCompetitionRequest` portent donc `organizationId` explicite (primitif), à côté d'`organizerId` (résolu depuis le JWT, jamais depuis le payload) : le client choisit sous quelle `Organization` créer la compétition, cohérent avec un organizer pouvant en posséder plusieurs.

### 2. Port d'autorisation étroit, pas de résolution

`Competition/Domain/Service/OrganizerOrganizationAuthorization::authorizes(organizerId, organizationId): bool` — vérifie que l'organizer authentifié possède bien l'`Organization` ciblée, plutôt que de résoudre une `Organization` implicite. `CreateCompetitionHandler` échoue via `OrganizerNotAuthorizedForOrganizationException` (nouvelle, mappée en **403**, pas 422 : échec d'autorisation d'un acteur authentifié, pas violation d'une règle métier sur le payload — même distinction que 401 vs 422 pour `InvalidCredentialsException`, ADR 027 décision 6).

### 3. Frontière ACL : Query CQRS côté `Organization/Application`, jamais de référence à `Organization/Domain` depuis `Competition`

Deux alternatives sérieusement envisagées et rejetées pour l'implémentation du port :

- **Conformist** (rejeté) : l'adapter Infrastructure de `Competition` appelait directement `Organization\Domain\Repository\OrganizationRepository::ofId()` et construisait des VOs `Organization\Domain\Model\*`. Passe `deptrac` (le ruleset `Competition → Organization` est déclaré à la granularité du module entier, pas par sous-couche), mais contredit ADR 027 §5 qui prescrit explicitement une traduction "via les primitives des Commands", pas via les classes Domain de l'autre contexte.
- **Simuler une frontière réseau dès maintenant** (rejeté) : faire parler l'adapter à la couche Infrastructure d'`Organization` (sous-requête HTTP in-process ou client HTTP réel), en anticipation d'un futur découpage microservices. Rejeté : dans un vrai découpage, `Competition` n'aurait accès en process à *aucune* couche d'`Organization` (ni Domain, ni Application, ni Infrastructure) — seulement à son API réseau publique. Le port (`OrganizerOrganizationAuthorization`) isole déjà entièrement Domain/Application de `Competition` de la façon dont l'autorisation est vérifiée ; seul l'adapter serait à réécrire le jour d'un vrai split. Simuler ce découpage aujourd'hui (sous-requête Symfony, client HTTP loopback) ajoute un coût réel — CLI/worker sans serveur HTTP joignable, gestion de timeouts/retries — pour un split non planifié, sans gain de robustesse supplémentaire par rapport à ce que le port fournit déjà.

Design retenu : nouvelle query CQRS côté `Organization/Application/IsOrganizerOwnerOfOrganization` (`IsOrganizerOwnerOfOrganizationQuery`/`Handler`, primitifs en entrée/sortie), dispatchée via le même `MessageBusInterface` que toutes les Commands existantes (ADR 008) — premier cas d'usage CQRS de lecture (Query) du projet, jusqu'ici uniquement des Commands. L'adapter `Competition/Infrastructure/Service/OrganizationOwnershipAuthorization` ne référence plus que cette Query (Application, primitifs) ; zéro import de `Organization\Domain\*` dans `Competition`.

## Conséquences

- Premier appel réel inter-bounded-context dans le code (jusqu'ici seule la règle deptrac existait, sans usage concret) : `Competition/Infrastructure/Service/OrganizationOwnershipAuthorization` est la seule classe du module à importer du code d'`Organization` (sa couche Application uniquement).
- `deptrac.yaml` reste inchangé (granularité module entier) : le respect de la frontière ACL au niveau Application-only est une discipline volontaire de ce chantier, pas encore un garde-fou CI. À reconsidérer si d'autres appels inter-contextes apparaissent et que la granularité grossière devient insuffisante.
- `POST /competitions` passe sous un nouveau firewall JWT (`create_competition`, mirroir exact d'`organization_checkout`) : première route `Competition` qui exige une authentification.
- `Competition.organizationId` mappé en Doctrine (`competition_organization_id` Type, colonne `organization_id`, migration dédiée) — non nullable, pas de compétition sans organisation désormais.