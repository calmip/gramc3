#! /bin/bash

# Faire tourner ce script juste après mise à jour de symfony
# Passe en -w pour tout le monde les fichiers installés par symfony:
#
# - composer.json
# - composer.lock
# - vendor
# - config

chmod -R a+rwX composer.json composer.lock symfony.lock config vendor



