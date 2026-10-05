# 042. Une feature multi-couches se livre en plusieurs PR, de l'intérieur vers l'extérieur

Date: 2026-10-05
Status: Accepted

## Contexte

Le workflow prévoit « une PR par famille/couche cohérente » et un commit par incrément vert, mergé en merge commit (jamais squash). En pratique, une feature qui traverse les couches — Domain, Application, persistance, HTTP — finit dans une seule PR : tous ses commits sont atomiques, mais la revue porte d'un bloc sur le modèle, une migration, un contrat d'API et un listener. Une PR de cette taille se relit moins attentivement, et un défaut de conception dans le Domain n'est vu qu'une fois tout le reste construit dessus.

Le découpage en commits atomiques est déjà en place ; ce qui manque, c'est la même granularité au niveau des PR.

## Décision

**Une feature qui traverse plusieurs couches se livre en PR successives**, chacune relisible seule et mergée avant que la suivante ne démarre (branche créée depuis `main` à jour, pas de PR empilées).

1. **Ordre de l'intérieur vers l'extérieur** : Domain et Application d'abord, puis l'infrastructure de persistance, puis le point d'entrée HTTP. La dernière PR est celle qui rend la feature utilisable.
2. **Chaque PR laisse `main` verte et sans régression de comportement** : une capacité peut y être dormante, jamais cassée.
   - Pour un **nouveau** use case, l'ordre suffit : tant que le contrôleur n'est pas mergé, rien n'expose la feature.
   - Quand la feature **modifie un point d'entrée existant**, la capacité reste dormante grâce à une valeur neutre, qui reproduit exactement le comportement actuel, jusqu'à la PR qui l'expose. Ce raccourci est signalé dans la description de la PR et retiré par la PR suivante de la série.
3. **Une contrainte technique prime sur le découpage par couche** : un ajout à l'état d'un agrégat persisté part dans la même PR que son mapping Doctrine et sa migration. Sans mapping, un agrégat rechargé depuis la base aurait une propriété non initialisée ; la PR ne laisserait pas `main` saine.
4. **L'ADR de la feature part dans la première PR** : ses décisions sont prises avant l'implémentation, et chaque PR suivante se relit avec elle comme référence.
5. **Suivi de l'issue** : « Part of #N » sur les PR intermédiaires, « Closes #N » sur la dernière ; l'issue reste `status: in-progress` jusque-là. Merger n'est pas livrer : le milestone, le tag et la release ([ADR 040](040-milestones-attribues-a-la-livraison.md)) suivent le merge de la dernière PR seulement.

Le découpage se décide pendant la passe de cadrage de la feature, avec ses autres décisions, et peut s'ajuster au fil des PR comme tout incrément.

Alternatives écartées :

- **Une PR par feature** (pratique précédente) : la taille de la revue est le problème que cette décision résout.
- **Une branche d'intégration par feature**, recevant les petites PR puis mergée dans `main` en fin de feature : utile pour valider une feature complète avant qu'elle n'atteigne `main`, besoin qui n'existe pas ici puisque l'ordre de l'intérieur vers l'extérieur et la valeur neutre gardent `main` sans régression. Elle ajouterait une branche longue à resynchroniser avec `main`.
- **PR empilées** (la PR suivante ciblant la branche de la précédente) : elles permettent d'avancer sans attendre le merge, au prix de rebases en cascade à chaque retour de revue sur une PR amont. Le rythme du projet (chaque itération validée avant la suivante) n'en a pas besoin.
- **Feature flags** : non retenus tant que l'ordre de l'intérieur vers l'extérieur et la valeur neutre suffisent. Un flag ne se justifierait que pour merger une partie visible avant qu'elle ne soit complète ; il ferait alors l'objet de sa propre ADR.

## Conséquences

- Plus de PR par feature, chacune plus courte ; `git log --first-parent main` liste un merge par PR, et les commits d'incrément restent visibles en dessous.
- La livraison (milestone, tag, release) reste à la granularité de la feature, pas de la PR.
- Une valeur neutre temporaire est une étape transitoire assumée de l'historique de `main`, jamais un état durable : la PR suivante de la série la retire.