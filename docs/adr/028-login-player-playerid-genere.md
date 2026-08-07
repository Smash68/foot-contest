# 028. Login `Player` : `PlayerId` devient un id généré, mot de passe et JWT dupliqués depuis `Organization`

Date: 2026-08-07
Status: Accepted

## Contexte

ADR 007 §3 avait choisi `PlayerId` = email en le signalant explicitement comme un compromis : *"aucun référentiel de comptes/joueurs n'existe encore... à réévaluer si un jour `Player` gagne une identité stable indépendante de l'email"*. ADR 027 avait anticipé ce moment en Conséquences : *"Auth Capitaine/Joueur (Priorité 6) n'est pas mutualisée avec ce chantier... `Competition` dupliquera sa propre version de `PasswordHasher`/`AccessTokenIssuer`/etc. si besoin, pas une dépendance vers celle d'`Organization`"*.

Une question de SSO (OpenID Connect Google/Apple) a été posée en parallèle, pour évaluer la faisabilité — pas retenue pour ce lot (ni Player, ni Organizer) : question de faisabilité tranchée, pas un besoin produit actuel.

Une question de conception a été levée avant d'implémenter : `CreatePlayer` a-t-il toujours été pensé comme une auto-inscription (le joueur crée son propre compte), ou comme un tiers (le capitaine) construisant un roster au nom d'autres joueurs ? La seconde lecture aurait interdit d'y brancher un mot de passe choisi par l'appelant. Confirmé : c'est bien une auto-inscription — l'ajout d'un capitaine à une équipe par un tiers est un use case distinct (`AddPlayerToTeam`, hors périmètre), pas ce que fait `CreatePlayer` aujourd'hui.

## Décision

### 1. `PlayerId` devient un id généré, découplé de l'email

`PlayerId` perd sa validation "email valide" au profit d'une simple validation non-vide (mirroir `OrganizerId`). `PlayerRepository` gagne `nextIdentity()`. `Player` gagne un champ `email` explicite, désormais découplé de l'identité — validé à la construction (`Player::register()`, mirroir `Organizer::register()`).

### 2. `CreatePlayer` devient `RegisterPlayer`, gagne un mot de passe

Renommage cohérent avec `RegisterOrganizer` une fois confirmée la sémantique d'auto-inscription (voir Contexte). `RegisterPlayerCommand` gagne `plainPassword`, hashé via un nouveau port `PasswordHasher`. Le comportement anti-énumération de `CreatePlayer` (ADR 024, un email déjà pris renvoie le même id sans erreur) est préservé à l'identique : un second appel sur un email existant ne modifie ni le nom ni le mot de passe déjà enregistrés — jamais d'upsert silencieux.

### 3. Ports `PasswordHasher`/`AccessTokenIssuer` dupliqués, pas réutilisés depuis `Organization`

Conformément à la décision déjà actée en ADR 027 Conséquences : bien que `deptrac` autorise `Competition → Organization`, mutualiser un port technique recréerait le couplage qu'il empêche par ailleurs. `Competition/Domain/Service/{PasswordHasher,AccessTokenIssuer}` et leurs adapters (`Competition/Infrastructure/{Password,Security}/*`) sont des copies structurelles des ports `Organization`, pas des dépendances vers eux. `Login` (Application) mirrore `Organization/Application/Login` à l'identique (`LoginCommand`/`LoginHandler`/`InvalidCredentialsException`).

### 4. Endpoint HTTP `POST /players/login`, pas `PlayerUserProvider`/firewall dédié pour l'instant

Route distincte de `/login` (déjà pris par `Organizer`). `SecurityPlayer implements UserInterface` existe (nécessaire pour signer le JWT via `LexikAccessTokenIssuer`), mais aucun `PlayerUserProvider`/firewall n'est câblé : aucune route ne consomme encore le JWT émis (rien n'authentifie le capitaine dans `RegisterTeam`/`Withdraw` aujourd'hui — Priorité 6). Câbler un firewall inutilisé aurait anticipé une décision qui n'a pas encore son contexte complet (quelles routes protéger, quel provider).

### 5. `RegisterTeamCommand.captainId` : aucun changement de code nécessaire

`captainId` n'a jamais été validé comme un email dans `RegisterTeamHandler` (simple string passé à `new PlayerId(...)` puis lookup par id) — le passage à un id généré ne casse rien fonctionnellement. Seules les fixtures de tests, qui utilisaient des emails par habitude héritée d'ADR 007, ont été alignées sur un id généré réaliste (`PlayerRepository::nextIdentity()`) pour ne pas laisser croire à tort qu'un `captainId` doit ressembler à un email.

### 6. SSO (Google/Apple OIDC) — non retenu, pas dans ce lot

Évalué uniquement pour sa faisabilité technique. Se brancherait proprement sur l'architecture existante (port `OidcClient`/`SsoAuthenticator`, adapters réels + `FakeOidcClient` de test, même patron que `PaymentGateway`/`FakePaymentGateway`), mais reste un chantier à part entière si un jour retenu : Apple Sign In est nettement plus lourd que Google (client secret = JWT signé maison, domaine vérifié, pas de `localhost` en dev), et la politique de liaison de compte (email déjà existant via un provider SSO) est une décision produit à trancher séparément.

## Conséquences

- Deux migrations Doctrine (`email`, `hashed_password` sur `player`)
- `RegisterPlayerControllerTest`/`RegisterTeamControllerTest`/`RegisterTeamHandlerTest` mis à jour pour refléter le nouveau contrat (id généré, plus d'email-as-id)
- Reste à faire, hors périmètre de ce chantier : `PlayerUserProvider` + firewall `Competition`, authentification effective du capitaine dans `RegisterTeam`/`Withdraw`/`CloseRegistration` (Priorité 6) — le pattern est posé (mirroir `Organization`), pas encore branché sur une route protégée
- SSO explicitement écarté pour l'instant, pas de port `OidcClient` créé