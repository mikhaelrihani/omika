
L'application permet à l'utilisateur de consulter rapidement tous les événements (tasks ou infos) d'une journée donnée. L'objectif est d'optimiser les requêtes pour un affichage rapide, tout en automatisant la gestion des statuts des tâches (selon qu'elles soient réalisées en interne ou en externe) et en optimisant les requêtes liées à l'affichage des tags.

Les événements peuvent être signalés comme importants grâce au champ "importance". Ils peuvent être visibles uniquement pour l'utilisateur lui-même ou partagés grâce au champ `shared_with`, qui est un tableau JSON.





## 1. Optimisation des requêtes
Un champ `active_day_range` est utilisé pour limiter les recherches aux événements actifs sur une période définie (par exemple, de -3 à +7 jours autour de la date actuelle). Cette plage réduit le volume de données à traiter et permet de filtrer rapidement les événements. Les événements en dehors de cette plage nécessitent des requêtes plus longues (off range).

Tous les jours à minuit, un cron job met à jour cette plage active afin de garantir que les événements à afficher soient toujours à jour. Si l'utilisateur demande des événements en dehors de cette plage, des requêtes plus complexes sont effectuées.


1. Visibilité des Tâches
Période de Visibilité :
Une tâche est visible uniquement si elle se trouve entre periodeStart et periodeEnd.sauf si late ou pending





Chaque interaction (consultation d'une info ou création d'une tâche) met à jour ces champs en temps réel.

## 4. Gestion des événements de type "Info"
Les événements "Info" sont des informations importantes affichées pendant un mois, dès lors qu'elles ont été lues par tous (champ `shared_with`). Après cette période, elles sont automatiquement supprimées par le cron job. L'utilisateur peut également les supprimer avec une interaction frontale, en vérifiant que tous les utilisateurs les ont lues (le bouton "Supprimer" est désactivé tant que ce n'est pas le cas, en vérifiant la liste des `read_users`).

### Visibilité :
Les infos restent visibles jusqu'à ce qu'elles deviennent obsolètes


Le cron job ne modifie pas directement les statuts des tâches ou des infos, mais se concentre sur la gestion des plages de jours actifs et l'optimisation des tags.


un event correspond a un seul jour.
on pourrait avoir un champs date status avec past/activedayrange/future
couplé avec un champs eventStatus todo/done/late/pending/unrealised
on pourrait conserver active day rangepour accelerer les requetes grace a une recherche sur un integer(coreespond a une date)couplé avec un tableau d id des events(grace auquel on aurait les status et le reste des infos)
lorsque l on souaiterai afficher les events d'une date on regarderai si la date de recherche correspond a une date de active day range,ou posterieur ou antérieure et on utilise date status pour filtrer , puis on recupere tous les events de la date et on affiche leur status.

tous les events cree par user meme dans le futur sont inscrit en bdd avec un status todo pour les task et unreaduser peuplé avec tous les users existant pour les infos; sauf si ils sont recurrents ou une autre logique s applique.
en effet pour eviter de surcharger la bdd on n'inscrit/crée les evenements recurrents en bdd uniquement lorsque ceux ci sont dans le range activedayrange,puis comme tous les events il restent en bdd jusqu'a un mois apres avoir été marque avec le status done/unrealised pour les taches et pour les infos c ets 30 jours apres que le derniers user est lu soit qd unreaduser est vide .
De ce fait les events recurrents futurs ne sont inscrit en bdd que lorsque la tache a un status diff de todo ou info a été lu par un user. 

lorsque l user clique sur une date futur alors les events recurrents qui ne sont pas inscrit en bdd seront rendu visible sur l interface front sans pour autant s inscrire en bdd.
pour cela on utilise une requete filtrer sur date_status(futur) et afficherai les corespondances entre date souhaite par user et les event recurrents programme pour apparaitre a cette meme date.
toute event non recurents a les champd de eventfrequence avec une valeur null.
Les events recurrents programmés sont recuperable grace aux valeurs des champs de l entite eventfrequence: 

on irai donc voir quels sont les events unlimited , et hebdomadaire ou mensuel qui matchent avec la jour/date demandé  .
pour accelerer et pour le cote international les requetes on pourrait dans frequence events avoir un champs day(mardi = 2, dimanche = 7..) qui aurait pour valeur un integer relatif au jour de la semaine et pour unlimited ce serait 8; ainsi qu un champs monthDay pour les recurences mensuelles avec une valeur integer du jour du mois (de 1 a 31).
par exemple nous sommes le 8 fevrier et on fait une recherche sur le mardi 5 juin (on a un jour de la semaine et un jour du mois comme parametre de requete)
il faudrait donc passer sur toutes les occurences en bdd de future du champs date_status de l'entité event pour recuperer les event future inscris en bdd mais aussi recuperer ceux non inscris /visible en bdd.
Pour cela des lors qu'un user crée un event special de type recurring, celui ci est enregistrer en bdd pour garder une trace de l existnece de cet recurrence;
de ce fait on doit inscrire cet event avec les parameter suivants :
- a une periode start mais pas end ds l entité event
- a une valeur dans un des champs de l entité event_frequence. cette valeur sera utilise pour la compare avec la date demande par user.
- a une valeur true pour le champ isRecurring(null par default).
ce champs is recurring permet de optimiser la recherche d erequete , peut etre créer un index .

pour les events dont le date_status est passé la recherche/requete se ferai uniquement avec l entité event car tous les events sont inscris en bdd et ce pendant un mois.
on ferai la recherhe en utilisant tous les champs de date_status = past et ou la date matche avec la date demande et non sur un integer comme dans active_day_range.
la requete serai donc lourde si il y a plus de 1000 entrées ! 
dans le passé tous les evnts ont un status inscris en bdd.


avec cette logique d event passe, activerange et futur on aurait donc des evnets visible a l infini dans le futur et sur un mois en historique.


des lors qu un event est recurrent il faudrait les retrouver si modification necessaire .

- event automatique de l app: il faudrait ajouter un champs recuring_events dans l'entité supplier qui listerai les ids des events recurrents qui lui sont associés; 
car on vient automatiquement chercher les habitudes de cde etc, pour créér la valeur des champs de ces event recurrent dasn l entite event et l entité event_frequence.
ainsi on pourrai retrouver facilement les events recurrent et ainsi etre en mesure de les modifier automatiquement si le supplier change ses habitudes.

- event créer par user : par exemple laver les frigos tous les jeudi  et je veux le faire le vendredi maintenant.
il faudrait que l utilisateur puisse acceder a une page "mes events automatics" et les modifier.
on peut imaginer une entité userrecurringEvents avec les champs id, eventIds(qui serait un array avec les events recurrent id), ainsi lorsque user souhaite modifie un userecurringevent on peut modifier tous les events recurrent liés; 
le user va voir son event qu'il a créé auparavant comme un seul bloc a modifier, mais derriere en bdd cet event a inscrit plusieurs event par ex 3 events (mardi et jeudi et samedi).
pour cela on vient chercher tous les events relatifs a cette recurrence(mardi et jeudi ou unlimited ou mensuel) directement avbec les ids.
ceci aura pour consequences de modifier uniquement les futurs events recurrents non inscrit en bdd.


lorsqu un user est dans un cas ou l event qu'il veut créer a une periode de fin ,donc n est pas considere comme recurent , et que cette event doit se repeter sur une periode plus longue que un jour, alors pour autant de jour que l event se repete on aura le meme nbre d event inscrit en bdd tous independant des autres car je me repete mais un event correspond a un jour.
Par contre pour eviter de surpeupler la bdd, si un user decide que ce type d'event se repete sur une periode de plus de 7 jours alors on marquera cet event sur le champs Ispseudo_recurring qui devra etre rajoute aux autres champs necessaire a la visibilté des events futur d'une date en particuliere;je rappel ces events ne sont cree que visuelement sur l interface pas d'inscription en bdd .
le champ ispseudo_recurring est un boolean false par default. 
ici cela veut dire que le cron job devra aussi chercher le champs pseudo_recurring en plus de isrecuring lors de l actualisation des datas a minuit.

Cote front et pour plus d'effciacite lorsque l utilisateur passe sur un autre jour alors on conserve les datas en cache pour la session.

Les events sont definitivement créé et inscrit en bdd lors de leur pasage sur le range actvedayrange. et seront supprime a la fin dune periode de 30jours pour ne pas surcharger la bdd et auront une valeur past  dans le champs date_status  des lors qu il sorte de active day range pour optimiser les requetes. 
un champ date_limit est ici pour ecrire la date correspondant a 30 jour  des lors qu il sorte de active day range pour optimiser la suppression automatique par un cron job.


lorsqu un event n est pas inscrit en bdd dans le futur alors son statut est todo, par contre des lors qu un event dans le futur(encore non inscrit) est realise ou en cours par l user alors 
l event est inscrit en bdd et son status ajusté a sa valeur soumise par l user(par ex passer le balai -->dans un mois, l user glise la task de gauche(todo) a droite (done) la tache passer le balais dans un mois et desormais inscrite)


les taches en cours ou late aujourdhui seront represente a demain et ainsi de suite meme si elles sortent de leur periode active/existance de un jour , cela se traduira par un non mouvement de leur etat dans le active day range a la date de aujourdhui.

par contre si l event avait une periode active plus longue que un jour et a donc la meme valeur dans l event de demain alors la tache sera ecrite comme unrealised et continuera son cours dans uns schema classique.cela evitera d avoir une deux fois la meme tache une dans le bloc front encours/late et l autre dans todo (par ex 2 fois passer le balai).

lorsqu'une tache est realise sur l app par l user alors son stut est modifie (par ex : la tache est en todo et a pour description "passer une cde chez le fournisseur b", dans ce cas lorsque l user passe la cde la tacxhe passe en todo); mais ici on doit pouvoir lier la tache  et l action de luser sur l app donc pour cela on va utiliser un champ task_details .
Ce champs task_deatils a pour valeur la concatenation des valeurs de event_section + d autres repere/indice comme supplierName...
ce champs taskDetails est cree lors de la creation de la task par l app ou par l user .


pour les tags des tasks, l'idée est de comptabiliser les taches pour chaque jour demandé par l user et en fonction des sections.
il faut donc recopuper deux valeurs section et date demandé,et compter les occurences.
pour optimiser les requetes on va utiliser active day range dans un champs tag_task_active qui sera un array avec pour cle activerange value et pour valeur un array avec des champs associatif section:nbre occurences.
ce champ active day range serait mis a jour par un cron job et manuellement achaque modification de l user par ex lorsquil pass une tache de todo a done.ou lorsque l on cree une nouvelle tache pour une section/date.
hors du range active on devoir faire une requete plus lourde sur le meme modele que les events.

pour les tags des infos , l'idée est de comptabiliser les infos non lue par un user pour chaque jour demandé et par section
il faut donc recouper les trois valeurs: section, user a t il lu, et la date ,et compter les occurences.
le champs unreadUsers est un tableau comportant les id des users n'ayant pas lu l info.
pour optimiser les requetes on va utiliser active day range dans un champs tag_info_active qui sera un array avec pour cle activerange value et pour valeur un array avec des champs associatif section:nbre occurences.
a minuit un cron job vient calculer le nbre d user n ayant pas lu chaque info et modifie le champs tag_info_active grace a l usage de unreadusers/section pour chaque event de type info.
et manuellement achaque modification de l user (lorsqu il ouvre la section avec les nouvelles infos alors le compteur est reinitilaisé a zéro).
lorsque l'user ouvre la section des infos , alors les infos non lues seront affichées en première.
hors du range active on devoir faire une requete plus lourde sur le meme modele que les events.

Pour chaque event on devra avoir un champs pour dire qui est l author de l event (app/users) et pour event de type task qui a realise/commencé(modifie en dernier) la tache.

les autres champs de l entite event sont :
- "id": "string",                     // Identifiant unique de l'événement
  "type": "string",                   // Type d'événement (task ou info)
  "importance": "boolean",            // Indique si l'événement est important
  "shared_with": ["string"],          // Tableau JSON des utilisateurs avec qui l'événement est partagé
  "date_created": "datetime",         // Date de création de l'événement
  "description": "string",            // Détails de l'événement
   "updatedBy": "string",                 // Auteur qui modiife de l'événement
  "periode_start": "datetime",        // Date de début de la période de l'événement
  "periode_end": "datetime",          // Date de fin de la période de l'événement
  "side": "string",                   // Côté ou section associée à l'événement


on a aussi l entite event section qui a une relation one to one avec event tout comme eventfrequence a une relation one to one avec event.
l entité event section a les champs :

"event_section": {
    "id": "string",                   // ID de la section liée à l'événement
    "name": "string"                  // Nom de la section
  },
