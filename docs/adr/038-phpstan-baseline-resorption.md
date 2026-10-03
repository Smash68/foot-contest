# 038. Résorption du baseline PHPStan sur les tests

Date: 2026-10-03
Status: Accepted

## Contexte

ADR 018 a adopté PHPStan au niveau `max` en corrigeant `src/` mais en plaçant les erreurs de `tests/` dans `phpstan-baseline.neon`, au motif qu'elles venaient de « types génériques que PHPStan ne peut pas affiner sans annotations — pas de vrais bugs », à résorber au fil de l'eau sans chantier dédié.

Les deux hypothèses se sont révélées fausses :

- **Le baseline n'a pas été résorbé au fil de l'eau, il a grossi** : de 43 entrées à l'adoption à 84 avant le chantier de lisibilité des tests (ADR 036), qui l'a ramené à 62 en effet de bord. Le baseline bloque bien toute erreur en excès de son `count`, mais une simple régénération (`--generate-baseline`) suffit à l'absorber : corriger le test et régénérer coûtent le même geste, et rien ne distingue en revue une entrée ajoutée d'une entrée légitime.
- **Les erreurs n'étaient pas irréductibles** : aucune n'exigeait une annotation PHPStan ou une limitation de l'outil. Toutes étaient des vérifications manquantes — résultat d'un `getContainer()->get()` jamais vérifié, `?Competition`/`?Bracket`/`?Encounter` déréférencé sans `assertNotNull()`, `json_decode()` lu comme un tableau sans `assertIsArray()`, data providers sans type de retour. Plusieurs masquaient un vrai trou de test : dans `DoctrineCompetitionRepositoryTest`, le résultat de `ofId()` était utilisé sans être vérifié non nul — si la persistance avait cassé, ces tests auraient échoué par une erreur PHP plutôt que par une assertion explicite.

## Décision

### 1. Plus de baseline : exceptions ciblées et commentées uniquement

Les 61 entrées de `tests/` sont corrigées dans les tests eux-mêmes (issue #18, PR #21 à #23). La dernière, dans `tests/bootstrap.php` (`method_exists()` toujours vrai), n'est pas corrigeable : fichier généré par la recipe Symfony Flex, pas du code du projet — le modifier créerait une divergence avec la recipe à chaque mise à jour. Elle passe en `ignoreErrors` ciblé dans `phpstan.dist.neon` (message exact + chemin + commentaire justificatif), même mécanisme que le faux positif de `Kernel.php` (ADR 018 §3), et `phpstan-baseline.neon` est supprimé.

Rejeté : conserver `phpstan-baseline.neon` réduit à cette seule entrée. Le fichier resterait un réceptacle prêt à l'emploi, où `--generate-baseline` absorbe n'importe quelle nouvelle erreur sans justification écrite. Une exception dans `ignoreErrors` doit au contraire être rédigée à la main et commentée, ce qui la rend délibérée et visible en revue.

Toute nouvelle erreur PHPStan, dans `src/` comme dans `tests/`, se corrige. Seule une erreur réellement irréductible (faux positif de l'outil, code généré hors du projet) justifie une entrée `ignoreErrors` ciblée — jamais un motif générique, jamais un baseline.

### 2. Idiomes de narrowing dans les tests

Deux outils distincts selon la nature de la valeur :

- **`assert($x instanceof Y)`** pour la plomberie du framework, qui n'est pas le comportement vérifié : service récupéré via `self::getContainer()->get()`, `HandledStamp` lu sur une `Envelope` Messenger. Même idiome que `CreateCompetitionController` (ADR 018 §3) et que les tests Doctrine du module `Organization` qui l'appliquaient déjà.
- **Assertions PHPUnit** (`self::assertNotNull()`, `self::assertInstanceOf()`, `self::assertIsArray()`, `self::assertIsString()`) pour les valeurs produites par le système testé : agrégat rechargé d'un repository, bracket d'une compétition, réponse HTTP décodée. Leur échec est un échec de test lisible, pas une erreur PHP.

Pour le JSON des tests HTTP :

- **`json_encode([...], JSON_THROW_ON_ERROR)`** plutôt qu'un cast `(string)` sur le `string|false` retourné. Rejeté : le cast, qui transformerait silencieusement un échec d'encodage en corps de requête vide et ferait échouer le test plus loin pour une raison trompeuse (rejet du payload par le contrôleur). Même choix que les types Doctrine de `src/` (`RegistrationsType`, `BracketType`).
- **`json_decode((string) $response->getContent(), true)` suivi de `assertIsArray()`** : ici le cast est sûr, car `getContent()` ne retourne `false` que pour une réponse streamée ; un corps vide décode en `null` et fait échouer `assertIsArray()` immédiatement. Idiome déjà en place dans `GetBracketControllerTest`/`GetEncounterControllerTest`.

Après un `EntityManager::clear()`, l'agrégat rechargé porte un nom distinct de celui de l'arrange (`$reloadedBracket`, `$reloadedThirdPlaceEncounter`) : réutiliser la variable de l'objet en mémoire permettrait à une assertion portée par erreur sur l'objet non persisté de passer sans rien prouver sur la persistance.

## Conséquences

- `phpstan-baseline.neon` supprimé (62 entrées à l'ouverture du chantier). `composer stan` passe au niveau `max` sur `src/` et `tests/` avec deux exceptions ciblées et commentées, toutes deux hors du code métier du projet.
- ADR 018 §3 (« `tests/` mis en baseline ») et sa conséquence (« dette à résorber au fil de l'eau ») sont révisées par cette décision ; le reste d'ADR 018 (choix de PHPStan, niveau `max`, intégration CI) est inchangé.
- Réintroduire un baseline serait un changement de configuration visible en revue, plus un effet de bord ordinaire d'une régénération.
