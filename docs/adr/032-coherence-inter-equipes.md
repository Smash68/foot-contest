# 032. Règles de cohérence inter-équipes : unicité du nom, appartenance unique

Date: 2026-08-24
Status: Accepted

## Contexte

Le flux "rejoindre une équipe" (ADR 022, 031) a laissé volontairement hors périmètre deux invariants notés en backlog : l'unicité du nom d'équipe dans une compétition, et le fait qu'un joueur ne peut appartenir qu'à une seule `Team` par `Competition`. Ces deux règles impliquent une vue transverse sur l'ensemble des équipes inscrites — elles ne peuvent pas être portées par `Team` seule (qui ne connaît que son propre roster), et vivent donc sur `Competition`, l'agrégat racine qui possède `$teams`.

Trois nuances devaient être tranchées avant d'implémenter :

1. La comparaison des noms d'équipe doit-elle être stricte ou normalisée (casse, espaces) ?
2. Un `withdraw()` ou un rejet de demande libère-t-il immédiatement le nom/les joueurs pour une nouvelle inscription ?
3. L'invariant "une seule équipe" compte-t-il les demandes en attente (`pendingRequests`), ou seulement le roster confirmé ? Et si un joueur a des demandes en attente sur deux équipes simultanément, faut-il rouvrir la vérification au moment de l'approbation (fenêtre de course) ?

## Décision

### 1. Comparaison de nom normalisée (casse + espaces)

`Competition::register()` compare les noms via `normalizeTeamName()` (`mb_strtolower(trim($name))`) plutôt qu'une égalité stricte — "Les Aigles", "les aigles" et " Les Aigles " sont considérés comme le même nom. Alternative rejetée : comparaison stricte caractère pour caractère, écartée car elle laisserait passer des doublons visuellement identiques, la confusion la plus probable pour de vrais organisateurs saisissant un nom d'équipe à la main.

### 2. Libération immédiate, aucun état supplémentaire à modéliser

`Competition::withdraw()` fait déjà `unset($this->teams[$teamId->value])` (comportement préexistant) : dès qu'une équipe est désistée, son nom et son roster disparaissent de `$this->teams`, donc redeviennent immédiatement disponibles pour une nouvelle inscription/demande — aucun nouvel état "réservé" à modéliser. Un rejet de demande (`rejectJoinRequest()`) ne verrouillait déjà rien côté nom/roster (la demande n'était que `pendingRequests`, jamais comptée par les nouvelles règles ci-dessous), donc rien à changer là non plus.

### 3. Invariant scopé au roster confirmé, revérifié à l'approbation

L'invariant "un joueur ne peut appartenir qu'à une seule équipe" ne porte que sur le roster confirmé (`Team::getRoster()`), jamais sur `pendingRequests`. Un joueur peut donc avoir des demandes en attente sur plusieurs équipes de la même compétition simultanément (candidature multiple) — seule une approbation le fige. Alternative rejetée : compter aussi les demandes en attente, ce qui aurait interdit toute candidature multiple sans bénéfice fonctionnel clair, et sans qu'aucun besoin produit ne l'exige.

Cette portée volontairement plus permissive ouvre une fenêtre de course : si un joueur a une demande en attente sur les équipes A et B, et que A approuve en premier, l'invariant doit être revérifié au moment où B tente d'approuver à son tour — sinon le joueur se retrouverait dans deux rosters à la fois. `Competition::approveJoinRequest()` revalide donc l'invariant à l'approbation, pas seulement à la demande.

Implémentation : une unique méthode privée `belongsToAnotherTeam(PlayerId $playerId, ?TeamId $excludingTeamId = null): bool` parcourt les rosters des équipes inscrites (hors l'équipe cible quand elle est fournie). L'exclusion de l'équipe cible est nécessaire pour préserver l'idempotence déjà actée de `Team::approveJoinRequest()` (ré-approuver un joueur déjà dans le roster de la **même** équipe reste un no-op) — sans cette exclusion, la vérification globale aurait levé une exception y compris pour ce cas légitime. `register()` (pas encore de `TeamId` cible pertinent, la nouvelle équipe n'est pas encore dans `$this->teams`) et `requestToJoinTeam()` réutilisent la même méthode.

## Conséquences

- Trois nouveaux cas d'erreur en `\LogicException` sur `register()`, `requestToJoinTeam()` et `approveJoinRequest()` : nom déjà pris, capitaine/joueur déjà dans une autre équipe. Comme les `\LogicException` déjà existantes sur ces mêmes méthodes (compétition pleine, équipe déjà inscrite), elles ne sont pas encore mappées en HTTP (ADR 015 ne mappe que `\InvalidArgumentException` en 422) — gap préexistant, pas introduit par ce chantier, à traiter le jour où `\LogicException` devient atteignable et doit remonter un code HTTP explicite plutôt qu'un 500.
- `belongsToAnotherTeam()` suppose que le roster de chaque `Team` reste consultable en mémoire (`getRoster()`) — pas de scan SQL, cohérent avec le mapping JSON auto-suffisant de `registrations` (ADR 022).
- Aucun nouvel état sur `Team`/`Competition` : les deux règles se vérifient entièrement en lisant l'état déjà existant (`teams`, `roster`), pas de champ de verrouillage à persister.