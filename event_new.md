
des lors que l event parent  a ete crée par user avec une periode pseudo recurring alors tous les events enfants inscrit en bdd doivent faire partie du meme id de group .
de ce fait l user lorsqu'il souhaite modifier un eventparent , cela va modifier tous les events enfants en bdd.
on doit donc penser a avoir sur chaque event en plus de isrecurring ou is pseudo recuring un champs isAlone
je vais avoir une entité event_group qui va avoir comme champs eventChildren qui sera un aray avec tous les id des events enfants.
dans event on a un champs qui etablit la relation many to one avec eventgroup .
de cette maniere un user qui veut modifier un event parent , il va chercher soit l onglet mes events qui lui liste en plus des eventAlone ses eventparent comme un seul event , ou directement sur un event depuis leur affichage  par jour.
lorsque l user clique sur un event depuis l interface pour le modifier alors il ne fait plus partie du group car il n est lus identique aux autres et peut etre que l user veut le garder telquel mais modifier tous les autres du groupe!!!
si l'user souhaite modifier/supprimer un event depuis l interface qui est periodique allors l faut demander si il veut le faire sur cette date en particulier ou si il veut modifier les autres dates aussi .
events avec lesquel il a interagit 


l user veut pouvoir modifier les events alone et periodique et les retrouver facilement.
il faut donc acceder a une liste des events par ordre de dernier créé ou modifié
ici on ne tient pas compte de leur date active 

deja un event du past a today est pas modifiable, mais suprimable.
si un event periodique est modifie alors on modifie simplement tous les events enfants futures inscrit en bdd qui ont un status todo or unread;
pour ceux qui ont un status different alors on modifie la date active uniquement;


prenons le cas d'un event parent/ repetitif: "passer une cde tres importante a 8h a supplier b du 8 nov au 25 nov",
nous sommes le 10 nov.
les events du 8 et 9 sont inscrit en bdd dans le passé avec un status done, les events du 10 au 15 ont un status todo et sont inscrit en bdd, les events du 16 au 24 ont un statut todo,
l'event du 25 est inscrit en bdd car son statut est done.







tous les events alone sont inscrit en bdd;
les events periodique sont inscrit en bdd sur une plage de 7 jours(activedayrange) a compter de leur periode_start, puis un cron job continue a les inscrire quotidiennment a minuit tant que le dernier event enfant correspondant a période end n'a pas été inscrit.
les events récurrent eux suivent le meme process que les périodique mais sont unlimited/ pas de periode end.


lorsque la recherche se fait sur une date postérieure a 7 jours(date_status = futur) alors on va rechercher en bdd les champs is_recurrnet et is_pseudoRecureent pour afficher les events non inscris en bdd en plus de ceux deja inscris en bdd.
notre point de reference pour optimiser les requete est datenow/today;
est ce que la valeur de la date cherche est entre j-3 et j+7(activedayrange);
si oui alors on fait une requete sur la valeur de la date sur les events faisant partie de activedayrange et plus precisement sur ceux qui correspondent a l'index (j-2 par ex) de la date cherche.
si non alors est ce que la valeur de la date est futur ou passe a ce activedayrange.
De ce fait on peut optimiser les requetes en ayant un champs date_statut (past, activedayrange,future).
ca resout aussi le problem de choisir a partir de quel moment on n inscrit pas un nouvele event en bdd pour eviter le surpeuplement; et permet une recheche par index composite.



