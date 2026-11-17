# 045. Correction du résultat d'une rencontre : conséquences portées par le tableau, place retrouvée par l'équipe

Date: 2026-11-17
Status: Accepted

## Contexte

L'organisateur doit pouvoir corriger un résultat saisi par erreur. Une correction qui ne change pas le vainqueur est toujours possible ; une correction qui change le vainqueur n'est possible que tant que les rencontres qui en dépendent ne sont pas jouées (le tour suivant et, pour une demi-finale, le match pour la 3e place), et ces rencontres doivent alors refléter le nouveau vainqueur et le nouveau perdant.

Plusieurs questions couplées devaient être tranchées ensemble :

- **Retrouver la place du vainqueur au tour suivant.** Une fois propagé par `Round::resolveParticipant()`, le vainqueur occupe sa place comme un `Participant::forTeam()` : le lien `pendingWinnerOf` vers la rencontre d'origine ([ADR 002](002-participant-trois-etats.md)) n'existe plus.
- **La répartition des règles** entre `Encounter`, le tableau et son décorateur de 3e place, dans la continuité de l'[ADR 044](044-saisie-resultat-rencontre.md).
- **L'atomicité** : une correction refusée ne doit laisser aucune trace, y compris quand le refus vient du décorateur après que le tableau enveloppé a été sollicité.
- **Le point d'entrée applicatif** : un use case dédié ou une extension de la saisie.

## Décision

### 1. La correction remplace toujours le résultat ; elle ne propage que si le vainqueur change

`Encounter::correct(EncounterResult)` remplace le résultat d'une rencontre déjà jouée, que le vainqueur change ou non. Une rencontre pas encore jouée ne se corrige pas, elle se saisit (`\LogicException`). La règle vaut pour tous les formats.

Les conséquences sur les autres rencontres n'existent que si le vainqueur change : l'ancien vainqueur est remplacé par le nouveau au tour suivant et, pour une demi-finale, l'ancien perdant par le nouveau dans le match pour la 3e place.

### 2. Les conséquences d'une correction sont portées par le tableau

Comme pour la saisie (ADR 044), ce qu'implique une correction dépend du format : le contrat `Bracket` gagne `correctResult(EncounterId, EncounterResult)`, et chaque implémentation en décide.

- **`SingleEliminationBracket`** applique `SingleEliminationResultRules` au résultat corrigé, refuse un changement de vainqueur si la rencontre du tour suivant est jouée (`\LogicException`), puis remplace l'ancien vainqueur par le nouveau.
- **`BracketWithThirdPlaceMatch`** porte tout ce qui concerne le match pour la 3e place ([ADR 006](006-match-3e-place-bracket-interface-et-decoration.md)) : il refuse un changement de vainqueur d'une demi-finale si ce match est joué, et y remplace l'ancien perdant par le nouveau s'il est déjà construit. S'il ne l'est pas encore, il sera construit plus tard à partir des résultats corrigés.
- **`Competition::correctResult()`** est le point d'entrée, refuse la correction tant que le tableau n'est pas généré, et délègue au tableau.

Un futur format (championnat) décidera de ses propres conséquences, typiquement la mise à jour du classement, sans toucher au use case.

### 3. La place du vainqueur se retrouve par l'équipe qui l'occupe

Au tour suivant, `Round` remplace l'équipe de l'ancien vainqueur par celle du nouveau. C'est fiable en élimination directe : une équipe n'apparaît qu'une fois par tour, et la règle de blocage garantit que l'ancien vainqueur n'a pas joué au-delà du tour suivant.

Alternatives écartées :

- **Conserver la provenance dans `Participant`** (une équipe « issue du vainqueur de telle rencontre ») : ajoute un état à un concept volontairement limité à trois (ADR 002, ADR 006), et change le format persisté du tableau, avec une reprise des données existantes.
- **Retrouver la place par sa position** (la rencontre *i* d'un tour alimente la place *i/2* du suivant) : couple la correction, de façon invisible, à l'ordre de construction du générateur et à l'ordre de persistance.

### 4. Toutes les vérifications précèdent la moindre modification

Une correction refusée laisse le tableau intact. Le décorateur vérifie donc le match pour la 3e place **avant** de déléguer au tableau enveloppé : sinon, une demi-finale pourrait être corrigée dans le tableau principal puis rejetée à cause d'un match pour la 3e place déjà joué. Le changement de vainqueur se détermine en comparant le côté vainqueur de l'ancien résultat et celui du nouveau, les participants étant inchangés.

### 5. Un use case dédié, `PUT` sur la ressource du résultat

La correction est un use case distinct, `CorrectEncounterResult`, exposé par `PUT /competitions/{competitionId}/encounters/{encounterId}/result` : `POST` crée le résultat (ADR 044), `PUT` le remplace. Les deux intentions ont des règles opposées (rencontre pas encore jouée contre rencontre déjà jouée).

Alternative écartée : un `POST` qui remplacerait un résultat existant. Un double envoi accidentel écraserait alors un résultat sans que l'organisateur l'ait voulu.

Les dossiers de use case ne partageant rien, `CorrectEncounterResult` a son propre `ScoreInput`.

## Conséquences

- Aucun changement de persistance : le résultat corrigé et les participants remplacés passent par les normalizers existants ([ADR 023](023-doctrine-mapping-bracket.md)).
- Aucune trace des corrections n'est conservée : le résultat corrigé remplace l'ancien.
- La recherche par équipe repose sur l'unicité d'une équipe dans un tour, propre à l'élimination directe ; un format où une équipe joue plusieurs fois dans une même phase portera sa propre logique de correction.
