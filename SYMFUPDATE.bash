#! /bin/bash

#
# Met à jour symfony pour gramc3
#

#
# Usage:
#
# cd dans le répertoire où est installé gramc3  
# bash ./UPDATE.bash
#

# Recherche  composer.json
if [ ! -f ./composer.json ]; then echo "Je ne trouve pas composer.json." && exit 1; fi

# Supprime toutes les sécurités dans le répertoire courant
REP=$( basename $(pwd -P) )
DIR=$( dirname $(pwd -P) )
( cd $DIR && chmod -R a+rwX $REP)

echo Permissions OK
echo Mise à jour de symfony en utilisant composer

sudo -u www-data composer update -n

# Remet les permissions correctes dans le répertoire courant
( cd $DIR && chmod -R go-w $REP )
chmod 400 .env.local

echo fini




