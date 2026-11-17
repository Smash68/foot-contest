# 046. `EncounterResult::of()` traduit les phases saisies en résultat

Date: 2026-11-17
Status: Accepted

## Contexte

Trois endroits traduisent des scores par phase (temps réglementaire, puis prolongation et tirs au but optionnels) en `EncounterResult`, avec le même aiguillage vers le constructeur nommé de la dernière phase jouée :

- `RecordEncounterResultHandler` ([ADR 044](044-saisie-resultat-rencontre.md)) ;
- `CorrectEncounterResultHandler` ([ADR 045](045-correction-resultat-rencontre.md)) ;
- `EncounterResultNormalizer::denormalize()`, qui de plus ne sait pas relire des tirs au but sans prolongation : l'ADR 044 laissait cette relecture à la feature qui autoriserait ce déroulement.

Cette traduction est une connaissance métier, pas une ressemblance de code : ses copies doivent toujours évoluer ensemble. Un déroulement nouveau (tirs au but directs configurables, nouvelle phase) oublié dans l'une d'elles recréerait un résultat différent de celui saisi, défaut déjà rencontré lors de la saisie.

Un constructeur `EncounterResult::of()` avait été écarté lors de la saisie : il n'avait alors qu'un appelant, et aurait doublé les constructeurs nommés pour un gain mince.

## Décision

**`EncounterResult::of(Score $regularTime, ?Score $extraTime = null, ?Score $penalties = null)`** choisit le constructeur nommé selon la dernière phase jouée : tirs au but, sinon prolongation, sinon temps réglementaire. Il ne porte aucune règle propre ; les constructeurs nommés restent seuls garants de la cohérence du résultat, et le tableau de ce que son format accepte (ADR 044).

Les deux Handlers et le normalizer l'appellent. Le normalizer relit ainsi un résultat aux tirs au but sans prolongation, ce qui **remplace la conséquence de l'ADR 044** qui en laissait la relecture à une feature future.

Ce qui reste dupliqué entre les deux use cases l'est délibérément : le chargement et l'autorisation, orchestration commune à tous les Handlers d'organisateur, et `ScoreInput`, donnée pure sans règle.

Alternatives écartées :

- **Garder l'aiguillage dans chaque appelant** : trois copies d'une même connaissance, à faire évoluer ensemble sans que rien ne le garantisse.
- **Une classe partagée dans la couche Application** (fabrique de résultat et `ScoreInput` communs) : contraire à la convention d'un dossier autonome par use case, et couple deux intentions que l'ADR 045 a volontairement séparées.

## Conséquences

- Les constructeurs nommés restent l'API expressive pour le code et les tests du domaine ; `of()` sert les appelants qui reçoivent des phases optionnelles.
- Un nouveau déroulement de match ne se traduit qu'à un seul endroit.
- La relecture d'un résultat aux tirs au but sans prolongation est prise en charge dès maintenant, bien qu'aucun tableau ne l'accepte encore.
