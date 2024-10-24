

# Gestion des événements et optimisation des requêtes

Cette application permet à l'utilisateur de consulter rapidement tous les événements (tâches ou informations) d'une journée donnée. L'objectif est d'optimiser les requêtes pour un affichage rapide, d'automatiser la gestion des statuts des tâches (internes à l'application ou externes) et d'améliorer la gestion des tags.

Les événements peuvent être signalés comme importants grâce au champ **`importance`** et peuvent être partagés avec d'autres utilisateurs à l'aide du champ **`shared_with`**, un tableau JSON.
Les événements sont supprimés automatiquement après 30 jours suivant leur date de presentation "today" via un **cron job**.
Un événement correspond a un seul jour demandé de réalisation, si l'événement a une période plus longue ce sont alors des duplications signifiant que l'événement est pseudo_récurrent sur une période donnée.
## 1. Optimisation des requêtes

### 1.1 Plage de jours actifs
- **Champ `active_day_range`** : Limite les recherches aux événements actifs dans une plage de jours définie (par exemple, de -3 à +7 jours autour de la date actuelle).
  - Cela permet de réduire le volume de données à traiter et d'optimiser les performances.
  - Mise à jour quotidienne via un **cron job**, qui ajuste la plage active des événements.
  - Si l'utilisateur demande des événements en dehors de cette plage, des requêtes plus complexes sont effectuées.

### 1.2 Gestion du `date_status`
- **Champ `date_status`** : Classifie les événements selon leur statut temporel :
  - `past` : événements passés.
  - `before_yesterday` : événements d'avant hier.
  - `yesterday` : événements d'hier.
  - `today` : événements aujourd'hui.
  - `tomorrow` : événements demain.
  - `after_tomorrow` : événements après-demain.
  - `after_after_tomorrow` : événements après_après-demain.
  - `future` : événements futurs.

La recherche utilise ce champ pour filtrer les événements et récupérer les détails nécessaires.
Exemple de requete: Pour une recherche dans le futur , on récupére d'abord tous les événements avec date_status = future,
puis on affine la recherche pour trouver ceux qui correspondent exactement au jour demandé.

### 1.3 Cache des sessions
- Les événements sont stockés en cache pour la session de l'utilisateur, ce qui permet de réduire les appels à la base de données lorsque l'utilisateur navigue sur plusieurs jours.

### 1.4 Index composite

- Un index composite sur date_status et active_day_range : Cet index permet d'optimiser les requêtes filtrant d'abord par date_status (par exemple, "activedayrange") puis en affinant par la valeur de active_day_range (par exemple, pour "today" ou un autre jour précis dans la plage active).

- Un index composite sur date_status, periode_start, et periode_end : Cet index permet de filtrer efficacement les événements en fonction de leur date_status (futur ou passé), puis de restreindre la sélection en fonction des plages de dates periode_start et periode_end pour identifier les événements pertinents à une date donnée.


## 2. Gestion des événements tâches et information
- Les événements sont visibles uniquement s'ils se trouvent entre periode_start et periode_end, à l'exception des événements de type tâche qui sont marqués comme en retard (late) ou en attente (pending).
- Dans les cas où une tâche est en statut late ou pending, l'événement tâche est dupliqué et enregistré en base de données avec une période active limitée à une journée (aujourd'hui). Cette duplication continue chaque jour jusqu'à ce que le statut de la tâche passe à done ou unrealised. Cette mise à jour est effectuée par un cron job chaque jour à minuit.
- L'événement original qui a généré cette duplication sera quant à lui marqué comme unrealised.
- De plus, si l'événement initial avait une période active supérieure à une journée et que cette même tâche est planifiée pour le lendemain, celle-ci sera automatiquement marquée comme unrealised. Cela permet d'éviter d'avoir deux tâches identiques : une dans la section "en cours/retard" et l'autre dans "à faire" (par exemple, pour éviter deux tâches "passer le balai").

### 2.1 Gestion des tâches
- **Statuts des tâches** :
  - `todo`, `done`, `late`, `pending`, `unrealised`.
- Chaque tâche créée par un utilisateur est inscrite en base de données avec un statut `todo` par défaut, sauf si elle est récurrente, auquel cas d'autres règles s'appliquent (voir section récurrence).
- L'utilisateur peut également supprimer manuellement une tache , dans ce cas la un event annoté important est crée pour en aviser les autres users et garder une trace de cette décision.

### 2.2 Gestion des événements d'information
- **Champ `unreadUsers`** : Tableau contenant les utilisateurs n'ayant pas encore lu l'information.
- Dans le cas ou un user n'a pas lu une info, l' événement info est créé  par duplication et donc inscrit en bdd avec une période active de un jour (aujourd'hui) et ce tant que le user est présent dans le champ **`shared_with`**. Cette actualisation se fait par un cron job a minuit.
- L'utilisateur peut également supprimer manuellement un événement de type "Info", sous réserve que tous les utilisateurs l'aient lu.

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