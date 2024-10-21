# Processus de gestion des événements dans l'application (Tasks et Infos)

## Objectif global du système :
L'application permet à l'utilisateur de consulter rapidement tous les événements (tasks ou infos) d'une journée donnée. L'objectif est d'optimiser les requêtes pour un affichage rapide, tout en automatisant la gestion des statuts des tâches (selon qu'elles soient réalisées en interne ou en externe) et en optimisant les requêtes liées à l'affichage des tags.

Les événements peuvent être signalés comme importants grâce au champ "importance". Ils peuvent être visibles uniquement pour l'utilisateur lui-même ou partagés grâce au champ `shared_with`, qui est un tableau JSON.

Les événements ont un champ `date_limit` qui est défini en fonction de la date à laquelle la tâche est réalisée (status = "done") ou l'info est lue par tous. Cela permettra à l'utilisateur de voir les tâches et infos jusqu'à un mois après leur état final.

## 1. Optimisation des requêtes
Un champ `active_day_range` est utilisé pour limiter les recherches aux événements actifs sur une période définie (par exemple, de -3 à +7 jours autour de la date actuelle). Cette plage réduit le volume de données à traiter et permet de filtrer rapidement les événements. Les événements en dehors de cette plage nécessitent des requêtes plus longues (off range).

Tous les jours à minuit, un cron job met à jour cette plage active afin de garantir que les événements à afficher soient toujours à jour. Si l'utilisateur demande des événements en dehors de cette plage, des requêtes plus complexes sont effectuées.

## 2. Automatisation des statuts des tâches
Les tâches sont gérées via trois champs essentiels :

- **task_details** : Ce champ identifie certaines tâches et permet d'automatiser le passage du statut de la tâche à "done" lorsque l'utilisateur y répond depuis l'application. Cependant, pour des tâches simples ou manuelles (comme "faire la plonge"), cette automatisation n'est pas possible, et la mise à jour doit être effectuée manuellement.

- **task_status_active_range** : Un tableau associatif JSON qui associe chaque jour de la plage active à un statut de tâche ("todo", "pending", "late" ou "done").

- **task_status_off_range** : Ce champ gère les statuts des tâches en dehors de la plage active, permettant de suivre les tâches à long terme ou de les consulter après leur exécution.

Les tâches internes sont mises à jour automatiquement via ces champs, tandis que les tâches externes nécessitent une mise à jour manuelle par l'utilisateur.

## 3. Optimisation des requêtes pour les tags
Pour améliorer l'efficacité des requêtes liées aux tâches et infos, des arrays spécifiques associent des valeurs numériques à des plages de jours actifs ou des dates hors plage. Ces champs optimisent l'affichage en réduisant le nombre de requêtes nécessaires.

### Pour les infos :
- **tag_info_active_range** : Associe chaque jour de la plage active à un nombre d'infos non lues (not_view) par utilisateur.
- **tag_info_off_range** : Gère les infos non lues en dehors de la plage active.
- **read_users (JSON)** : Liste des utilisateurs ayant lu une info. Chaque fois qu'une info est consultée, l'ID de l'utilisateur est ajouté, permettant de savoir qui a vu l'information.

### Pour les tâches :
- **tag_task_active_range** : Associe chaque jour actif à un nombre de tâches à faire (todo) par utilisateur.
- **tag_task_off_range** : Gère les tâches en dehors de la plage active.

Chaque interaction (consultation d'une info ou création d'une tâche) met à jour ces champs en temps réel.

## 4. Gestion des événements de type "Info"
Les événements "Info" sont des informations importantes affichées pendant un mois, dès lors qu'elles ont été lues par tous (champ `shared_with`). Après cette période, elles sont automatiquement supprimées par le cron job. L'utilisateur peut également les supprimer avec une interaction frontale, en vérifiant que tous les utilisateurs les ont lues (le bouton "Supprimer" est désactivé tant que ce n'est pas le cas, en vérifiant la liste des `read_users`).

### Visibilité :
Les infos restent visibles jusqu'à ce qu'elles deviennent obsolètes. Le nombre d'infos non lues est mis à jour via le champ `tag_info_active_range` à chaque consultation.

## 5. Gestion des événements de type "Task"
Les tâches sont également limitées à une durée de vie (date_limit) d'un mois après que leur statut soit passé en `done`, après quoi elles sont supprimées automatiquement. Les tâches ont un statut `pending`, qui permet de signaler une tâche en cours (todo) sur le dashboard, annexé à un bouton qui permet de récupérer la tâche en cours.

### Suivi des statuts :
Lorsqu'une tâche est marquée comme "done", le compteur de tâches à faire (`tag_task_active_range`) est mis à jour. Lors de la création d'une nouvelle tâche, le compteur est incrémenté.

## 6. Mises à jour automatiques via Cron Job
Le cron job joue un rôle clé dans la maintenance du système et s'exécute chaque jour à minuit pour :
- Mettre à jour les plages de jours actifs (`active_day_range`) des événements (tâches et infos).
- Mettre à jour les clés des arrays des champs associés aux tâches et infos (`tag_task_active_range`, `tag_info_active_range`), en fonction du statut des événements (todo, done, not_view, etc.).
- Supprimer les événements obsolètes :
  - Tâches réalisées il y a plus d'un mois.
  - Infos non consultées depuis plus d'un mois.

Le cron job ne modifie pas directement les statuts des tâches ou des infos, mais se concentre sur la gestion des plages de jours actifs et l'optimisation des tags.

## Conclusion
Le système de gestion des événements est optimisé pour afficher les tâches et infos de manière rapide et efficace, tout en automatisant la mise à jour des statuts des tâches internes. Le cron job maintient à jour les plages de jours actifs et supprime automatiquement les événements obsolètes, garantissant une gestion fluide des événements à venir et passés.
