

# Gestion des événements et tags et optimisation des requêtes

Cette application permet à l'utilisateur de consulter rapidement tous les événements (tâches ou informations) d'une journée donnée. L'objectif est d'optimiser les requêtes pour un affichage rapide, d'automatiser la gestion des statuts des tâches (internes à l'application ou externes) et d'améliorer la gestion des tags.

Primordial de comprendre que un event ou un tag(count) correspond a un seul jour; un event récurrent est lui defini sur une période avec une fin ou unlimited.
Les événements peuvent être signalés comme importants grâce au champ **`importance`** ou favorite

Les événements sont supprimés automatiquement après 30 jours suivant leur date de presentation "today" via un **cron job**.
Un événement correspond a un seul jour demandé de réalisation ou d'information
## 1. Optimisation des requêtes

### 1.1 Plage de jours actifs
- **Champ `active_day`** : Limite les recherches aux événements actifs dans une plage de jours définie dans .env "active day range" (par exemple, de -3 à +7 jours autour de datenow()).
  - Cela permet de réduire le volume de données à traiter et d'optimiser les performances.
  - Mise à jour quotidienne via un **cron job**, qui ajuste la plage active des événements.
  - Si l'utilisateur demande des événements en dehors de cette plage, des requêtes plus complexes sont effectuées.

### 1.2 Gestion du `date_status`
- **Champ `date_status`** : Classifie les événements selon leur statut temporel :
  - `past` : événements passés.
  - `active_day_range` : événements autours de today sur un range de  10 jours
  - `future` : événements futurs.
un champ dueDate dans event et un champ day dans tag/taginfo permet d'horodater ceux ci et de filtrer. 

### 1.3 Cache des sessions
- Les événements sont stockés en cache pour la session de l'utilisateur, ce qui permet de réduire les appels à la base de données lorsque l'utilisateur navigue sur plusieurs jours.

### 1.4  6 Index composite

- `index composite` sur date_status et active_day dans la table Event: utile pour sélectionner directement les événements actifs dans la plage active_day_range et afficher rapidement les événements/tags pour une date précise.

- `index composite`sur date_status et due_date(dans le cas ou l event futur a été inscrit en bdd) dans la table Event: permet de gérer efficacement les événements futurs et passé  en fonction de leur date de réalisation souhaitée originellement.(necessaire pour afficher le bon status de la tache/info)

- `index composite` pour les Événements Récurrents
Index sur periode_start, et periode_end dans la table EventRecurring
Utilisation : Filtrer les événements récurrents selon leur date_status (futur/passé) et vérifier si la date souhaitée tombe entre periode_start et periode_end.
Avantages : Optimise les requêtes pour identifier les événements à afficher ou enregistrer en base sans surcharger la base de données avec tous les événements récurrents.

Processus d’Utilisation
Recherche des Tags et Événements :

Utilisez l’`index composite` (date_status, active_day) dans la table tag, pour récupérer les tags et événements qui sont actifs à une date donnée de activeDayRange.
Utilisez l’`index composite` (date_status, day) dans la table tag, pour récupérer les tags et événements qui sont actifs à une date donnée off activeDayRange.

Cela vous donne une liste de tags pertinents.
Affiner la recherche avec les TagInfos :

Avec les tags trouvés, utilisez l'`index composite` sur (user_id, tag_id) dans la table TagInfo, pour récupérer les informations liées à l'utilisateur connecté pour chaque tag.
Récupération des informations non lues :

Une fois que vous avez les TagInfo pour l'utilisateur et les tags, vous pouvez facilement afficher le nombre d'informations non lues par section.



## 2. Gestion des événements tâches et information
- Les événements sont visibles uniquement sur leur due_date.
- les events et events récurrents ne sont inscris en bdd que lorsqu'il font partie du range active_day_range. un cronjob mets a jour quotidinnement la bdd en inscrivant chaque event(en observant la due date) /eventRécurring(en observant leur periode) qui entre dans la fentere de temps active _day_range.
- Dans les cas où une tâche est en statut late ou pending, l'événement tâche est dupliqué et enregistré en base de données au lendemain. Cette duplication continue chaque jour jusqu'à ce que le statut de la tâche passe à done ou unrealised. Cette mise à jour est effectuée par un cron job chaque jour à minuit;
cette duplication a lieu si et seulement si apres avoir vérifié que l'event tache ne fait pas partie d'un groupe eventRécurring/et que la période de l'eventRécurring est active le lendemain.
Cela permet d'éviter d'avoir deux tâches identiques.
- L'événement tache de la veille qui a généré cette duplication sera quant à lui marqué comme unrealised.
- les events de type info eux n'ont pas de status, par contre une logique métier est appliqué au niveau des tags pour signaler les nouveaux events info, cad non lu par l'user conneté.
- les statuts des events dans le passé sont immuables.


### 2.1 Gestion des tâches
- **Statuts des tâches** :
  - `todo`, `done`, `late`, `pending`, `unrealised`, `warning`.
- Chaque event tâche créée par un utilisateur est initialisé avec un statut `todo` par défaut.
- le status `late` est utilisé pour marquer un event tache comme non réalise mais representé aujourdhui.
- le statut `unrealized` est utilisé pour marquer les events de la veille des lors qu'un event tache est marqué comme late a la date de aujourdhui.ce statut ne changera pas dans le passé.
- le statut `warning` est utilisé pour indiquer a l'user qu'un event doit etre reconsideré car il a été classé obsolete de par un changement de son event_recurrents qui l'a créee.
- L'utilisateur peut également supprimer manuellement un event tache  , dans ce cas un event info, avec un tag important, est crée pour en aviser les autres users et garder une trace de cette décision.

### 2.2 Gestion des événements d'information
- **Champ `unreadUsers`** : Tableau contenant les utilisateurs n'ayant pas encore lu l'information.
- Dans le cas ou un user n'a pas lu une info, l' événement info est créé  par duplication et donc inscrit en bdd avec une période active de un jour (aujourd'hui) et ce tant que le user est présent dans le champ **`shared_with`**. Cette actualisation se fait par un cron job a minuit.
- L'utilisateur peut également supprimer manuellement un événement de type "Info", sous réserve que tous les utilisateurs l'aient lu.
pour cela on utilise le champs `userReadInfoCount` qui est un integer qui s'incremente des qu'un user lis l'info ; puis lorsque la valeur du champs `userReadInfoCount` est egal au nbre de user `sharedWithCount` alors on passe la valeur du boolean isFullyRead a true, ce qui va permettre de valider la suppression souhaité de l'info.

## 3. Gestion des événements récurrents

### 3.1 Structure des événements récurrents
- Les événements récurrents ne sont inscrits en base de données que lorsqu'ils entrent dans l'**`active_day_range`** ou lorsqu'un utilisateur les modifie. Cela permet d'éviter de surcharger la base de données.

#### Champs spécifiques :
- **`isRecurring`** : Boolean indiquant si un événement est récurrent.
- **`ispseudo_recurring`** : Boolean (valeur par défaut false) utilisé pour identifier les événements pseudo-récurrents, c'est-à-dire des événements qui se répètent ou se dupliquent, mais dont la période de fin (periode_end) est définie.
- **`event_frequence`** : Relation One-to-One avec l'entité `Event_Frequence` pour gérer les informations de récurrence (jours de la semaine, jour du mois, etc.).

Les événements récurrents qui ne sont pas encore enregistrés dans la base de données peuvent être affichés en fonction de la date recherchée par l'utilisateur sans pour autant être inscrits en base.
Pour éviter de surcharger la base de données, si un utilisateur décide qu'un événement doit se répéter sur une période excédant 7 jours, cet événement sera marqué avec le champ ispseudo_recurring.
Ce champ ispseudo_recurring devra être pris en compte pour gérer la visibilité des événements futurs à une date spécifique, mais ces événements ne seront créés qu'au niveau de l'interface, sans être enregistrés en base de données.

Le cron job qui met à jour les événements à minuit devra non seulement vérifier le champ isRecurring, mais également le champ ispseudo_recurring pour actualiser les événements futurs basés sur ces critères. Le champ ispseudo_recurring est initialement défini à false.


#### Recherche d'événements récurrents :
- Lorsqu'un utilisateur crée un événement récurrent, les paramètres de l'événement sont stockés en base de données :
  - `periode_start` (sans `periode_end`),
  - Une valeur dans le champ **`event_frequence`** (par exemple, `day` ou `monthDay`),
  - `isRecurring = true` pour optimiser les recherches.Cela signifie que seul les events récurrents ont une inscription dans l'entité event_frequence.

Cette structure permet de retrouver facilement un événement récurrent ou de modifier tous les événements associés à une récurrence (par exemple, changer la fréquence de lavage des réfrigérateurs de jeudi à vendredi).
Par exemple si un user souhaite voir les events d'une date future, alors on va chercher tous les events ou `isRecurring = true`(champ ispseudo_recurring devra être pris en compte) , puis on utilise l'entité event_frequence ou on cherche un match avec les champs days ou monthDays et la date recherchée; puis pour etre sur de la valeur de l'event futur, on va devoir comparer chaque occurence des events futurs déja inscrit en bdd (qui a la base etaitent des events_recurrents)sur la date recherchée et afficher la vrai valeur.
On doit aussi vérifier si la valeur de la date recherchée est postérieur a la valeur de période_start.

### 3.2 Modification des événements récurrents
- Lorsqu'un utilisateur modifie les champs d'un événement récurrent, l'application modifiera les futurs événements liés demandés visuelement par l'user , mais ne modifie pas les événements récurrents futurs déja inscrit en bdd et ne modifie pas les événements passés.

### 3.3 Gestion des événements automatiques
- **Entité `supplier`** : Associe des événements récurrents à des fournisseurs grâce au champ **`recuring_events`**, qui liste les IDs des événements récurrents liés aux habitudes de commandes ou d'opérations du fournisseur.

## 4. Suppression et mise à jour des événements

### 4.1 Suppression automatique
- **Champ `date_limit`** : Enregistre la date limite pour la suppression automatique d'un événement, 30 jours après son passage à `done` ou `unrealised`.
- Les événements passés sont supprimés automatiquement après cette période via un **cron job**.

## 5. Gestion des tags et comptage

### 5.1 Comptage des tâches par section
- Champ tag_task_active : Utilisé pour comptabiliser le nombre de tâches actives par section, pour chaque jour de l'active_day_range.
- Ce comptage est mis à jour automatiquement via un cron job quotidien ou manuellement lorsque l'utilisateur effectue une modification, par exemple lorsqu'il passe une tâche de todo à done, ou lorsqu'une nouvelle tâche est créée pour une section et une date données.
Pour optimiser les requêtes, le comptage des tâches est enregistré dans le **Champ  `tag_task_active`**, qui est un tableau associatif. La clé est la valeur du jour dans l'active_day_range, et la valeur est un tableau contenant les sections et le nombre d'occurrences pour chaque section. Par exemple :

tag_task_active = {
   "day1": {"section1": 5, "section2": 3},
   "day2": {"section1": 2, "section2": 6},
   ...
}
- En dehors de l'active_day_range, une requête plus lourde est nécessaire pour récupérer ces informations, suivant le même modèle que pour les événements. Le cron job veille à actualiser ce champ chaque jour et lors de toute modification de l'utilisateur.

### 5.2 Comptage des informations non lues par section
- Champ tag_info_active : Utilisé pour comptabiliser le nombre d'informations non lues par section, en fonction du champ unreadUsers, qui est un tableau des utilisateurs n'ayant pas encore lu l'information.
- Pour chaque jour dans l'active_day_range, le **Champ `tag_info_active`** stocke un tableau associatif avec les sections et le nombre d'occurrences d'informations non lues par utilisateur. Les informations à comptabiliser se basent sur trois éléments : la section, la date, et si l'utilisateur a lu l'information ou non (via le champ unreadUsers). Par exemple :

tag_info_active = {
   "day1": {"section1": 2, "section2": 4},
   "day2": {"section1": 1, "section2": 5},
   ...
}
- Chaque jour, un cron job exécute le comptage des informations non lues par section et met à jour le champ tag_info_active, en utilisant les données du champ unreadUsers pour chaque événement de type info. Ce champ est également mis à jour manuellement lorsque l'utilisateur ouvre une section avec de nouvelles informations, ce qui réinitialise le compteur pour cette section.

- Lorsqu'un utilisateur consulte une section contenant des informations non lues, celles-ci seront affichées en priorité. Si l'utilisateur demande des événements ou des informations en dehors de l'active_day_range, une requête plus complexe sera nécessaire, similaire à celle utilisée pour la gestion des événements.

## 6. Gestion des modifications utilisateur

### 6.1 Auteur et modifications
- **Champ `créatedBy`** : Identifie l'auteur initial de l'event (application ou utilisateur).
- **Champ `updatedBy`** : Identifie l'auteur de la modification (application ou utilisateur).
- **Champ `task_details`** : Permet de lier la tâche à une action spécifique (par exemple, passer une commande chez un fournisseur).

Lorsque l'utilisateur réalise une action sur l'application (comme passer une commande), le statut de la tâche doit etre mis à jour en conséquence. Par exemple, une tâche initialement en statut "todo" avec la description "passer une commande chez le fournisseur B" doit passer à un autre statut (comme "done") une fois l'action effectuée par l'utilisateur.

Pour associer précisément la tâche à l'action de l'utilisateur sur l'application et pouvoir modifier le status de celle ci, le champ task_details est utilisé. Ce champ contient des informations spécifiques telles que la concaténation des valeurs de l'event_section (pour indiquer la catégorie de l'événement) et d'autres indicateurs/références comme le supplierName (nom du fournisseur) ou d'autres éléments contextuels.

Le champ task_details est généré lors de la création de la tâche, que ce soit par l'application ou par l'utilisateur, et contient toutes les informations nécessaires pour tracer l'action effectuée.

## 7. Schéma des entités et relations

### 7.1 Entité `Event`

#### Champs principaux :
- **`id`** : Identifiant unique de l'événement.
- **`type`** : Type d'événement, soit `task` (tâche), soit `info` (information).
- **`importance`** : Boolean indiquant si l'événement est marqué comme important (true ou false).
- **`description`** : Texte décrivant les détails de l'événement.
- **`shared_with`** : Tableau JSON listant les utilisateurs avec qui l'événement est partagé.
- **`createdBy`** : Identifiant de l'utilisateur qui a créé l'événement.
- **`updatedBy`** : Identifiant de l'utilisateur qui a modifié l'événement pour la dernière fois.
- **`periode_start`** : Date de début de la période durant laquelle l'événement est actif.
- **`periode_end`** : Date de fin de la période d'activité de l'événement. Peut être null pour des événements d'une seule journée.
- **`date_status`** : Statut temporel de l'événement, pouvant être :
  - `past` : Événement passé.
  - `activedayrange` : Événement dans la plage active.
  - `future` : Événement futur en dehors de la plage active.
- **`isRecurring`** : Boolean indiquant si l'événement est récurrent (true ou false).
- **`ispseudo_recurring`** : Boolean indiquant si l'événement est pseudo-récurrent (répétition sur plusieurs jours mais non continue).
- **`active_day_range`** : Plage de jours durant laquelle l'événement est actif (exemple : de -3 à +7 jours autour de la date courante).
- **`event_frequence`** : Relation One-to-One avec l'entité `Event_Frequence`, qui stocke les détails de la fréquence pour les événements récurrents (ex. jours de la semaine, jours du mois).
- **`task_details`** : Détails supplémentaires associés à une tâche, comme des informations spécifiques sur une commande, une procédure, etc.
- **`task_status`** : Statut de la tâche, qui peut être :
  - `todo` : À faire.
  - `pending` : En attente.
  - `done` : Réalisée.
  - `late` : En retard.
  - `unrealised` : Non réalisée.
- **`unreadUsers`** : Tableau contenant les utilisateurs qui n'ont pas encore lu l'information associée à l'événement de type `info`.
- **`side`** : Indique le contexte ou le côté de l'événement (ex. "kitchen", "office", ou tout autre domaine pertinent pour l'organisation des événements).
- **`date_limit`** : Date limite à laquelle l'événement sera automatiquement supprimé (30 jours après la réalisation ou après la fin de la période active).
- **`tag_task_active`** : Comptabilise les tâches actives pour chaque section par jour dans la plage active.
- **`tag_info_active`** : Comptabilise les informations non lues pour chaque section et utilisateur par jour dans la plage active.

#### Relations :
- **`section`** : Relation One-to-One avec une section spécifique via son `id`.
- **`event_frequence`** : Relation One-to-One pour gérer la récurrence.
- **`supplier`** : Associe un fournisseur à des événements récurrents.

### 7.2 Entité `Section`
- `id` : Identifiant de la section .
- `name` : Nom de la section.

### 7.3 Entité `Event_Frequence`
- `day` : Jour de la semaine pour les événements récurrents hebdomadaires (1-7, 8 = illimité).
- `monthDay` : Jour du mois pour les événements récurrents mensuels.

### 7.4 Entité `User_Events`
- **Champ `recurringEvents`** : Tableau associatif contenant les IDs des événements récurrents créés/modifiés par l'utilisateur et leur section.
- **Champ `infoEvents`** : Tableau associatif contenant les IDs des événements info créés/modifiés par l'utilisateur et leur section.
- **Champ `taskEvents`** : Tableau associatif contenant les IDs des événements task créés/modifiés par l'utilisateur et leur section.

cela permet a l'utilisateur de retrouver facilement les events pour lesquels il a participé grace a un affichage par section et chronologie;
et ainsi eviter a chercher parmi toutes les dates.

### 7.5 Entité `Supplier`
- `id` : Identifiant du fournisseur.
- `recuring_events` : Tableau des IDs des événements récurrents associés au fournisseur.