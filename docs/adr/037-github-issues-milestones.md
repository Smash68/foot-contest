# 037. Suivi de projet par Issues/Milestones GitHub plutôt que ROADMAP.md

Date: 2026-10-01
Status: Accepted — partie « Milestones » remplacée par ADR 040

## Contexte

`ROADMAP.md` avait progressivement grossi en liste de tâches détaillée (cases à cocher, sous-puces de cadrage, renvois d'ADR) en plus de son rôle de vision produit — jusqu'à devenir le document le plus volumineux du repo après `CLAUDE.md`. Il n'offrait ni statut individuel par item, ni discussion, ni lien automatique vers les PR qui le traitent. Par ailleurs, le besoin exprimé en conversation glissait souvent directement vers une solution technique (noms de classes, de VO, de routes) dès l'écriture du prochain item, sans étape de cadrage métier séparée.

## Décision

Le suivi de l'avancement passe par les **Issues et Milestones GitHub**, pas par un fichier markdown :

- **Milestones = versions semver standard** (`Majeure.Mineure.Patch`), pas un label `Priorité N` propre au projet. `v0.1.0` tague l'état de `main` au moment de cette décision (Priorités 1 à 5c du ROADMAP et une bonne partie des Priorités 6/7, déjà faites — non re-versionnées rétroactivement, git log et ADR en gardent la trace). Chaque feature à venir ouvre son propre Milestone MINOR (`v0.2.0`, `v0.3.0`…) ; `v1.0.0` marquera l'API stabilisée et le front livré.
- **Une issue "Feature" ne porte que l'intention métier** : user story (`En tant que [acteur], je veux [objectif], afin de [valeur]`, vocabulaire des acteurs de `README.md`), critères d'acceptation observables, et un "Hors périmètre" explicite — jamais de nom de classe, de VO, de route HTTP ou de référence d'ADR. La conception technique émerge à l'implémentation (cohérent avec la règle déjà établie "Design avant implémentation") et vit dans la PR/les commits/l'ADR qui en résulte, pas dans le ticket en amont.
- **Exception assumée : les issues "Tech debt"** (chantiers sans histoire utilisateur — nettoyage, refactor, outillage) restent librement techniques, labellisées `tech-debt`.
- Deux templates GitHub (`.github/ISSUE_TEMPLATE/feature.yml`, `tech-debt.yml`) imposent structurellement cette séparation plutôt que de compter sur la discipline seule.
- **Statut suivi par labels** (`status: needs-definition` → `status: ready` → `status: in-progress`, la fermeture de l'issue valant "terminé") plutôt qu'un GitHub Project (vue kanban native envisagée, écartée pour l'instant : nécessite un scope OAuth supplémentaire non accordé ; à reconsidérer si le besoin d'une vue tableau se confirme).
- `ROADMAP.md` est **retiré entièrement**, pas conservé en index allégé : son historique (Priorités déjà ✅) est déjà capturé par git log et `docs/adr/`, le dupliquer n'apporte rien.

## Conséquences

- `CLAUDE.md` : la règle "documentation à jour dès qu'une feature est terminée" ne mentionne plus la mise à jour de `ROADMAP.md`/"Prochaine étape prioritaire" — fermer l'issue (ou ouvrir la suite) en tient lieu. La section décrivant le modèle de domaine et l'Application reste inchangée : elle documente l'état du code, pas une file de tâches.
- Les ADR restent l'unique mémoire du raisonnement architectural — aucun changement de leur rôle.
- Premiers tickets ouverts sous ce format : #16 (effectif minimum du roster, `v0.2.0`), #17 (`RecordEncounterResult`, `v0.3.0`), #18 (résorption du baseline PHPStan, `tech-debt`, sans milestone — une ADR séparée documentera cette résorption une fois terminée, sur le modèle d'ADR 036).