<?php

    namespace App\DataFixtures\ORM;

    use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
    use Doctrine\Persistence\ObjectManager;

    use App\Entity\Laboratoire;
    use App\Entity\Individu;
    use App\GramcServices\GramcDate;

    const NOM_FICHIER_LABOS='liste_labo_criann.csv';
    const NOM_FICHIER_USERS='liste_des_personnes_comptes.csv';

    // Remplissage de la table Laboratoire à partir d'un fichier csv
    // Le csv a ce format: ID;Sigle;Nom
    // ID sera déposé dans numerolabo, sigle acrolabo, Nom nomlabo
    class RepriseCriann implements ORMFixtureInterface
    {
        private $sd;
        
        public function __construct (GramcDate $sd) {
            $this -> sd = $sd;
        }
        
        public function load(ObjectManager $em)
        {
            $this->loadLabos($em);
            $this->loadIndividus($em);
        }
        
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
                        echo "ERREUR - Ligne $row - $num champs\n";
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
        
        private function loadIndividus(ObjectManager $em)
        {
            $sd = $this->sd;
            
            // Lecture du fichier csv - sera mis dans un tableau de tableaux
            $csv_indivs = [];
            $row = 0;
            if (($handle = fopen(NOM_FICHIER_USERS, "r")) !== false) {
                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    $row += 1;
                    $num = count($data);
                    if ($num != 4) {
                        echo "ERREUR - Ligne $row - $num champs\n";
                        continue;
                    }
                    if ($data[0] === 'Nom') {
                        continue;
                    }
                    $csv_indivs[] = $data;
                }
            }
            
            // Lecture de la table Laboratoire
            $laboratoires = $em->getRepository('App:Laboratoire')->findAll();
            
            // Index avec AcroLabo comme point d'entrée
            $ind_labos = [];
            foreach ($laboratoires as $l) {
                $acro = $l->getAcroLabo();
                if ( isset ($ind_labos[$acro])) {
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
                if (isset($ind_labos[$acro]))
                {
                    $ind -> setLabo($ind_labos[$acro]);
                }
                
                $em -> persist($ind);
            }
            $em -> flush();
        }

    }
