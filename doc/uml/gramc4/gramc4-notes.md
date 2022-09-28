# Notes sur le diagramme UML de gramc4

## Serveur

Représente une machine qui pourra proposer des ressources

* **desc** La description de la machine (pour affichage)
* **nom** Le nom de la machne (ou l'URL)
* **mail** Pour contacter les admins

## Ressource

Représente une ressource pouvant être *demandée/attribuée/consommée*

* Elle est fournie par un **Serveur**
* On peut demander entre **maxDem** et **seuilDem** ou **0** selon le type de projet
* La valeur totale attribuable de la ressource:
  * **totalAttrAn** pour total par année (pour des heures de calcul)
  * **totalAttr** pour total (pour de l'espace-disque)

* On pourra éventuellement faire une attribution secondaire
  (**descAttr2** précise ce que c'est, par exemple heures d'été)

## Dac

Un dac est un *triplet*: Demande-Attribution-Consommation

* Un dac est associé à une version et une ressource
* Il contient la demande (**dem**), l'argumentaire justifiant la demande (**demJustif**),
  l'attribution (**attr**) et une attribution secondaire (**attr2**) ainsi que la consommation (**conso**)

## Session

Une session permet de décuper le temps: une période pour les demandes, une autre pour les expertises, etc.

* **IdSession** (`22A`, `22B`), **typeSession** (`A` ou `B`), **etatSession** (pour les worflows)
* **commGlobal** est un commentaire qui sera envoyé à tous les demandeurs (commentaire de session)

## Projet

Un projet comprend une version par renouvellement

* **IdProjet** (`P2212345` où `22` est l'année de création du projet)
* **versionActive** la version active (zéro ou un) 
* **versionDerniere** la dernière version (toujours un)
* **typeProjet** (de session, fil de l'eau etc)
* **etatProjet** utile pour les workflows

## Version

 Une nouvelle version est créée lors de la création du projet et à chaque renouvellement

* **idVersion** (`22AP2212345` soit version du projet `P2212345` pour la session `22A`)
* **etatVersion** utilisé par les workflows
* Les autres champs correspondent aux formulaires remplis par les utilisateurs: description générale du projet

## Rallonge

Permet de redemander un peu de ressource sans attendre la prochaine session.

- **IdRallonge** (`22AP2212345R1`) soit IdVersion + n° de séquence
- **EtatRallonge** utilisé par les workflows

## Individu

Individu représente les "comptes" gramc

* **nom**, **prénom**, **mail**, **statut**, **laboratoire**, **établissement**
* Contient les privilèges éventuels (expert, admin, président, etc.)
* **getRoles()** renvoie les rôles de l'individu calculé à partir de ses privilèges

* implémente des interfaces permettant d'utiliser cette entité pour l'authentification

## CollaborateurVersion

CollaborateurVersion représente les collaborateurs de la version

* Il y a plusieurs collaborateurs par version, et un individu peut collaborer à plusieurs versions
* **labo**, **statut**, **etablissement** sont recopiés de la table Individu lorsque le collaborateur est déclaré, cela permet de garder la trace de l'historique (une personne change de laboratoire, de statut, etc)
* **deleted** est true si le collaborateur doit être supprimé: il n'est pas supprimé tout de suite à cause des conséquences sur la machine (fermeture de compte), mais ne sera pas repris lors du prochain renouvellement.

## Expertise

Expertise représente l'expertise d'une Dac (demande) par un expert. On peut mettre les 

* **propAttrib** proposition d'attribution
* **propAttrib2** proposition d'attribution n° 2 (ex: heures d'été)
* **validation**: 
* **definitif**: si false l'expertise est en cours
* **commentaireExterne**: le texte de l'expertise, sera transmis au demandeur
* **commentaireInterne**: le texte de l'exterpise, dans sa version interne au comité d'attributon
Plusieurs expertises peuvent être associées à une version
(cf. le paramètre max_expertises_nb)

## User

User représente un utilisateur sur un serveur fournisseur de ressources.

* **password** est le mot de passe temporaire (sous forme cryptée réversible)
* **cpassword** est le mot de passe crpyté par crypt, transmis pas le supercalculateur (crypté non réversible)
* **passexpir** est la date d'expiration
end note
