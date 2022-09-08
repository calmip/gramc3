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

use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Session;
use App\Entity\Individu;
use App\Entity\Formation;
use App\Entity\User;
use App\Entity\CollaborateurVersion;

use App\GramcServices\Etat;
use App\GramcServices\ServiceForms;
use App\GramcServices\ServiceInvitations;
use App\Form\IndividuFormType;

use App\Utils\Functions;

use App\Validator\Constraints\PagesNumber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormInterface;
use App\Form\IndividuForm\IndividuForm;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ServiceVersions
{
    public function __construct(
                                private $attrib_seuil_a,
                                private $prj_prefix,
                                private $rapport_directory,
                                private $fig_directory,
                                private $signature_directory,
                                private $coll_login,
                                private $nodata,
                                private $max_fig_width,
                                private $max_fig_height,
                                private $max_size_doc,
                                private $resp_peut_modif_collabs,
                                private ServiceJournal $sj,
                                private ServiceInvitations $sid,
                                private ValidatorInterface $vl,
                                private ServiceForms $sf,
                                private FormFactoryInterface $ff,
                                private TokenStorageInterface $tok,
                                private EntityManagerInterface $em
                                )
    {
        $this->attrib_seuil_a = intval($this->attrib_seuil_a);
    }

    /*********
     * Utilisé seulement en session B
     * renvoie true si l'attribution en A est supérieure à ATTRIB_SEUIL_A et la demande en B supérieure à attr_heures_a / 2
     *
     * param  id_version, $attr_heures_a, $attr_heures_b
     * return true/false
     *
     **************************/
    public function is_demande_toomuch($attr_heures_a, $dem_heures_b): bool
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
     *         $displayName Nom pouvant être affiché (ex: figure 1)
     *         $version  Version associée
     *
     * return: Plein d'informations
     *
     ***************************/
    public function imageProperties(string $filename, string $displayName, Version $version): array
    {
        $full_filename = $this->imagePath($filename, $version);
        if (file_exists($full_filename) && is_file($full_filename))
        {
            $imageinfo  =   [];
            $imgSize = getimagesize($full_filename, $imageinfo);
            
            return [
                'contents' => base64_encode(file_get_contents($full_filename)),
                'width'    => $imgSize[0],
                'height'   => $imgSize[1],
                'balise'   => $imgSize[2],
                'mime'     => $imgSize['mime'],
                'name'     => $filename,
                'displayName' => $displayName
            ];
        }
        else
        {
            return [
                'name'     => $filename,
                'displayName' => $displayName
            ];
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
    public function getDocument(Version $version): ?string
    {
        $document = $this->imageDir($version).'/document.pdf';
        if (file_exists($document) && is_file($document)) {
            return $document;
        } else {
            return null;
        }
    }

    /**************************************************
     * Crée un formulaire qui permettra de téléverser un fichier pdf
     * Gère le mécanisme de soumission et validation
     * Renvoie un json: soit OK soit les erreurs.
     * 
     * Fonctionne aussi bien en ajax avec jquery-upload-file-master
     * que de manière "normale"
     *
     * Appelé par VersionController::televerserAction
     *
     * params = request
     *          dirname : répertoire de destination
     *          filename: nom définitif du fichier
     *
     * return = la form si pas encore soumise
     *          ou une string: "OK"
     *          ou un message d'erreur
     *
     ********************************/
    public function televerserFichier(Request $request,
                                      Version $version,
                                      string $dirname,
                                      string $filename,
                                      string $type): Form|string
    {
        $sj = $this->sj;
        $ff = $this->ff;
        $sf = $this->sf;
        $max_size_doc = intval($this->max_size_doc);
        $maxSize = strval(1024 * $max_size_doc) . 'k';

        // Les contraintes ne sont pas les mêmes suivant le type de fichier
        $format_fichier = '';
        $constraints = [];
        switch ($type)
        {
            case "pdf":
                $format_fichier = new \Symfony\Component\Validator\Constraints\File(
                    [
                        'mimeTypes'=> [ 'application/pdf' ],
                        'mimeTypesMessage'=>' Le fichier doit être un fichier pdf. ',
                        'maxSize' => $maxSize,
                        'uploadIniSizeErrorMessage' => ' Le fichier doit avoir moins de {{ limit }} {{ suffix }}. ',
                        'maxSizeMessage' => ' Le fichier est trop grand ({{ size }} {{ suffix }}), il doit avoir moins de {{ limit }} {{ suffix }}. ',
                    ]
                );
                $constraints = [$format_fichier , new PagesNumber() ];
                break;

            case "jpg":
                $format_fichier = new \Symfony\Component\Validator\Constraints\File(
                    [
                        'mimeTypes'=> [ 'image/jpeg' ],
                        'mimeTypesMessage'=>" L'image doit être au format jpeg.",
                        'maxSize' => $maxSize,
                        'uploadIniSizeErrorMessage' => ' Le fichier doit avoir moins de {{ limit }} {{ suffix }}. ',
                        'maxSizeMessage' => ' Le fichier est trop grand ({{ size }} {{ suffix }}), il doit avoir moins de {{ limit }} {{ suffix }}. ',
                    ]
                );
                $constraints = [$format_fichier ];
                break;
            
            default:
                $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " Erreur interne - type $type pas supporté");
                break;
        }

        $form = $ff
        ->createNamedBuilder('fichier', FormType::class, [], ['csrf_protection' => false ])
        ->add(
            'fichier',
            FileType::class,
            [
                'required'          =>  true,
                'label'             => "Fichier à téléverser",
                'constraints'       => $constraints
            ]
        )
        ->getForm();

        $form->handleRequest($request);

        // form soumise et valide = On met le fichier à sa place et on retourne OK
        $rvl = [];
        $rvl['OK'] = false;
        if ($form->isSubmitted() && $form->isValid())
        {
            $tempFilename = $form->getData()['fichier'];

            if (is_file($tempFilename) && ! is_dir($tempFilename))
            {
                $file = new File($tempFilename);
            }
            elseif (is_dir($tempFilename))
            {
                return "Erreur interne : Le nom  " . $tempFilename . " correspond à un répertoire" ;
            }
            else
            {
                return "Erreur interne : Le fichier " . $tempFilename . " n'existe pas" ;
            }

            $file->move($dirname, $filename);
            
            $sj->debugMessage(__METHOD__ . ':' . __LINE__ . " Fichier -> " . $filename);
            $rvl['OK'] = true;

            // Si le type est 'jpg', c'est une image: on la renvoie !
            if ($type == 'jpg')
            {
                $rvl['properties'] = $this->imageProperties($filename, 'image au format jpeg', $version);
            }
            return json_encode($rvl);
        }

        // formulaire non valide ou autres cas d'erreur = On retourne un message d'erreur
        elseif ($form->isSubmitted() && ! $form->isValid())
        {
            if (isset($form->getData()['fichier']))
            {
                $rvl['message'] = $sf->formError($form->getData()['fichier'], $constraints);
                return json_encode($rvl);
            }
            else
            {
                $rvl['message'] = "<strong>Erreurs :</strong>Fichier trop gros ou autre problème";
                return json_encode($rvl);
            }
        }
        
        elseif ($request->isXMLHttpRequest())
        {
            $rvl['message'] = "Le formulaire n'a pas été soumis";
            return json_encode($rvl);
        }

        // formulaire non soumis = On retourne le formulaire
        else
        {
            return $form;
        }
    }

    /*************************************************************
     *
     * IMAGES ET DOC ATTACHE
     *
     *************************************************************/
    /*************************
     * Calcule le chemin de fichier de l'image
     *
     * param = $filename Nom du fichier, sans le répertoire ni l'extension
     *         $version  Version associée
     *
     * return = Le chemin complet (si le fichier existe)
     *          Le chemin avec répertoire mais sans extension sinon
     *          TODO - Pas clair du tout !
     *
     ************************************/
    public function imagePath(string $filename, Version $version): string
    {
        $full_filename = $this->imageDir($version) .'/'.  $filename;

        if (file_exists($full_filename . ".jpeg") && is_file($full_filename . ".jpeg"))
        {
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
    public function imageDir(Version $version): string
    {
        $dir = $this->fig_directory;
        if (! is_dir($dir))
        {
            if (file_exists($dir) && is_file($dir))
            {
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

    /***********************************************************************
     *
     * SIGNATURES
     *
     *************************************************************/

    /***************************************************
     * Renvoie true si le fichier pdf de signature est présent
     * TODO - Le champ prjFicheVal de l'entité Version n'est pas utilisé !
     *        On devrait pouvoir le supprimer ?
     *********************************************************/
    public function isSigne(Version $version) : bool
    {
        $file = $this->getSignePath($version);
        if (file_exists($file) && ! is_dir($file))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /*****************
     * Retourne le chemin vers le fichier de signature correspondant à cette version
     *          null si pas de fichier de signature
     ****************/
    public function getSigne(Version $version): ?string
    {
        if ( $this->isSigne($version) )
        {
            return $this->getSignePath($version);
        }
        else
        {
            return null;
        }
    }

    /*****************************
     * Retourne la taille du fichier de signature arrondi au Ko inférieur
     *****************************/
    public function getSizeSigne(Version $version): int
    {
        $signe = $this->getSigne($version);
        if ($signe == null)
        {
            return 0;
        }
        else
        {
            return intdiv(filesize($signe), 1024);
        }
    }

    /*************************
     * Calcule le chemin de fichier de la fiche signée
     *
     * param = $version  Version
     *
     * return = Le chemin complet (que le fichier existe ou non)
     *
     ************************************/
     public function getSignePath(Version $version): string
     {
         return $this->getSigneDir($version) . '/' . $version . '.pdf';
     }

     /*******************************
     * Crée si besoin le répertoire pour les fiches signées
     *
     * param = $version  La version associée
     *
     * return = Le chemin complet vers le répertoire
     *
     *******************************************/
    public function getSigneDir(Version $version): string
    {
        $dir = $this->signature_directory . '/' . $version->getSession();
        if (! is_dir($dir))
        {
            if (file_exists($dir) && is_file($dir))
            {
                unlink($dir);
            }
            mkdir($dir);
            $this->sj->warningMessage("Répertoire pour les fiches signées " . $dir . " créé !");
        }
        return $dir;
    }

    /***********************************************************************
     *
     * RAPPORTS D'ACTIVITE
     *
     *************************************************************/

    /*******************************
     * Crée si besoin le répertoire pour les rapports d'activité
     *
     * param = $version  La version associée
     *
     * return = Le chemin complet vers le répertoire
     *
     *******************************************/
    public function rapportDir(Version $version): string
    {
        $annee = $version->anneeRapport();
        $dir = $this->rapport_directory . '/' . $annee;
        if (! is_dir($dir))
        {
            if (file_exists($dir) && is_file($dir))
            {
                unlink($dir);
            }
            mkdir($dir);
            $this->sj->warningMessage("rapport_directory " . $dir . " créé !");
        }
        
        return $dir;
    }
    public function rapportDir1(Projet $projet, string $annee): string
    {
        $dir = $this->rapport_directory . '/' . $annee;
        if (! is_dir($dir))
        {
            if (file_exists($dir) && is_file($dir))
            {
                unlink($dir);
            }
            mkdir($dir);
            $this->sj->warningMessage("rapport_directory " . $dir . " créé !");
        }        
        return $dir;
    }


    /**************************************
     * Changer le responsable d'une version
     **********************************************/
    public function changerResponsable(Version $version, Individu $new): void
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
    private function TrouverCollaborateur(Version $version, Individu $individu): ?CollaborateurVersion
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
    public function supprimerCollaborateur(Version $version, Individu $individu): void
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
    public function syncDeleted( Version $version, Individu $individu, bool $delete): void
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

    // modifier le login d'un collaborateur d'une version
    // Si le login passe à false, suppression du Loginname,
    // et suppression de la ligne correspondante si elle existe (mot de passe) dans la table user
    public function modifierLogin(Version $version, Individu $individu, $login=false, $clogin=false): void
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
    public function isNouvelle(Version $version): bool
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

    ////////////////////////////////////////////////////
    public function setLaboResponsable(Version $version, Individu $individu): void
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
    public function buildFormations(Version $version) : array
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
    public function effacerDonnees(Version $version): void
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

    /*********************************************
     *
     * LES IMAGES
     * 
     ********************************************/
     
    /*************************************************************
     * Efface un seul fichier lié à une version de projet
     *
     *  - Les fichiers img_* et *.pdf du répertoire des figures
     *  - Le fichier de signatures s'il existe
     *  - N'EFFACE PAS LE RAPPORT D'ACTIVITE !
     *    cf. ServiceProjets pour cela
     *************************************************************/
    public function effacerFichier(Version $version, string $filename): void
    {
        // Les figures et les doc attachés
        $img_dir = $this->imageDir($version);

        $fichiers = [ 'img_expose_1',
                      'img_expose_2',
                      'img_expose_3',
                      'img_justif_renou_1',
                      'img_justif_renou_2',
                      'img_justif_renou_3'
                    ];

        if (in_array($filename, $fichiers))
        {
            $path = $img_dir . '/' . $filename;
            unlink($path);
        }
    }

    /*************************************************************
     * Lit un fichier image et renvoie la version base64 pour affichage
     * dans le html
     *************************************************************/
    public function image2Base64(string $filename, Version $version) : ?string
    {
        $full_filename  = $this->imagePath($filename, $version);

        if (file_exists($full_filename) && is_file($full_filename))
        {
            //dd($full_filename);
            //$sj->debugMessage('ServiceVersion image  ' .$filename . ' : ' . base64_encode( file_get_contents( $full_filename ) )  );
            return base64_encode(file_get_contents($full_filename));
        }
        else
        {
            return null;
        }
    }

    /***
     * Redimensionne une image aux valeurs fixées dans le fichier de paramètres
     *
     * Utilise convert (d'imagemagick)
     *
     *  params $image, le chemin vers un fichier image
     *
     */
    private function imageRedim(string $image): void
    {

        $cmd = "identify -format '%w %h' $image";
        //$sj->debugMessage('imageRedim cmd identify = ' . $cmd);
        $format = `$cmd`;
        list($width, $height) = explode(' ', $format);
        $width = intval($width);
        $height= intval($height);
        $rap_w = 0;
        $rap_h = 0;
        $rapport = 0;      // Le rapport de redimensionnement

        $max_fig_width = $this->max_fig_width;
        if ($width > $max_fig_width && $max_fig_width > 0) {
            $rap_w = (1.0 * $width) /  $max_fig_width;
        }

        $max_fig_height = $this->max_fig_height;
        if ($height > $max_fig_height && $max_fig_height > 0) {
            $rap_h = (1.0 * $height) / $max_fig_height;
        }

        // Si l'un des deux rapports est > 0, on prend le plus grand
        if ($rap_w + $rap_h > 0) {
            $rapport = ($rap_w > $rap_h) ? 1/$rap_w : 1/$rap_h;
            $rapport = 100 * $rapport;
        }

        // Si un rapport a été calculé, on redimensionne
        if ($rapport > 1) {
            $cmd = "convert $image -resize $rapport% $image";
            //$sj->debugMessage('imageRedim cmd convert = ' . $cmd);
            `$cmd`;
        }
    }

    /*********************************************
     *
     * LES COLLABORATEURS
     * 
     ********************************************/

    /**************************
     * préparation de la liste des collaborateurs
     *
     * params = $version
     *
     * return = Un tableau d'objets de type IndividuForm (cf Util\IndividuForm)
     *          Le responsable est dans la cellule 0 du tableau
     *
     *****************************************************************************/
    public function prepareCollaborateurs(Version $version) : array
    {
        $sj = $this->sj;
        
        if ($version == null) {
            $sj->throwException('ServiceVersion:modifierCollaborateurs : version null');
        }

        $dataR  =   [];    // Le responsable est seul dans ce tableau
        $dataNR =   [];    // Les autres collaborateurs
        foreach ($version->getCollaborateurVersion() as $cv) {
            $individu = $cv->getCollaborateur();
            if ($individu == null)
            {
                $sj->errorMessage("ServiceVersion:modifierCollaborateurs : collaborateur null pour CollaborateurVersion ".
                         $cv->getId());
                continue;
            }
            else
            {
                $individuForm = new IndividuForm($individu, $this->resp_peut_modif_collabs);
                $individuForm->setLogin($cv->getLogin());
                $individuForm->setClogin($cv->getClogin());
                $individuForm->setResponsable($cv->getResponsable());
                $individuForm->setDelete($cv->getDeleted());

                if ($individuForm->getResponsable() == true) {
                    $dataR[] = $individuForm;
                } else {
                    $dataNR[] = $individuForm;
                }
            }
        }

        // On merge les deux, afin de revoyer un seul tableau, le responsable en premier
        return array_merge($dataR, $dataNR);
    }

    /*********************************
     * 
     * Validation du formulaire des collaborateurs - Retourne true/false
     *
     * params = Retour de $sv->prepareCollaborateurs
     *          $definitif = Si false, on fait une validation minimale
     ***********************************************************************/
    public function validateIndividuForms(array $individu_forms, $definitif = false) : bool
    {
        $coll_login = $this->coll_login;
        $resp_peut_modif_collabs = $this->resp_peut_modif_collabs;
        $one_login = false;
        foreach ($individu_forms as  $individu_form) {
            if ($individu_form->getLogin()) {
                $one_login = true;
            }
            // Si pas définitif, on ne teste pas ça
            // Si le resp ne peut pas modifier les profils des collabs, on ne teste pas ça
            if ($definitif == true && $resp_peut_modif_collabs == true &&
                (
                   $individu_form->getPrenom() == null
                   || $individu_form->getNom() == null
                   || $individu_form->getEtablissement() == null
                   || $individu_form->getLaboratoire() == null
                   || $individu_form->getStatut() == null
                )
            )
            {
                return false;
            }
        }

        // Personne n'a de login !
        // Seulement si $coll_login est true
        if ($coll_login) {
            if ($definitif == true && $one_login == false) {
                return false;
            }
        }

        if ($individu_forms != []) {
            return true;
        } else {
            return false;
        }
    }

    /***************************************
     * Traitement des formulaires des individus individuellement
     *
     * $individu_forms = Tableau contenant un formulaire par individu
     * $version        = La version considérée
     ****************************************************************/
    public function handleIndividuForms(array $individu_forms, Version $vers): void
    {
        $em   = $this->em;
        $sj   = $this->sj;
        $sval = $this->vl;

        // On fait la modification sur 1 ou 2 versions suivant les cas:
        //    - Version active
        //    - Dernière version
        $projet = $vers->getProjet();
        $verder = $projet->getVersionDerniere();
        $versions = [];
        if ($projet->getVersionActive() != null) $versions[] = $projet->getVersionActive();
        if ($projet->getVersionDerniere() != null && $projet->getVersionDerniere() != $projet->getVersionActive()) $versions[] = $projet->getVersionDerniere();

        foreach ($versions as $version)
        {
            foreach ($individu_forms as $individu_form)
            {
                $id =  $individu_form->getId();
    
                // Le formulaire correspond à un utilisateur existant
                if ($id != null) {
                    $individu = $em->getRepository(Individu::class)->find($id);
                }
                
                // On a renseigné le mail de l'utilisateur mais on n'a pas encore l'id: on recherche l'utilisateur !
                // Si $utilisateur == null, il faudra le créer (voir plus loin)
                elseif ($individu_form->getMail() != null) {
                    $individu = $em->getRepository(Individu::class)->findOneBy([ 'mail' =>  $individu_form->getMail() ]);
                    if ($individu!=null) {
                        $sj->debugMessage(__METHOD__ . ':' . __LINE__ . ' mail=' . $individu_form->getMail() . ' => trouvé ' . $individu);
                    } else {
                        $sj->debugMessage(__METHOD__ . ':' . __LINE__ . ' mail=' . $individu_form->getMail() . ' => Individu à créer !');
                    }
                }
    
                // Pas de mail -> pas d'utilisateur !
                else {
                    $individu = null;
                }
    
                // Cas d'erreur qui ne devraient jamais se produire
                if ($individu == null && $id != null) {
                    $sj->errorMessage(__METHOD__ . ':' . __LINE__ .' idIndividu ' . $id . 'du formulaire ne correspond pas à un utilisateur');
                }
    
                elseif (is_array($individu_form)) {
                    // TODO je ne vois pas le rapport
                    $sj->errorMessage(__METHOD__ . ':' . __LINE__ .' individu_form est array ' . Functions::show($individu_form));
                }
    
                elseif (is_array($individu)) {
                    // TODO pareil un peu nawak
                    $sj->errorMessage(__METHOD__ . ':' . __LINE__ .' individu est array ' . Functions::show($individu));
                }
    
                elseif ($individu != null && $individu_form->getMail() != null && $individu_form->getMail() != $individu->getMail()) {
                    $sj->errorMessage(__METHOD__ . ':' . __LINE__ ." l'adresse mails de l'utilisateur " .
                        $individu . ' est incorrecte dans le formulaire :' . $individu_form->getMail() . ' != ' . $individu->getMail());
                }
    
                // --------------> Maintenant des cas réalistes !
                // L'individu existe déjà
                elseif ($individu != null) {
                    // On modifie l'individu... si on a le droit
                    if ($this->resp_peut_modif_collabs)
                    {
                        $individu = $individu_form->modifyIndividu($individu, $sj);
                        $em->persist($individu);
                    }
    
                    // Il devient collaborateur
                    if (! $version->isCollaborateur($individu)) {
                        $sj->infoMessage(__METHOD__ . ':' . __LINE__ .' individu ' .
                            $individu . ' ajouté à la version ' .$version);
                        $collaborateurVersion   =   new CollaborateurVersion($individu);
                        $collaborateurVersion->setDeleted(false);
                        $collaborateurVersion->setVersion($version);
                        if ($this->coll_login) {
                            $collaborateurVersion->setLogin($individu_form->getLogin());
                        };
                        if ($this->nodata == false) {
                            $collaborateurVersion->setClogin($individu_form->getClogin());
                        };
                        $collaborateurVersion->setLogin($individu_form->getLogin());
                        $collaborateurVersion->setClogin($individu_form->getClogin());
                        $em->persist($collaborateurVersion);
                    }
    
                    // il était déjà collaborateur
                    else {
                        $sj->debugMessage(__METHOD__ . ':' . __LINE__ .' individu ' .
                            $individu . ' confirmé pour la version '.$version);
    
                        // Modif éventuelle des cases de login
                        $this->modifierLogin($version, $individu, $individu_form->getLogin(), $individu_form->getClogin());
    
                        // modification du labo du projet
                        if ($version->isResponsable($individu)) {
                            $this->setLaboResponsable($version, $individu);
                        }
    
                        // modification éventuelle du flag deleted
                        $this->syncDeleted($version, $individu, $individu_form->getDelete());
                    }
                    $em -> flush();
                }
    
                // Le formulaire correspond à un nouvel utilisateur (adresse mail pas trouvée)
                elseif ($individu_form->getMail() != null && $individu_form->getDelete() == false) {
                    
                    // Création d'un individu à partir du formulaire
                    // Renvoie null si la validation est négative
                    $individu = $individu_form->nouvelIndividu($sval);
                    if ($individu != null) {
                        $collaborateurVersion   =   new CollaborateurVersion($individu);
                        $collaborateurVersion->setLogin($individu_form->getLogin());
                        $collaborateurVersion->setClogin($individu_form->getClogin());
                        $collaborateurVersion->setVersion($version);
    
                        $sj->infoMessage(__METHOD__ . ':' . __LINE__ . ' nouvel utilisateur ' . $individu .
                            ' créé et ajouté comme collaborateur à la version ' . $version);
    
                        $em->persist($individu);
                        $em->persist($collaborateurVersion);
                        $em->persist($version);
                        $em->flush();
                        $sj->warningMessage('Utilisateur ' . $individu . '(' . $individu->getMail() . ') id(' . $individu->getIdIndividu() . ') a été créé');
    
                        // Envoie une invitation à ce nouvel utilisateur
                        $connected = $this->tok->getToken()->getUser();
                        if ($connected != null)
                        {
                            $this->sid->sendInvitation($connected, $individu);
                        }
                    }
                }
    
                // Ligne vide
                elseif ($individu_form->getMail() == null && $id == null) {
                    $sj->debugMessage(__METHOD__ . ':' . __LINE__ . ' nouvel utilisateur vide ignoré');
                }
            }
        }
    }

    /*************************************************************
     * Génère et renvoie un form pour modifier un collaborateur
     *************************************************************/
    public function getCollaborateurForm(Version $version): FormInterface
    {
        $sj = $this->sj;
        $em = $this->em;
        $sval= $this->vl;

        $text_fields = true;
        if ( $this->resp_peut_modif_collabs)
        {
            $text_fields = false;
        }
        return $this->ff
                   ->createNamedBuilder('form_projet', FormType::class, [ 'individus' => $this->prepareCollaborateurs($version, $sj, $sval) ])
                   ->add('individus', CollectionType::class, [
                       'entry_type'     =>  IndividuFormType::class,
                       'label'          =>  false,
                       'allow_add'      =>  true,
                       'allow_delete'   =>  true,
                       'prototype'      =>  true,
                       'required'       =>  true,
                       'by_reference'   =>  false,
                       'delete_empty'   =>  true,
                       'attr'         => ['class' => "profil-horiz",],
                       'entry_options' =>['text_fields' => $text_fields]
                    ])
                    ->getForm();
    }
}
