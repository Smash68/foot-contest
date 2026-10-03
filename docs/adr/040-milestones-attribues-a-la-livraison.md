# 040. Milestones attribués à la livraison, pas à l'ouverture de l'issue

Date: 2026-10-03
Status: Accepted

## Contexte

ADR 037 fait de chaque feature à venir un Milestone MINOR ouvert dès la création de l'issue (`v0.2.0`, `v0.3.0`…). Un numéro de version attribué à l'avance encode un ordre de livraison : il mêle le *quoi* (le périmètre de la feature) et le *quand* (sa position dans la séquence des versions).

Cet ordre ne tient pas. La première réorganisation de priorités a déjà imposé une renumérotation : l'issue « Empêcher une équipe incomplète de participer au tournoi » ouverte sous `v0.2.0` est passée en `v0.3.0` lorsqu'une autre feature a été jugée plus prioritaire, et la feature suivante a glissé de `v0.3.0` à `v0.4.0`. Chaque changement de priorité oblige à décaler tous les milestones en aval, et un numéro de version planifié ne dit rien de fiable tant que la feature n'est pas livrée.

## Décision

- **Aucun milestone sur une issue ouverte.** Le numéro de version est un constat de livraison, pas un engagement de planification.
- **Le milestone est créé à la livraison** : une fois la PR d'une feature mergée, le Milestone MINOR suivant (`vX.Y.0`) est créé, l'issue fermée y est rattachée, puis `main` est taggé et une release GitHub publiée sous ce même numéro.
- **La priorité ne passe plus par la version** : le label `status: ready` (ADR 037) signale qu'une issue est prête à être prise, et la suivante se choisit au moment de démarrer — conformément à la règle des petits incréments, qui ne tranche une étape qu'une fois la précédente terminée.
- **Inchangé** : semver standard, une feature livrée = une version MINOR, `v1.0.0` = API stabilisée et front livré ; les issues "Tech debt" restent sans milestone.

Alternative écartée : des milestones thématiques (regroupement par domaine fonctionnel plutôt que par version). Ils ajoutent un second axe de classement à maintenir en parallèle des labels, sans information que les labels et le titre des issues ne portent déjà, à l'échelle actuelle du backlog.

## Conséquences

- Remplace la partie « Milestones » d'ADR 037 ; le reste d'ADR 037 (labels de statut, issues "Feature" limitées à l'intention métier, templates) est inchangé.
- Les milestones `v0.2.0`, `v0.3.0` et `v0.4.0`, ouverts par anticipation, sont supprimés et leurs issues détachées.
- La liste des milestones GitHub devient l'historique des versions livrées, cohérent avec les tags git et les releases.
