# 003. Les byes sont résolus à la génération du bracket

Date: 2026-07-01
Status: Accepted

## Contexte

Quand le nombre d'équipes n'est pas une puissance de 2, des "byes" sont insérés en round 1 : un encounter entre `bye` et une équipe réelle, dont le vainqueur est immédiatement connu.

Deux approches possibles :

**Vision A — résolution explicite :** les byes produisent `Participant::pendingWinnerOf($id)` comme n'importe quel encounter, et une méthode `Bracket::progressBye()` est appelée pour résoudre ces cas automatiquement après la génération.

**Vision B — résolution à la génération :** `nextRoundParticipants()` détecte les encounters bye et place directement l'équipe avançant en `Participant::forTeam()` dans le round suivant, sans créer de pending artificiel.

## Décision

**Vision B adoptée.** Les byes sont résolus dans `SingleEliminationBracketGenerator::nextRoundParticipants()` via `participantAdvancingFrom()` :

```php
private function participantAdvancingFrom(Encounter $encounter): Participant
{
    if ($encounter->getHome()->isBye()) return $encounter->getAway();
    if ($encounter->getAway()->isBye()) return $encounter->getHome();
    return Participant::pendingWinnerOf($encounter->id);
}
```

## Conséquences

- Dès la génération, les participants du round 2 reflètent la réalité : les équipes avançant sur bye sont déjà `forTeam`, pas `pending`
- `Bracket::progressBye()` n'existe pas et n'existera pas
- `Bracket::recordResult()` n'a pas à gérer de cas spécial pour les byes
- Les encounters "bye vs équipe" en round 1 restent dans le bracket (traçabilité), mais leur impact est déjà propagé