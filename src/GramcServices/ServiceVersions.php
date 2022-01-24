<?php

/**
 * This file is part of GRAMC (Computing Ressource Granting Software)
 * GRAMC stands for : Gestion des Ressources et de leurs Attributions pour Mésocentre de Calcul
 *
 * GRAMC is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 *  GRAMC is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with GRAMC.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  authors : Emmanuel Courcelle - C.N.R.S. - UMS 3667 - CALMIP
 *            Nicolas Renon - Université Paul Sabatier - CALMIP
 **/

namespace App\GramcServices;

use App\Utils\Etat;
use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Session;
use App\Entity\Individu;
use App\Entity\Formation;
use App\Entity\User;

use App\Utils\GramcDate;
use App\Utils\Functions;

use Doctrine\ORM\EntityManagerInterface;

class ServiceVersions
{
    public function __construct(private $attrib_seuil_a, private $prj_prefix, private $fig_directory, private $signature_directory, private ServiceJournal $sj, private EntityManagerInterface $em)
    {
        $this->attrib_seuil_a      = intval($this->attrib_seuil_a);
    }

    /*********
     * Utilisé seulement en session B
     * renvoie true si l'attribution en A est supérieure à ATTRIB_SEUIL_A et la demande en B supérieure à attr_heures_a / 2
     *
     * param  id_version, $attr_heures_a, $attr_heures_b
     * return true/false
     *
     **************************/
    public function is_demande_toomuch($attr_heures_a, $dem_heures_b)
    {
        // Si demande en A = 0, no pb (il s'agit d'un nouveau projet apparu en B)
        if ($attr_heures_a==0) {
            return false;
        }

        // Si demande en B supérieure à attribution en A, pb
        if ($dem_heures_b > $attr_heures_a) {
            return true;
        }

        // Si attribution inférieure au seuil, la somme ne doit pas dépasser 1,5 * seuil
        if ($attr_heures_a < $this->attrib_seuil_a) {
            if (floatval($dem_heures_b + $attr_heures_a) > $this->attrib_seuil_a * 1.5) {
                return true;
            } else {
                return false;
            }
        } else {
            if (intval($dem_heures_b) > (intval($attr_heures_a)/2)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /************************************
     *
     * informations à propos d'une image liée à une version
     *
     * params: $filename Nom du fichier image
     *         $version  Version associée
     *
     * return: Plein d'informations
     *
     ***************************/
    public function imageProperties($filename, Version $version)
    {
        $full_filename = $this->imagePath($filename, $version);
        if (file_exists($full_filename) && is_file($full_filename)) {
            $imageinfo  =   [];
            $my_image_info = getimagesize($full_filename, $imageinfo);
            return [
        'contents'  =>  base64_encode(file_get_contents($full_filename)),
        'width'     =>  $my_image_info[0],
        'height'    =>  $my_image_info[1],
        'balise'    =>  $my_image_info[2],
        'mime'      =>  $my_image_info['mime'],
        ];
        } else {
            return [];
        }
    }

    /************************************
     *
     * Renvoie le nom du fichier attaché, s'il existe, null sinon
     * params: $version  Version associée
     *
     * return: chemin vers fichier ou null
     *
     ***************************/
    public function getDocument(Version $version)
    {
        $document = $this->imageDir($version).'/document.pdf';
        if (file_exists($document) && is_file($document)) {
            return $document;
        } else {
            return null;
        }
    }

    /*************************
     * Calcule le nom de fichier de l'image
     *
     * param = $filename Nom du fichier, sans le répertoire ni l'extension
     * 		   $version  Version associée
     *
     * return = Le chemin complet (si le fichier existe)
     *          Le chemin avec répertoire mais sans extension sinon
     *          TODO - Pas clair du tout !
     *
     ************************************/
    public function imagePath($filename, Version $version)
    {
        $full_filename = $this->imageDir($version) .'/'.  $filename;

        if (file_exists($full_filename . ".png") && is_file($full_filename . ".png")) {
            $full_filename  =  $full_filename. ".png";
        } elseif (file_exists($full_filename . ".jpeg") && is_file($full_filename . ".jpeg")) {
            $full_filename  =  $full_filename. ".jpeg";
        }
        return $full_filename;
    }

    /*******************************
     * Crée si besoin le répertoire pour les fichiers d'image
     *
     * param = $version  La version associée
     *
     * return = Le chemin complet vers le répertoire
     *
     *******************************************/
    public function imageDir(Version $version)
    {
        $dir = $this->fig_directory;
        if (! is_dir($dir)) {
            if (file_exists($dir) && is_file($dir)) {
                unlink($dir);
            }
            mkdir($dir);
            $this->sj->warningMessage("fig_directory " . $dir . " créé !");
        }
        
        $dir  .= '/'. $version->getProjet()->getIdProjet();
        if (! is_dir($dir)) {
            if (file_exists($dir) && is_file($dir)) {
                unlink($dir);
            }
            mkdir($dir);
        }

        $dir  .= '/'. $version->getIdVersion();
        if (! is_dir($dir)) {
            if (file_exists($dir) && is_file($dir)) {
                unlink($dir);
            }
            mkdir($dir);
        }
        return $dir;
    }

    /**************************************
     * Changer le responsable d'une version
     **********************************************/
    public function changerResponsable(Version $version, Individu $new)
    {
        foreach ($version->getCollaborateurVersion() as $item) {
            $collaborateur = $item->getCollaborateur();
            if ($collaborateur == null) {
                $this->sj->errorMessage(__METHOD__ .":". __LINE__ . " collaborateur null pour CollaborateurVersion ". $item->getId());
                continue;
            }

            if ($collaborateur->isEqualTo($new)) {
                $item->setResponsable(true);
                $this->em->persist($item);
                $labo = $item->getLabo();
                if ($labo != null) {
                    $version->setPrjLLabo(Functions::string_conversion($labo->getAcroLabo()));
                } else {
                    $this->sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Le nouveau responsable " . $new . " ne fait partie d'aucun laboratoire");
                }
                $this->setLaboResponsable($version, $new);
                $this->em->persist($version);
            } elseif ($item->getResponsable() == true) {
                $item->setResponsable(false);
                $this->em->persist($item);
            }
        }
        $this->em->flush();
    }

    /********************************************
     * Trouver un collaborateur d'une version
     *
     * Renvoie soit null, soit le $cv correspondant à $individu
     * 
     **********************************************************/
    private function TrouverCollaborateur(Version $version, Individu $individu)
    {
        $filteredCollection = $version
                                ->getCollaborateurVersion()
                                ->filter(function($cv) use ($individu) {
                                    return $cv
                                            ->getCollaborateur()
                                            ->isEqualTo($individu);
                                    });

        // Normalement 0 ou 1 !
        if (count($filteredCollection) >= 1)
        {
            return $filteredCollection->first();
        } else {
            return null;
        }
    }

    /********************************************
     * Supprimer un collaborateur d'une version
     **********************************************************/
    public function supprimerCollaborateur(Version $version, Individu $individu)
    {
        $em = $this->em;
        $sj = $this->sj;
        
        $cv = $this->TrouverCollaborateur($version, $individu);
        $sj->debugMessage("ServiceVersion:supprimerCollaborateur $cv -> $individu supprimé");
        $em->remove($cv);
        $em->flush();
    }

    /*********************************************************
     * Synchroniser le flag Deleted d'un collaborateurVersion
     **********************************************************/
    public function syncDeleted( Version $version, Individu $individu, bool $delete)
    {
        $em = $this->em;
        $sj = $this->sj;
        
        $cv = $this->TrouverCollaborateur($version, $individu);
        if ($cv->getDeleted() != $delete) {
            $sj->debugMessage("ServiceVersion:syncDeleted !$delete => $delete");
            $cv -> setDeleted($delete);
            $em->persist($cv);
            $em->flush();
        }
    }

    // modifier login d'un collaborateur d'une version
    // Si le login passe à false, suppression du Loginname,
    // et suppression de la ligne correspondante si elle existe (mot de passe) dans la table user
    public function modifierLogin(Version $version, Individu $individu, $login=false, $clogin=false)
    {
        $em = $this->em;
        $sj = $this->sj;
        
        if ($clogin==null) $clogin=false;

        $cv = $this->TrouverCollaborateur($version, $individu);
        $cv->setLogin($login);
        $cv->setClogin($clogin);
        $this->em->persist($cv);
        $this->em->flush();

        /*
        if (! $login)
        {
            $loginname = $item->getLoginname();
            if (! empty($loginname)) {
                $item->setLoginname(null);
                $user = $em->getRepository(User::class)->findOneBy(['loginname' => $loginname]);
                if ($user != null) {
                    $em->remove($user);
                }
            }
        }*/
        
    }

    /*******
    * Retourne true si la version correspond à un Nouveau projet
    *
    *      - session A -> On vérifie que l'année de création est la même que l'année de la session
    *      - session B -> En plus on vérifie qu'il n'y a pas eu une version en session A
    *
    *****/
    public function isNouvelle(Version $version)
    {
        // Un projet test ne peut être renouvelé donc il est obligatoirement nouveau !
        if ($version->isProjetTest()) {
            return true;
        }

        $idVersion      = $version->getIdVersion();
        $anneeSession   = substr($idVersion, 0, 2);	// 19, 20 etc
        $typeSession    = substr($idVersion, 2, 1);   // A, B
        $anneeProjet    = substr($idVersion, -5, 2);  // 19, 20 etc qq soit le préfixe
        $numero         = substr($idVersion, -3, 3);  // 001, 002 etc.

        if ($anneeProjet != $anneeSession) {
            return false;
        } elseif ($typeSession == 'A') {
            return true;
        } else {
            $type_projet = $version->getProjet()->getTypeProjet();
            $idVersionA  = $anneeSession . 'A' . $this->prj_prefix[$type_projet] . $anneeProjet . $numero;

            if (0 < $this->em->getRepository(Version::class)->exists($idVersionA)) {
                return false; // Il y a une version précédente
            } else {
                return true; // Non il n'y en a pas donc on est bien sur une nouvelle version
            }
        }
    }

    public function isSigne(Version $version)
    {
        $dir = $this->signature_directory;
        if ($dir == null) {
            $this->sj->errorMessage("ServiceVersions:isSigne parameter signature_directory absent !");
            return false;
        }
        $file   =  $dir . '/' . $version->getSession()->getIdSession() . '/' . $version->getIdVersion() . '.pdf';
        if (file_exists($file) && ! is_dir($file)) {
            return true;
        } else {
            return false;
        }
    }

    /*****************
     * Retourne le chemin vers le fichier de signature correspondant à cette version
     *          null si pas de fichier de signature
     ****************/
    public function getSigne(Version $version)
    {
        $dir = $this->signature_directory;
        if ($dir == null) {
            //$sj->errorMessage("Version:isSigne parameter signature_directory absent !" );
            return null;
        }

        $file   =  $dir . '/' . $version->getSession()->getIdSession() . '/' . $version->getIdVersion() . '.pdf';
        if (file_exists($file) && ! is_dir($file)) {
            return $file;
        } else {
            return null;
        }
    }

    /*****************************
     * Retourne la taille du fichier de signature
     *****************************/
    public function getSizeSigne(Version $version)
    {
        $signe    =   $this->getSigne($version);
        if ($signe == null) {
            return 0;
        } else {
            return intdiv(filesize($signe), 1024);
        }
    }

    ////////////////////////////////////////////////////
    public function setLaboResponsable(Version $version, Individu $individu)
    {
        if ($individu == null) {
            return;
        }

        $labo = $individu->getLabo();
        if ($labo != null) {
            $version->setPrjLLabo(Functions::string_conversion($labo));
        } else {
            $this->sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Le nouveau responsable " . $individu . " ne fait partie d'aucun laboratoire");
        }
    }

    // A partir des champs demFormN et de la table Formation, construit et retourne un tableau des formations
    // demandées, sous une forme plus simple à manipuler
    public function buildFormations(Version $version)
    {
        $em = $this->em;

        // Construction du tableau formations
        // $form_ver contient les getDemFormN()
        // TODO --> Un eval ? (pas réussi !)
        $form_ver=[];
        $form_ver[0] = $version->getDemForm0();
        $form_ver[1] = $version->getDemForm1();
        $form_ver[2] = $version->getDemForm2();
        $form_ver[3] = $version->getDemForm3();
        $form_ver[4] = $version->getDemForm4();
        $form_ver[5] = $version->getDemForm5();
        $form_ver[6] = $version->getDemForm6();
        $form_ver[7] = $version->getDemForm7();
        $form_ver[8] = $version->getDemForm8();
        $form_ver[9] = $version->getDemForm9();

        $formations_all = $em -> getRepository(Formation::class) -> getFormationsPourVersion();
        $formation = [];

        $all_empty = true;
        if ( ! empty($version->getDemFormAutresAutres())) {
            $all_empty = false;
        }
        foreach ($formations_all as $fa) {
            $nb = $fa->getNumeroForm();
            $f = [];
            $f['nb']  = $nb;
            $f['nom'] = $fa->getNomForm();
            $f['acro']= $fa->getAcroForm();
            $f['rep'] = $form_ver[$nb];
            if (! empty($f['rep'])) {
                $all_empty = false;
            }
            $formation[$f['acro']] = $f;
        }
        // En espérant qu'il n'y a pas de formation avec ALL_EMPTY comme acronyme !
        $f = [];
        $f['nb'] = 10;
        $f['nom'] = "ERREUR - Cette colonne ne devrait pas être affichée";
        $f['acro']= "ALL_EMPTY";
        $f['rep'] = $all_empty;
        $formation['ALL_EMPTY'] = $f;
        return $formation;
    }

    /*************************************************************
     * Efface les données liées à une version de projet
     *
     *  - Les fichiers img_* et *.pdf du répertoire des figures
     *  - Le fichier de signatures s'il existe
     *  - N'EFFACE PAS LE RAPPORT D'ACTIVITE !
     *    cf. ServiceProjets pour cela
     *************************************************************/
    public function effacerDonnees(Version $version)
    {
        // Les figures et les doc attachés
        $img_dir = $this->imageDir($version);
        array_map('unlink', glob("$img_dir/img*"));
        array_map('unlink', glob("$img_dir/*.pdf"));

        // Les signatures
        $fiche = $this->getSigne($version);
        if ( $fiche != null) {
            unlink($fiche);
        }
    }
}
