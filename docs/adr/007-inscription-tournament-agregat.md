# 007. Inscription au tournoi : nouvel agrégat `Tournament`, `Player` distinct de `Team`, IDs typés généralisés

Date: 2026-07-15
Status: Accepted

## Contexte

Priorité 3 de la roadmap : un organisateur crée un tournoi, un capitaine y inscrit une équipe, le déclenchement de la génération du bracket devient une action de l'organisateur. Plusieurs décisions de modélisation à trancher avant d'écrire du code :

1. Le nouvel agrégat `Tournament` absorbe-t-il `Bracket`, ou reste-t-il séparé ?
2. Comment représenter le capitaine qui inscrit une équipe ? `Team` n'a que `id` + `name`, aucune notion de capitaine.
3. `Team.id` est un `string` brut, alors que la convention documentée impose des IDs typés (`EncounterId`) — l'incohérence est-elle corrigée à cette occasion ?
4. Quand la clôture de l'inscription et la génération du bracket doivent-elles se déclencher ?
5. La cohérence `minTeams`/`maxTeams` : simple validation dans `Tournament::create()`, ou VO dédié ?

## Décision

### 1. `Tournament` est un agrégat séparé, référence `Bracket` par composition directe

`Tournament` gère l'inscription et le déclenchement ; `Bracket` continue de gérer le déroulement des matchs (`recordResult`, `isComplete`...). Pas de fusion : ce sont deux responsabilités différentes. Tant qu'il n'y a pas de persistance, `Tournament` détient l'instance de `Bracket` directement (`getBracket(): ?Bracket`) plutôt qu'un identifiant — la référence par ID n'aurait de sens qu'une fois un repository introduit (priorité 5).

### 2. `Player` est une Entity distincte, `Captain` n'est qu'un rôle

Le README définit déjà l'acteur : *"Capitaine : inscrit et gère une équipe (est aussi joueur)"*. Un capitaine **est** un joueur — pas un concept à part. Donc pas de type `Captain` : `Registration` (VO associant `Team` + `Player`) nomme son paramètre `captain`, mais son type est `Player`. `Player` reste minimal pour ce lot : `PlayerId` + `name`, même forme que `Team` — pas de roster, pas de flux "un joueur rejoint une équipe" (hors périmètre de la priorité 3).

### 3. `PlayerId` = email ; `TeamId` est rétrofité en VO typé

`PlayerId` encapsule un email validé (`FILTER_VALIDATE_EMAIL`) plutôt qu'un identifiant technique arbitraire : aucun référentiel de comptes/joueurs n'existe encore, l'email est la seule identité naturelle disponible côté organisateur/capitaine. **Compromis assumé** : coupler l'identité à un attribut qui peut changer dans la vraie vie (changement d'email) est un anti-pattern classique en DDD ; acceptable ici en l'absence de toute couche compte/auth, à réévaluer si un jour `Player` gagne une identité stable indépendante de l'email.

`Team.id`, jusqu'ici un `string` brut, devient `TeamId` (VO readonly, même forme qu'`EncounterId`) — l'incohérence avec la convention documentée est corrigée à l'occasion de l'introduction de `Registration`, qui référence des équipes par identifiant typé.

### 4. Clôture et génération sont deux actions manuelles et distinctes de l'organisateur

Pas de clôture automatique, y compris quand `maxTeams` est atteint (`register()` refuse simplement les inscriptions au-delà du maximum, sans clore) : l'organisateur garde la main pour absorber un désistement de dernière minute même si le quota est provisoirement atteint. `closeRegistration()` est une action explicite, qui échoue si `countRegistrations() < minTeams`. `generateBracket(BracketGenerator)` est une seconde action explicite, distincte de la clôture, qui échoue si l'inscription est encore ouverte ou si un bracket a déjà été généré.

### 5. `TeamCapacity` porte l'invariant min/max

Plutôt que deux `int` validés inline dans `Tournament::create()`, un VO `TeamCapacity::of(int $min, int $max)` porte lui-même ses deux règles (min ≥ 2, min ≤ max) — même traitement que `Score::of()` pour la validation des buts négatifs. `Tournament::create()` prend directement un `TeamCapacity`, cohérent avec le fait qu'il prend déjà un `TournamentId` typé plutôt qu'un `string`.

## Pourquoi pas une couche Application dans ce lot ?

Un use case comme `RegisterTeam` n'aurait, sans repository, aucune orchestration à faire au-delà d'un appel direct à `$tournament->register(...)` — de l'abstraction sans comportement réel. Reporté à la priorité 5, quand la persistance donnera une vraie raison d'être à ces classes (fetch → mutation → save).

## Conséquences

- `Tournament` et `Bracket` restent deux agrégats indépendants et testables séparément
- `Registration`, `Player`, `PlayerId`, `TeamId`, `TeamCapacity`, `TournamentId` sont de nouveaux concepts du domaine, tous introduits par TDD strict (un comportement à la fois)
- Les VO d'identité purement techniques (`TeamId`, `PlayerId`, `TournamentId`) ne portent pas de fichier de test dédié — seuls les VO à sens métier (`TeamCapacity`, `Score`) en ont un ; validé indirectement dans les tests de comportement de `Tournament` (voir aussi la mémoire projet sur les tests comportementaux)
- Toute inscription en trop après `maxTeams` échoue immédiatement (`LogicException`), sans jamais clore automatiquement l'inscription
- Pas de couche `Application/` pour cette priorité — `Tournament` expose directement son API riche (`create`/`register`/`withdraw`/`closeRegistration`/`generateBracket`)