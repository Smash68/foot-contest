# 048. Direction des dépendances entre couches imposée par deptrac

Date: 2026-11-24
Status: Accepted

## Contexte

`deptrac` n'imposait que la direction entre bounded contexts ([ADR 027](027-organization-bounded-context-auth-paiement.md)) : `Competition` pouvait dépendre d'`Organization`, jamais l'inverse. À l'intérieur d'un module, aucune règle ne contrôlait la direction des dépendances entre couches. Rien n'empêchait en CI une classe du `Domain` d'importer Doctrine ou Symfony, ni une classe d'`Application` de dépendre d'un adapter d'`Infrastructure`.

Les conventions de `docs/architecture.md` reposent pourtant sur cette règle de dépendance vers l'intérieur : `Domain` en PHP pur, `Application` sans dépendance au framework ([ADR 025](025-tag-messenger-handlers-par-resource.md)). De même, la règle « `Competition` ne référence jamais `Organization/Domain` » ([ADR 029](029-competition-organization-id.md)) n'était pas couverte par la règle entre modules, qui autorisait `Competition` → tout `Organization`. Ces garanties ne reposaient que sur la revue de code.

## Décision

### 1. Une couche deptrac par module et par couche hexagonale

Chaque couche de chaque module est une couche deptrac (`Competition_Domain`, `Competition_Application`, `Competition_Infrastructure`, et de même pour `Organization`), collectée par répertoire. Les dépendances sont orientées vers l'intérieur uniquement :

- `Domain` ne dépend d'aucune autre couche ;
- `Application` dépend du `Domain` de son module ;
- `Infrastructure` dépend de l'`Application` et du `Domain` de son module.

### 2. Le framework et les bibliothèques réservés à l'Infrastructure

Une couche `Vendor` regroupe toute classe qui n'appartient ni à l'application (`App\`) ni au cœur de PHP. Seules les couches `Infrastructure` peuvent en dépendre. `Domain` et `Application` n'ont droit qu'aux classes natives de PHP (`\InvalidArgumentException`, `\DateTimeImmutable`, `\JsonSerializable`…), que deptrac ne rattache à aucune couche et ne contrôle donc pas.

Aucune bibliothèque n'est autorisée dans le `Domain` aujourd'hui. Si une bibliothèque de pur langage devient un jour nécessaire (manipulation d'UUID, de monnaie…), elle fait l'objet d'une couche dédiée, explicitement autorisée et justifiée, plutôt que d'un assouplissement de la règle `Vendor`.

### 3. Entre modules, seule l'Infrastructure de `Competition` atteint `Organization`

La règle entre bounded contexts est conservée et précisée : `Organization` ne dépend jamais de `Competition`, et seule `Competition_Infrastructure` peut référencer `Organization`, de deux façons :

- **`Organization_Application`** : l'adapter d'autorisation de `Competition` dispatche une Query d'`Organization` à primitifs en entrée et en sortie ([ADR 029](029-competition-organization-id.md)) ;
- **`Organization_Infrastructure`** : les contrôleurs de `Competition` lisent l'identité de l'organisateur authentifié via `SecurityOrganizer`, l'utilisateur Symfony Security fourni par `Organization`.

Le `Domain` d'`Organization` reste inaccessible depuis `Competition`, quelle que soit la couche.

La dépendance vers `Organization_Infrastructure` est une exception assumée. L'authentification de l'organisateur relève d'`Organization` ([ADR 027](027-organization-bounded-context-auth-paiement.md)), et un contrôleur a besoin de l'identité authentifiée. La dépendance reste confinée à l'Infrastructure, entre deux adapters du même framework, sans atteindre ni l'`Application` ni le `Domain` de `Competition`. Un port d'identité propre à `Competition` découplerait les deux Infrastructures, mais n'apporte rien tant qu'`Organization` est le seul fournisseur d'identité.

### Alternatives rejetées

- **Deux axes indépendants** (trois couches transverses `Domain`/`Application`/`Infrastructure` d'un côté, deux modules de l'autre) : plus court à déclarer, mais une dépendance `Competition_Domain` → `Organization_Domain` satisferait les deux axes (`Domain` → `Domain` et `Competition` → `Organization` sont tous deux permis). La règle de l'ADR 029 resterait non contrôlée.
- **Lister les bibliothèques interdites dans le `Domain`** (Doctrine, Symfony…) plutôt que les interdire toutes par défaut : toute nouvelle dépendance Composer serait autorisée silencieusement dans le `Domain` tant que personne ne pense à l'ajouter à la liste.

## Conséquences

- La règle de dépendance vers l'intérieur est vérifiée en CI pour chaque module, sans baseline : le code existant la respectait déjà.
- Un nouveau module se déclare en trois couches dans `deptrac.yaml`, avec ses dépendances vers les autres modules explicitement listées couche par couche.
- Une nouvelle dépendance de `Competition` vers une autre couche d'`Organization`, ou l'introduction d'une bibliothèque dans `Domain`/`Application`, fait échouer la CI et impose une décision explicite dans `deptrac.yaml`.
- Le couplage de `Competition_Infrastructure` à l'utilisateur Symfony d'`Organization` est désormais visible dans la configuration. Il est à réévaluer si un autre type d'acteur authentifié (capitaine, joueur) apparaît : chaque module aura alors son propre firewall et son propre utilisateur ([ADR 027](027-organization-bounded-context-auth-paiement.md)).
