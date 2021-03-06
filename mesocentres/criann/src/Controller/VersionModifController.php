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
 *  authors : Miloslav Grundmann - C.N.R.S. - UMS 3667 - CALMIP
 *            Emmanuel Courcelle - C.N.R.S. - UMS 3667 - CALMIP
 *            Nicolas Renon - Université Paul Sabatier - CALMIP
 **/

namespace App\Controller;

use App\Entity\Version;
use App\Entity\Projet;
use App\Entity\User;
use App\Entity\Session;
use App\Entity\Individu;
use App\Entity\CollaborateurVersion;
use App\Entity\RapportActivite;
use App\Entity\Rattachement;
use App\Entity\Formation;

use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServiceVersions;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceForms;
use App\GramcServices\Workflow\Projet\ProjetWorkflow;

use App\Utils\Functions;
use App\Utils\Etat;
use App\Utils\Signal;
use App\Utils\GramcDate;
use App\Utils\IndividuForm;

use App\Form\IndividuFormType;

use App\Repository\FormationRepository;

use App\Validator\Constraints\PagesNumber;

use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
//use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Doctrine\ORM\EntityManager;

use Twig\Environment;

/**
 * Version controller.
 *
 * @Route("version")
 */
class VersionModifController extends AbstractController
{
    private $sj = null;
    private $sm = null;
    private $ss = null;
    private $sv = null;
    private $sp = null;
    private $sf = null;
    private $pw = null;
    private $ff = null;
    private $vl = null;
    private $tw = null;
    private $tok = null;

    public function __construct(
        ServiceJournal $sj,
        ServiceMenus $sm,
        ServiceSessions $ss,
        ServiceVersions $sv,
        ServiceProjets $sp,
        ServiceForms $sf,
        ProjetWorkflow $pw,
        FormFactoryInterface $ff,
        ValidatorInterface $vl,
        Environment $tw,
        TokenStorageInterface $tok
    ) {
        $this->sj  = $sj;
        $this->sm  = $sm;
        $this->ss  = $ss;
        $this->sv  = $sv;
        $this->sp  = $sp;
        $this->sf  = $sf;
        $this->pw  = $pw;
        $this->ff  = $ff;
        $this->vl  = $vl;
        $this->tw = $tw;
        $this->tok= $tok;
    }

    /**
     * Montre les projets d'un utilisateur
     *
     * NOTE - etait autrefois dans le controleur Projet, mais a été déplacé
     *        ici pour avoir une version dépendant du mésocentre
     * 
     * @Route("/projet/accueil", name="projet_accueil",methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function accueilAction()
    {
        $sm                  = $this->sm;
        $ss                  = $this->ss;
        $sp                  = $this->sp;
        $token               = $this->tok->getToken();
        $em                  = $this->getDoctrine()->getManager();
        $individu            = $token->getUser();
        $id_individu         = $individu->getIdIndividu();

        $projetRepository    = $em->getRepository(Projet::class);
        $cv_repo             = $em->getRepository(CollaborateurVersion::class);
        $user_repo           = $em->getRepository(User::class);

        $list_projets_collab = $projetRepository-> getProjetsCollab($id_individu, false, true);
        $list_projets_resp   = $projetRepository-> getProjetsCollab($id_individu, true, false);

        $projets_term        = $projetRepository-> get_projets_etat($id_individu, 'TERMINE');

        $session_actuelle    = $ss->getSessionCourante();

        // TODO - Faire en sorte pour que les erreurs soient proprement affichées dans l'API
        // En attendant ce qui suit permet de se dépanner mais c'est franchement dégueu
        //echo '<pre>'.strlen($_SERVER['CLE_DE_CHIFFREMENT'])."\n";
        //echo SODIUM_CRYPTO_SECRETBOX_KEYBYTES.'</pre>';
        //$enc = Functions::simpleEncrypt("coucou");
        //$dec = Functions::simpleDecrypt($enc);
        //echo "$dec\n";

        // projets responsable
        $projets_resp  = [];
        foreach ($list_projets_resp as $projet) {
            $versionActive  =   $sp->versionActive($projet);
            if ($versionActive != null) {
                $rallonges = $versionActive ->getRallonge();
                $cpt_rall  = count($rallonges->toArray());
            } else {
                $rallonges = null;
                $cpt_rall  = 0;
            }

            if ($versionActive != null) {
                $cv    = $cv_repo->findOneBy(['version' => $versionActive, 'collaborateur' => $individu]);
                $login = $cv->getLoginname()==null ? 'nologin' : $cv->getLoginname();
                $u     = $user_repo->findOneBy(['loginname' => $login]);
                if ($u==null) {
                    $passwd    = null;
                    $pwd_expir = null;
                } else {
                    $passwd    = $u->getPassword();
                    $passwd    = Functions::simpleDecrypt($passwd);
                    $pwd_expir = $u->getPassexpir();
                }
            } else {
                $login  = 'nologin';
                $passwd = null;
                $pwd_expir = null;
            }
            $projets_resp[]   =
            [
                'projet'    => $projet,
                'conso'     => $sp->getConsoCalculP($projet),
                'rallonges' => $rallonges,
                'cpt_rall'  => $cpt_rall,
                'meta_etat' => $sp->getMetaEtat($projet),
                'login'     => $login,
                'passwd'    => $passwd,
                'pwd_expir' => $pwd_expir
            ];
        }

        // projets collaborateurs
        $projets_collab  = [];
        foreach ($list_projets_collab as $projet) {
            $versionActive = $sp->versionActive($projet);

            if ($versionActive != null) {
                $rallonges = $versionActive ->getRallonge();
                $cpt_rall  = count($rallonges->toArray());
            } else {
                $rallonges = null;
                $cpt_rall  = 0;
            }

            $cv    = $cv_repo->findOneBy(['version' => $versionActive, 'collaborateur' => $individu]);
            if ($cv != null) {
                $login = $cv->getLoginname()==null ? 'nologin' : $cv->getLoginname();
                $u     = $user_repo->findOneBy(['loginname' => $login]);
                if ($u==null) {
                    $passwd = null;
                    $pwd_expir  = null;
                } else {
                    $passwd    = $u->getPassword();
                    $pwd_expir = $u->getPassexpir();
                }
            } else {
                $login  = 'nologin';
                $passwd = null;
                $pwd_expir = null;
            }

            $projets_collab[] =
                [
                    'projet'    => $projet,
                    'conso'     => $sp->getConsoCalculP($projet),
                    'rallonges' => $rallonges,
                    'cpt_rall'  => $cpt_rall,
                    'meta_etat' => $sp->getMetaEtat($projet),
                    'login'     => $login,
                    'passwd'    => $passwd,
                    'pwd_expir' => $pwd_expir
                ];
        }

        // projets collaborateurs
        $projets_collab  = [];
        foreach ($list_projets_collab as $projet) {
            $versionActive = $sp->versionActive($projet);

            if ($versionActive != null) {
                $rallonges = $versionActive ->getRallonge();
                $cpt_rall  = count($rallonges->toArray());
            } else {
                $rallonges = null;
                $cpt_rall  = 0;
            }

            $cv    = $cv_repo->findOneBy(['version' => $versionActive, 'collaborateur' => $individu]);
            if ($cv != null) {
                $login = $cv->getLoginname()==null ? 'nologin' : $cv->getLoginname();
                $u     = $user_repo->findOneBy(['loginname' => $login]);
                if ($u==null) {
                    $passwd = null;
                    $pwd_expir = null;
                } else {
                    $passwd = $u->getPassword();
                    $pwd_expir = $u->getPassexpir();
                }
            } else {
                $login = 'nologin';
                $passwd= null;
                $pwd_expir = null;
            }
            $projets_collab[] =
                [
                'projet'    => $projet,
                'conso'     => $sp->getConsoCalculP($projet),
                'rallonges' => $rallonges,
                'cpt_rall'  => $cpt_rall,
                'meta_etat' => $sp->getMetaEtat($projet),
                'login'     => $login,
                'passwd'    => $passwd,
                'pwd_expir' => $pwd_expir
                ];
        }

        /*
         * JUIN 2021 - On ne crée QUE des projets PROJET_FIL !
         *             Eventuellement ils se transforment par la suite en PROJET_SESS
         */
        //$prefixes = $this->getParameter('prj_prefix');
        //foreach (array_keys($prefixes) as $t)
        //{
        //	$menu[] = $sm->nouveau_projet($t);
        //}
        $menu = [];
        $menu[] = $sm -> nouveau_projet(3);
        //$menu[] = $this->menu_nouveau_projet_test();
        //$menu[] = $this->menu_nouveau_projet_sess();

        return $this->render(
            'projet/demandeur.html.twig',
            [
                'projets_collab'  => $projets_collab,
                'projets_resp'    => $projets_resp,
                'projets_term'    => $projets_term,
                'menu'            => $menu,
                ]
        );
    }

    /**
     * Appelé par le bouton Envoyer à l'expert: si la demande est incomplète
     * on envoie un écran pour la compléter. Sinon on passe à envoyer à l'expert
     *
     * @Route("/{id}/avant_modifier", name="avant_modifier_version",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function avantModifierVersionAction(Request $request, Version $version)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $vl = $this->vl;
        $em = $this->getDoctrine()->getManager();


        // ACL
        if ($sm->modifier_version($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de modifier la version " . $version->getIdVersion().
                " parce que : " . $sm->modifier_version($version)['raison']);
        }
        if ($this->versionValidate($version) != []) {
            return $this->render(
                'version/avant_modifier.html.twig',
                [
                'version'   => $version
                ]);
        }
        else {
            return $this->redirectToRoute('avant_envoyer_expert', [ 'id' => $version->getIdVersion() ]);
        }
    }
    
    /**
     * Modification d'une version existante
     *
     *      1/ D'abord une partie générique (images, collaborateurs)
     *      2/ Ensuite on appelle modifierTypeX, car le formulaire dépend du type de projet
     *
     * @Route("/{id}/modifier", name="modifier_version",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function modifierAction(Request $request, Version $version, $renouvellement = false, LoggerInterface $lg)
    {
        $sm = $this->sm;
        $sv = $this->sv;
        $sj = $this->sj;
        $twig = $this->tw;
        $html = [];
        
        // ACL
        if ($sm->modifier_version($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de modifier la version " . $version->getIdVersion().
        " parce que : " . $sm->modifier_version($version)['raison']);
        }

        // ON A CLIQUE SUR ANNULER
        // version est sauvegardée autrement et je ne sais pas pourquoi
        $form = $this->createFormBuilder(new Version())->add('annuler', SubmitType::class)->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->get('annuler')->isClicked()) {
            $sj->debugMessage(__METHOD__ .':'. __LINE__ . ' annuler clicked');
            return $this->redirectToRoute('consulter_projet', ['id' => $version->getProjet()->getIdProjet() ]);
        }

        // TELEVERSEMENT DES IMAGES PAR AJAX
        // $sj->debugMessage('modifierAction ' .  print_r($_POST, true) );
        $image_forms = [];

        $image_forms['img_expose_1'] =   $this->image_form('img_expose_1', false);
        $image_forms['img_expose_2'] =   $this->image_form('img_expose_2', false);
        $image_forms['img_expose_3'] =   $this->image_form('img_expose_3', false);

        $image_forms['img_justif_renou_1'] =   $this->image_form('img_justif_renou_1', false);
        $image_forms['img_justif_renou_2'] =   $this->image_form('img_justif_renou_2', false);
        $image_forms['img_justif_renou_3'] =   $this->image_form('img_justif_renou_3', false);


        //$sj->debugMessage('modifierAction image_handle');
        foreach ($image_forms as $my_form) {
            $this->image_handle($my_form, $version, $request);
        }
        //$sj->debugMessage('modifierAction après image_handle');

        //$sj->debugMessage('modifierAction ajax ');
        // upload image ajax

        $image_form = $this->image_form('image_form', false);
        //$sj->debugMessage('modifierAction ajax form');

        $ajax = $this->image_handle($image_form, $version, $request);
        //$sj->debugMessage('modifierAction ajax handled');
        // $sj->debugMessage('modifierAction ajax = ' .  print_r($ajax, true) );

        // Téléversement des images
        if ($ajax['etat'] != null) {
            $div_sts  = substr($ajax['filename'], 0, strlen($ajax['filename'])-1).'sts'; // img_justif_renou_1 ==>  img_justif_renou_sts
            //$sj->debugMessage(__METHOD__ . " koukou $div_sts");
            if ($ajax['etat'] == 'OK') {
                $html[$ajax['filename']]  = '<img class="dropped" src="data:image/png;base64, ' . base64_encode($ajax['contents']) .'" />';
                $template                 = $twig->createTemplate('<img class="icone" src=" {{ asset(\'icones/poubelle32.png\') }}" alt="Supprimer cette figure" title="Supprimer cette figure" />');
                $html[$ajax['filename']] .= $twig->render($template);
                $html[$div_sts] = '<div class="message info">votre figure a été correctement téléversée</div>';
            } elseif ($ajax['etat'] == 'KO') {
                $html[$div_sts] = "Le téléchargement de l'image a échoué";
            } elseif ($ajax['etat'] == 'nonvalide') {
                $html[$div_sts] = '<div class="message warning">'.$ajax['error'].'</div>';
            }

            if ($request->isXMLHttpRequest()) {
                return new Response(json_encode($html));
            }
        }

        // SUPPRESSION DES IMAGES TELEVERSEES
        $remove_form = $this->ff
                ->createNamedBuilder('remove_form', FormType::class, [], [ 'csrf_protection' => false ])
                ->add('filename', TextType::class, [ 'required'       =>  false,])
                ->getForm();

        $remove_form->handleRequest($request);
        if ($remove_form->isSubmitted() &&  $remove_form->isValid()) {
            $sj->debugMessage('remove_form is valid');
            $filename  =   $remove_form->getData()['filename'];

            $rem_nb        = substr($filename, strlen($filename)-1, 1);
            $filename      = basename($filename); // sécurité !
            $full_filename = $sv->imageDir($version).'/'.$filename;
            if (file_exists($full_filename) && is_file($full_filename)) {
                unlink($full_filename);
            } else {
                $sj->errorMessage('VersionController modifierAction Fichier '. $full_filename . " n'existe pas !");
            }
            $div_sts  = substr($filename, 0, strlen($filename)-1).'sts'; // img_justif_renou_1 ==>  img_justif_renou_sts

            $html[$div_sts] = '<div class="message info">La figure ' . $rem_nb . ' a été supprimée</div>';
            $html[$filename] = 'Figure ' . $rem_nb;

            return new Response(json_encode($html));
        }

        // FORMULAIRE DES COLLABORATEURS
        $collaborateur_form = $this->getCollaborateurForm($version);
        $collaborateur_form->handleRequest($request);
        $data   =   $collaborateur_form->getData();

        if ($data != null && array_key_exists('individus', $data)) {
            $sj->debugMessage('modifierAction traitement des collaborateurs');
            $this->handleIndividuForms($data['individus'], $version);

            // ACTUCE : le mail est disabled en HTML et en cas de POST il est annulé
            // nous devons donc refaire le formulaire pour récupérer ces mails
            $collaborateur_form = $this->getCollaborateurForm($version);
        }

        // DES FORMULAIRES QUI DEPENDENT DU TYPE DE PROJET
        $type = $version->getProjet()->getTypeProjet();
        switch ($type) {
        case Projet::PROJET_SESS:
        return $this->modifierType1($request, $version, $renouvellement, $image_forms, $collaborateur_form, $lg);

        case Projet::PROJET_TEST:
        return $this->modifierType2($request, $version, $renouvellement, $image_forms, $collaborateur_form, $lg);

        case Projet::PROJET_FIL:
        return $this->modifierType3($request, $version, $renouvellement, $image_forms, $collaborateur_form, $lg);

        default:
           $sj->throwException(__METHOD__ . ":" . __LINE__ . " mauvais type de projet " . Functions::show($type));
    }
    }

    /*
     * Appelée par modifierAction pour les projets de type 1 (PROJET_SESS)
     *
     * params = $request, $version
     *          $renouvellement (toujours true/false)
     *          $image_forms (formulaire de téléversement d'images)
     *          $collaborateurs_form (formulaire des collaborateurs)
     *
     */
    private function modifierType1(Request $request, Version $version, $renouvellement, $image_forms, $collaborateur_form, LoggerInterface $lg)
    {
        $sj   = $this->sj;
        $ss   = $this->ss;
        $sval = $this->vl;
        $em   = $this->getDoctrine()->getManager();

        // formulaire principal
        $form_builder = $this->createFormBuilder($version);
        $this->modifierType1PartieI($version, $form_builder);
        $this->modifierType1PartieII($version, $form_builder);
        $this->modifierType1PartieIII($version, $form_builder);
        if ($this->getParameter('nodata')==false) {
            $this->modifierType1PartieIV($version, $form_builder);
        }
        $nb_form = 0;
        $this->modifierType1PartieV($version, $form_builder, $nb_form);

        $form_builder
            ->add('fermer', SubmitType::class)
                //->add( 'enregistrer',   SubmitType::Class )
            ->add('annuler', SubmitType::class);

        $form = $form_builder->getForm();
        $form->handleRequest($request);

        // traitement du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('annuler')->isClicked()) {
                // on ne devrait jamais y arriver !
                $sj->errorMessage(__METHOD__ . ' seconde annuler clicked !');
                return $this->redirectToRoute('projet_accueil');
            }

            // Changement de type ou plafonnement de la demande en heures !
            $this->validDemHeures($version);

            // on sauvegarde le projet
            $return = Functions::sauvegarder($version, $em, $lg);

            if ($request->isXmlHttpRequest()) {
                $sj->debugMessage(__METHOD__ . ' isXmlHttpRequest clicked');
                if ($return == true) {
                    return new Response(json_encode('OK - Votre projet est correctement enregistré'));
                } else {
                    return new Response(json_encode("ERREUR - Votre projet n'a PAS été enregistré !"));
                }
            }
            return $this->redirectToRoute('consulter_projet', ['id' => $version->getProjet()->getIdProjet() ]);
        }

        $session = $ss -> getSessionCourante();
        return $this->render(
            'version/modifier_projet_sess.html.twig',
            [
        'session' => $session,
            'form'      => $form->createView(),
            'version'   => $version,
            'img_expose_1'   => $image_forms['img_expose_1']->createView(),
            'img_expose_2'   => $image_forms['img_expose_2']->createView(),
            'img_expose_3'   => $image_forms['img_expose_3']->createView(),
            'imageExp1'    => $this->image('img_expose_1', $version),
            'imageExp2'    => $this->image('img_expose_2', $version),
            'imageExp3'    => $this->image('img_expose_3', $version),
            'img_justif_renou_1'    =>  $image_forms['img_justif_renou_1']->createView(),
            'img_justif_renou_2'    =>  $image_forms['img_justif_renou_2']->createView(),
            'img_justif_renou_3'    =>  $image_forms['img_justif_renou_3']->createView(),
            'imageJust1'    =>   $this->image('img_justif_renou_1', $version),
            'imageJust2'    =>   $this->image('img_justif_renou_2', $version),
            'imageJust3'    =>   $this->image('img_justif_renou_3', $version),
            'collaborateur_form' => $collaborateur_form->createView(),
            'todo'          => $this->versionValidate($version),
            'renouvellement'    => $renouvellement,
        'nb_form'       => $nb_form
            ]
        );
    }

    /*
     * Appelée par modifierType1
     *         Si on est en type 3, soit plafonne le nombre d'heures, soit passe en type 1,
     *         selon l'état de la session courante
     *         NB - On ne fait pas de flush, c'est l'appelant qui s'en chargera.
     *
     * params = $version
     *
     */
    private function validDemHeures($version)
    {
        $em = $this->getDoctrine()->getManager();
        $ss = $this->ss;
        $projet = $version->getProjet();
        $type = $projet->getTypeProjet();
        $seuil = intval($this->getParameter('prj_seuil_sess'));
        $demande = $version->getDemHeures();

        if ($type == Projet::PROJET_FIL) {
            if ($demande > $seuil) {
                $etat_session = $ss->getSessionCourante()->getEtat();

                // Si on est en edition_demande on passe le projet en type 1
                // Sinon on plafonne les heures
                if ($etat_session == Etat::EDITION_DEMANDE) {
                    $projet->setTypeProjet(Projet::PROJET_SESS);
                    $em->persist($projet);
                } else {
                    $version -> setDemHeures($seuil);
                }
            }
        } elseif ($type == Projet::PROJET_SESS) {
            if ($demande <= $seuil) {
                $projet->setTypeProjet(Projet::PROJET_FIL);
                $em->persist($projet);
            }
        }
    }

    /* Les champs de la partie I */
    private function modifierType1PartieI($version, &$form)
    {
        $em = $this->getDoctrine()->getManager();
        $form
    ->add('prjTitre', TextType::class, [ 'required'       =>  false ])
    ->add(
        'prjThematique',
        EntityType::class,
        [
        'required'    => false,
        'multiple'    => false,
        'class'       => 'App:Thematique',
        'label'       => '',
        'placeholder' => '-- Indiquez la thématique',
        ]
    )
    ->add('prjSousThematique', TextType::class, [ 'required'       =>  false ]);

        if ($this->getParameter('norattachement')==false) {
            $form
        ->add(
            'prjRattachement',
            EntityType::class,
            [
            'required'    => false,
            'multiple'    => false,
            'expanded'    => true,
            'class'       => 'App:Rattachement',
            'empty_data'  => null,
            'label'       => '',
            'placeholder' => 'AUCUN',
            ]
        );
        };
        $form
    ->add('demHeures', IntegerType::class, [ 'required'       => false, 'attr' => ['min' => $this->getParameter('prj_heures_min')] ])
    ->add('demHeuresGpu', IntegerType::class, [ 'required'       => false ])
    ->add('prjFinancement', TextType::class, [ 'required'     => false ])
    ->add('prjGenciCentre', TextType::class, [ 'required' => false ])
    ->add('prjGenciMachines', TextType::class, [ 'required' => false ])
    ->add('prjGenciHeures', TextType::class, [ 'required' => false ])
    ->add('prjGenciDari', TextType::class, [ 'required'   => false ]);

        /* Pour un renouvellement, ajouter la justification du renouvellement */
        if (count($version->getProjet()->getVersion()) > 1) {
            $form = $form->add('prjJustifRenouv', TextAreaType::class, [ 'required' => false ]);
        }
    }

    /* Les champs de la partie II */
    private function modifierType1PartieII($version, &$form)
    {
        $form
    ->add('prjResume', TextAreaType::class, [ 'required'       =>  false ])
    ->add('prjExpose', TextAreaType::class, [ 'required'       =>  false ])
    ->add('prjAlgorithme', TextAreaType::class, [ 'required'       =>  false ]);
    }

    /* Les champs de la partie III */
    private function modifierType1PartieIII($version, &$form)
    {
        $form
    ->add('prjConception', CheckboxType::class, [ 'required'       =>  false ])
    ->add('prjDeveloppement', CheckboxType::class, [ 'required'       =>  false ])
    ->add('prjParallelisation', CheckboxType::class, [ 'required'       =>  false ])
    ->add('prjUtilisation', CheckboxType::class, [ 'required'       =>  false ])
    ->add('codeNom', TextType::class, [ 'required'       =>  false ])
    ->add('codeFor', CheckboxType::class, [ 'required'       =>  false ])
    ->add('codeC', CheckboxType::class, [ 'required'       =>  false ])
    ->add('codeCpp', CheckboxType::class, [ 'required'       =>  false ])
    ->add('codeAutre', CheckboxType::class, [ 'required'       =>  false ])
    ->add('codeLangage', TextType::class, [ 'required'       =>  false ])
    ->add('codeLicence', TextAreaType::class, [ 'required'       =>  false ])
    ->add('codeUtilSurMach', TextAreaType::class, [ 'required'       =>  false ])
    ->add(
        'codeHeuresPJob',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 6000 heures" => "< 6000 heures",
                "< 18000 heures" => "< 18000 heures",
                "< 72000 heures" => "< 72000 heures",
                "> 72000 heures" => "> 72000 heures",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add(
        'gpu',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "Oui" => "Oui",
                "Non" => "Non",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add(
        'codeRamPCoeur',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 5Go" => "< 5Go",
                "> 5Go" => "> 5Go",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add(
        'codeRamPart',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 192Go" => "< 192Go",
                "> 192Go" => "> 192Go",
                "< 500Go" => "< 500Go",
                "< 1To" => "< 1To",
                "> 2To" => "> 2To",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add(
        'codeEffParal',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 36" => "< 36",
                "36-360" => "36-360",
                "> 360" => "> 360",
                "< 1008" => "< 1008",
                "> 1008" => "> 1008",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add(
        'codeVolDonnTmp',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 10Go" => "< 10Go",
                "< 100Go" => "< 100Go",
                "< 1To" => "< 1To",
                "< 10To" => "< 10To",
                "> 10To" => "> 10To",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add(
        'codeVolDonnUsr',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 10Go" => "< 10Go",
                "< 100Go" => "< 100Go",
                "< 1To" => "< 1To",
                "< 10To" => "< 10To",
                "> 10To" => "> 10To",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add(
        'codeNbFichTmp',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 1 000" => "< 1 000",
                "< 10 000" => "< 10 000",
                "< 100 000" => "< 100 000",
                "> 1 000 000" => "> 1 000 000",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add(
        'codeNbFichPerm',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                 "< 1 000" => "< 1 000",
                "< 10 000" => "< 10 000",
                "< 100 000" => "< 100 000",
                "> 1 000 000" => "> 1 000 000",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add('demLogiciels', TextAreaType::class, [ 'required'       =>  false ])
    ->add('demBib', TextAreaType::class, [ 'required'       =>  false ])
    ->add('demAutres', TextAreaType::class, [ 'required'       =>  false ])
    ->add(
        'demPostTrait',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "Oui" => "Oui",
                "Non" => "Non",
                "Je ne sais pas" => "je ne sais pas",
                ],
        ]
    );
    }

    /* Les champs de la partie IV */
    private function modifierType1PartieIV($version, &$form)
    {
        $form
    ->add(
        'sondVolDonnPerm',
        ChoiceType::class,
        [
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 1To" => "< 1To",
                "1 To" => "1 To",
                "2 To" => "2 To",
                "3 To" => "3 To",
                "4 To" => "4 To",
                "5 To" => "5 To",
                "10 To" => "10 To",
                "25 To" => "25 To",
                "50 To" => "50 To",
                "75 To" => "75 To",
                "100 To" => "100 To",
                "500 To" => "500 To",
                "je ne sais pas" => "je ne sais pas",
                ],
        ]
    )
    ->add('sondJustifDonnPerm', TextAreaType::class, [ 'required'       =>  false ])
    ->add(
        'dataMetadataFormat',
        ChoiceType::class,
        [
        'label' => 'Format de métadonnées',
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "IVOA" => "IVOA",
                "OGC" => "OGC",
                "Dublin Core" => "DC",
                "Autre" => "Autre",
                "Je ne sais pas" => "je ne sais pas",
                "je ne suis pas intéressé.e" => "pas intéressé.e",
                ],
        ]
    )
             ->add(
                 'dataNombreDatasets',
                 ChoiceType::class,
                 [
        'label' => 'Estimation du nombre de datasets à partager',
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 10 datasets" => "< 10 datasets",
                "< 100 datasets" => "< 100 datasets",
                "< 1000 datasets" => "< 1000 datasets",
                "> 1000 datasets" => "> 1000 datasets",
                "Je ne sais pas" => "je ne sais pas",
                "je ne suis pas intéressé.e" => "pas intéressé.e",
                ],
        ]
             )
            ->add(
                'dataTailleDatasets',
                ChoiceType::class,
                [
        'label' => 'Taille moyenne approximative pour un dataset',
        'required'       =>  false,
        'placeholder'   =>  "-- Choisissez une option",
        'choices'  =>   [
                "< 100 Mo" => "<100 Mo",
                "< 500 Mo" => "< 500 Mo",
                "> 1 Go" => ">1 Go",
                "Je ne sais pas" => "je ne sais pas",
                                "je ne suis pas intéressé.e" => "pas intéressé.e"
                ],
        ]
            );
    }

    /* Les champs de la partie V */
    private function modifierType1PartieV($version, &$form, &$nb_form)
    {
        $em = $this->getDoctrine()->getManager();
        $formations = $em->getRepository(\App\Entity\Formation::class)->getFormationsPourVersion();

        $nb_form = 0;
        foreach ($formations as  $f) {
            $champ = 'demForm'.$f->getNumeroForm();
            $label = $f->getNomForm();
            $form->add($champ, IntegerType::class, [ 'required' => false, 'label' => $label ]);
            $nb_form++;
        }
        $form->add('demFormAutresAutres', TextAreaType::class, [ 'required' => false, 'label' => 'Vous pouvez préciser ici vos souhaits']);
    }

    /*
     * Appelée par modifierAction pour les projets de type 2 (PROJET_TEST)
     *
     * params = $request, $version
     *          $renouvellement (toujours false)
     *          $image_forms (formulaire de téléversement d'images)
     *          $collaborateurs_form (formulaire des collaborateurs)
     *
     */
    private function modifierType2(Request $request, Version $version, $renouvellement, $image_forms, $collaborateur_form, LoggerInterface $lg)
    {
        $sj = $this->sj;
        $sval = $this->vl;
        $em = $this->getDoctrine()->getManager();

        if ($this->has('heures_projet_test')) {
            $heures_projet_test = $this->getParameter('heures_projet_test');
        } else {
            $heures_projet_test =  5000;
        }

        $version->setDemHeures($heures_projet_test);
        $form_builder = $this->createFormBuilder($version)
        ->add('prjTitre', TextType::class, [ 'required'       =>  false ])
        ->add(
            'prjThematique',
            EntityType::class,
            [
                'required'       =>  false,
                'multiple' => false,
                'class' => 'App:Thematique',
                'label'     => '',
                'placeholder' => '-- Indiquez la thématique',
                ]
        );
        if ($this->getParameter('norattachement')==false) {
            $form_builder->add(
                'prjRattachement',
                EntityType::class,
                [
        'required'    => false,
        'multiple'    => false,
        'expanded'    => true,
        'class'       => 'App:Rattachement',
        'empty_data'  => null,
        'label'       => '',
        'placeholder' => 'AUCUN',
        ]
            );
        }
        $form_builder->add(
            'demHeures',
            IntegerType::class,
            [
            'required'       =>  false,
            'data' => $heures_projet_test,
            'disabled' => 'disabled' ]
        )
        ->add('prjResume', TextAreaType::class, [ 'required'       =>  false ])
        ->add('codeNom', TextType::class, [ 'required'       =>  false ])
        ->add('codeFor', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeC', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeCpp', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeAutre', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeLangage', TextType::class, [ 'required'       =>  false ])
        ->add('codeLicence', TextAreaType::class, [ 'required'       =>  false ])
        ->add('codeUtilSurMach', TextAreaType::class, [ 'required'       =>  false ])
        ->add('demLogiciels', TextAreaType::class, [ 'required'       =>  false ])
        ->add('demBib', TextAreaType::class, [ 'required'       =>  false ])
        ->add(
            'gpu',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                            "Oui" => "Oui",
                            "Non" => "Non",
                            "Je ne sais pas" => "je ne sais pas",
                            ],
            ]
        )
        ->add('fermer', SubmitType::class)
        ->add('annuler', SubmitType::class);

		$form = $form_builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // on sauvegarde tout de même mais il semble que c'est déjà fait avant
            $version->setDemHeures($heures_projet_test);
            $return = Functions::sauvegarder($version, $em, $lg);
            return $this->redirectToRoute('consulter_projet', ['id' => $version->getProjet()->getIdProjet() ]);
        }

        $version->setDemHeures($heures_projet_test);
        return $this->render(
            'version/modifier_projet_test.html.twig',
            [
        'form'      => $form->createView(),
        'version'   => $version,
        'collaborateur_form' => $collaborateur_form->createView(),
        'todo'      => $this->versionValidate($version),
        ]
        );
    }


    /*
     * Appelée par modifierAction pour les projets de type 3 (PROJET_FIL)
     *
     * params = $request, $version
     *          $renouvellement (toujours false)
     *          $image_forms (formulaire de téléversement d'images)
     *          $collaborateurs_form (formulaire des collaborateurs)
     *
     */
    private function modifierType3(Request $request, Version $version, $renouvellement, $image_forms, $collaborateur_form, LoggerInterface $lg)
    {
		# Même formulaire pour Type3 que pour Type1
		return $this->modifierType1($request,$version,$renouvellement, $image_forms, $collaborateur_form, $lg);
	}

    ////////////////////////////////////////////////////////////////////////////////////

    private function image_form($name, $csrf_protection = true)
    {
        $format_fichier = $this->imageConstraints();
        $form           = $this ->ff
                                ->createNamedBuilder($name, FormType::class, [], ['csrf_protection' => $csrf_protection ])
                                ->add('filename', HiddenType::class, [ 'data' => $name,])
                                ->add(
                                    'image',
                                    FileType::class,
                                    ['required' => false,
                                        'label' => 'Fig',
                                        'constraints'=>[$format_fichier] ]
                                )
                                ->getForm();
        return $form;
    }

    /////////////////////////////////////////////////////////////////////////////////////

    private function image_handle($form, Version $version, $request)
    {
        $sv = $this->sv;
        $sf = $this->sf;
        $sj = $this->sj;
        $em = $this->getDoctrine()->getManager();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) { //return ['etat' => 'OKK'];
            $filename               =    $form->getData()['filename'];
            $image                  =    $form['image']->getData();

            //$sj->debugMessage(__METHOD__ . ':' . __LINE__ .' form submitted filename = ' . $filename .' , image = ' . $image);

            $dir = $sv->imageDir($version);

            $full_filename          =    $dir .'/' . $filename;

            if (is_file($form->getData()['image'])) {
                $tempFilename = $form->getData()['image'];
                $this->redim_figure($tempFilename);
                //$sj->debugMessage(__METHOD__ . ':' . __LINE__ .' $tempFilename = ' . $form->getData()['image'] );
                $contents = file_get_contents($tempFilename);

                $file = new File($form->getData()['image']);


                if (file_exists($full_filename) && is_file($full_filename)) {
                    unlink($full_filename);
                }
                if (! file_exists($full_filename)) {
                    $file->move($dir, $filename);
                } else {
                    $sj->debugMessage('Version controller image_handle : mauvais fichier pour la version ' . $version->getIdVersion());
                }

                //$sj->debugMessage('file  ' .$filename . ' : ' .  base64_encode( $contents ) );

                return ['etat' => 'OK', 'contents' => $contents, 'filename' => $filename ];
            } else {
                $sj->debugMessage('VersionController:image_handle $tempFilename = (' . $form->getData()['image'] . ") n'existe pas");
                return ['etat' => 'KO'];
            }
        } elseif ($form->isSubmitted() && ! $form->isValid()) {
            //$sj->debugMessage(__METHOD__ . ':' . __LINE__ .' wrong form submitted filename = ' . $filename .' , image = ' . $image);
            if (isset($form->getData()['filename'])) {
                $filename   =  $form->getData()['filename'];
            } else {
                $filename   =  'unkonwn';
            }

            if (isset($form->getData()['image'])) {
                $image  =    $form->getData()['image'];
            } else {
                return ['etat' => 'nonvalide', 'filename' => $filename, 'error' => 'Erreur indeterminée' ];
            }

            return ['etat' => 'nonvalide', 'filename' => $filename, 'error' => $sf->formError($image, [ $this->imageConstraints() ]) ];

        //$sj->debugMessage('VersionController:image_handle form for ' . $filename . '('. $form->getData()['image'] . ') is not valide, error = ' .
            //    (string) $form->getErrors(true,false)->current() );
            //return ['etat' => 'nonvalide', 'filename' => $filename, 'error' => (string) $form->getErrors(true,false)->current() ];
            //return ['etat' => 'nonvalide', 'filename' => $filename, 'error' => (string) $form->getErrors(true,false)->current() ];
        } else {
            //$sj->debugMessage('VersionController:image_handle form not submitted');
            return ['etat' => null ];
        }
    }

    ///////////////////////////////////////////////////////////

    private function image($filename, Version $version)
    {
        $sv = $this->sv;
        $full_filename  = $sv->imagePath($filename, $version);

        if (file_exists($full_filename) && is_file($full_filename)) {
            //$sj->debugMessage('VersionController image  ' .$filename . ' : ' . base64_encode( file_get_contents( $full_filename ) )  );
            return base64_encode(file_get_contents($full_filename));
        } else {
            return null;
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////

    private function imageConstraints()
    {
        return new \Symfony\Component\Validator\Constraints\File(
            [
                'mimeTypes'=> [ 'image/jpeg', 'image/gif', 'image/png' ],
                'mimeTypesMessage'=>' Le fichier doit être un fichier jpeg, gif ou png. ',
                'maxSize' => "2048k",
                'uploadIniSizeErrorMessage' => ' Le fichier doit avoir moins de {{ limit }} {{ suffix }}. ',
                'maxSizeMessage' => ' Le fichier est trop grand ({{ size }} {{ suffix }}), il doit avoir moins de {{ limit }} {{ suffix }}. ',
                ]
        );
    }

    ///////////////////////////////////////////////////////////
    private function getCollaborateurForm(Version $version)
    {
        $sj = $this->sj;
        $em = $this->getDoctrine()->getManager();
        $sval= $this->vl;

        return $this->ff
                   ->createNamedBuilder('form_projet', FormType::class, [ 'individus' => self::prepareCollaborateurs($version, $sj, $sval) ])
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
                    ])
                    ->getForm();
    }

    /**
    * préparation de la liste des collaborateurs
    *
    * params = $version, $sj, $sval
    *
    * TODO - Une fonction statique horreur !
    *
    * return = Un tableau d'objets de type IndividuForm (cf Util\IndividuForm)
    *          Le responsable est dans la cellule 0 du tableau
    *
    *****************************************************************************/
    private static function prepareCollaborateurs(Version $version, ServiceJournal $sj, ValidatorInterface $sval)
    {
        if ($version == null) {
            $sj->throwException('VersionController:modifierCollaborateurs : version null');
        }

        $dataR  =   [];    // Le responsable est seul dans ce tableau
        $dataNR =   [];    // Les autres collaborateurs
        foreach ($version->getCollaborateurVersion() as $cv) {
            $individu = $cv->getCollaborateur();
            if ($individu == null) {
                $sj->errorMessage("VersionController:modifierCollaborateurs : collaborateur null pour CollaborateurVersion ".
                         $cv->getId());
                continue;
            } else {
                $individuForm = new IndividuForm($individu);
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

    /**
     * Avant de modifier les collaborateurs d'une version.
     * On demande de quelle version il s'agit !
     *
     * @Route("/{id}/avant_collaborateurs", name="avant_modifier_collaborateurs",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function avantModifierCollaborateursAction(Version $version, Request $request)
    {
        $sm = $this->sm;

        /* Si le bouton modifier est actif, il faut demander de quelle version il s'agit ! */
        $modifier_version_menu = $sm->modifier_version($version);
        if ($modifier_version_menu['ok'] == true) {
            $projet = $version->getProjet();
            $veract = $projet->getVersionActive();

            // Si pas de version active (nouveau projet) = pas de problème on redirige sur l'édition en cours
            if ($veract == null) {
                return $this->redirectToRoute('modifier_version', ['id' => $version, '_fragment' => 'liste_des_collaborateurs']);
            }

            // Peut arriver pour l'administrateur car pour lui le bouton modifier est toujours acitf
            elseif ($veract->getId() == $version->getId()) {
                return $this->redirectToRoute('modifier_version', ['id' => $version, '_fragment' => 'liste_des_collaborateurs']);
            }

            // Si version active on demande de préciser quelle version !
            else {
                return $this->render(
                    'version/avant_modifier_collaborateurs.html.twig',
                    [
                    'veract'  => $veract,
                    'version' => $version
                ]
                );
            }
        }

        // bouton modifier_version inactif: on modifie les collabs de la version demandée !
        else {
            return $this->redirectToRoute('modifier_collaborateurs', ['id' => $version]);
        }
    }

    /**
     * Modifier les collaborateurs d'une version.
     *
     * @Route("/{id}/collaborateurs", name="modifier_collaborateurs",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function modifierCollaborateursAction(Version $version, Request $request)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval= $this->vl;
        $em = $this->getDoctrine()->getManager();


        if ($sm->modifier_collaborateurs($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de modifier la liste des collaborateurs de la version " . $version .
                " parce que : " . $sm->modifier_collaborateurs($version)['raison']);
        }

        /* Si le bouton modifier est actif, on doit impérativement passer par le formulaire de la version ! */
        $modifier_version_menu = $sm->modifier_version($version);
        if ($modifier_version_menu['ok'] == true) {
            return $this->redirectToRoute($modifier_version_menu['name'], ['id' => $version, '_fragment' => 'liste_des_collaborateurs']);
        }


        $collaborateur_form = $this->ff
                                   ->createNamedBuilder('form_projet', FormType::class, [
                                       'individus' => self::prepareCollaborateurs($version, $sj, $sval)
                                   ])
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
                                   ])
                                   ->add('submit', SubmitType::class, [
                                        'label' => 'Sauvegarder',
                                   ])
                                   ->getForm();
        $collaborateur_form->handleRequest($request);

        $projet =  $version->getProjet();
        if ($projet != null) {
            $idProjet   =   $projet->getIdProjet();
        } else {
            $sj->errorMessage(__METHOD__ .':' . __LINE__ . " : projet null pour version " . $version->getIdVersion());
            $idProjet   =   null;
        }

        if ($collaborateur_form->isSubmitted() && $collaborateur_form->isValid()) {
            // Un formulaire par individu
            $individu_forms =  $collaborateur_form->getData()['individus'];
            $validated = $this->validateIndividuForms($individu_forms);
            if (! $validated) {
                return $this->render(
                    'version/collaborateurs_invalides.html.twig',
                    [
                    'projet' => $idProjet,
                    'version'   =>  $version,
                    'session'   =>  $version->getSession(),
                    ]
                );
            }
            // On traite les formulaires d'individus un par un
            $this->handleIndividuForms($individu_forms, $version);


            // return new Response( Functions::show( $resultat ) );
            // return new Response( print_r( $mail, true ) );
            //return new Response( print_r($request->request,true) );

            // SI ON VIRE ça ON N'A PLUS LES MAILS: POURQUOI ???????????????
            return $this->redirectToRoute(
                'modifier_collaborateurs',
                [
                'id'    => $version->getIdVersion() ,
            ]
            );
        }

        //return new Response( dump( $collaborateur_form->createView() ) );
        return $this->render(
            'version/collaborateurs.html.twig',
            [
             'projet' => $idProjet,
             'collaborateur_form'   => $collaborateur_form->createView(),
             'version'   =>  $version,
             'session'   =>  $version->getSession(),
         ]
        );
    }

    ///////////////////////////////////////////////////////////////////////////////////

    /**
     * Validation du formulaire des collaborateurs - Retourn true/false
     ***/
    private function validateIndividuForms($individu_forms, $definitif = false)
    {
        $coll_login = $this->getParameter('coll_login');

        $one_login = false;
        foreach ($individu_forms as  $individu_form) {
            if ($individu_form->getLogin()) {
                $one_login = true;
            }
            if ($definitif ==  true  &&
                (
                    $individu_form->getPrenom() == null   || $individu_form->getNom() == null
                || $individu_form->getEtablissement() == null
                || $individu_form->getLaboratoire() == null || $individu_form->getStatut() == null
                )
            ) {
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

    ////////////////////////////////////////////////////////////////////////////////////
    /***************************************
     * Traitement des formulaires des individus individuellement
     *
     * $individu_forms = Tableau contenant un formulaire par individu
     * $version        = La version considérée
     ****************************************************************/
    private function handleIndividuForms($individu_forms, Version $version)
    {
        $em   = $this->getDoctrine()->getManager();
        $sv   = $this->sv;
        $sj   = $this->sj;
        $sval = $this->vl;

        foreach ($individu_forms as $individu_form) {
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
                // On modifie l'individu
                $individu = $individu_form->modifyIndividu($individu, $sj);
                $em->persist($individu);

                // Il devient collaborateur
                if (! $version->isCollaborateur($individu)) {
                    $sj->infoMessage(__METHOD__ . ':' . __LINE__ .' individu ' .
                        $individu . ' ajouté à la version ' .$version);
                    $collaborateurVersion   =   new CollaborateurVersion($individu);
                    $collaborateurVersion->setDeleted(false);
                    $collaborateurVersion->setVersion($version);
                    if ($this->getParameter('coll_login')) {
                        $collaborateurVersion->setLogin($individu_form->getLogin());
                    };
                    if ($this->getParameter('nodata') == false) {
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
                    $sv->modifierLogin($version, $individu, $individu_form->getLogin(), $individu_form->getClogin());

                    // modification du labo du projet
                    if ($version->isResponsable($individu)) {
                        $sv->setLaboResponsable($version, $individu);
                    }

                    // modification éventuelle du flag deleted
                    $sv->syncDeleted($version, $individu, $individu_form->getDelete());
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
                }
            }

            // Ligne vide
            elseif ($individu_form->getMail() == null && $id == null) {
                $sj->debugMessage(__METHOD__ . ':' . __LINE__ . ' nouvel utilisateur vide ignoré');
            }
        }
    }

    /**
     * Demande de partage stockage ou partage des données
     *
     * @Route("/{id}/donnees", name="donnees",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method({"GET", "POST"})
     */
    public function donneesAction(Request $request, Version $version)
    {
        $sm = $this->sm;
        $sj = $this->sj;

        if ($sm->donnees($version)['ok'] == false) {
            $sj->throwException("VersionController:donneesAction Bouton donnees inactif " . $version->getIdVersion());
        }

        /* Si le bouton modifier est actif, on doit impérativement passer par là ! */
        $modifier_version_menu = $sm->modifier_version($version);
        if ($modifier_version_menu['ok'] == true) {
            return $this->redirectToRoute($modifier_version_menu['name'], ['id' => $version, '_fragment' => 'tab4']);
        }

        $form = $this->createFormBuilder($version);
        $this->modifierType1PartieIV($version, $form);
        $form
            ->add('valider', SubmitType::class)
            ->add('annuler', SubmitType::class);
        $form = $form->getForm();
        $projet =  $version->getProjet();
        if ($projet != null) {
            $idProjet   =   $projet->getIdProjet();
        } else {
            $sj->errorMessage(__METHOD__ .':' . __LINE__ . " : projet null pour version " . $version->getIdVersion());
            $idProjet   =   null;
        }

        // Pour traiter le retour d'une validation du formulaire
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('valider')->isClicked()) {
                //$sj->debugMessage("Entree dans le traitement du formulaire données");
                //$this->handleCallistoForms( $form, $version );
                $em = $this->getDoctrine()->getManager();
                $em->persist($version);
                $em->flush();
            }
            return $this->redirectToRoute('consulter_projet', ['id' => $projet->getIdProjet() ]);
        }
        /*
        if ($callisto_form->get('valider')->isClicked()) {
            static::handleCallistoForms( $callisto_form, $version );
        }
        */
        // Affichage du formulaire
        return $this->render(
            'version/donnees.html.twig',
            [
//            'usecase' => $usecase,
//            'session'   =>  $version->getSession(),
              'projet' => $projet,
//            'version'    => $version,
            'form'       => $form->createView(),
        ]
        );
    }

    ////////// Recupère et traite le retour du formulaire
    ////////// lié à l'écran données
    private function handleDonneesForms($form, Version $version)
    {
        $version->setDataMetaDataFormat($form->get('dataMetadataFormat')->getData());
        $version->setDataNombreDatasets($form->get('dataNombreDatasets')->getData());
        $version->setDataTailleDatasets($form->get('dataTailleDatasets')->getData());
        $em = $this->getDoctrine()->getManager();
        $em->persist($version);
        $em->flush();
    }

    /**
     * Displays a form to edit an existing version entity.
     *
     * @Route("/{id}/renouveler", name="renouveler_version",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method({"GET", "POST"})
     */
    public function renouvellementAction(Request $request, Version $version, LoggerInterface $lg)
    {
        $sm = $this->sm;
        $sv = $this->sv;
        $sj = $this->sj;
        $projet_workflow = $this->pw;
        $em = $this->getDoctrine()->getManager();

        // ACL
        //if( $sm->renouveler_version($version)['ok'] == false && (  $this->container->hasParameter('kernel.debug') && $this->getParameter('kernel.debug') == false ) )
        if ($sm->renouveler_version($version)['ok'] == false) {
            $sj->throwException("VersionController:renouvellementAction impossible de renouveler la version " . $version->getIdVersion());
        }

        $session = $em->getRepository(Session::class)->findOneBy([ 'etatSession' => Etat::EDITION_DEMANDE ]);
        $this->get('session')->remove('SessionCourante');
        if ($session != null) {
            $idVersion = $session->getIdSession() . $version->getProjet()->getIdProjet();
            if ($em->getRepository(Version::class)->findOneBy([ 'idVersion' =>  $idVersion]) != null) {
                $sj->errorMessage("VersionController:renouvellementAction version " . $idVersion . " existe déjà !");
                return $this->redirect($this->generateUrl('modifier_version', [ 'id' => $version->getIdVersion() ]));
            } else {
                $old_dir = $sv->imageDir($version);
                // nouvelle version
                $projet = $version->getProjet();
                //$em->detach( $version );
                $new_version = clone $version;
                //$em->detach( $new_version );

                $new_version->setSession($session);

                // Mise à zéro de certains champs
                $new_version->setDemHeures(0);
                $new_version->setPrjJustifRenouv(null);
                $new_version->setAttrHeures(0);
                $new_version->setAttrHeuresEte(0);
                $new_version->setAttrAccept(false);
                $new_version->setPenalHeures(0);
                $new_version->setPrjGenciCentre('');
                $new_version->setPrjGenciDari('');
                $new_version->setPrjGenciHeures('');
                $new_version->setPrjGenciMachines('');
                $new_version->setPrjFicheVal(false);
                $new_version->setPrjFicheLen(0);
                $new_version->setRapConf(0);
                $new_version->setCgu(0);

                $new_version->setIdVersion($session->getIdSession() . $version->getProjet()->getIdProjet());
                $new_version->setProjet($version->getProjet());
                $new_version->setEtatVersion(Etat::CREE_ATTENTE);
                $sv->setLaboResponsable($new_version, $version->getResponsable());

                // nouvelles collaborateurVersions
                Functions::sauvegarder($new_version, $em, $lg);

                $collaborateurVersions = $version->getCollaborateurVersion();
                foreach ($collaborateurVersions as $collaborateurVersion) {

                    // ne pas reprendre un collaborateur sans login et marqué comme supprimé
                    // Attention un collaborateurVersion avec login = false mais loginname renseigné signifie ue le compte
                    // n'a pas encore été détruit: dans ce cas on le reprends !'
                    if ($collaborateurVersion->getDeleted() &&
                        $collaborateurVersion->getClogin() === false &&
                        $collaborateurVersion->getLoginname() === null ) continue;

                    $newCollaborateurVersion    = clone  $collaborateurVersion;
                    //$em->detach( $newCollaborateurVersion );
                    $newCollaborateurVersion->setVersion($new_version);
                    $em->persist($newCollaborateurVersion);
                }

                //On ne fait rien car ce sera fait dans l'EventListener !
                // $projet->setVersionDerniere( $new_version );
                $projet_workflow->execute(Signal::CLK_DEMANDE, $projet);

                // Remettre à false Nepasterminer qui n'a pas trop de sens ici
                $projet->setNepasterminer(false);
                $em->persist($projet);
                $em->flush();

                // images: On reprend les images "img_expose" de la version précédente
                //         On ne REPREND PAS les images "img_justif_renou" !!!
                $new_dir = $sv->imageDir($new_version);
                for ($id=1;$id<4;$id++) {
                    $f='img_expose_'.$id;
                    $old_f = $old_dir . '/' . $f;
                    $new_f = $new_dir . '/' . $f;
                    if (is_file($old_f)) {
                        $rvl = copy($old_f, $new_f);
                        if ($rvl==false) {
                            $sj->errorMessage("VersionController:erreur dans la fonction copy $old_f => $new_f");
                        }
                    }
                }
                return $this->redirect($this->generateUrl('modifier_version', [ 'id' => $new_version->getIdVersion() ]));
            }
        } else {
            $sj->errorMessage("VersionController:renouvellementAction il n'y a pas de session en état EDITION_DEMANDE !");
            return $this->redirect($this->generateUrl('modifier_version', [ 'id' => $version->getIdVersion() ]));
        }
    }

    /**
     * Validation du formulaire de version
     *
     *    param = Version
     *            $em l'entity manager
     *    return= Un array contenant la "todo liste", ie la liste de choses à faire pour que le formulaire soit validé
     *            Un array vide [] signifie: "Formulaire validé"
     **/
    private function versionValidate(Version $version)
    {
        $em   = $this->getDoctrine()->getManager();
        $nodata = $this->getParameter('nodata');

        $todo   =   [];
        if ($version->getPrjTitre() == null) {
            $todo[] = 'prj_titre';
        }
        if ($version->getDemHeures() == null) {
            $todo[] = 'dem_heures';
        }
        if ($version->getDemHeuresGpu() === null) {
            $todo[] = 'dem_heures_gpu';
        }
        if ($version->getPrjThematique() == null) {
            $todo[] = 'prj_id_thematique';
        }
        if ($version->getPrjResume() == null) {
            $todo[] = 'prj_resume';
        }
        if ($version->getCodeNom() == null) {
            $todo[] = 'code_nom';
        }
        if ($version->getCodeLicence() == null) {
            $todo[] = 'code_licence';
        }
        if ($version->getGpu() == null) {
            $todo[] = 'gpu';
        }

        // Pas de projets test
        //if (! $version->isProjetTest()) {
        if ($version->getPrjExpose() == null) {
            $todo[] = 'prj_expose';
        }
        if ($version->getCodeHeuresPJob() == null) {
            $todo[] = 'code_heures_p_job';
        }
        if ($version->getCodeRamPCoeur() == null) {
            $todo[] = 'code_ram_p_coeur';
        }
        if ($version->getCodeRamPart() == null) {
            $todo[] = 'code_ram_part';
        }

        if ($version->getCodeEffParal() == null) {
            $todo[] = 'code_eff_paral';
        }
        if ($version->getCodeVolDonnTmp() == null) {
            $todo[] = 'code_vol_donn_tmp';
        }
        if ($version->getCodeVolDonnUsr() == null) {
            $todo[] = 'code_vol_donn_usr';
        }
        if ($version->getCodeNbFichTmp() == null) {
            $todo[] = 'code_nb_fich_tmp';
        }
        if ($version->getCodeNbFichPerm() == null) {
            $todo[] = 'code_nb_fich_perm';
        }
        if ($version->getDemPostTrait() == null) {
            $todo[] = 'dem_post_trait';
        }

        // s'il s'agit d'un renouvellement
        if (count($version->getProjet()->getVersion()) > 1 && $version->getPrjJustifRenouv() == null) {
            $todo[] = 'prj_justif_renouv';
        }

        // Centres nationaux
        if ($version->getPrjGenciCentre()     == null
            || $version->getPrjGenciMachines() == null
            || $version->getPrjGenciHeures()   == null
            || $version->getPrjGenciDari()     == null) {
            $todo[] = 'genci';
        };

        // Partage de données
        if ($nodata == false) {		// Stockage de données
            if ($version->getSondVolDonnPerm() == null) {
                $todo[] = 'sond_vol_donn_perm';
            } elseif ($version->getSondJustifDonnPerm() == null
            &&  $version->getSondVolDonnPerm() != '< 1To'
            &&  $version->getSondVolDonnPerm() != '1 To'
            &&  $version->getSondVolDonnPerm() !=  'je ne sais pas') {
                $todo[] = 'sond_justif_donn_perm';
            }

            if ($version->getDataMetaDataFormat() == null) {
                $todo[] = 'Format de métadonnées';
            }
            if ($version->getDataNombreDatasets() == null) {
                $todo[] = 'Nombre de jeux de données';
            }
            if ($version->getDataTailleDatasets() == null) {
                $todo[] = 'Taille de chaque jeu de données';
            }
        }
        
        if ($this->getParameter('rapport_dactivite')) {
            if ($version->typeSession()  == 'A') {
                $version_precedente = $version->versionPrecedente();
                if ($version_precedente != null) {
                    $rapportActivite = $em->getRepository(RapportActivite::class)->findOneBy(
                        [
                        'projet' => $version_precedente->getProjet(),
                        'annee' => $version_precedente->getAnneeSession(),
                    ]
                    );
                    if ($rapportActivite == null) {
                        $todo[] = 'rapport_activite';
                    }
                }
            }
        }

        if (! $this->validateIndividuForms(self::prepareCollaborateurs($version, $this->sj, $this->vl), true)) {
            $todo[] = 'collabs';
        }

        return $todo;
    }

    /***
     * Redimensionne une figure
     *
     *  params $image, le chemin vers un fichier image
     *
     */
    private function redim_figure($image)
    {
        $cmd = "identify -format '%w %h' $image";
        //$sj->debugMessage('redim_figure cmd identify = ' . $cmd);
        $format = `$cmd`;
        list($width, $height) = explode(' ', $format);
        $width = intval($width);
        $height= intval($height);
        $rap_w = 0;
        $rap_h = 0;
        $rapport = 0;      // Le rapport de redimensionnement

        $max_fig_width = $this->getParameter('max_fig_width');
        if ($width > $max_fig_width && $max_fig_width > 0) {
            $rap_w = (1.0 * $width) /  $max_fig_width;
        }

        $max_fig_height = $this->getParameter('max_fig_height');
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
            //$sj->debugMessage('redim_figure cmd convert = ' . $cmd);
            `$cmd`;
        }
    }
}
