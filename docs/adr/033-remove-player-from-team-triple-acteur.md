# 033. Retrait d'un joueur du roster (`RemovePlayerFromTeam`) : capitaine bloqué, autorisation à triple acteur

Date: 2026-08-29
Status: Accepted

## Contexte

Le flux "rejoindre une équipe" (ADR 032) a laissé une question ouverte, notée en backlog dans `ROADMAP.md` : `Team`/`Competition` bloquent déjà toute action de `request`/`approve`/`reject` visant le capitaine via l'invariant générique "déjà dans le roster", mais aucun flux ne permet à un joueur non-capitaine de quitter le roster, ni au capitaine ou à l'organisateur de l'en exclure — `Withdraw` (ADR 030) ne retire que l'équipe entière, jamais un seul joueur.

Deux questions couplées devaient être tranchées avant d'implémenter, mappées contre `Withdraw` (ADR 030, autorisation à double acteur capitaine/organisateur) et `ApproveJoinRequest`/`RejectJoinRequest` (ADR 031, autorisation capitaine seul) :

1. Que se passe-t-il si le capitaine lui-même est visé (départ volontaire ou exclusion) ? La succession de capitaine (désigner un remplaçant) n'a jamais été conçue.
2. Qui peut retirer un joueur non-capitaine : le joueur lui-même (départ volontaire), le capitaine (exclusion), l'organisateur (exclusion), une combinaison des trois ?

## Décision

### 1. Le capitaine ne peut pas être retiré — succession hors scope, bloquée explicitement

`Team::removeFromRoster(PlayerId)` lève une `\LogicException` explicite si le joueur ciblé est le capitaine, plutôt que de tenter de modéliser une succession de capitaine dans le même incrément. Alternative rejetée : concevoir la désignation d'un successeur comme partie de ce flux — écarté par la discipline "petits incréments" (une question de conception non triviale, à traiter dans un futur flux dédié si le besoin se confirme) plutôt que d'élargir le périmètre par ricochet.

### 2. Autorisation à triple acteur : le joueur lui-même, le capitaine, ou l'organisateur propriétaire

`RemovePlayerFromTeamCommand` porte `playerId` (la cible) et `actorId` (issu du JWT, `#[CurrentUser]`, jamais du payload client) — même séparation que `ApproveJoinRequestCommand`. `RemovePlayerFromTeamHandler` autorise si `actorId === playerId` (départ volontaire) **ou** `actorId` est le capitaine de l'équipe **ou** `OrganizerOrganizationAuthorization::authorizes()` réussit (exclusion par l'organisateur propriétaire) — extension directe du patron en cascade `||` d'ADR 030 (`Withdraw`, deux acteurs) à un troisième acteur, sans tag de type ni `instanceof` : même raisonnement qu'ADR 030 §3, les trois espaces d'identifiants (le joueur ciblé, le capitaine, l'organisateur) sont comparés successivement sur un unique `actorId` brut.

Alternative envisagée puis rejetée : deux `Command`s séparées (`LeaveTeamCommand` pour le départ volontaire, `RemoveTeamMemberCommand` pour l'exclusion), séparant explicitement les deux intentions métier. Écartée pour la même raison qu'ADR 030 §3 : la duplication de plomberie (charger la compétition, déléguer à `Competition::removePlayerFromTeam()`, sauvegarder) n'est pas justifiée par une différence d'intention métier réelle — "retirer un joueur du roster" reste une seule action, seule l'identité de l'acteur autorisé varie.

Rejet unifié via une nouvelle `NotAuthorizedToRemoveTeamMemberException` (mirroir `NotAuthorizedToWithdrawException`/`NotAuthorizedToManageJoinRequestException`), réutilisant le `NotAuthorizedExceptionListener` unique d'ADR 031 — pas de nouveau listener.

Exposé en HTTP via `DELETE /competitions/{id}/teams/{teamId}/players/{playerId}`, firewall dédié `remove_player_from_team` réutilisant le chain provider `players_or_organizers` déjà introduit par ADR 030 (aucun nouveau provider nécessaire, les trois acteurs sont déjà couverts par `players`/`organizers`).

### 3. Fake plutôt que stub PHPUnit pour tester `OrganizerOrganizationAuthorization`

En écrivant le test de la branche organisateur, le stub existant (`createStub(OrganizerOrganizationAuthorization::class)` + `authorizationStub(bool $authorized)`, dupliqué dans `CreateCompetitionHandlerTest`/`CloseRegistrationHandlerTest`/`WithdrawHandlerTest`) a été jugé trop couplé au comportement interne attendu (une réponse fixe programmée) plutôt qu'à un état observable. `InMemoryOrganizerOrganizationAuthorization` (`Infrastructure/Service/`) le remplace : un vrai objet configurable via `grantOwnership(organizerId, OrganizationId)`, dans le même esprit que `InMemoryCompetitionRepository`. Les trois tests existants ont été alignés sur ce fake dans un commit refactor séparé, sans changement de comportement testé — pour ne pas laisser deux conventions coexister sur le même port.

## Conséquences

- Le blocage du capitaine sur `removeFromRoster()` est un choix de périmètre, pas un invariant définitif : si un besoin de succession de capitaine émerge, cette garde devra être revisitée en conception dédiée, pas patchée localement.
- Le patron "cascade `||` sur un `actorId` unique, sans tag de type" (ADR 030) s'étend maintenant à trois acteurs sans modification de sa forme — validation que ce patron scale au-delà de deux acteurs tant que les espaces d'identifiants restent disjoints.
- `InMemoryOrganizerOrganizationAuthorization` devient le test double de référence pour ce port ; tout nouveau test consommant `OrganizerOrganizationAuthorization` doit l'utiliser plutôt que de réintroduire un stub PHPUnit.