# 044. Saisie du résultat d'une rencontre : point d'entrée Competition, règles du format portées par le tableau

Date: 2026-11-16
Status: Accepted — traduction des phases saisies et relecture des tirs au but directs remplacées par ADR 046

## Contexte

Le domaine sait déjà enregistrer un résultat et faire progresser le tableau (`Bracket::recordResult()`, [ADR 004](004-bracket-aggregate-root-sans-service-separe.md)), mais aucun use case ne l'expose. Avant de l'ajouter, plusieurs questions couplées devaient être tranchées ensemble :

- **Le point d'entrée de la mutation.** L'ADR 004 désignait `Bracket` comme racine d'agrégat ; depuis, le tableau est absorbé par `Competition`, seule racine persistée ([ADR 022](022-bracket-teamid-et-team-absorbe-registration.md), [ADR 023](023-doctrine-mapping-bracket.md)).
- **La règle « pas de nul en élimination directe ».** L'[ADR 005](005-encounter-result-neutre-vis-a-vis-du-format.md) la confiait au use case applicatif, ce qui contredit la convention selon laquelle un Handler délègue les règles métier à l'agrégat. En l'absence de garde, un nul est aujourd'hui accepté : `Encounter::play()` enregistre le résultat, puis la propagation échoue faute de vainqueur, et la rencontre reste dans un état incohérent (jouée, sans vainqueur).
- **Les rencontres contre une exemption.** Un participant `bye` n'est pas « en attente » : `Encounter::play()` accepte donc un résultat sur une rencontre qui n'a pas lieu, et la propagation échoue dès que l'exemption est désignée vainqueur.

## Décision

### 1. `Competition::recordResult()` est le point d'entrée

La saisie passe par `Competition::recordResult(EncounterId, EncounterResult)`, qui délègue au tableau. Le Handler n'appelle jamais `getBracket()->recordResult()` : ce serait muter une entité interne en contournant la racine d'agrégat. `Competition` porte la seule règle qui la concerne : un résultat ne se saisit pas tant que le tableau n'est pas généré (`\LogicException`).

L'ADR 004 reste valable sur le fond : la progression du tableau vit dans `Bracket`, sans service dédié. Seule la racine exposée à la couche Application change.

### 2. Ce qu'un format accepte est une règle du tableau, pas du use case

En élimination directe, une rencontre doit désigner un vainqueur, et les tirs au but n'ont lieu qu'après une prolongation. Ces règles sont regroupées dans `SingleEliminationResultRules`, appliquées par `SingleEliminationBracket::recordResult()` et par le chemin du match pour la 3e place de `BracketWithThirdPlaceMatch`, **avant** l'appel à `Encounter::play()`, pour que l'agrégat ne soit jamais modifié par un résultat rejeté. Un résultat refusé par le format est une donnée invalide (`\InvalidArgumentException`).

`EncounterResult` reste neutre vis-à-vis du format, conformément à l'ADR 005 : c'est l'implémentation de `Bracket`, qui incarne le format, qui décide des résultats acceptables. Cette décision **remplace** la phrase de l'ADR 005 qui confiait la règle au use case `RecordMatchResult` ; le use case s'appelle `RecordEncounterResult` ([glossaire](../glossary.md) : `Match` est écarté).

Alternative écartée : la garde dans le Handler, comme le prévoyait l'ADR 005. Elle ferait d'un agrégat qui accepte un état incohérent la norme, et chaque futur point d'entrée devrait la répéter.

### 3. Une rencontre ne se joue qu'entre deux équipes

`Encounter::play()` exige que les deux participants soient des équipes, ce qui exclut l'exemption en plus du vainqueur encore inconnu. La règle vaut pour tous les formats : une rencontre contre une exemption n'a jamais lieu. Elle rejoint la garde existante sur les participants en attente (`\LogicException`).

### 4. `EncounterResult` ne garantit que la cohérence interne du résultat

Le partage suit l'ADR 005 : `EncounterResult` porte ce qui est vrai dans tous les formats, le tableau ce que son format accepte.

- **`EncounterResult`** : prolongation et tirs au but seulement après une égalité, pas de nul aux tirs au but, score de prolongation **cumulé** (il inclut le temps réglementaire, ce que le contrat d'API devra expliciter).
- **Le tableau** : le refus du nul et l'exigence d'une prolongation avant les tirs au but (§2).

Aller aux tirs au but directement après le temps réglementaire est une pratique courante, notamment en tournoi amateur : ce n'est pas une contrainte du football mais un choix de format. `EncounterResult::afterPenalties()` accepte donc une prolongation absente, et `EncounterResult::wentStraightToPenalties()` permet au tableau de refuser ce résultat. Le jour où l'organisateur pourra choisir ce déroulement, seule la règle du tableau deviendra configurable.

Alternative écartée : laisser l'exigence de prolongation dans la signature d'`afterPenalties()`. Elle aurait fait d'un choix de format une règle universelle, et forcé tout appelant recevant des tirs au but sans prolongation à décider lui-même de les ignorer ou de les rejeter.

### 5. Chaque score de la commande est un `ScoreInput`

La commande `RecordEncounterResultCommand` porte chaque phase du match (temps réglementaire, puis prolongation et tirs au but, optionnels) sous forme de `ScoreInput`, un DTO de deux entiers propre au use case. Le Value Object `Score` reste construit dans le Handler.

Alternatives écartées :

- **Six champs nullables `home`/`away`** : ils dispersent le concept de score et rendent représentable un score à moitié rempli, que le Handler devrait détecter pour ne pas l'ignorer silencieusement.
- **Le `Score` du domaine dans la commande** : contraire à la convention selon laquelle les Value Objects sont construits dans le Handler ; un score invalide serait en outre rejeté avant le contrôle d'autorisation.

Le Handler ne porte aucune règle de forme : il transmet chaque phase saisie au constructeur nommé correspondant à la dernière phase jouée, sans jamais en ignorer une, et laisse le domaine accepter ou rejeter le résultat.

## Conséquences

- La couche Application ne porte aucune règle métier de la saisie : chargement, autorisation de l'organisateur, traduction des scores reçus en `EncounterResult`, persistance.
- Toute future implémentation de `Bracket` (championnat, poules) décide elle-même des résultats acceptables (nul, tirs au but directs), sans toucher à `EncounterResult` ni au use case.
- Aucun changement de persistance pour les résultats que le tableau accepte aujourd'hui, match pour la 3e place compris ([ADR 023](023-doctrine-mapping-bracket.md)). Un résultat aux tirs au but sans prolongation n'étant accepté par aucun tableau, sa relecture n'est pas encore prise en charge : la feature qui l'autorisera devra l'ajouter.
