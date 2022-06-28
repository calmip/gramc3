#! /bin/bash

# Faire tourner ce script juste après mise à jour de symfony
# Passe en -w pour tout le monde les fichiers installés par symfony:
#
# - composer.json
# - composer.lock
# - vendor
# - config

for f in symfony.lock composer.lock
do
    [ -r $f ] && chmod a+rwX $f
done 

chmod -R a+rwX composer.json config public/index.php vendor

