# 041. Objets de support d'authentification pour les tests HTTP

Date: 2026-10-05
Status: Accepted

## Contexte

Les tests de contrôleur sous `tests/Competition/Infrastructure/Http/` authentifient un acteur avant la requête sous test : enregistrement d'un `Player`, ou d'un `Organizer` et de l'`Organization` qu'il possède, puis émission d'un JWT par l'`AccessTokenIssuer` du module concerné. Ce code vivait dans des helpers privés `authenticatedPlayer()`/`authenticatedOrganizer()` recopiés dans neuf fichiers, avec des signatures divergentes d'une copie à l'autre : token seul, ou tableau positionnel `[token, organizationId]` / `[token, playerId]` déstructuré dans le test (`[$token] = …`, `[$captainToken, $captainId] = …`). Ces helpers construisaient leurs agrégats directement (`Player::register()`, `Organizer::register()`, `Organization::create()`), sans passer par les Test Data Builders d'[ADR 036](036-test-data-builders-arrange.md).

En parallèle, la création du client et la substitution du `CompetitionRepository` in-memory ([ADR 013](013-in-memory-tests-http-controleur.md)) étaient répétées en tête de chaque méthode de test.

## Décision

**Un objet de support par type d'acteur authentifié**, sous `tests/Support/Http/` : `AuthenticatedPlayer` et `AuthenticatedOrganizer`.

- `signIn(ContainerInterface $container): self`, statique, assume explicitement ses effets de bord : identifiants générés par `nextIdentity()` des repositories, agrégats construits via `PlayerBuilder`/`OrganizerBuilder`/`OrganizationBuilder` puis enregistrés, JWT émis par l'`AccessTokenIssuer` du module de l'acteur.
- Le résultat est un objet `final readonly` aux propriétés nommées (`token`, `playerId`, `organizationId`) plutôt qu'un tableau positionnel : le test nomme l'acteur qui agit selon son rôle dans le scénario (`$captain`, `$member`, `$applicant`, `$organizer`).
- `authorizationHeader()` retourne l'en-tête `HTTP_AUTHORIZATION` prêt à passer en `server:` à la requête, ou à décomposer (`...`) à côté d'un `CONTENT_TYPE`.
- Une propriété n'est exposée que lorsqu'un test la consomme (même règle que la règle 3 d'ADR 036) : `AuthenticatedOrganizer` n'expose pas encore l'identifiant de l'organisateur.

**Ces objets ne sont pas des builders au sens d'ADR 036.** La règle 2 d'ADR 036 (« un builder n'enregistre jamais rien dans un repository ») reste inchangée : un builder construit, le test enregistre. Un objet de support d'authentification n'a de sens qu'une fois l'acteur enregistré et son token émis, précondition technique d'une requête authentifiée et non état métier mis en place par le test ; son nom (`signIn`) annonce ces effets de bord. Les builders restent la seule façon de construire les agrégats, y compris à l'intérieur de `signIn()`.

**Socle commun client et repository in-memory : `setUp()` par fichier.** Chaque test de contrôleur crée son `KernelBrowser` dans `setUp()`, ainsi que le `CompetitionRepository` in-memory pour les fichiers qui le substituent ; les fichiers qui passent par le repository réel ne créent que le client.

Alternatives écartées :

- **Entrée fluide dès maintenant** (`AuthenticatedOrganizer::anOrganizer()->signIn(...)`) : aucun test n'a encore besoin de paramétrer l'acteur authentifié ; l'étape intermédiaire serait vide. Elle sera introduite au premier test qui en aura besoin, `signIn()` devenant alors la méthode terminale.
- **Traits** (`AuthenticatesPlayer`…) : l'acteur redevient une méthode héritée implicitement par la classe de test, le test ne lit plus directement qui agit, et les helpers privés gardent leur forme positionnelle.
- **Classe de base commune** aux tests de contrôleur : chaque test hérite de tous les acteurs et de tout le socle, fourre-tout qui grossit à chaque nouveau type d'acteur ou de précondition.

## Conséquences

- Plus aucun helper d'authentification privé dans les tests de contrôleur ; un nouveau test authentifié passe par `AuthenticatedPlayer::signIn()` ou `AuthenticatedOrganizer::signIn()`.
- `OrganizerBuilder` et `OrganizationBuilder` gagnent `withId()`, nécessaire pour construire un agrégat à partir d'un identifiant généré par le repository.
- Les acteurs authentifiés prennent les valeurs par défaut des builders (email `{id}@example.com`), uniques par construction : plusieurs acteurs d'un même type peuvent coexister dans un test sans collision d'email.
- `tests/Support/` compte désormais trois catégories : `Builder/` (construction pure, ADR 036), `Assertion/` (objets d'assertion fluides) et `Http/` (préconditions HTTP à effets de bord assumés).
