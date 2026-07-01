# 005. EncounterResult est neutre vis-à-vis du format de tournoi

Date: 2026-07-01
Status: Accepted

## Contexte

`EncounterResult::regularTime()` rejetait initialement les nuls avec une `InvalidArgumentException`. Cette contrainte est correcte pour l'élimination directe, mais pas pour un championnat où le match nul est un résultat valide et définitif (pas de prolongations, pas de tirs au but).

La question : faut-il un `EncounterResult` par format, ou une classe permissive ?

## Décision

`EncounterResult` est **neutre** : il ne valide pas si un nul est autorisé ou non. Cette responsabilité appartient au format de tournoi, qui sera porté par la couche application (use case `RecordMatchResult`).

La seule règle qu'`EncounterResult` garde : `winner()` lève une `LogicException` si on tente de déterminer un vainqueur sur un résultat nul sans extra time ni tirs au but.

```php
// Valide dans tous les formats
EncounterResult::regularTime(Score::of(1, 1))

// winner() → LogicException : "Cannot determine a winner from a draw."
// C'est au format de décider si ce résultat est acceptable
```

## Pourquoi pas deux classes ?

Créer `EliminationEncounterResult` et `ChampionshipEncounterResult` violerait le principe DRY et fragmenterait inutilement le modèle. Un résultat de match est un résultat de match — ce qui varie, c'est le contexte dans lequel il est utilisé.

## Score VO

`Score` est introduit comme Value Object léger pour éviter la primitive obsession sur les paires d'entiers :

```php
final readonly class Score
{
    public static function of(int $home, int $away): self { ... }
    public function isDraw(): bool { ... }
}
```

Il valide le non-négatif à la construction et sert de paramètre à tous les named constructors d'`EncounterResult`.

## Conséquences

- Un seul `EncounterResult` couvre tous les formats de tournoi présents et futurs
- La règle "pas de nul en élimination directe" sera implémentée dans le use case applicatif correspondant
- `winner()` reste utilisable partout où un vainqueur est attendu — il communique clairement l'erreur si le résultat est un nul