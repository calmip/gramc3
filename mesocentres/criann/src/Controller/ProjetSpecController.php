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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Config\Definition\Exception\Exception;
use App\Utils\Functions;
use App\Utils\Etat;
use App\Utils\Signal;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use Twig\Environment;

/**
 * Projet controller.
 *
 * @Route("projet")
 */
 // Tous ces controleurs sont exécutés au moins par OBS, certains par ADMIN seulement
 // et d'autres par DEMANDEUR

class ProjetSpecController extends AbstractController
{
    private $sj;
    private $sm;
    private $sp;
    private $ss;
    private $gcl;
    private $gstk;
    private $gall;
    private $sd;
    private $sv;
    private $se;
    private $pw;
    private $ff;
    private $token;
    private $sss;
    private $tw;
    private $ac;

    public function __construct(
        ServiceJournal $sj,
        ServiceMenus $sm,
        ServiceProjets $sp,
        ServiceSessions $ss,
        Calcul $gcl,
        Stockage $gstk,
        CalculTous $gall,
        GramcDate $sd,
        ServiceVersions $sv,
        ServiceExperts $se,
        ProjetWorkflow $pw,
        FormFactoryInterface $ff,
        TokenStorageInterface $tok,
        SessionInterface $sss,
        Environment $tw,
        AuthorizationCheckerInterface $ac
    ) {
        $this->sj  = $sj;
        $this->sm  = $sm;
        $this->sp  = $sp;
        $this->ss  = $ss;
        $this->gcl = $gcl;
        $this->gstk= $gstk;
        $this->gall= $gall;
        $this->sd  = $sd;
        $this->sv  = $sv;
        $this->se  = $se;
        $this->pw  = $pw;
        $this->ff  = $ff;
        $this->token= $tok->getToken();
        $this->sss = $sss;
        $this->tw  = $tw;
        $this->ac  = $ac;
    }

    /**
     * Montre les projets d'un utilisateur
     *
     * @Route("/accueil", name="projet_accueil")
     * @Route("/accueil/", name="projet_accueil1")
     * @Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function accueilAction()
    {
        $sm                  = $this->sm;
        $ss                  = $this->ss;
        $sp                  = $this->sp;
        $token               = $this->token;
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

		$etat = 3;
		if ($ss->getSessionCourante()->getEtatSession() == Etat::EDITION_DEMANDE)
		{
			$etat = 1;
		}
        $menu[] = $sm->nouveau_projet($etat);
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
}
