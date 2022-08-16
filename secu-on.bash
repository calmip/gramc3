#! /bin/bash

# Faire tourner ce script juste avant mise à jour de symfony
# Passe en +w pour tout le monde les fichiers utiles pour la mise à jour:
#
# - composer.json
# - composer.lock
# - vendor
# - config

for f in symfony.lock composer.lock
do
    [ -r $f ] && chmod a-w $f
done

chmod -R a-w composer.json config public/index.php vendor



