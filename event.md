Processus de gestion des événements dans l'application (Tasks et Infos)

Objectif global du système :
L'application permet à l'utilisateur de consulter rapidement tous les événements (tasks ou infos) d'une journée donnée. L'objectif est d'optimiser les requêtes pour un affichage rapide, tout en automatisant la gestion des statuts des tâches (selon qu'elles soient réalisées en interne ou en externe) et en optimisant les requêtes liées à l'affichage des tags.

1. Optimisation des requêtes
Un champ active_day_range est utilisé pour limiter les recherches aux événements actifs sur une période définie (par exemple, de -3 à +7 jours autour de la date actuelle). Cette plage réduit le volume de données à traiter et permet de filtrer rapidement les événements. Les événements en dehors de cette plage nécessitent des requêtes plus longues (off range).

Tous les jours à minuit, un cron job met à jour cette plage active afin de garantir que les événements à afficher soient toujours à jour. Si l'utilisateur demande des événements en dehors de cette plage, des requêtes plus complexes sont effectuées.

2. Automatisation des statuts des tâches
Les tâches sont gérées via trois champs essentiels :

task_détails : Ce champ identifie certaines tâches et permet d'automatiser le passage du statut de la tâche à "done" lorsque l'utilisateur y répond depuis l'application. Cependant, pour des tâches simples ou manuelles (comme "faire la plonge"), cette automatisation n'est pas possible, et la mise à jour doit être effectuée manuellement.

task_status_active_range : Un tableau associatif JSON qui associe chaque jour de la plage active à un statut de tâche ("todo" ou "done").

task_status_off_range : Ce champ gère les statuts des tâches en dehors de la plage active, permettant de suivre les tâches à long terme ou les consulter après leur exécution.

Les tâches internes sont mises à jour automatiquement via ces champs, tandis que les tâches externes nécessitent une mise à jour manuelle par l'utilisateur.

3. Optimisation des requêtes pour les tags
Pour améliorer l'efficacité des requêtes liées aux tâches et infos, des arrays spécifiques associent des valeurs numériques à des plages de jours actifs ou des dates hors plage. Ces champs optimisent l'affichage en réduisant le nombre de requêtes nécessaires.

Pour les infos :

tag_info_active_range : Associe chaque jour de la plage active à un nombre d'infos non lues (not_view) par utilisateur.
tag_info_off_range : Gère les infos non lues en dehors de la plage active.
read_users (JSON) : Liste des utilisateurs ayant lu une info. Chaque fois qu'une info est consultée, l'ID de l'utilisateur est ajouté, permettant de savoir qui a vu l'information.
Pour les tâches :

tag_task_active_range : Associe chaque jour actif à un nombre de tâches à faire (todo) par utilisateur.
tag_task_off_range : Gère les tâches en dehors de la plage active.
Chaque interaction (consultation d'une info ou création d'une tâche) met à jour ces champs en temps réel.

4. Gestion des événements de type "Info"
Les événements "Info" sont des informations importantes affichées pendant un mois. Après cette période, elles sont automatiquement supprimées par le cron job.

Visibilité : Les infos restent visibles jusqu'à ce qu'elles deviennent obsolètes. Le nombre d'infos non lues est mis à jour via le champ tag_info_active_range à chaque consultation.
5. Gestion des événements de type "Task"
Les tâches sont également limitées à une durée de vie d'un mois après leur réalisation, après quoi elles sont supprimées automatiquement.

Suivi des statuts :
Lorsqu'une tâche est marquée comme "done", le compteur de tâches à faire (tag_task_active_range) est mis à jour.
Lors de la création d'une nouvelle tâche, le compteur est incrémenté.
6. Mises à jour automatiques via Cron Job
Le cron job joue un rôle clé dans la maintenance du système et s'exécute chaque jour à minuit pour :

Mettre à jour les plages de jours actifs (active_day_range) des événements (tâches et infos).
Mettre à jour les clés des arrays des champs associés aux tâches et infos (tag_task_active_range, tag_info_active_range), en fonction du statut des événements (todo, done, not_view, etc.).
Supprimer les événements obsolètes :
Tâches réalisées il y a plus d'un mois.
Infos non consultées depuis plus d'un mois.
Le cron job ne modifie pas directement les statuts des tâches ou des infos, mais se concentre sur la gestion des plages de jours actifs et l'optimisation des tags.

Conclusion
Le système de gestion des événements est optimisé pour afficher les tâches et infos de manière rapide et efficace, tout en automatisant la mise à jour des statuts des tâches internes. Le cron job maintient à jour les plages de jours actifs et supprime automatiquement les événements obsolètes, garantissant une gestion fluide des événements à venir et passés.




#### Liste de scénarios incluant la gestion des périodes, des fréquences, des types d'événements (tâche ou information), et des utilisateurs qui lisent ou non les informations. 

1. Scénario basique : Événement unique sans fréquence
Description :
Un événement simple de type "information" qui est visible par tous, n'a pas de récurrence, et est limité dans le temps.

side : kitchen
visible : true
status : user
text : "Réunion d'équipe à 15h"
author : John Doe
type : info
periodeStart : 2024-10-01
periodeEnd : 2024-10-01
periodeUnlimited : false
eventSection : planning
eventFrequence : null (pas de récurrence)
active_day_range : [2024-10-01]
read_users : [User1, User2] (les utilisateurs qui ont lu cette information)

2. Scénario récurrent quotidien : Tâche récurrente tous les jours
Description :
Un événement de type "tâche" qui se répète tous les jours, visible uniquement par l'auteur.

side : office
visible : false
status : user
text : "Faire l'inventaire quotidien"
author : Jane Doe
type : task
periodeStart : 2024-10-01
periodeEnd : 2024-10-31
periodeUnlimited : false
eventSection : recette
eventFrequence :
everyday : true
weekDays : [] (vide car il se répète chaque jour)
monthDay : null
active_day_range : [2024-10-01, 2024-10-31]
tag_task_active_range : ["Inventaire: 30 tâches restantes"]
tag_task_off_range : [] (pas de tâches hors période active)
read_users : [] (aucun utilisateur n'a encore vu l'information)

3. Scénario récurrent hebdomadaire : Tâche tous les lundis et mercredis
Description :
Un événement de type "tâche" qui se répète chaque lundi et mercredi. Visible pour tout le personnel.

side : kitchen
visible : true
status : app
text : "Nettoyage du réfrigérateur"
author : App
type : task
periodeStart : 2024-10-01
periodeEnd : 2024-12-31
periodeUnlimited : false
eventSection : menu
eventFrequence :
everyday : false
weekDays : [1, 3] (Lundi, Mercredi)
monthDay : null
active_day_range : [2024-10-01, 2024-12-31]
tag_task_active_range : ["Nettoyage: 26 tâches à venir"]
tag_task_off_range : [] (pas de tâches hors période active)
read_users : [User1] (un utilisateur a lu la tâche)

4. Scénario récurrent mensuel : Tâche le 15 de chaque mois
Description :
Un événement de type "tâche" qui se répète le 15 de chaque mois pour une révision technique.

side : office
visible : true
status : app
text : "Révision technique mensuelle"
author : Admin
type : task
periodeStart : 2024-10-01
periodeEnd : 2025-10-01
periodeUnlimited : false
eventSection : planning
eventFrequence :
everyday : false
weekDays : []
monthDay : 15
active_day_range : [2024-10-01, 2025-10-01]
tag_task_active_range : ["Révision: 12 tâches à venir"]
tag_task_off_range : []
read_users : []

5. Scénario avec période illimitée : Information sans date de fin
Description :
Un événement de type "information" sans date de fin, qui reste visible indéfiniment.

side : office
visible : true
status : user
text : "Guide de sécurité"
author : Safety Team
type : info
periodeStart : 2024-01-01
periodeEnd : null
periodeUnlimited : true
eventSection : planning
eventFrequence : null
active_day_range : [2024-01-01, null]
tag_info_active_range : ["Guide de sécurité non lu"]
tag_info_off_range : []
read_users : [User1, User2, User3]

6. Scénario avec utilisateurs ayant lu l'info : Information marquée comme lue
Description :
Une information où plusieurs utilisateurs ont déjà consulté le contenu.

side : kitchen
visible : true
status : user
text : "Changements dans le menu du mois"
author : Chef
type : info
periodeStart : 2024-10-01
periodeEnd : 2024-12-01
periodeUnlimited : false
eventSection : menu
eventFrequence : null
active_day_range : [2024-10-01, 2024-12-01]
tag_info_active_range : ["Changements dans le menu : non lu par 3 personnes"]
read_users : [User1, User2] (2 utilisateurs ont lu l'information)

7. Scénario d'incohérence potentielle : Période illimitée avec date de fin spécifiée
Description :
Un événement avec une période définie mais marqué comme "période illimitée".

side : kitchen
visible : true
status : user
text : "Formation continue"
author : HR
type : info
periodeStart : 2024-10-01
periodeEnd : 2024-11-01 (date de fin spécifiée)
periodeUnlimited : true (incohérent avec la date de fin)
eventSection : planning
eventFrequence : null
active_day_range : [2024-10-01, 2024-11-01]
read_users : []
Vérification :

Incohérence : Si la période est marquée comme illimitée (periodeUnlimited = true), la date de fin ne devrait pas être spécifiée (periodeEnd devrait être null).
