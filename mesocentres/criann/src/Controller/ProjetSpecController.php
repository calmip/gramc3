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

use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Session;
use App\Entity\CollaborateurVersion;
use App\Entity\Formation;
use App\Entity\User;
use App\Entity\Thematique;
use App\Entity\Rattachement;
use App\Entity\Expertise;
use App\Entity\Individu;
use App\Entity\Sso;
use App\Entity\CompteActivation;
use App\Entity\Journal;
use App\Entity\Compta;

use App\GramcServices\Workflow\Projet\ProjetWorkflow;
use App\GramcServices\Workflow\Version\VersionWorkflow;
use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceNotifications;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServiceVersions;
use App\GramcServices\ServiceExperts\ServiceExperts;
use App\GramcServices\GramcDate;
use App\GramcServices\GramcGraf\CalculTous;
use App\GramcServices\GramcGraf\Stockage;
use App\GramcServices\GramcGraf\Calcul;

use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Config\Definition\Exception\Exception;
use App\Utils\Functions;
use App\GramcServices\Etat;
use App\GramcServices\Signal;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use Doctrine\ORM\EntityManagerInterface;

use Twig\Environment;

/**
 * Projet controller.
 *
 * Les méthodes liées aux projets mais SPECIFIQUES à un mésocentre particulier
 *
 * @Route("projet")
 */
 // Tous ces controleurs sont exécutés au moins par OBS, certains par ADMIN seulement
 // et d'autres par DEMANDEUR

class ProjetSpecController extends AbstractController
{
    private $token;
    public function __construct(
        private ServiceJournal $sj,
        private ServiceMenus $sm,
        private ServiceProjets $sp,
        private ServiceSessions $ss,
        private Calcul $gcl,
        private Stockage $gstk,
        private CalculTous $gall,
        private GramcDate $sd,
        private ServiceVersions $sv,
        private ServiceExperts $se,
        private ProjetWorkflow $pw,
        private FormFactoryInterface $ff,
        private TokenStorageInterface $tok,
        private Environment $tw,
        private AuthorizationCheckerInterface $ac,
        private EntityManagerInterface $em
    )
    {
        $this->token = $tok->getToken();
    }

    /**
     * Montre les projets d'un utilisateur
     *
     * @Route("/accueil", name="projet_accueil",methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function accueilAction()
    {
        $sm                  = $this->sm;
        $ss                  = $this->ss;
        $sp                  = $this->sp;
        $token               = $this->token;
        $em                  = $this->em;
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
                    $pwd_expir = null;
                } else {
                    $passwd = $u->getPassword();
                    $passwd = Functions::simpleDecrypt($passwd);
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
        //    $menu[] = $sm->nouveauProjet($t);
        //}

        $etat = 3;
        if ($ss->getSessionCourante()->getEtatSession() == Etat::EDITION_DEMANDE)
        {
            $etat = 1;
        }
        $menu[] = $sm->nouveauProjet($etat);
        $menu = [];
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
     * Affiche un projet avec un menu pour choisir la version
     *
     * @Route("/{id}/consulter", name="consulter_projet",methods={"GET","POST"})
     * @Route("/{id}/consulter/{version}", name="consulter_version",methods={"GET","POST"})
     * 
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */

     // SUPPRIME ! PASSE PAR LA SESSION ! Route("/{id}/consulter/{warn_type}", name="consulter_projet",methods={"GET","POST"})
    public function consulterAction(Projet $projet, Version $version = null, Request $request, $warn_type=0)
    {
        $em = $this->em;
        $sp = $this->sp;
        $sj = $this->sj;
        $coll_vers_repo= $em->getRepository(CollaborateurVersion::class);
        $token = $this->token;

        // On récupère warn_type depuis la session, où il peut avoir été sauvegardé !
        // NOTE - warn_type NON UTILISE par le Criann
        $warn_type = $request->getSession()->get('warn_type');
        if (empty($warn_type)) $warn_type = 0;

        // choix de la version
        if ($version == null) {
            $version =  $projet->getVersionDerniere();
            if ($version == null) {
                $sj->throwException(__METHOD__ . ':' . __LINE__ .' Projet ' . $projet . ': la dernière version est nulle !');
            }
        } else {
            $projet =   $version->getProjet();
        } // nous devons être sûrs que le projet corresponde à la version

        if (! $sp->projetACL($projet)) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        // Calcul du loginname, pour affichage de la conso
        $loginname = null;
        $cv = $coll_vers_repo->findOneBy(['version' => $version, 'collaborateur' => $token->getUser()]);
        if ($cv != null) {
            $loginname = $cv -> getLoginname() == null ? 'nologin' : $cv -> getLoginname();
        } else {
            $loginname = 'nologin';
        }

        //            if ($loginname == 'nologin')
        //            {
        //                $sj->throwException(__METHOD__ . ':' . __LINE__ ." Projet $projet Année $annee Pas d'utilisateurs !");
        //            }

        // LA SUITE DEPEND DU TYPE DE PROJET !
        // Pareil pour projets de type 1 et de type 3
        $type = $projet->getTypeProjet();
        switch ($type) {
            case Projet::PROJET_SESS:
                return $this->consulterType1_3($projet, $version, $loginname, $request, $warn_type);
            case Projet::PROJET_TEST:
                return $this->consulterType2($projet, $version, $loginname, $request, $warn_type);
            case Projet::PROJET_FIL:
                return $this->consulterType1_3($projet, $version, $loginname, $request, $warn_type);
            default:
                $sj->errorMessage(__METHOD__ . " Type de projet inconnu: $type");
        }
    }

    // Consulter les projets de type 1 (projets PROJET_SESS) ou type 3 (PROJET_FIL)
    private function consulterType1_3(Projet $projet, Version $version, $loginname, Request $request, $warn_type)
    {
        $em = $this->em;
        $sm = $this->sm;
        $sp = $this->sp;
        $ac = $this->ac;
        $sv = $this->sv;
        $sj = $this->sj;
        $ff = $this->ff;


        $session_form = Functions::createFormBuilder($ff, ['version' => $version ])
        ->add(
            'version',
            EntityType::class,
            [
            'multiple' => false,
            'class'    => Version::class,
            'required' =>  true,
            'label'    => '',
            'choices'  =>  $projet->getVersion(),
            'choice_label' => function ($version) {
                return $version->getSession();
            }
            ]
        )
    ->add('submit', SubmitType::class, ['label' => 'Changer'])
    ->getForm();

        $session_form->handleRequest($request);

        if ($session_form->isSubmitted() && $session_form->isValid()) {
            $version = $session_form->getData()['version'];
        }

        $session = null;
        if ($version != null) {
            $session = $version->getSession();
        } else {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' projet ' . $projet . ' sans version');
        }

        $menu = [];
        if ($ac->isGranted('ROLE_ADMIN')) {
            $menu[] = $sm->nouvelleRallonge($projet);
        }
        $menu[] = $sm->changerResponsable($version);
        $menu[] = $sm->renouvelerVersion($version);
        $menu[] = $sm->modifierVersion($version);
        $menu[] = $sm->envoyerEnExpertise($version);
        $menu[] = $sm->modifierCollaborateurs($version);
        if ($this->getParameter('nodata')==false) {
            $menu[] = $sm->donnees($version);
        }
        $menu[] = $sm->telechargerFiche($version,ServiceMenus::BPRIO);
        $menu[] = $sm->televerserFiche($version,ServiceMenus::BPRIO);

        $etat_version = $version->getEtatVersion();
        if ($this->getParameter('rapport_dactivite')) {
            if (($etat_version == Etat::ACTIF || $etat_version == Etat::TERMINE) && ! $sp->hasRapport($projet, $version->getAnneeSession())) {
                $menu[] = $sm->telechargerModeleRapportDactivite($version,ServiceMenus::BPRIO);
                $menu[] = $sm->televerserRapportAnnee($version,ServiceMenus::BPRIO);
            }
        }

        $menu[]       = $sm->gererPublications($projet);
        $img_expose = [
            $sv->imageProperties('img_expose_1', 'Figure 1', $version),
            $sv->imageProperties('img_expose_2', 'Figure 2', $version),
            $sv->imageProperties('img_expose_3', 'Figure 3', $version),
        ];
        $document     = $sv->getdocument($version);

        $img_justif_renou = [
            $sv->imageProperties('img_justif_renou_1', 'Figure 1', $version),
            $sv->imageProperties('img_justif_renou_2', 'Figure 2', $version),
            $sv->imageProperties('img_justif_renou_3', 'Figure 3', $version)
        ];

        $toomuch = false;
        if ($session->getLibelleTypeSession()=='B' && ! $sv->isNouvelle($version)) {
            $version_prec = $version->versionPrecedente();
            if ($version_prec->getAnneeSession() == $version->getAnneeSession()) {
                $toomuch  = $sv -> is_demande_toomuch($version_prec->getAttrHeures(), $version->getDemHeures());
            }
        }
        $rapport_1 = $sp -> getRapport($projet, $version->getAnneeSession() - 1);
        $rapport   = $sp -> getRapport($projet, $version->getAnneeSession());

        $formation = $sv->buildFormations($version);

        if ($projet->getTypeProjet() == Projet::PROJET_SESS) {
            $tmpl = 'projet/consulter_projet_sess.html.twig';
        } else {
            $tmpl = 'projet/consulter_projet_fil.html.twig';
        }
        
        return $this->render(
            $tmpl,
            [
                'warn_type' => $warn_type,
                'projet' => $projet,
                'loginname' => $loginname,
                'version_form' => $session_form->createView(),
                'version' => $version,
                'session' => $session,
                'menu' => $menu,
                'img_expose' => $img_expose,
                'img_justif_renou' => $img_justif_renou,
                'conso_cpu' => $sp->getConsoRessource($projet, 'cpu', $version->getAnneeSession()),
                'conso_gpu' => $sp->getConsoRessource($projet, 'gpu', $version->getAnneeSession()),
                'rapport_1' => $rapport_1,
                'rapport' => $rapport,
                'document' => $document,
                'toomuch' => $toomuch,
                'formation' => $formation
            ]
        );
    }

    // Consulter les projets de type 2 (projets test)
    // PAS UTILISE
    // TODO - Supprimer cette méthode !
    private function consulterType2(Projet $projet, Version $version, $loginname, Request $request)
    {
        $sm = $this->sm;
        $sp = $this->sp;
        $ac = $this->ac;

        if ($ac->isGranted('ROLE_ADMIN')) {
            $menu[] = $sm->nouvelleRallonge($projet);
        }
        $menu[] = $sm->modifierVersion($version);
        $menu[] = $sm->envoyerEnExpertise($version);
        $menu[] = $sm->modifierCollaborateurs($version);

        return $this->render(
            'projet/consulter_projet_test.html.twig',
            [
            'projet'      => $projet,
            'version'     => $version,
            'session'     => $version->getSession(),
            'consocalcul' => $sp->getConsoCalculVersion($version),
            'quotacalcul' => $sp->getQuotaCalculVersion($version),
            'conso_cpu'   => $sp->getConsoRessource($projet, 'cpu', $version->getAnneeSession()),
            'conso_gpu'   => $sp->getConsoRessource($projet, 'gpu', $version->getAnneeSession()),
            'menu'        => $menu,
            ]
        );
    }
}
