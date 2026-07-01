# 002. Participant avec trois états (forTeam / bye / pendingWinnerOf)

Date: 2026-07-01
Status: Accepted

## Contexte

Un bracket d'élimination directe doit représenter trois situations possibles pour une "place" dans un encounter :

1. Une équipe connue (ex : "France")
2. Une place vide automatiquement qualifiée (bye)
3. Le vainqueur d'un encounter précédent, pas encore joué

L'option naïve était d'utiliser un objet `Encounter` comme référence dans le cas 3 — ce qui aurait donné accès "en temps réel" au vainqueur une fois le match joué.

## Décision

`Participant` est un **Value Object** avec trois états nommés, construits par named constructors :

```php
Participant::forTeam(Team $team)
Participant::bye()
Participant::pendingWinnerOf(EncounterId $id)
```

Le 3e état référence un `EncounterId` (valeur immuable), pas un objet `Encounter` mutable.

**Pourquoi pas `Encounter` directement ?**
Un VO ne doit pas tenir de référence vers une Entity mutable — cela violerait son immutabilité et créerait un couplage temporel invisible. En DDD, un VO ne doit référencer une Entity que par son identifiant.

Le terme `Slot` a été rejeté (sans sens métier dans le football). `Participant` reflète ce qu'est réellement l'objet : quelqu'un qui participe à une rencontre, qu'on le connaisse déjà ou non.

## Conséquences

- `Participant` reste `readonly` et sans effet de bord
- La résolution d'un pending se fait explicitement dans `Round::resolveParticipant()`, appelé par `Bracket::recordResult()`
- `Encounter::play()` peut vérifier que les deux participants sont connus avant d'accepter un résultat
- Le nom `Slot` n'existe plus dans le codebase