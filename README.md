
# INSTALLATION sur une Debian ou dérivés

**Note** - Cette documentation est validée sur Debian 10 Buster et Debian 11 Bullseye

Installations de paquets
-----

- fonctionne en **php 8.0 MINIMUM**, validé avec mariadb 10.3

- Installer apache/php 8.0:
```
apt install ca-certificates apt-transport-https lsb-release wget gnupg2
wget -q https://packages.sury.org/php/apt.gpg -O- | sudo apt-key add -
echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
apt update
apt install php8.0
```
 Voir ici pour les détails: https://sys-admin.fr/installation-php-7-4-sur-debian/

- Modules php:
```
apt install apache2 libapache2-mod-php8.0 php8.0-intl php8.0-cli php8.0-common php8.0-intl php-json php8.0-opcache php8.0-readline php8.0-xml  php8.0-mysql php8.0-gd php8.0-intl php8.0-curl
```
- Maria-db:
```
apt install mariadb-client mariadb-server
```
- Image magick, polices et autres (pour les graphiques de consommation, la conversion html vers pdf et la validation des pdf téléversés):
```
apt install imagemagick zip unzip poppler-utils
apt install xfonts-75dpi xfonts-base xfonts-utils x11-common libfontenc1 xfonts-encodings
```
- Installer `wkhtmltopd` depuis https://wkhtmltopdf.org (disponible en .deb)

Configuration du mail:
----

**Le serveur doit être capable d'envoyer des mails:**

  - Par exemple `Exim4` (le MTA standard sous Debian) fonctionne très bien avec gramc
  - Ou encore `ssmtp`, ` msmtp` (les mails sont envoyés, jamais reçus), ou postfix

### Configurer le mail pour une version de développement:

Avec une version de développement vous ne voudrez pas que l'application envoie des notifications à de vrais utilisateurs. C'est surtout vrai si vous avez chargé la version de dev avec une vraie base de données. Pour éviter cela, n'oubliez pas la variable `MAILER_RECIPIENT` du fichier `.env.local` (voir ci-dessous)

Installer le code de gramc3:
----

```
git clone https://github.com/calmip/gramc3
cd gramc3
```

Répertoire data:
-----

C'est dans ce répertoire que vont se retrouver:
- Les documents téléversés: rapports d'activité, ficher projet
- Les figures téléversées
- Le modèles de rapport d'activité

~~~~
tar xfz data-dist.tar.gz
~~~~

Le répertoire data:
- *ne doit pas* être exporté par apache (cf. ci-dessus)
- peut appartenir à root
- Les sous-répertoires data/* *doivent* appartenir à `www-data`
~~~~
     chown root.root data
     chown -R www-data.www-data data/*
~~~~

## Répertoire var

C'est dans ce répertoire que vont se trouver:

- Le cache

- Les répertoires de session php

- Les fichiers de log

- doit être accessible en écriture par www-data

- Les sous-répertoires var *ne doivent* pas être exportés par apache (cf. ci-dessus)

~~~~
  mkdir var
  chown www-data.www-data var
~~~~

Configuration, personnalisation:
----

### Répertoire altermeso:

Créer un lien symbolique vers calmip ou criann.

`ln -s mesocentres/calmip altermeso`

### Charte graphique:

Vous pouvez modifier les couleurs ainsi que les logos afin de les faire coller à votre charte graphique. 

#### Logos:

Il faut générer **trois fichiers png**:

- La bannière (en haut à gauche de l'écran): `altermeso/public/icones/banniere.png`
- Le favicon: `altermeso/public/icones/favicon.ico`
- Un élément graphique tirée de votre charte et qui sera affiché en haut à droite: `altermeso/public/icones/header.png`

Des fichiers `.dist` sont fournis, ils peuvent servir d'exemple *(à ne pas prendre pour une installation qui ne dépendrait pas de calmip)*.

#### Couleurs:

Vous devez copier le fichier `altermeso/public/css/colors.css.dist` sur `colors.css` et l'éditer afin de faire correspondre les couleurs de l'application à celles de votre charte graphique:

### Fichier parameters.yaml:

```
cd config
cp parameters.yaml.dist parameters.yaml
```

Editer le fichier et paramétrer l'application:

- Le nom du mésocentre, quelques url, etc.
- Les fonctionnalités que vous souhaitez utiliser, ou non.
- Les types de projets supportés (1,2,3)
- Et pour finir les idp "préférés" (dépend des établissements à proximité du mésocentre)

### Fichier index.php:

```
cd public
cp index.php.dist index.php
```

Editez ce fichier et éventuellement commentez ou décommentez quelques lignes suivant que vous êtes derrière un reverse proxy ou pas.

### Fichier adresses.txt:

Ce fichier est propre à gramc3, il est utilisé uniquement en mode "développement". Il répertorie les adresses IP à partir desquelles il est possible d'utiliser gramc3.

~~~~
cd config
cp adresses.txt.dist adresses.txt
~~~~

Editer le fichier et introduire les adresses IP de vos postes de développement

### Fichier env.local:

~~~~
cp .env.local.dist .env.local
~~~~

Editer le fichier et inscrivez les paramètres demandés (identifiants de connexion à la base de données notamment). N'oubliez pas de remplacer `serverVersion=mariadb-10.3.29` par la version correcte qui se trouve sur votre serveur

**ATTENTION** - **tout** doit être renseigné dans ce fichier

**ATTENTION**: Ce fichier contient des informations sensibles, il doit être protégé:

Les deux premières lignes sont importantes:

APP_ENV=dev   On a la barre symfony en bas de l'écran, très utile pour développer ou déboguer
APP_DEV=prod  On n'a plus la basse symfony, utile pour la prod
Il est à noter que certaines configurations sont différentes selon qu'on est en dev ou en prod (cf. config/packages), certains objets n'ont pas le même type, etc:
il est donc important de tester avec ce paramètre sur prod avant de mettre en production une nouvelle version

APP_DEBUG=1   On peut se connecter en mode debug, c'est-à-dire SANS AUTHENTIFICATION, il faudra donc protéger l'installation par ailleurs (par adresses IP par exemple)
              Les mails sont envoyés uniquement à l'adresse se trouvant dans la variable MAILER_RECIPIENT, utile pour tester sans importuner les utilisateurs
APP_DEBUG=0   A UTILISER EN PRODUCTION (sinon n'importe qui peut se connecter !)

```
chown www-data .env.local
chmod 400 .env.local
```

Installation de symfony:
----
Installer composer depuis https://getcomposer.org/

Appeler composer avec les droits www-data:

~~~~
mkdir vendor && chown www-data.www-data vendor
./secu-off.bash
sudo -u www-data php composer.phar --no-scripts install
./secu-on.bash
~~~~

Base de données:
----

**Création d'une base de données et d'un utilisateur**

Si vous utilisez mariadb, vous pouvez créer l'utilisateur et la base comme indiqué ici: https://www.security-helpzone.com/2016/05/15/developpement-sql-mysql-creer-un-utilisateur-et-lui-attribuer-des-droits/

Configuration du mail:
----
Si vous avez un sendmail qui sait envoyer les mails, la config de .env.local est sans doute OK pour vous
N'oubliez pas de renseigner MAILER_RECIPIENT pour ne pas envoyer des mails à n'importe qui lors des tests
**ATTENTION** - Cela ne fonctionne QUE si APP_DEBUG=1 dans .env.local !

Pour tester la configuration:

~~~~
sudo -u www-data bin/console app:send-a-mail titi@toto.fr
~~~~

Si APP_DEBUG vaut zéro le mail sera envoyé à titi@toto.fr
Si APP_DEBUG vaut 1 le mail sera envoyé à MAILER_RECIPIENT

**Installation d'une base de donnees déjà en exploitation sur une instance de développement:**

~~~~
cd reprise
sudo -u www-data ./reload-db un-dump-de-la-bd.sql
~~~~

**ATTENTION:**
Si votre dump provient d'une version 3.5 ou 3.6 de gramc3, il est nécessaire d'initaliser certains champs. Cela se fait année par année:

~~~~
bin/console app:InitTypeVersion 2022
~~~~

A exécuter pour chaque année se trouvant dans votre base de données.

**Installation d'une base de données vide sur une instance de développement:**

~~~~
cd reprise
sudo -u www-data ./reload-db gramc3.sql.dist
~~~~

La commande reload-db va effacer la base existante, recharger la base à partir du fichier sql, la mettre à niveau si besoin puis appliquer les "fixtures", ci-besoin.

Ensuite, un mail sera envoyé à l'adresse toto@exemple.com: cela permet de vérifier que le mail est bien envoyé à l'adresse $MAILER_RECIPIENT et de s'assurer que les utilisateurs ne recevront pas de mails lors des essais...

#### Vérification:

Pour vérifier que tout est correctement configuré, vous pouvez utiliser la commande ci-dessous, elle doit se dérouler correctement et envoyer un mail à `MAILER_RECIPIENT`:

```
bin/console app:send-a-mail toto@titi.fr
```

- Si vous mettez **`dev`** dans `APP_ENV`, vous êtes en mode `dev`, ce qui signfie entre autres que tout le monde peut se connecter sans authentification. Il faut donc protéger l'application par un autre système, en l'occurrence les adresses IP doivent être déclarées dans le fichier `adresses.txt`. Par ailleurs, toutes les notifications seront automatiquement envoyées à l'adresse mail déclarée dans  `MAILER_RECIPIENT` , qui recevra donc un mail de tests si vous appelez la commande ci-dessus.

- Si vous mettez **`prod`** dans APP_ENV, vous êtes en mode `prod`, ce qui signfie que vous ne pourrez utiliser l'application que si vous vous connectez réellement, donc si shibboleth est configurée. Par ailleurs les notifications sont *réellement* envoyées aux adresses figurant dans la base de données, la commande ci-dessus ne fonctionnera donc pas.

Remplissage initial du cache:
----

~~~~
sudo -u www-data bin/console cache:clear
sudo -u www-data bin/console cache:warm
~~~~
Il ne doit pas y avoir de warning ou de message d'erreur. S'il y a des messages, c'est probableme dû à une variable non configurée dans .env.local !

configuration apache2:
----

*Il est important que gramc3 ne ne soit pas à l'URL /, sinon on aura du mal à configurer shibboleth. Le plus simple est alors de:*

- Activer le module rewrite d'Apache

- Laisser `DocumentRoot` sur `/var/www/html`

- Créer un lien symbolique `gramc3` sur le répertoire `public`:

~~~~
cd /var/www/html
ln -s chemin/vers/gramc3/public gramc3
~~~~

- On peut utiliser la commande suivante pour générer un fichier `public/.htaccess`:

  ```
  composer.phar remove symfony/apache-pack
  composer.phar require symfony/apache-pack
  ```
- la variable d'environnement BASE doit être positionnée à gramc3, ce qui peut se faire grâce à la directiver Apache SetEnvIf 

- Le fichier doc/apache2-gramc3.conf donne un exemple de fichier de configuration pour Apache

Sécuriser l'installation:
----
Le script secu-on.bash passe l'essentiel des fichiers en readonly
~~~~
./secu-on.bash
~~~~

Revenir en mode "mise à jour"
----
Pour mettre à jour Symfony ou pour d'autres opérations de maintenance, il faut repasser tous les fichiers en rw:

~~~~
./secu-off.bash
~~~~

Fin de la configuration:
-----

- Se connecter à gramc avec un navigateur: cliquer sur `connection (dbg)`
- Utilisateur = `admin admin`

#### En cas de problème:
- Regarder les différents fichiers log, notamment `var/dev.log` et le fichier apache.
- Vérifier la configuration apache (ssl ? rewrite ?)
- Vérifier que tout est renseigné dans .env.local
- Si vous avez le message "Erreur dans la page d'accueil", vous pouvez éditer le fichier `gramc3/src/EventListener/ExceptionListener.php` et décommenter deux lignes (aux environs de la ligne 94, voir le commentaire) afin d'afficher l'exception. Mais n'oubliez pas de les recommenter par la suite !

Premier démarrage:
-----
Il reste maintenant à:
- Ajouter des utilisateurs administrateurs
- Ajouter des utilisateurs experts et les connecter avec des thématiques
- Ajouter des utilisateurs présidents
- Ajouter des laboratoires
- Configurer shibboleth (voir ci-dessous)
- On peut maintenant supprimer le user admin admin et le laboratoire GRAMC qui ne servent plus à rien

Configuration de Shibboleth:
----

- Installer quelques paquets supplémentaires:
~~~~
  apt install libapache2-mod-shib shibboleth-sp-common shibboleth-sp-utils
~~~~
- Configuration apache: Ajouter dans la section VirtualHost de gramc3:
  ~~~~
  # important pour pouvoir utiliser d'autres techniques d'authentification (cf. pour git)
  ShibCompatValidUser On
  
  <Location "url-de-gramc/login">
       AuthType shibboleth
       ShibRequestSetting requireSession 1
       ShibRequestSetting applicationId default
       Require shibboleth
       ShibUseHeaders On
  </Location>
  ~~~~
- Redémarrer apache:
  ~~~~
  systemctl restart apache2
  ~~~~



Où est le code de gramc ?
=========================
`gramc3` est une application symfony, il repose sur le patron de conception MVC. Les principaux répertoires sont les suivants:

        src                   Le code php de l'application
        src/Controller        Tous les contrôleurs (les points d'entrée de chaque requête)
        src/Entity            Les objets permettant de communiquer avec la base de données en utilisant l'ORM Doctrine (un objet par table, un membre par champ)
        src/Form              Les formulaires (correspondent aux entités)
        src/Repository        quelques fonctions non standards d'accès à la base de données
        src/GramcServices     L'essentiel du code, implémenté en "services symfony" cf. https://symfony.com/doc/current/service_container.html
        src/GramcServices/Workflow  Les workflows de l'application (changement d'états des objets Projet, Version, Rallonge)
        src/Utils             Des trucs bien utiles
        src/XXX               Le code php "extérieur" utilisé par gramc3


        templates             Les vues, c'est-à-dire tous les affichages, écrits en html/twig
        templates/default     Les vues de la page d'accueil, de l'aide, etc., et les bouts de code utilisés partout (base.html.twig etc)
        templates/xxx         Les vues correspondant aux principaux écrans - Voir dans les controleurs pour savoir quelle vue est utilisée à quel moment
    
        public                Accessible directement par apache2
        web/icones            Les icones (png)
        web/js                Le code javascript
        web/rm                Les modèles de rapports d'activité à télécharger
    
        var                   le cache, les sessions php, les fichiers log
                              Il faut **SUPPRIMER** le cache lors des mises à jour, sinon la mise à jour n'est pas correcte !
    
        vendor                Le code de symfony
        bin/console           L'application ligne de commande de symfony, utile lors des mises à jour ou des rechargements de base de donnée
    
        reprise               Le code permettant de recharger la base de données, soit pour initialiser gramc, soit pour installer une copie de la production (pour test et debug par exemple)

Comment modifier le code ?
----
Editer dans:
  - `src`
  - `templates`
  - `public`
  - `mesocentres/*/src`
  - `mesocentres/*/templates`
  - `mesocentres/*/public`
- Pour comprendre ce qui se passe, on peut utiliser la fonction debugMessage du service ServiceJournal
        La sortie se trouve dans le journal (Ecrans d'administration)
- OU
        Les logs symfony (var/log)
- OU
        Les logs apache
- Pour savoir quel contrôleur est appelé, quel fichier twig etc., regarder le bandeau de Symfony en bas de page (mode Debug seulement)
- Aide au déboguage:
  ~~~
  mkdir tools/phpstan
  php composer.phar require --working-dir=tools/phpstan --dev phpstan/phpstan
  php tools/phpstan/vendor/bin/phpstan.phar analyze -c tools/phpstan-config/config.neon -a ./vendor/autoload.php --level 1  src
  ~~~
- Attention au Coding Style:
  ~~~~
  mkdir -p tools/php-cs-fixer
  php composer.phar require --working-dir=tools/php-cs-fixer friendsofphp/php-cs-fixer
  tools/php-cs-fixer/vendor/bin/php-cs-fixer fix src
  ~~~~

### Comment modifier le formulaire principal ?

Pour modifier le formulaire principal (par exemple ajouter un champ) il faut intervenir dans les fichiers suivants:
- `src/Entity/Version.php` (l'entité correspondant à la table de la base de données, la structure est commune à tous les mésocentres).
- `mesocentres/*/src/Controller/VersionModifController.php` (le formulaire proprement dit, spécifique à chaque mésocentre)
- `mesocentres/*/templates/version/modifier_proset_sess*` (le fichier twig permettant d'afficher le formulaire. Ce fichier est découpé en plusieurs parties pour améliorer la visibilité)
- `mesocentres/*/templates/projet/consulter_projet.html.twig` (l'affichage des données de version)

Après avoir modifié l'entité Version il convient de mettre à jour la base de données par: `bin/console/doctrine:schema:update`

## Pour aller plus loin....

Le fichier `documentation.odt` contient toute la documentation de gramc3, et `documentation-dev.odt` est quant à lui centré sur la structure du code, à des fins de développement
