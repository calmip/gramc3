
# INSTALLATION sur une Debian ou dérivés

**Note** - Cette documentation est validée sur Debian 10 Buster

Installations de paquets
-----

- fonctionne en php 7.4, validé avec mariadb 10.3

- Installer apache/php 7.4 comme expliqué ici: https://sys-admin.fr/installation-php-7-4-sur-debian/
- Modules php:
```
apt-get install apache2 libapache2-mod-php php-intl php-cli php-common php-intl php-json php-opcache php-readline php-xml  php7.4-mysql php7.4-gd php7.4-intl
```
- Maria-db:
```
apt-get install mariadb-client mariadb-server
```
- Image magick, polices et autres (pour les graphiques de consommation et la conversion html vers pdf):
```
apt-get install imagemagick zip unzip
apt-get install xfonts-75dpi xfonts-base xfonts-utils x11-common libfontenc1 xfonts-encodings
```
- Installer `wkhtmltopd` depuis https://wkhtmltopdf.org (disponible en .deb)

configuration apache2:
----
*Il est important que gramc3 ne ne soit pas à l'URL /, sinon on aura du mal à configurer shibboleth. Le plus "simple" est alors de:*

- Activer le module rewrite d'Apache

- Laisser `DocumentRoot` sur `/var/www/html`

- Créer un lien symbolique `gramc3` sur le répertoire `public`

- Utiliser la commande suivante pour générer un fichier .htaccess:

  ```
  composer.phar remove symfony/apache-pack
  composer.phar require symfony/apache-pack
  ```

- Ajouter au fichier /etc/apache2/envvars la ligne suivante:

  ```
  export BASE="/gramc3"
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

  Les sous-répertoires var *ne doivent* pas être exportés par apache (cf. ci-dessus)

~~~~
  mkdir var
  chown www-data.www-data var
~~~~

- doit être accessible en écriture par www-data

Configuration, personnalisation:
----

### Fichier services.yaml:

```
cd config
cp services.yaml.dist services.yaml
```

Editer le fichier et paramétrer l'application:

- Le nom du mésocentre, quelques url, etc.
- Les types de projets supportés (1,2,3)
- Et pour finir les idp "préférés" (dépend des établissements à proximité du mésocentre)

### Fichier index.php:

```
cd public
cp index.php.dist index.php
```

Editez ce fichier et éventuellement commentez ou décommentez quelques lignes suivant que vous êtes derrière un reverse proxy ou pas.

### Fichier adresses.txt:

Ce fichier est propre à gramc3, il est utilisé uniquement en mode "développement". Il répertorie les adresses IP à partir desquelles il est possible d'utiliser gramc2.

~~~~
cd config
cp adresses.txt.dist adresses.txt
~~~~

Editer le fichier et introduire les adresses IP de vos postes de développement

### Fichier env.local:

~~~~
cp .env.local.dist .env.local
~~~~

Editer le fichier et inscrivez les paramètres demandés (identifiants de connexion à la base de données notamment)

**ATTENTION**: Ce fichier contient des informations sensibles, il doit être protégé:

```
chown www-data .env.local
chmod 400 .env.local
```

#### Vérification:

Pour vérifier que tout est correctement configuré, vous pouvez utiliser la commande ci-dessous, elle doit se dérouler correctement et envoyer un mail à `MAILER_RECIPIENT`:

```
bin/console app:send-a-mail toto@titi.fr
```

- Si vous mettez **`dev`** dans `APP_ENV`, vous êtes en mode `dev`, ce qui signfie entre autres que tout le monde peut se connecter sans authentification. Il faut donc protéger l'application par un autre système, en l'occurrence les adresses IP doivent être déclarées dans le fichier `adresses.txt`. Par ailleurs, toutes les notifications seront automatiquement envoyées à l'adresse mail déclarée dans  `MAILER_RECIPIENT` , qui recevra donc un mail de tests si vous appelez la commande ci-dessus.

- Si vous mettez **`prod`** dans APP_ENV, vous êtes en mode `prod`, ce qui signfie que vous ne pourrez utiliser l'application que si vous vous connectez réellement, donc si shibboleth est configurée. Par ailleurs les notifications sont *réellement* envoyées aux adresses figurant dans la base de données, la commande ci-dessus ne fonctionnera donc pas.


Base de données:
----

**Installation d'une base de donnees déjà en exploitation sur une instance de développement:**
~~~~
cd reprise
sudo -u www-data ./drop-db-recharge-pour-debug un-dump-de-la-bd.sql
~~~~

**Installation d'une base de données vide sur une instance de développement:**

~~~~
cd reprise
sudo -u www-data ./drop-db-recharge-pour-debug.php gramc2.sql.dist
~~~~

Dans les deux cas, un mail sera envoyé à l'adresse toto@exemple.com, cela permet de vérifier que le mail est bien envoyé à l'adresse $MAILER_RECIPIENT et de s'assurer que les utilisateurs ne recevront pas de mails lors des essais...

Configuration du mail:
----

**Le serveur doit être capable d'envoyer des mails:**
  - Par exemple `postfix` fonctionne très bien avec gramc
  - Ou encore `ssmtp` (les mails sont envoyés, jamais reçus)

### Configurer le mail pour une version de développement:

Avec une version de développement vous ne voudrez pas que l'application envoie des notifications à de vrais utilisateurs. C'est surtout vrai si vous avez chargé la version de dev avec une vraie base de données.

Pour éviter cela, n'oubliez pas la variable `MAILER_RECIPIENT` du fichier `.env.local`

Fin de la configuration:
----

- Se connecter à gramc avec un navigateur: cliquer sur `connection (dbg)`
  **ATTENTION**: `app_dev.php` doit être activée dans la configuration apache ci-dessus
- Utilisateur = `admin`

Il reste maintenant à:
- Ajouter des utilisateurs administrateurs
- Ajouter des utilisateurs experts et les connecter avec des thématiques
- Ajouter des utilisateurs présidents
- Ajouter des laboratoires
- Configurer shibboleth (voir ci-dessous)


- Dans la configuration apache, remplacer app_dev.php par app.php (cf. ci-dessus)
- On peut maintenant supprimer le user admin admin et le laboratoire GRAMC qui ne servent plus à rien

CONFIGURATION DE SHIBBOLETH:
----

- Installer quelques paquets supplémentaires:
~~~~
  apt-get install libapache2-mod-shib2 liblog4shib1v5 libshibsp-plugins libshibsp7 shibboleth-sp2-common shibboleth-sp2-utils
~~~~
- Configuration apache Ajouter dans la section VirtualHost de gramc2:
  ~~~~
  # important pour pouvoir utiliser d'autres techniques d'authentification (cf. pour git)
  ShibCompatValidUser On

  <Location "url-de-gramc/login">
       AuthType shibboleth
       ShibRequestSetting requireSession 1
       ShibRequestSetting applicationId default
       Require shibboleth
  </Location>
  ~~~~
- Redémarrer apache:
  ~~~~
  systemctl restart apache2
  ~~~~

OU EST LE CODE DE GRAMC ?
=========================
gramc2 est une application symfony, il repose donc sur le patron de conception MVC. Les principaux répertoires sont les suivants:

        src                   Le code php de l'application
        src/Controller        Tous les contrôleurs (les points d'entrée de chaque requête)
        src/Entity            Les objets permettant de communiquer avec la base de données en utilisant l'ORM Doctrine (un objet par table, un membre par champ)
        src/Form              Les formulaires (correspondent aux entités)
        src/Repository        quelques fonctions non standards d'accès à la base de données
        src/GramcServices     L'essentiel du code, implémenté en "services symfony" cf. https://symfony.com/doc/current/service_container.html
        src/GramcServices/Workflow  Les workflows de l'application (changement d'états des objets Projet, Version, Rallonge)
        src/Utils             Des trucs bien utiles
        src/DataFixtures      Mise à jour de la base de données lors des changements de version
        src/XXX                         Le code php "extérieur" utilisé par gramc2


        templates             Les vues, c'est-à-dire tous les affichages, écrits en html/twig
        templates/default     Les vues de la page d'accueil, de l'aide, etc., et les bouts de code utilisés partout (base.html.twig etc)
        templates/xxx         Les vues correspondant aux principaux écrans - Voir dans les controleurs pour savoir quelle vue est utilisée à quel moment
    
        public                Accessible directement par apache2
        web/icones            Les icones (png)
        web/js                Le code javascript
        web/rm                Les modèles de rapports d'activité à télécharger
    
        var                   le cache, les sessions php, les fichiers log
                              Il faut **SUPPRIMER** le cache lors des mises à jour, sinon la mise à jour n'est pas correcte !
    
        vendor                          Le code de symfony
        bin/console                     L'application ligne de commande de symfony, utile lors des mises à jour ou des rechargements de base de donnée
    
        reprise                         Le code permettant de recharger la base de données, soit pour initialiser gramc, soit pour installer une copie de la production (pour test et debug par exemple)

COMMENT MODIFIER LE CODE ?
----
- Editer dans:
  - `src`
  - `app/Resources/views`
  - `web`
- Pour comprendre ce qui se passe:
        `Functions::debugMessage(__METHOD__ .":" . __LINE__ . " bla bla " . $variable );`
        La sortie se trouve dans le journal (Ecrans d'administration)
- OU
        Les logs apache2
- OU
        Les logs symfony: dans var/log
- Pour savoir quel contrôleur est appelé (et accéder au code), regarder le bandeau de Symfony en bas de page (mode Debug seulement)
