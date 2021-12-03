#! /bin/bash

# Faire tourner ce script juste avant mise à jour de symfony
# Passe en +w pour tout le monde les fichiers utiles pour la mise à jour:
#
# - composer.json
# - composer.lock
# - vendor
# - config

chmod -R a-w composer.json composer.lock symfony.lock config public/index.php vendor



