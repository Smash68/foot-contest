# 007. Inscription au tournoi : nouvel agrégat `Competition`, `Player` distinct de `Team`, IDs typés généralisés

Date: 2026-07-15
Status: Accepted

## Contexte

Priorité 3 de la roadmap : un organisateur crée une compétition, un capitaine y inscrit une équipe, le déclenchement de la génération du bracket devient une action de l'organisateur. Plusieurs décisions de modélisation à trancher avant d'écrire du code :

1. Le nouvel agrégat absorbe-t-il `Bracket`, ou reste-t-il séparé ?
2. Comment représenter le capitaine qui inscrit une équipe ? `Team` n'a que `id` + `name`, aucune notion de capitaine.
3. `Team.id` est un `string` brut, alors que la convention documentée impose des IDs typés (`EncounterId`) — l'incohérence est-elle corrigée à cette occasion ?
4. Quand la clôture de l'inscription et la génération du bracket doivent-elles se déclencher ?
5. La cohérence `minTeams`/`maxTeams` : simple validation dans le constructeur, ou VO dédié ?
6. Comment nommer l'agrégat lui-même ?

## Décision

### 1. `Competition` est un agrégat séparé, référence `Bracket` par composition directe

`Competition` gère l'inscription et le déclenchement ; `Bracket` continue de gérer le déroulement des matchs (`recordResult`, `isComplete`...). Pas de fusion : ce sont deux responsabilités différentes. Tant qu'il n'y a pas de persistance, `Competition` détient l'instance de `Bracket` directement (`getBracket(): ?Bracket`) plutôt qu'un identifiant — la référence par ID n'aurait de sens qu'une fois un repository introduit (priorité 5).

### 2. `Player` est une Entity distincte, `Captain` n'est qu'un rôle

Le README définit déjà l'acteur : *"Capitaine : inscrit et gère une équipe (est aussi joueur)"*. Un capitaine **est** un joueur — pas un concept à part. Donc pas de type `Captain` : `Registration` (VO associant `Team` + `Player`) nomme son paramètre `captain`, mais son type est `Player`. `Player` reste minimal pour ce lot : `PlayerId` + `name`, même forme que `Team` — pas de roster, pas de flux "un joueur rejoint une équipe" (hors périmètre de la priorité 3).

### 3. `PlayerId` = email ; `TeamId` est rétrofité en VO typé

`PlayerId` encapsule un email validé (`FILTER_VALIDATE_EMAIL`) plutôt qu'un identifiant technique arbitraire : aucun référentiel de comptes/joueurs n'existe encore, l'email est la seule identité naturelle disponible côté organisateur/capitaine. **Compromis assumé** : coupler l'identité à un attribut qui peut changer dans la vraie vie (changement d'email) est un anti-pattern classique en DDD ; acceptable ici en l'absence de toute couche compte/auth, à réévaluer si un jour `Player` gagne une identité stable indépendante de l'email.

`Team.id`, jusqu'ici un `string` brut, devient `TeamId` (VO readonly, même forme qu'`EncounterId`) — l'incohérence avec la convention documentée est corrigée à l'occasion de l'introduction de `Registration`, qui référence des équipes par identifiant typé.

### 4. Clôture et génération sont deux actions manuelles et distinctes de l'organisateur

Pas de clôture automatique, y compris quand `maxTeams` est atteint (`register()` refuse simplement les inscriptions au-delà du maximum, sans clore) : l'organisateur garde la main pour absorber un désistement de dernière minute même si le quota est provisoirement atteint. `closeRegistration()` est une action explicite, qui échoue si `countRegistrations() < minTeams`. `generateBracket(BracketGenerator)` est une seconde action explicite, distincte de la clôture, qui échoue si l'inscription est encore ouverte ou si un bracket a déjà été généré.

### 5. `TeamCapacity` porte l'invariant min/max

Plutôt que deux `int` validés inline dans le constructeur, un VO `TeamCapacity::of(int $min, int $max)` porte lui-même ses deux règles (min ≥ 2, min ≤ max) — même traitement que `Score::of()` pour la validation des buts négatifs. `Competition::create()` prend directement un `TeamCapacity`, cohérent avec le fait qu'il prend déjà un `CompetitionId` typé plutôt qu'un `string`.

### 6. Nommage : `Competition`, pas `Tournament` ni `Contest`

L'agrégat a d'abord été nommé `Tournament` (nom du namespace historique du module domaine), avant d'être renommé en cours de session. Raison : la roadmap (priorité 4) prévoit un futur format round-robin/championnat. En vocabulaire foot français, « tournoi » et « championnat » ne sont pas interchangeables — un tournoi évoque un événement borné (souvent élimination directe ou poules + élimination), un championnat une saison en round-robin avec classement. Nommer l'agrégat `Tournament` aurait tendu le vocabulaire ubiquitaire dès qu'un format round-robin serait ajouté.

`Competition` a été retenu comme terme umbrella (celui qu'utilisent les fédérations pour couvrir tournois, championnats, coupes — ex. « UEFA competitions »). `Contest` a été envisagé pour sa cohérence avec le nom du repo (`foot-contest`), mais écarté : plus générique/informel en anglais, employé plus souvent pour une confrontation ponctuelle que pour une structure organisée avec inscription et plusieurs tours.

Le renommage a été fait immédiatement (namespace `App\Tournament\*` → `App\Competition\*`, classes, tests, docs) plutôt que reporté à la priorité 4 : le coût est le plus bas maintenant, avant que persistance/API (priorité 5) ne fassent dépendre des noms de table/endpoints du nom choisi.

**Point encore ouvert, volontairement non traité ici** : `Bracket`/`BracketGenerator`/`generateBracket()`/`getBracket()` restent du vocabulaire spécifique à l'élimination directe (un bracket est un arbre d'élimination ; un championnat round-robin produit un classement/calendrier, pas un bracket). Renommer `Tournament` en `Competition` ne résout pas cette couche-là — le sujet sera à retraiter quand le format round-robin sera conçu (priorité 4), avec le contexte complet de ce qu'une abstraction neutre doit couvrir.

## Pourquoi pas une couche Application dans ce lot ?

Un use case comme `RegisterTeam` n'aurait, sans repository, aucune orchestration à faire au-delà d'un appel direct à `$competition->register(...)` — de l'abstraction sans comportement réel. Reporté à la priorité 5, quand la persistance donnera une vraie raison d'être à ces classes (fetch → mutation → save).

## Conséquences

- `Competition` et `Bracket` restent deux agrégats indépendants et testables séparément
- `Registration`, `Player`, `PlayerId`, `TeamId`, `TeamCapacity`, `CompetitionId` sont de nouveaux concepts du domaine, tous introduits par TDD strict (un comportement à la fois)
- Les VO d'identité purement techniques (`TeamId`, `PlayerId`, `CompetitionId`) ne portent pas de fichier de test dédié — seuls les VO à sens métier (`TeamCapacity`, `Score`) en ont un ; validé indirectement dans les tests de comportement de `Competition` (voir aussi la mémoire projet sur les tests comportementaux)
- Toute inscription en trop après `maxTeams` échoue immédiatement (`LogicException`), sans jamais clore automatiquement l'inscription
- Pas de couche `Application/` pour cette priorité — `Competition` expose directement son API riche (`create`/`register`/`withdraw`/`closeRegistration`/`generateBracket`)
- Le module domaine vit désormais sous `App\Competition\*` (répertoires `src/Competition/`, `tests/Competition/`), plus `App\Tournament\*`
- Le vocabulaire spécifique à l'élimination directe (`Bracket` et dérivés) reste tel quel — à retraiter lors de la conception du format round-robin, pas anticipé ici
