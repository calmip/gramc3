
# Importation de données en masse pour le CRIANN

## Fichiers csv:

Les fichiers csv suivants sont requis et doivent se trouver dans le dossier racine du projet:

- coll_login.csv
- Individus.csv
- liste_labo_criann.csv
- Projets.csv
- resp.csv
- Thematique.csv

## Code:

Le fichier `reprise/criann/RepriseCriann.php` doit être copié dans le répertoire `src/DataFixtures/ORM` (un lien symbolique peut suffire)

## Exécution du code:

Pour importer les données il suffit de faire:

```
cd reprise
./reload-db gramc2.sql.dist
```

La base de données de distribution sera relue, le schéma sera modifié pour correspondre à la version de gramc, puis le code d'importation de données sera exécuté.

**Important:** Lorsque l'importation aura été effectuée, ne pas oublier de supprimer le fichier ou le lien du répertoire `src/DataFixtures/ORM`

