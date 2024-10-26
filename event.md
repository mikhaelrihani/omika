

# Gestion des événements et tags et optimisation des requêtes

Cette application permet à l'utilisateur de consulter rapidement tous les événements (tâches ou informations) d'une journée donnée. L'objectif est d'optimiser les requêtes pour un affichage rapide, d'automatiser la gestion des statuts des tâches (internes à l'application ou externes) et d'améliorer la gestion des tags.

Primordial de comprendre que un event ou un tag(count) correspond a un seul jour; un event récurrent est lui defini sur une période avec une fin ou unlimited.
Les événements peuvent être signalés comme importants grâce au champ **`importance`** ou **`favoriteby`**

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
  - `todo`, `done`, `late`, `pending`, `unrealised`, `warning`,`modified`.
- Chaque event tâche créée par un utilisateur est initialisé avec un statut `todo` par défaut.
- le status `late` est utilisé pour marquer un event tache comme non réalise mais representé aujourdhui.
- le statut `unrealized` est utilisé pour marquer les events de la veille des lors qu'un event tache est marqué comme late a la date de aujourdhui.ce statut ne changera pas dans le passé.
- le statut `warning` est utilisé pour indiquer a l'user qu'un event doit etre reconsideré car il a été classé obsolete de par un changement de son event_recurrents qui l'a créee.
- L'utilisateur peut également supprimer manuellement un event tache  , dans ce cas un event info, avec un tag important, est crée pour en aviser les autres users et garder une trace de cette décision.
- Le statut `modified` est utilisé dans le cas ou l utisateur a modifie la description ou tout autre champs, accesible a la modification depuis l'interface, de l event tache.

### 2.2 Gestion des événements d'information
- **Champ `unreadUsers`** : Tableau contenant les utilisateurs n'ayant pas encore lu l'information.
- Dans le cas ou un user n'a pas lu une info, l' événement info est créé  par duplication et donc inscrit en bdd avec une période active de un jour (aujourd'hui) et ce tant que le user est présent dans le champ **`shared_with`**. Cette actualisation se fait par un cron job a minuit.
- L'utilisateur peut également supprimer manuellement un événement de type "Info", sous réserve que tous les utilisateurs l'aient lu.
pour cela on utilise le champs `userReadInfoCount` qui est un integer qui s'incremente des qu'un user lis l'info ; puis lorsque la valeur du champs `userReadInfoCount` est egal au nbre de user `sharedWithCount` alors on passe la valeur du boolean `isFullyRead` a true, ce qui va permettre de valider la suppression souhaité de l'info.
- un champ `sharedWith` dans l'entité eventInfo est utilisé pour vérifier si l'eventinfo peut etre affiche pour cet user.

#### Champs spécifiques :

## chapitre entité event:

- **`isRecurring`** : boolean utilisé pour trier/séparer les eventRecurring des events affichable sur l interface
- **`eventRecurring`** : integer de la relation de l'instance eventRecurring à laquelle cet event est attaché, permet de recuperer les events qui doivent etre pris en compte lors d'une modification de l'eventRecurring parent.
- **`task`** : integer de la relation de l'instance task dans laquelle on retrouve les infos propre a un event task(voir chapitre entite task).
- **`info`** : integer de la relation de l'instance task dans laquelle on retrouve les infos propre a un event info(voir chapitre entite info).
- **`dueDate`** : date a laquelle l'event doit etre visible sur interface
- **`date_status`** : a une des valeurs suivantes past/activedayrange/future, permet de degrossir les events a rechercher pour optimiser les requetes.
- **`active_day`** : a une valeur integer qui represente la valeur d une date entre -3 et +7 (activeDayRange determine dans .env)
la valeur est modifie chaque jour par le cronjob a minuit pour garantir la coherence des dates par rapport a datenow() qui est aujourdhui et aui a pour valeur 0. demain par ex a une valeur 1 et hier -1.
ce champ existe pour optimiser les requetes en utilisant les indexs.
si due date est hors du champs activeDayRange alors ce champs est null.

- **`side`** : event appartient a kitchen ou office
- **`type`** : si l'event est de type info ou task
- **`section `** : sur l interface les events sont filtrer d abord sur leur side puis sur leur type et enfin par section(carte,recette,fournisuers,inventaire,planning,etc...)
chaque section etant propre a kitchen ou office, mais peuvent etre pour une info ou une tache.


- **`title`** : titre pour rapidement visualiser le sujet lors du listing des events dune recherche.
- **`description`** : description de la tache a effectuer ou de l'information a lire.
- **`createdBy `** : auteur original(application ou utilisateur)
- **`updatedBy`** : auteur de l update(application ou utilisateur)
- **`isImportant`** : permet de mettre en evidence sur l interface un event plus qu'un autre car on le considere priortaire par ex.
- **`favoritedBy`** : permet de mettre en evidence sur l interface un event favoris de l user connecté.

## chapitre entité task:
- **Champ `task_details`** : Permet de lier la tâche à une action spécifique (par exemple, passer une commande chez un fournisseur).
Pour associer précisément la tâche à l'action de l'utilisateur sur l'application et pouvoir modifier le status de celle ci, le champ task_details est utilisé. Ce champ contient des informations spécifiques telles que la concaténation des valeurs de l'event_section (pour indiquer la catégorie de l'événement) et d'autres indicateurs/références comme le supplierName (nom du fournisseur) ou d'autres éléments contextuels.

Le champ task_details est généré lors de la création de la tâche, que ce soit par l'application ou par l'utilisateur, et contient toutes les informations nécessaires pour tracer l'action effectuée.

Lorsque l'utilisateur réalise une action sur l'application (comme passer une commande), le statut de la tâche doit etre mis à jour en conséquence. Par exemple, une tâche initialement en statut "todo" avec la description "passer une commande chez le fournisseur B" doit passer à un autre statut (comme "done") une fois l'action effectuée par l'utilisateur.


## chapitre entité info:



## 3. Gestion des événements récurrents

### 3.1 Structure des événements récurrents
- Les événements_récurrents sont inscrits en base de données lors de leur création par l user.
- les evenement_récurrents ne sont pas affiché sur l interface car leur but est de gérer les events qui sont récurrents.
- Les evenement_récurrents peuvent etre considérés comme les parents des events récurrents, cad des events que l user souhaite voir se répéetrsur une periode donnee ou ilimité.
- Les evenement_récurrents permettent d afficher les events récurrents non encore inscrit en bdd pour une date choise par user; cad qu'ils sont visible sur l interface mais pas en bdd.
- les evenement_récurrents permettent l inscription des events recurrents lorsque leur duedate est dans la fenetre de active_day_range; ou lorsqu'un utilisateur interagit/modifie un event recurrent pour le moment uniquemnt visible sur interface(par ex  si il est de type info alors celui ci est marque comme lue, ou si c'est une tache alors son statut todo est modifie en fonction ) .

#### Champs spécifiques :
- **`periodeStart`** et **`periodeEnd`** : ils definissent la periode sur laquelle les events recurrents seront a terme crée/inscrit en bdd ou simplement visible sur interface dans ces dates des la creation de cet event_recurrents parent. 
- **`events`** : liste tous les events recurrents enfant inscrit en bdd . 
un cas d usage frequent sera le jour ou un user decide de modifier un event_recurrent parent alors il faut pouvoir modifier automatiquement les events inscrit(les events futurs/non inscrit ne sont pas impacte car il refleteront les champs du event_recurrent parent le jour de leur creation uniquement ).
pour modifier les events recurents enfants , le processus sera :
- la suppression de tous les events_info inscrit(en verifiant/supprimant l aref isfavorite dans entité user), et creation/inscription(si dans active_day_range) des nouveaux events info"modifiés".
- la suppression de tous les events taches qui ont un statut todo,et creation/inscription(si dans active_day_range) des nouveaux events taches"modifiés".
- le signalement a l user pour les evnets taches inscrit dont le statut est differents de todo; chacun de ses event taches aura desormais un statut"warning" et l user decidera que faire avec ses events taches devenu obsoletes.
- **`periodDates`**,**`weekDays`**,**`monthDays`** sont des listes de dates ou integers qui permettent la mise a jour/inscriptions(par le cronjob) des nouveaux events taches/infos en bdd ou simplement pour visualiser les events taches/infos sur une date hors de la fenetre active_day_range(en plus dec eux eventuellement déjà inscrit en bdd).
par ex une recherche sur une date future va aller chercher l'entité eventRecurring et va utiliser ces trois champs pour afficher/inscrire les events taches/infos.
- **`isEveryday`** est un boolean utilise pour la meme raison que les champs **`periodDates`**,**`weekDays`**,**`monthDays`** et indique si un event recurring a été crée pour etre inscrit/visible tous les jours de sa periode active entre **`periodeStart`** et **`periodeEnd`**.

### 3.3 Gestion des événements automatiques de l'application
- **Entité `supplier`** : Associe des événements récurrents à des fournisseurs grâce au champ **`recuring_events`**, qui liste les IDs des événements récurrents liés aux habitudes de commandes ou d'opérations du fournisseur.
cela permet de modifier les events_recurrents facilement et de lancer la logique metier pour la suppression des events recurrents enfant.

## 4. Suppression et mise à jour des événements

### 4.1 Suppression automatique

- Les événements passés sont supprimés automatiquement 30 jours après la dueDate  via un **cron job**.





## 5. Gestion des tags et comptage

$unreadInfoCount --> a checker avec la suppresion/ modification 'un event_recurrents parent pour les infos et taches.

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





