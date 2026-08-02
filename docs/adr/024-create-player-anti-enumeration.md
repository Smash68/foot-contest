# 024. `CreatePlayer` : réponse identique qu'un email soit nouveau ou déjà pris

Date: 2026-08-02
Status: Accepted

## Contexte

Priorité 5c (voir ROADMAP) : `CreatePlayer` est le préalable manquant pour qu'une vraie personne — hors accès direct au repository — puisse faire exister son identité (`PlayerId`/email + `name`) dans le système et ainsi devenir capitaine via `RegisterTeam`. C'est un endpoint public (`POST /players`) qui prend un email en entrée, dans un contexte où aucune couche compte/auth n'existe encore (compromis déjà assumé par l'ADR 007 §3 pour `PlayerId = email`).

Se pose alors la question du comportement quand l'email soumis est déjà associé à un `Player` existant :

**1. Répondre différemment (ex: 201 si nouveau, 409/422 "déjà existant" sinon) ?**

Rejeté : un attaquant peut soumettre des emails un par un et déduire, à partir du code retourné, qui possède déjà un profil sur la plateforme — une énumération d'utilisateurs (OWASP). Le risque existe dès qu'un endpoint public distingue "existe" de "n'existe pas" dans sa réponse, indépendamment de la présence d'une couche auth.

**2. Écraser le `Player` existant par les nouvelles données (upsert silencieux) ?**

Rejeté : dans un flux réel, un attaquant connaissant l'email de quelqu'un d'autre pourrait ainsi remplacer son `name` sans aucune vérification d'identité. Répondre de façon identique ne doit pas se faire au prix d'une écriture non autorisée sur un profil existant — les deux préoccupations (ne pas fuiter l'information, ne pas altérer une donnée existante) sont distinctes et doivent être satisfaites toutes les deux.

## Décision

`CreatePlayerHandler` vérifie l'existence du `Player` (`PlayerRepository::ofId()`) avant d'écrire :

- Si absent : crée et persiste un nouveau `Player`, retourne son `PlayerId`.
- Si déjà présent : ne touche pas au `Player` existant, retourne le même `PlayerId`.

Dans les deux cas, le contrôleur HTTP répond `201` avec le même `id` — aucune différence observable côté appelant entre "création" et "email déjà connu".

## Conséquences

- Pas de code d'erreur dédié pour "email déjà pris" ; ce choix est délibéré, pas un oubli — un futur ajout de ce contrôle casserait la garantie anti-énumération et doit être reconsidéré explicitement, pas glissé incidemment dans un refactor.
- `CreatePlayerHandlerTest::it_does_not_overwrite_an_existing_player_with_the_same_email` verrouille ce comportement au niveau Application.
- Ce choix est cohérent avec le compromis déjà pris par l'ADR 007 §3 (`PlayerId = email`, faute de couche auth) : il ne le résout pas, il évite d'aggraver la surface d'exposition en attendant une vraie gestion de comptes (Priorité 6, voir ROADMAP).