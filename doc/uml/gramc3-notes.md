# Notes sur le diagramme uml de gramc3

## Session

Une session permet de demander des ressources pour des versions de projets.

* **IdSession** (22A, 22B), **typeSession** (A ou B), **etatSession** (pour les worflows)
* **commGlobal** est un commentaire qui sera envoyé à tous les demandeurs
* **hparannee** permet d'entrer le nombre d'heures total attribuables pour une année

## Projet

Un projet comprend une version par renouvellement

* **IdProjet** (P2212345 où 22 est l'année de création du projet)
* **versionActive** la version active (zéro ou une) **versionDerniere** la dernière version (toujours une)
* **typeProjet** (de session, fil de l'eau etc)
* **etatProjet** utile pour les workflows

## Version

Une nouvelle version est créée à la création du projet et à chaque renouvellement

* **idProjet** (22AP2212345 soit version du projet P2212345 pour la session 22A)
* **etatVersion** utilisé par les workflows
* **attrHeures** et **attrHeuresEte** heures attribuées
* Les autres champs correspondent aux formulaires remplis par les utilisateurs

## Rallonge

Une rallonge, rattachée à une version, est créée lorsque le responsable de projet soouhaite avoir des heures sans attendre la prochaine session

* **IdRallonge** (22AP2212345R1) soit IdVersion + n° de séquence
* **Etat** utilisé par les workflows
* L'expertise est intégrée à Rallonge (Une seule expertise possible)
* **nbHeuresAtt** représente la proposition de l'expert
* **attrHeures** représente l'attribution définitive
* **attrAccept** ? **validation** ?

## Individu

Individu représente les "comptes" gramc

* **nom**, **prénom**, **mail**, **statut**, **laboratoire**, **établissement**
* Contient les privilèges éventuels (expert, admin, président, etc.)
* **getRoles** renvoie les rôles de l'individu calculé à partir de ses privilèges
* implémente des interfaces permettant d'utiliser cette entité pour l'authentification'

## Sso

Permet de maintenir plusieurs eppn par utilisateur

## CollaborateurVersion

CollaborateurVersion représente les collaborateurs de la version

* Il y a plusieurs collaborateurs par version, et un individu peut collaborer à plusieurs versions
* **labo**, **statut**, **etablissement** sont recopiés de la table Individu lorsque le collaborateur est déclaré,
cela permet de garder la trace de l'historique (une personne change de laboratoire, de statut, etc)
* **login** est true si le collaborateur aun compte "calcul"
* **clogin** est true si le collaborateur a un compte "data" (callisto)
* **deleted** est true si le collaborateur doit être supprimé: il n'est pas supprimé tout de suite (à cause des conséquences
sur la machine), mais ne sera pas repris lors du prochain renouvellement.

## Expertise

Expertise représente l'expertise d'une version (demande) par un expert

* **nbHeuresAtt** proposition d'attribution
* **nbHeuresAttEte** proposition d'attribution pour l'été
* **validation**: projet accepté ou pas
* **definitif**: si false l'expertise est en cours
* **commentaireExterne**: le texte de l'expertise, sera transmis au demandeur
* **commentaireInterne**: le texte de l'exterpise, dans sa version interne au comité d'attributon

Plusieurs expertises peuvent être associées à une version
(cf. le paramètre max_expertises_nb)

## Invitation

utilisé lors de la création de compte par un responsable de projet

## User

User est uilisé pour transmettre le mot de passe initial à un utilisateur

Lorsque le mot de passe est transmis ou expiré, la ligne disparaît dans la base
* **password** est le mot de passe temporaire (sous forme cryptée réversible)
* **cpassword** est le mot de passe crpyté par crypt, transmis pas le supercalculateur (crypté non réversible)
* **passexpir** est la date d'expiration

## Compta

Contient les consommations de chaque utilisateur, chaque groupe, chaque ressources
Permet d'afficher l'état courant ainsi que l'historique de la consommation
