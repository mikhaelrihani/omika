
des lors que l event recurrent parent  a ete crée par user  alors tous les events enfants inscrit en bdd doivent faire partie du meme id de group .
de ce fait l user lorsqu'il souhaite modifier un event recurrent parent , cela va modifier tous les events enfants en bdd.

lorsque l user clique sur un event depuis l interface pour le modifier alors il ne fait plus partie du group car il n est plus identique aux autres et peut etre que l user veut le garder telquel mais modifier tous les autres du groupe!!!
si l'user souhaite modifier/supprimer un event depuis l interface qui est periodique allors l faut demander si il veut le faire sur cette date en particulier ou si il veut modifier les autres dates deja inscrite en bdd et avecun statut todo ou unread  .
events avec lesquel il a interagit 

RECHERCHE D EVENT :
l user veut pouvoir modifier les events alone et periodique et les retrouver facilement.
il faut donc acceder a une liste des events par ordre de dernier créé ou modifié
on pourrait diferencier les events d un jour de ceux périodique sur l'interface avec un tag et un filtre

situation un :
l user clic sur l interface et interagit avec l event enfant.
il doit pouvoir le supprimer ou le modifier 
sur l interface il ne peut pas supprimer l'event parent periodique car il ne le voit pas.
situation 2 :
l user est dans l'historique
il va donc voir deux type d'event periodique ou daily.
il doit pouvoir supprimer ou modifier l event enfant qui est un event daily et qui n affecte pas les autres de son groupe periodique.
il doit pouvoir supprimer ou modifier l event parent;
dans ce cas on se demande que faire avec les events enfant deja inscrit dans le futur et qui ont un status autre que done et read.
pour les infos on les modifie toute selon les dernieres modifs de event parent(on va donc les supprimer sans trace garde en bdd et les recreer comme si de rien n 'etait)
pour les task hors statut done on fait la meme chose que info.
pour les tasks deja faites/done on pourrait les marquer avec un tag "warning" et sur l interface ecrire une note a l user pour lui expliquer que la tache est obsolete et de faire avec les consequeneces.

ici on ne tient pas compte de leur date active 

un event du past a today est pas modifiable, mais suprimable.


les events periodique sont inscrit en bdd sur une plage de 7 jours(activedayrange) a compter de leur periode_start, puis un cron job continue a les inscrire quotidiennment a minuit tant que le dernier event enfant correspondant a période end n'a pas été inscrit.
les events récurrent eux suivent le meme process que les périodique mais sont unlimited/ pas de periode end.


lorsque la recherche se fait sur une date postérieure a 7 jours(date_status = futur) alors on va rechercher en bdd les champs is_recurrnet et is_pseudoRecureent pour afficher les events non inscris en bdd en plus de ceux deja inscris en bdd.
notre point de reference pour optimiser les requete est datenow/today;
est ce que la valeur de la date cherche est entre j-3 et j+7(activedayrange);
si oui alors on fait une requete sur la valeur de la date sur les events faisant partie de activedayrange et plus precisement sur ceux qui correspondent a l'index (j-2 par ex) de la date cherche.
si non alors est ce que la valeur de la date est futur ou passe a ce activedayrange.
De ce fait on peut optimiser les requetes en ayant un champs date_statut (past, activedayrange,future).
ca resout aussi le problem de choisir a partir de quel moment on n inscrit pas un nouvele event en bdd pour eviter le surpeuplement; et permet une recheche par index composite.



