# 027. Bounded context `Organization` : auth organisateur, paiement simulé, multi-tenancy

Date: 2026-08-06
Status: Accepted

## Contexte

Dernière brique de la Priorité 5c (voir ROADMAP) : le multi-tenancy. Il n'existe alors aucune notion d'identité/auth dans l'app — ni `Player` (email nu, sans mot de passe) ni "organisateur" (acteur implicite du README, jamais modélisé). Ce chantier pose les fondations : un organisateur peut créer un compte, payer (simulé) pour créer son `Organization` (le tenant), et s'authentifier. Rattacher `Competition` à une `Organization` (`organizationId`) est explicitement **hors périmètre** — suite logique une fois cette fondation posée.

Objectif produit (vision solopreneur) : self-service, sans rôle super-admin — s'inscrire crée son propre compte, payer crée sa propre `Organization`. Aucune donnée de paiement (même fictive) n'est stockée par l'app elle-même — pattern PCI-DSS-safe même en fake.

Plusieurs décisions ont été révisées en cours de chantier, chacune après une remise en question explicite plutôt qu'une intuition de départ : le paiement synchrone (`charge()`) a été entièrement remplacé par un flux initiation/confirmation asynchrone après vérification des pratiques Stripe réelles ; le token opaque persisté a été remplacé par un JWT stateless après discussion sur ce qu'apporte réellement `symfony/security-bundle` par rapport à une mécanique maison. Cette ADR documente l'état final, pas l'historique complet des itérations (conservé en mémoire de session).

## Décision

### 1. Nouveau bounded context `Organization`, dépendance à sens unique

`src/Organization/{Domain,Application,Infrastructure}`, même structure que `src/Competition/`. `Competition` peut dépendre d'`Organization`, jamais l'inverse — règle imposée en CI par `deptrac` (`deptrac.yaml`, layers par directory collector), pas laissée à la seule relecture.

### 2. `Organizer` ≠ `Player`

Nouvelle identité avec identifiants de connexion (email + mot de passe hashé), distincte de `Player` (ADR 007, inchangée). `OrganizerId` est un id généré (`nextIdentity()`), pas l'email — contrairement au compromis `PlayerId` (ADR 007 §3, explicitement signalé "à réévaluer" à l'époque) : ne pas reproduire une dette déjà identifiée alors qu'on peut faire autrement dès le départ.

### 3. `Organization` (le tenant) — agrégat minimal, créé uniquement après paiement confirmé

`OrganizationId`, `name`, `ownerId: OrganizerId`. Pas d'état "pending" sur `Organization` elle-même : la précondition "paiement confirmé" est respectée en ne créant rien du tout tant qu'elle n'est pas remplie, pas en modélisant un statut supplémentaire sur l'agrégat.

### 4. Paiement — flux initiation/confirmation asynchrone, pas de `charge()` synchrone

Un paiement, même unitaire, ne se confirme jamais de façon fiable de façon synchrone (fermeture d'onglet, perte de connexion après paiement mais avant redirection) — Stripe recommande explicitement le webhook comme source de vérité, vérifié via sa documentation officielle avant de trancher.

- `PaymentGateway::initiateCheckout(): CheckoutReference` (Domain/Service) — démarre une session chez le fournisseur, retourne une référence opaque immédiatement, aucune information de succès/échec à ce stade.
- `CheckoutSession` (aggregate Domain, états `Pending`/`Completed`/`Failed` — `CheckoutSessionStatus`, enum backé en string pour permettre le mapping Doctrine `enum-type`) — retient `organizationName`/`ownerId`/`checkoutReference` le temps que le paiement se confirme. `complete(OrganizationId)`/`fail()` protègent leur propre invariant (refusent d'être appelés hors état `Pending`), même patron que `Competition::generateBracket()`.
- La confirmation arrive plus tard (webhook simulé) et est **idempotente** : `ConfirmOrganizationCheckoutHandler` rejoué sur une session déjà terminée renvoie le résultat déjà obtenu plutôt que de retenter l'opération — directement motivé par la doc Stripe ("your fulfillment function might be called multiple times... for the same Checkout Session").
- `FakePaymentGateway` (Infrastructure) simule uniquement l'étape d'initiation (retourne toujours une référence fraîche) — l'échec/succès se décide à la confirmation, pas à l'initiation, cohérent avec le vrai flux Checkout.
- Deux Handlers séparés (`InitiateOrganizationCheckout`/`ConfirmOrganizationCheckout`) plutôt qu'un seul `CreateOrganization` — reflète le découplage initiation/confirmation, pas un artefact d'implémentation.
- Aucune donnée de titulaire/carte stockée côté domaine. Un concept `Payment`/historique séparé de `Organization` a été envisagé (éviter un god object, permettre un futur abonnement) puis abandonné : la plupart des SaaS solo s'appuient sur le dashboard du fournisseur comme source de vérité plutôt que de dupliquer l'historique en local — capturer une donnée d'audit sans jamais la consommer n'a pas de valeur.

### 5. `OrganizationId` — Anti-Corruption Layer, pas de Shared Kernel

Chaque bounded context définira son propre `OrganizationId` local le jour où `Competition` en aura besoin (hors périmètre de ce chantier) ; la traduction se fera via les primitives des Commands (convention déjà établie sur `CompetitionFormat`/`TeamCapacity` : primitif sur le DTO, VO reconstruit dans le Handler), pas de dossier `src/Shared/` ni de 3e couche `deptrac`.

### 6. Auth HTTP — JWT stateless via `LexikJWTAuthenticationBundle`, pas de token opaque

Un token opaque persisté (`AccessTokenAuthenticator` natif + table de tokens hashés) a été le choix initial, pour sa révocabilité immédiate. Révisé : `LexikJWTAuthenticationBundle` est le standard de facto Symfony pour JWT + Security (authenticator natif fourni, paire de clés RSA générée via `bin/console lexik:jwt:generate-keypair`), plus proche de l'écosystème que la mécanique maison. Compromis assumé : **pas de révocation avant expiration en v1** (pas de blocklist — reviendrait à réintroduire le lookup base que le JWT évite justement), expiration courte pour borner le risque.

- `AccessTokenIssuer` (Domain/Service, même patron que `PasswordHasher`/`PaymentGateway`) isole Application/Domain de la librairie. `LexikAccessTokenIssuer` (Infrastructure) est son unique adapter.
- `PasswordHasher::verify()` (ajouté à l'interface existante, `NativePasswordHasher::verify()` via `password_verify()`) — nécessaire pour comparer un mot de passe en clair au hash stocké à la connexion.
- `LoginHandler` vérifie les identifiants puis délègue l'émission à `AccessTokenIssuer` — un échec (email inconnu ou mot de passe invalide) lève `InvalidCredentialsException` (nouvelle, `\RuntimeException`), distincte d'`InvalidArgumentException` : un échec de connexion est un 401 (non authentifié), pas un 422 (payload/règle métier invalide). `InvalidCredentialsExceptionListener` (mirroir `InvalidArgumentExceptionListener`) fait ce mapping.
- **`Organizer` n'implémente jamais d'interface Symfony Security** (`UserInterface`, `PasswordAuthenticatedUserInterface`) — même discipline que le mapping Doctrine en XML externe (zéro référence framework dans le Domain). `SecurityOrganizer implements UserInterface` (Infrastructure/Security) est un adapter minimal (identifiant seul, aucun rôle), construit à la volée pour la signature du JWT (`LexikAccessTokenIssuer`) et pour représenter l'utilisateur authentifié (`#[CurrentUser]`).
- `OrganizerUserProvider implements UserProviderInterface` recharge l'`Organizer` depuis le claim `username` du JWT (l'`OrganizerId`) via un nouveau `OrganizerRepository::ofId()` — ajouté à l'interface car réellement consommé par ce provider, pas par anticipation.
- `security.yaml` : firewall `organization_checkout` scopé strictement au pattern exact `^/organizations/checkout$` (`stateless: true`, `provider: organizers`, `jwt: ~`), seule route qui a besoin de connaître l'organisateur courant. `access_control` impose `IS_AUTHENTICATED_FULLY` sur cette seule route. Toutes les autres routes (`/organizers`, `/login`, `/organizations/checkout-webhook`, et l'intégralité des routes `Competition`) retombent sur le firewall `main` préexistant, inchangé — pas de firewall global JWT qui forcerait à lister des exceptions `PUBLIC_ACCESS`.

### 7. Email dupliqué à l'inscription `Organizer` — erreur explicite, pas anti-énumération

Volontairement différent du pattern anti-énumération de `CreatePlayer` (ADR 024) : celui-ci protégeait un annuaire de participants consultable par des tiers, alors qu'ici c'est un compte de connexion propre à son titulaire — toute UX de signup standard confirme l'email déjà pris, sinon on casse le parcours "j'ai oublié que j'avais déjà un compte".

### 8. Construire les endpoints HTTP a nécessité de câbler `services.yaml` en avance sur ce qui était planifié comme une étape séparée

Un contrôleur ne peut pas fonctionner sans que son Handler soit taggé `messenger.message_handler` et sans que les repositories/ports qu'il consomme soient liés — tagger *tous* les Handlers du module d'un coup (`resource:`, ADR 025) oblige à résoudre les dépendances de *tous* les Handlers existants au moment de compiler le container, pas seulement de celui qu'on construit. Le séquençage "endpoints HTTP" puis "câblage DI" du plan initial s'est donc réalisé dans l'ordre inverse à chaque contrôleur : construire un endpoint = câbler ses dépendances. `services_test.yaml` bascule les trois repositories sur leurs implémentations `InMemory` pour les tests de contrôleur (même convention qu'ADR 013).

### 9. `PaymentGateway`/`PasswordHasher`/`AccessTokenIssuer` — ports Domain/Service liés explicitement, jamais résolus par auto-binding implicite

Toute interface vivant sous `Domain/` est exclue de l'autodiscovery (`exclude: Domain`, même patron qu'ADR 009) — y compris quand une seule implémentation existe. Contrairement à l'auto-résolution "single implementing class" que Symfony offre pour les interfaces *incluses* dans le scan, une interface exclue doit systématiquement être liée explicitement dans `services.yaml`, sans exception liée au nombre d'implémentations.

## Conséquences

- `Organization`, `Organizer`, `CheckoutSession` sont mappés en Doctrine (mapping XML, `config/packages/doctrine_organization.yaml`), testés contre une vraie connexion PostgreSQL — même discipline qu'ADR 010. Trois nouveaux Doctrine Types (`OrganizerIdType`, `OrganizationIdType`, `CheckoutSessionIdType`) et un VO (`CheckoutReferenceType`), tous mirroirs directs de `CompetitionIdType`/`PlayerIdType`.
- `LexikJWTAuthenticationBundle` (et sa dépendance `symfony/security-bundle`) est désormais une dépendance de production — première dépendance d'auth du projet.
- `security.yaml` porte maintenant un firewall applicatif réel (`organization_checkout`), en plus du firewall `main` par défaut posé par la recipe Flex initiale et jusque-là jamais utilisé.
- Le rattachement `Competition.organizationId` reste entièrement à faire — aucune compétition n'est aujourd'hui liée à une organisation. C'est la suite naturelle une fois cette fondation posée (nécessite de trancher où vit l'autorisation "cet organisateur peut-il agir sur cette compétition", esquissé mais pas implémenté : `Competition` gagnerait un `organizationId` local (ACL, décision 5), comparé à l'acteur authentifié via un port d'autorisation étroit).
- Auth Capitaine/Joueur (Priorité 6) n'est pas mutualisée avec ce chantier : chaque bounded context aura son propre firewall Symfony et son propre `UserProviderInterface` le moment venu — un `User` technique partagé recréerait le couplage que `deptrac` empêche déjà. Le pattern (recette) est réutilisable, pas le code : `Competition` dupliquera sa propre version de `PasswordHasher`/`AccessTokenIssuer`/etc. si besoin, pas une dépendance vers celle d'`Organization`.
- Pas de révocation de JWT avant expiration en v1 (décision 6) — à réévaluer si un vrai besoin (déconnexion forcée, compromission) apparaît.