<?php

    namespace App\DataFixtures\ORM;

    use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
    use Doctrine\Persistence\ObjectManager;

    use App\Entity\Laboratoire;
    use App\Entity\Individu;
    use App\Entity\Projet;
    use App\Entity\Version;
    use App\Entity\CollaborateurVersion;
    use App\Entity\Session;
    use App\Entity\Thematique;

    use App\GramcServices\GramcDate;
    use App\GramcServices\ServiceSessions;

    const NOM_FICHIER_LABOS='liste_labo_criann.csv';
    const NOM_FICHIER_USERS='Individus.csv';
    const NOM_FICHIER_THEMATIQUES='Thematique.csv';
    const NOM_FICHIER_METATHEMATIQUES='Meta_thematique.csv';
    const NOM_FICHIER_PROJETS='Projets.csv';
    const NOM_FICHIER_LOGIN='coll_login.csv';
    const NOM_FICHIER_RESP='resp.csv';

    // Reprise des données fournies par le Criann
    // On charge plusieurs tables à partir de fichiers csv
    // Les fichiers csv sont déposés à la racine du projet
    class RepriseCriann implements ORMFixtureInterface
    {
        private $sd;
        private $ss;

        public function __construct(GramcDate $sd, ServiceSessions $ss)
        {
            $this -> sd = $sd;
            $this -> ss = $ss;
        }

        public function load(ObjectManager $em)
        {
            // On commence par créer la session 21B
            $this->newSession($em);
            $this->loadLabos($em);
            $this->loadIndividu($em);
            $this->loadThematique($em);
            $this->loadProjet($em);
        }

        private function newSession(ObjectManager $em)
        {
            $annee_base     = 2002;
            $annee_courante = 2021; // todo very dirty houlala !

            // Création d'autant de sessions A que nécessaire compte tenu des projets à reprendre
            // On ne crée pas de session B
            for ($a=$annee_base; $a <= $annee_courante; $a++) {
                $a_str = strval($a);
                $session = new Session();
                $session->setEtatSession(($a == $annee_courante) ? 5 : 9);    // toutes terminées sauf celle de cette année
                $session->setCommGlobal('Chargement initial des données');
                $session->setTypeSession(false); // session de type 'A'
                $session->setDateDebutSession(new \DateTime($a_str.'-11-01'));
                $session->setDateFinSession(new \DateTime($a_str.'-11-30'));
                $session->setHParAnnee(50000000);
                $id_session = substr($a_str, 2, 2) . 'A';
                $session->setIdSession($id_session);
                $em->persist($session);
                $em->flush();
            }
        }

        // Remplissage des tables Projet, Version, CollaborateurVersion etc.
        // On part des fichiers:
        //    - Projets.csv: id_projet, demandées, attribuées, Titre, thématique
        //    - CollaborateursVersion: id_projet, mail, responsable (0/1)

        private function loadProjet(ObjectManager $em)
        {
            // Lecture du fichier Projets.csv - sera mis dans un tableau de tableaux
            // On se limite aux projets dont l'id commence par 20
            //
            $csv_projets = [];
            $row = 0;
            $num = 0;
            if (($handle = fopen(NOM_FICHIER_PROJETS, "r")) !== false) {
                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    $row += 1;
                    $num = count($data);
                    if ($num != 5) {
                        error_log("ERREUR - ".NOM_FICHIER_PROJETS." Ligne $row - $num champs", 0);
                        continue;
                    }
                    if (substr($data[0], 0, 2) != "20") {
                        continue;
                    }
                    $csv_projets[] = $data;
                }
            } else {
                error_log("ne peut pas ouvrir " . NOM_FICHIER_PROJETS, 0);
                return;
            }

            // Lecture du fichier coll_login.csv - sera mis dans un tableau de tableaux de tableaux
            // Le tableau de plus haut niveau est indexé par id_projet
            // On se limite aussi aux projets dont l'id commence par 20
            // Si plusieurs utilisateurs ont le même mail on ignore à partir du second
            //
            $ver_mails = [];
            $csv_collver = [];
            $row = 0;
            $num = 0;
            if (($handle = fopen(NOM_FICHIER_LOGIN, "r")) !== false) {
                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    $row += 1;
                    $num = count($data);
                    if ($num != 2) {
                        error_log("ERREUR - ".NOM_FICHIER_LOGIN." Ligne $row - $num champs", 0);
                        continue;
                    }
                    if (substr($data[0], 0, 2) != "20") {
                        continue;
                    }

                    $ver = $data[0];
                    $mail = $data[1];
                    $ver_mail = $ver . "_" . $mail;
                    if (in_array($ver_mail, $ver_mails)) {
                        error_log("ATTENTION - fichier " . NOM_FICHIER_LOGIN . " ver_mail dupliqué: $ver_mail", 0);
                        continue;
                    } else {
                        $ver_mails[] = $ver_mail;
                    }

                    $cv = [];
                    $cv['mail'] = $data[1];
                    $cv['login']= true;
                    $cv['resp'] = false;
                    if (!isset($csv_collver[$ver])) {
                        $csv_collver[$ver] = [];
                    }
                    $csv_collver[$ver][] = $cv;
                }
            } else {
                error_log("ne peut pas ouvrir " . NOM_FICHIER_LOGIN, 0);
                return;
            }

            // Lecture du fichier resp.csv - sera ajouté à $csv_collver:
            //      Si le resp est déjà dans $csv_collver on met à true l'élément resp
            //      Sinon on crée un nouvel enregistrement avec resp true et login false
            //
            $row = 0;
            $num = 0;
            if (($handle = fopen(NOM_FICHIER_RESP, "r")) !== false) {
                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    $row += 1;
                    $num = count($data);
                    if ($num != 2) {
                        error_log("ERREUR - ".NOM_FICHIER_RESP." Ligne $row - $num champs");
                        continue;
                    }
                    if (substr($data[0], 0, 2) != "20") {
                        continue;
                    }
                    $ver = $data[0];
                    $mail= $data[1];

                    // Le projet a au moins un compte. Le resp en fait-il partie ?
                    if (isset($csv_collver[$ver])) {
                        $collver_data = &$csv_collver[$ver];

                        // on cherche un élément avec le mail correct !
                        $found = false;

                        //print_r($ver);echo "\n";
                        //print_r ($collver_data);
                        foreach ($collver_data as &$cv) {
                            if ($cv['mail'] == $mail) {
                                $cv['resp'] = true;
                                $found = true;
                                break;
                            }
                        }

                        // Le responsable n'a pas de compte
                        if (! $found) {
                            $cv1 = [];
                            $cv1['mail'] = $mail;
                            $cv1['login'] = false;
                            $cv1['resp'] = true;
                            $collver_data[] = $cv1;
                        }

                        // Le projet n'a pas de compte, seulement un responsable
                    // Je suppose que le responsable a un compte
                    } else {
                        $collver_data = [];
                        $cv = [];
                        $cv['mail'] = $mail;
                        $cv['login'] = true;
                        $cv['resp'] = true;
                        $collver_data[] = $cv;
                        $csv_collver[$ver] = $collver_data;
                    }
                }
            } else {
                error_log("ne peut pas ouvrir " . NOM_FICHIER_RESP, 0);
                return;
            }

            // On vérifie que pour chaque projet on a 1 et 1 seul responsable, et au moins 1 login !
            foreach ($csv_collver as $ver => $collver_data) {
                $resp = 0;
                $login= 0;
                foreach ($collver_data as $cv) {
                    if ($cv['resp']) {
                        $resp  += 1;
                    }
                    if ($cv['login']) {
                        $login += 1;
                    }
                }
                if ($resp != 1) {
                    error_log("ATTENTION - PROJET $ver: $resp RESPONSABLE", 0);
                }
                if ($login == 0) {
                    error_log("ATTENTION - PROJET $ver: PAS DE LOGIN", 0);
                }
            }


            // Session courante
            $session = $this->ss->getSessionCourante();

            // Toutes les sessions dans un tableau indexé par l'id_session
            $sessions = [];
            foreach ($em -> getRepository('App:Session')->findAll() as $s) {
                $sessions[$s->getIdSession()] = $s;
            }

            // Tableau thematiques, indexé par le libellé
            $thematiques = [];
            foreach ($em->getRepository('App:Thematique')->findAll() as $t) {
                $thematiques[ $t->getLibelleThematique() ] = $t;
            }

            // Tables projet/version/collaborateurVersion
            $repos_individu = $em->getRepository('App:individu');
            foreach ($csv_projets as $data) {
                $prj_erreur = false;     // Erreur sur ce projet
                $id_projet = $data[0];

                $projet = new Projet(1);
                $projet->setIdProjet($data[0]);
                $projet->setEtatProjet(41);     // Renouvelable
                $em->persist($projet);

                $prj_annee = intval(substr($id_projet, 0, 4));
                $annee_base     = 2002;
                $annee_courante = 2021; // todo - Hoooooo !

                // On crée une version par session depuis la création du projet
                for ($a=$prj_annee; $a <= $annee_courante; $a++) {
                    $id_session = substr(strval($a), 2, 2) . 'A';
                    $sess = $sessions[$id_session];
                    $id_version= $id_session . $id_projet;
                    error_log("Création de la version $id_version", 0);
                    $version = new Version();
                    $version->setIdVersion($id_version);
                    $version->setProjet($projet);
                    $version->setSession($sess);
                    $version->setEtatVersion(($a == $annee_courante) ? 5 : 9);    // Etat = ACTIF pour annee courante, terminé sinon
                    $version->setPrjTitre($data[3]);
                    $version->setPrjThematique($thematiques[$data[4]]);
                    $version->setDemHeures(intval($data[1]));
                    $version->setAttrHeures(intval($data[2]));

                    // Justification du renouvellement
                    if ($a > $prj_annee) {
                        $version->setPrjJustifRenouv("Renouvellement demandé et argumenté ici");
                    }
                    $em->persist($version);
                    $em->flush();

                    // Les collaborateurs, dont le responsable
                    $nom_laboratoire="";
                    foreach ($csv_collver[$id_projet] as $cv) {
                        $coll = $repos_individu -> findOneBy([ 'mail' => $cv['mail'] ]);
                        if ($coll == null) {
                            error_log("ATTENTION - " . $cv['mail'] . " PAS TROUVE DANS Individus !", 0);
                            $prj_erreur = true;
                            break;
                        }
                        $collver = new CollaborateurVersion();
                        $collver->setCollaborateur($coll);
                        $collver->setLabo($coll->getLabo());
                        $collver->setStatut($coll->getStatut());
                        $collver->setEtab($coll->getEtab());
                        $collver->setResponsable($cv['resp']);
                        if ($cv['resp'] == true) {
                            $laboratoire = $coll->getLabo();
                            if ($laboratoire != null) {
                                $nom_laboratoire = $laboratoire->getAcroLabo() . ' - ' .$laboratoire->getNomLabo();
                            }
                        }
                        $collver->setLogin($cv['login']);
                        $collver->setVersion($version);
                        $em->persist($collver);
                    }
                    if ($prj_erreur) {
                        error_log("ERREUR - Projet $id_projet - Erreur rencontrée, projet incomplet", 0);
                        break;
                    }

                    // Garder le nom du laboratoire
                    // NOTE - Un labo peut disparaître ou changer de nom, du coup on stoque son nom
                    $version->setPrjLLabo($nom_laboratoire);
                    $em->persist($version);

                    $em->flush();
                }
            }
        }

        // Remplissage de la table Laboratoire à partir d'un fichier csv
        // Le csv a ce format: ID;Sigle;Nom
        // ID sera déposé dans numerolabo, sigle acrolabo, Nom nomlabo
        private function loadLabos(ObjectManager $em)
        {
            // Lecture du fichier labos csv - sera mis dans un tableau de tableaux
            $csv_labos = [];
            $row = 0;
            $num = 0;
            if (($handle = fopen(NOM_FICHIER_LABOS, "r")) !== false) {
                while (($data = fgetcsv($handle, 0, ";")) !== false) {
                    $row += 1;
                    $num = count($data);
                    if ($num != 3) {
                        error_log("ERREUR - " . NOM_FICHIER_LABOS . " Ligne $row - $num champs", 0);
                        continue;
                    }
                    if ($data[0] === 'ID') {
                        continue;
                    }
                    // bidouille: CRIHAN a comme ID 1, or 1 est réservé pour AUTRES !
                    if ($data[0] === '1') {
                        $data[0] = '2';
                    }
                    $csv_labos[] = $data;
                }
            } else {
                error_log("ne peut pas ouvrir " . NOM_FICHIER_LABOS, 0);
                return;
            }

            // Remplissage de la table
            foreach ($csv_labos as $data) {
                $labo = new Laboratoire();
                $labo -> setNumeroLabo(intval($data[0]))
                      -> setAcroLabo($data[1])
                      -> setNomLabo($data[2]);
                $em -> persist($labo);
            }
            $em -> flush();
        }

        // Remplissage de la table Individu à partir d'un fichier csv
        // Le csv a ce format: Nom, Prénom, mail, labo
        private function loadIndividu(ObjectManager $em)
        {
            $sd = $this->sd;

            // Lecture du fichier csv - sera mis dans un tableau de tableaux
            $mails = [];    // Pour garantir l'unicité du mail !
            $csv_indivs = [];
            $row = 0;
            if (($handle = fopen(NOM_FICHIER_USERS, "r")) !== false) {
                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    $row += 1;
                    $num = count($data);
                    if ($num != 4) {
                        error_log("ERREUR - ".NOM_FICHIER_USERS." Ligne $row - $num champs", 0);
                        continue;
                    }
                    if ($data[0] === 'Nom') {
                        continue;
                    }

                    // Si le mail est dupliqué, on ignore !
                    $mail = $data[2];
                    if (in_array($mail, $mails)) {
                        error_log("ATTENTION - fichier " . NOM_FICHIER_USERS . " mail dupliqué: $mail", 0);
                        continue;
                    } else {
                        $mails[] = $mail;
                    }
                    $csv_indivs[] = $data;
                }
            } else {
                error_log("ne peut pas ouvrir " . NOM_FICHIER_USERS, 0);
                return;
            }


            // Lecture de la table Laboratoire
            $laboratoires = $em->getRepository('App:Laboratoire')->findAll();

            // Index avec AcroLabo comme point d'entrée
            $ind_labos = [];
            foreach ($laboratoires as $l) {
                $acro = $l->getAcroLabo();
                if (isset($ind_labos[$acro])) {
                    echo "ERREUR - $acro est présent pluieurs fois dans la table laboratoires !\n";
                    exit;
                }
                $ind_labos[$acro] = $l;
            }

            foreach ($csv_indivs as $data) {
                $ind = new Individu();
                $ind -> setNom($data[0])
                     -> setPrenom($data[1])
                     -> setMail($data[2])
                     -> setCreationStamp(new \DateTime());
                $acro = $data[3];
                if (isset($ind_labos[$acro])) {
                    $ind -> setLabo($ind_labos[$acro]);
                }

                $em -> persist($ind);
            }
            $em -> flush();
        }

        // Remplissage de la table Thematique à partir d'un fichier csv
        // Le csv a ce format: "id_thematique","id_meta_thematique","libelle_thematique"
        private function loadThematique(ObjectManager $em)
        {
            $sd = $this->sd;

            // On vide la table
            $this-> truncateTables($em, ['thematique']);

            // Lecture du fichier csv - sera mis dans un tableau de tableaux
            $csv_thema = [];
            $row = 0;
            if (($handle = fopen(NOM_FICHIER_THEMATIQUES, "r")) !== false) {
                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    $row += 1;
                    $num = count($data);
                    if ($num != 3) {
                        error_log("ERREUR - ".NOM_FICHIER_THEMATIQUES." Ligne $row - $num champs");
                        continue;
                    }
                    if ($data[0] === 'id_thematique') {
                        continue;
                    }
                    $csv_thema[] = $data;
                }
            } else {
                error_log("ne peut pas ouvrir " . NOM_FICHIER_THEMATIQUES, 0);
                return;
            }


            // Remplissage de la table
            foreach ($csv_thema as $data) {
                $thema = new Thematique();
                $thema -> setLibelleThematique($data[2]);
                $em -> persist($thema);
            }
            $em -> flush();
        }

        // Vider une table d'après https://stackoverflow.com/questions/8526534/how-to-truncate-a-table-using-doctrine
        public function truncateTables(ObjectManager $em, $tableNames = array(), $cascade = false)
        {
            $connection = $em->getConnection();
            $platform = $connection->getDatabasePlatform();
            $connection->executeQuery('SET FOREIGN_KEY_CHECKS = 0;');
            foreach ($tableNames as $name) {
                $connection->executeUpdate($platform->getTruncateTableSQL($name, $cascade));
            }
            $connection->executeQuery('SET FOREIGN_KEY_CHECKS = 1;');
        }
    }
