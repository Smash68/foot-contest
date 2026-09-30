# 036. Test Data Builders pour l'arrange des tests

Date: 2026-09-30
Status: Accepted

## Contexte

La suite de tests construisait ses agrégats (`Competition::create()`, `Team::create()`, `Organizer::register()`, `Organization::create()`, `CheckoutSession::initiate()`…) par de longs appels inline répétés dans quasiment chaque test — jusqu'à 96 occurrences de `Competition::create(` dans 22 fichiers avant migration. Cette répétition noie l'intention réelle de chaque test (ce qui est réellement mis en place vs. ce qui est accessoire) sous du boilerplate de construction identique d'un test à l'autre.

## Décision

Test Data Builders fluides et immuables sous `tests/Support/Builder/` : `CompetitionBuilder`/`PlayerBuilder` (module `Competition`), `OrganizerBuilder`/`OrganizationBuilder`/`CheckoutSessionBuilder` (module `Organization`). Vocabulaire anglais (cohérent avec le code de production), pas de trait ni de classe de base commune — chaque builder a une forme trop différente des autres (état d'inscription/adhésion d'équipe vs. quelques champs scalaires) pour qu'une abstraction partagée apporte quoi que ce soit.

Quatre règles suivies uniformément sur les deux familles migrées (`Competition`, `Organization`) :

1. **Le fichier de test d'un agrégat garde son propre constructeur direct.** `CompetitionTest`, `TeamTest`, `OrganizerTest`, `OrganizationTest`, `CheckoutSessionTest` continuent d'appeler `create()`/`register()`/`initiate()` eux-mêmes — un builder sert aux *consommateurs* d'un agrégat, pas à son propre test comportemental. Cette règle s'applique à chaque test individuellement, pas seulement à ceux qui vérifient la construction elle-même : l'action sous test (la méthode dont le comportement est vérifié) reste toujours un appel explicite dans le corps du test, seule la mise en place accessoire passe par le builder.
2. **Un builder n'enregistre jamais rien dans un repository.** L'arrange s'arrête à la construction de l'objet ; le `repository->save()` reste explicite dans le test consommateur (cohérent avec la pratique déjà établie pour les tests HTTP — arranger via repository, jamais via une requête HTTP de setup).
3. **Une méthode de builder par commit, juste avant son premier consommateur.** Pas de méthode ajoutée par anticipation d'un besoin futur non encore rencontré.
4. **Une mutation qui est elle-même le comportement vérifié reste explicite après `build()`**, même dans un test de persistance : `Bracket::recordResult()`, `CheckoutSession::complete()`. Le builder ne remplace que le boilerplate qui n'est pas ce qui est testé.

## Conséquences

- Cinq builders au total, tous suivant la même forme (méthode statique `unXxx()`, méthodes `withXxx()`/`ownedBy()` retournant un clone, `build()` terminal).
- Toute construction d'agrégat dans les couches `Application`/`Infrastructure` des deux modules passe désormais par un builder, à l'exception du fichier de test propre à chaque agrégat (règle 1).
- La phase 2 du chantier de lisibilité (objets d'assertion fluides, `tests/Support/Assertion/`) reste à faire séparément — périmètre déjà cadré mais non démarré.