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

use App\Entity\Expertise;
use App\Entity\CommentaireExpert;
use App\Entity\Individu;
use App\Entity\Thematique;
use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Rallonge;
use App\Entity\Session;
use App\Entity\CollaborateurVersion;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Utils\Functions;
use App\Utils\Menu;
use App\Utils\Etat;
use App\Utils\Signal;
use App\AffectationExperts\AffectationExperts;

use App\GramcServices\Workflow\Projet\ProjetWorkflow;
use App\GramcServices\Workflow\Version\VersionWorkflow;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceNotifications;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServiceVersions;
use App\GramcServices\ServiceExperts\ServiceExperts;
use App\GramcServices\GramcDate;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use App\Form\ChoiceList\ExpertChoiceLoader;

/**
 * Expertise controller.
 *
 * @Route("expertise")
 */
class ExpertiseController extends AbstractController
{

		private $sn;
		private $sj;
		private $sp;
		private $ss;
		private $sd;
		private $sv;
		private $pw;
		private $ff;
		private $vl;
		private $se;
		private $tok;
		private $ac;
	
	
	public function __construct (ServiceNotifications $sn,
								 ServiceJournal $sj,
								 ServiceProjets $sp,
								 ServiceSessions $ss,
								 GramcDate $sd,
								 ServiceVersions $sv,
								 ServiceExperts $se,
								 ProjetWorkflow $pw,
								 FormFactoryInterface $ff,
								 ValidatorInterface $vl,
								 TokenStorageInterface $tok,
								 AuthorizationCheckerInterface $ac
								 )
	{
		$this->sn  = $sn;
		$this->sj  = $sj;
		$this->sp  = $sp;
		$this->ss  = $ss;
		$this->sd  = $sd;
		$this->sv  = $sv;
		$this->se  = $se;
		$this->pw  = $pw;
		$this->ff  = $ff;
		$this->vl  = $vl;
		$this->tok = $tok->getToken();
		$this->ac  = $ac;
	}

	/**
     * Affectation des experts
     *
     * @Route("/affectation_test", name="affectation_test")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function affectationTestAction(Request $request)
    {
		$ss = $this->ss;
		$sp = $this->sp;
		$se = $this->se;
		$em = $this->getDoctrine()->getManager();
		
	    $session  = $ss->getSessionCourante();
	    $annee    = $session->getAnneeSession();
	    $versions =  $em->getRepository(Version::class)->findAnneeTestVersions($annee);
        $etatSession = $session->getEtatSession();

		$affectationExperts = $se;
		//new AffectationExperts($request, $versions, $this->get('form.factory'), $this->getDoctrine());
		$affectationExperts->setDemandes($versions);
		
		//
		// 1ere etape = Traitement des formulaires qui viennent d'être soumis
		//              On boucle sur les versions:
		//                  - Une version non sélectionnée est ignorée
		//                  - Pour chaque version sélectionnée on fait une action qui dépend du bouton qui a été cliqué
		//              Puis on redirige sur la page
		//
		$form_buttons = $affectationExperts->getFormButtons();
		$form_buttons->handleRequest($request);
		if ($form_buttons->isSubmitted())
		{
			$affectationExperts->traitementFormulaires($request);
			// doctrine cache les expertises précédentes du coup si on ne redirige pas
			// l'affichage ne sera pas correctement actualisé !
			// Essentiellement avec sub3 (ajout d'expertise)
			return $this->redirectToRoute('affectation_test');
		}

		// 2nde étape = Création des formulaires pour affichage et génération des données de "stats"
		$forms       = $affectationExperts->getExpertsForms();
		$stats       = $affectationExperts->getStats();
		$stats['nouveau'] = null;
		$attHeures   = $affectationExperts->getAttHeures();

        foreach( $versions as $version )
        {
			$id_version                  = $version->getIdVersion();
			$projet                      = $version->getProjet();
			$version_suppl               = [];
			$version_suppl['metaetat']   = $sp->getMetaEtat($projet);
			$version_suppl['consocalcul']= $sp->getConsoCalculVersion($version);
			$version_suppl['isnouvelle'] = true;

			$versions_suppl[$id_version] = $version_suppl;
		}
		
		$titre = "Affectation des experts aux projets tests de l'année 20$annee"; 
        return $this->render('expertise/affectation.html.twig', 
            [
            'titre'         => $titre,
            'versions'      => $versions,
            'versions_suppl'=> $versions_suppl,
            'forms'         => $forms,
            'sessionForm'   => null,
            'thematiques'   => null,
            'rattachements' => null,
            'experts'       => null,
            'stats'         => $stats,
            'attHeures'     => $attHeures,
            ]);
    }

    ///////////////////////

    /**
     * Affectation des experts
     *	  Affiche l'écran d'affectation des experts
     *
     * @Route("/affectation", name="affectation")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function affectationAction(Request $request)
    {
		$ss = $this->ss;
		$sp = $this->sp;
		$sv = $this->sv;
		$em = $this->getDoctrine()->getManager();
		$affectationExperts = $this->se;
		
		$session      = $ss->getSessionCourante();
        $session_data = $ss->selectSession($this->createFormBuilder(['session'=>$session]),$request); // formulaire
        $session      = $session_data['session']!=null?$session_data['session']:$session;
        $session_form = $session_data['form'];
	    $versions     = $em->getRepository(Version::class)->findSessionVersions($session);
        $etatSession  = $session->getEtatSession();

		//$affectationExperts = new AffectationExperts($request, $versions, $this->get('form.factory'), $this->getDoctrine());
		$affectationExperts->setDemandes($versions);
		
		//
		// 1ere etape = Traitement des formulaires qui viennent d'être soumis
		//              On boucle sur les versions:
		//                  - Une version non sélectionnée est ignorée
		//                  - Pour chaque version sélectionnée on fait une action qui dépend du bouton qui a été cliqué
		//              Puis on redirige sur la page
		//
		$form_buttons = $affectationExperts->getFormButtons();
		$form_buttons->handleRequest($request);
		if ($form_buttons->isSubmitted())
		{
			$affectationExperts->traitementFormulaires($request);
			// doctrine cache les expertises précédentes du coup si on ne redirige pas
			// l'affichage ne sera pas correctement actualisé !
			// Essentiellement avec sub3 (ajout d'expertise)
			return $this->redirectToRoute('affectation');
		}

		// 2nde étape = Création des formulaires pour affichage et génération des données de "stats"
	    $thematiques   = $affectationExperts->getTableauThematiques();
	    $rattachements = $affectationExperts->getTableauRattachements();
	    $experts       = $affectationExperts->getTableauExperts();
		$forms         = $affectationExperts->getExpertsForms();
		$stats         = $affectationExperts->getStats();
		$attHeures     = $affectationExperts->getAttHeures($versions);
		
		$versions_suppl   = [];
        foreach( $versions as $version )
        {
			$id_version                  = $version->getIdVersion();
			$projet                      = $version->getProjet();
			$version_suppl               = [];
			$version_suppl['metaetat']   = $sp->getMetaEtat($projet);
			$version_suppl['consocalcul']= $sp->getConsoCalculVersion($version);
			$version_suppl['isnouvelle'] = $sv->isNouvelle($version);

			$versions_suppl[$id_version] = $version_suppl;
		}
		
		$sessionForm      = $session_data['form']->createView();
		$titre            = "Affectation des experts aux projets de la session " . $session;
        return $this->render('expertise/affectation.html.twig',
            [
            'titre'         => $titre,
            'versions'      => $versions,
            'versions_suppl'=> $versions_suppl,
            'forms'         => $forms,
            'sessionForm'   => $sessionForm,
            'thematiques'   => $thematiques,
            'rattachements' => $rattachements,
            'experts'       => $experts,
            'stats'         => $stats,
            'attHeures'     => $attHeures,
            ]);
    }

    /**
     * Lists all expertise entities.
     *
     * @Route("/", name="expertise_index")
     * @Method("GET")
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $expertises = $em->getRepository(Expertise::class)->findAll();
        $projets =  $em->getRepository(Projet::class)->findAll();


        return $this->render('expertise/index.html.twig',
            [
            'expertises' => $expertises,
            ]);
    }

	// Helper function used by listeAction
	private static function exptruefirst($a,$b) { 
		if ($a['expert']==true  && $b['expert']==false) return -1;
		if ($a['projetId'] < $b['projetId']) return -1;
		return 1;
	}

	/**
	 * Liste les expertises attribuées à un expert
	 *       Aussi les anciennes expertises réalisées par cet expert
	 *
	 * @Route("/liste", name="expertise_liste")
	 * @Method("GET")
	 * @Security("is_granted('ROLE_EXPERT')")
	 */
    public function listeAction()
    {
		$sd  = $this->sd;
		$ss  = $this->ss;
		$sp  = $this->sp;
		$sj  = $this->sj;
		$tok = $this->tok;
        $em  = $this->getDoctrine()->getManager();
        
        $moi = $tok->getUser();
        if( is_string( $moi ) ) $sj->throwException();

        $mes_thematiques     = $moi->getThematique();
        $expertiseRepository = $em->getRepository(Expertise::class);
        $session             = $ss->getSessionCourante();

		// Les expertises affectées à cet expert
        $expertises  = $expertiseRepository->findExpertisesByExpert($moi, $session);
        
        # Pour une session B on lit aussi les expertises de la session A (il peut y avoir des projets tests qui trainent)
        if ($session->getTypeSession())
        {
			$id_sessionA= $session->getAnneeSession().'A';
			$sessionRepository = $em->getRepository(Session::class);
			$sessionA   = $sessionRepository->findOneBy( ['idSession' => $id_sessionA ] );
			if ($sessionA != null)
			{
				$expertisesA= $expertiseRepository->findExpertisesByExpert($moi, $sessionA);
				$expertisesB= $expertises;
				$expertises = array_merge($expertisesA,$expertisesB);
			}
		}
		
        $my_expertises  =   [];
        foreach( $expertises as $expertise )
        {
            // On n'affiche pas les expertises définitives
            if ( $expertise->getDefinitif()) continue;

            $version    =   $expertise->getVersion();
            $projetId   =   $version->getProjet()->getIdProjet();
            $thematique =   $version->getPrjThematique();

            $my_expertises[ $version->getIdVersion() ] = [
							    'expertise' => $expertise,
							    'demHeures' => $version->getDemHeures(),
							    'versionId' => $version->getIdVersion(),
							    'projetId'  => $projetId,
							    'titre'     => $version->getPrjTitre(),
							    'thematique'    => $thematique,
							    'responsable'   => $version->getResponsable(),
							    'expert'        => true,
                                                         ];
        }

		//$sj->debugMessage(__METHOD__ . " my_expertises " . Functions::show($my_expertises));
		// $sj->debugMessage(__METHOD__ . " mes_thematiques " . Functions::show($mes_thematiques). " count=" . count($mes_thematiques->ToArray()));
	
        // Les projets associés à une de mes thématiques
        $expertises_by_thematique   =   [];
        foreach( $mes_thematiques as $thematique )
        {
            // $expertises_thematique =  $expertiseRepository->findExpertisesByThematique($thematique, $session);
            $expertises_thematique =  $expertiseRepository->findExpertisesByThematiqueForAllSessions($thematique);
            //$sj->debugMessage(__METHOD__ . " expertises pour thématique ".Functions::show($thematique). '-> '.Functions::show($expertises_thematique));
            $expertises =   [];
            foreach( $expertises_thematique as $expertise )
            {

                // On n'affiche pas les expertises définitives
                if ( $expertise->getDefinitif()) continue;

                $version    =  $expertise->getVersion();

                // On  n'affiche que les expertises des versions en édition expertise
                if ($version->getEtatVersion()!=Etat::EDITION_EXPERTISE && $version->getEtatVersion()!=Etat::EXPERTISE_TEST) continue;
                $projetId   =  $version->getProjet()->getIdProjet();

                $output =               [
                                        'expertise'   => $expertise,
                                        'demHeures'   => $version->getDemHeures(),
                                        'versionId'   => $version->getIdVersion(),
                                        'projetId'    => $projetId,
                                        'titre'       => $version->getPrjTitre(),
                                        'thematique'  => $thematique,
                                        'responsable' =>  $version->getResponsable(),
                                        ];
				//$sj->debugMessage(__METHOD__ . " expertise ".$expertise->getId());

				// On n'affiche pas deux expertises vers la même version
				if (!array_key_exists( $version->getIdVersion(), $expertises ))
				{
					// Si j'ai une expertise vers cette version, je remplace l'expertise trouvée par la mienne
	                if( array_key_exists( $version->getIdVersion(), $my_expertises ) )
	                {
						$output = $my_expertises[ $version->getIdVersion() ];
	                    unset( $my_expertises[ $version->getIdVersion() ]);
	                    $output['expert']   =   true;
	                }
	                else
	                {
	                    $output['expert']   =   false;
					}
	                $expertises[$version->getIdVersion()] = $output;
				}
            }

            $expertises_by_thematique[] = [ 'expertises' => $expertises, 'thematique' => $thematique ];
        }

        ///////////////////
        // tri des tableaux expertises_by_thematique: d'abord les expertises pour lesquelles je dois intervenir
		foreach( $expertises_by_thematique as &$exp_thema )
		{
			uasort($exp_thema['expertises'],"self::exptruefirst");
		}
			

        
		///////////////////

        $old_expertises = [];
        $expertises  = $expertiseRepository->findExpertisesByExpertForAllSessions($moi);
        foreach( $expertises as $expertise )
        {
            // Les expertises non définitives ne sont pas "old"
            if ( ! $expertise->getDefinitif()) continue;

            $version    = $expertise->getVersion();
            $id_session = $version->getSession()->getIdSession();
            $output = [
                        'projetId'   => $version->getProjet()->getIdProjet(),
                        'sessionId'  => $id_session,
                        'thematique' => $version->getPrjThematique(),
                        'titre'      => $version->getPrjTitre(),
                        'demHeures'  => $version->getDemHeures(),
                        'attrHeures' => $version->getAttrHeures(),
                        'responsable' =>  $version->getResponsable(),
                        'versionId'   => $version->getIdVersion(),
                       ];
            $old_expertises[] = $output;
        };

        // rallonges
        $rallonges       = [];
        $all_rallonges   = $em->getRepository(Rallonge::class)->findRallongesExpert($moi);
        foreach( $all_rallonges as $rallonge )
		{
            $version    =   $rallonge->getVersion();
            if( $version == null )
			{
                $sj->errorMessage(__METHOD__ . ':'. __FILE__ . " Rallonge " . $rallonge . " n'a pas de version !");
                continue;
			}
            $projet = $version->getProjet();
            if( $projet == null )
			{
                $sj->errorMessage(__METHOD__ . ':'. __FILE__ . " Version " . $version . " n'a pas de projet !");
                continue;
			}
			
            $rallonges[$projet->getIdProjet()]['projet']                                =   $projet;
            $rallonges[$projet->getIdProjet()]['version']                               =   $version;
            $rallonges[$projet->getIdProjet()]['consocalcul']                           =   $sp->getConsoCalculVersion($version);
            $rallonges[$projet->getIdProjet()]['rallonges'][$rallonge->getIdRallonge()] =   $rallonge;
		}

		// Commentaires
		// On propose aux experts du comité d'attribution (c-a-d ceux qui ont une thématique) d'entrer un commentaire sur l'année écoulée
		$mes_commentaires_flag = false;
		$mes_commentaires_maj        = null;
		if ($this->has('commentaires_experts_d') && count($mes_thematiques)>0)
		{
			$mes_commentaires_flag = true;
			$mois = $sd->format('m');
			$annee= $sd->format('Y');
			if ($mois>=$this->getParameter('commentaires_experts_d'))
			{
				$mes_commentaires_maj = $annee;
			}
			elseif ($mois<$this->getParameter('commentaires_experts_f'))
			{
				$mes_commentaires_maj = $annee - 1;
			}
		}
		$mes_commentaires = $em->getRepository('App:CommentaireExpert')->findBy( ['expert' => $moi ] );

        ///////////////////////

        return $this->render('expertise/liste.html.twig',
            [
            'rallonges'                  => $rallonges,
            'expertises_by_thematique'   => $expertises_by_thematique,
            'expertises_hors_thematique' => $my_expertises,
            'old_expertises'             => $old_expertises,
            'mes_commentaires_flag'      => $mes_commentaires_flag,
            'mes_commentaires'           => $mes_commentaires,
            'mes_commentaires_maj'       => $mes_commentaires_maj,
			'session'                    => $session,
            ]);
    }

    /**
     * Creates a new expertise entity.
     *
     * @Route("/new", name="expertise_new")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function newAction(Request $request)
    {
        $expertise = new Expertise();
        $form = $this->createForm('App\Form\ExpertiseType', $expertise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($expertise);
            $em->flush($expertise);

            return $this->redirectToRoute('expertise_show', array('id' => $expertise->getId()));
        }

        return $this->render('expertise/new.html.twig', array(
            'expertise' => $expertise,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a expertise entity.
     *
     * @Route("/{id}", name="expertise_show")
     * @Method("GET")
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function showAction(Expertise $expertise)
    {
        $deleteForm = $this->createDeleteForm($expertise);

        return $this->render('expertise/show.html.twig', array(
            'expertise' => $expertise,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing expertise entity.
     *
     * @Route("/{id}/edit", name="expertise_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function editAction(Request $request, Expertise $expertise)
    {
        $deleteForm = $this->createDeleteForm($expertise);
        $editForm = $this->createForm('App\Form\ExpertiseType', $expertise);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('expertise_edit', array('id' => $expertise->getId()));
        }

        return $this->render('expertise/edit.html.twig', array(
            'expertise' => $expertise,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }


	// Helper function used by modifierAction
	private static function expprjfirst($a,$b) { 
		if ($a->getVersion()->getProjet()->getId() < $b->getVersion()->getId()) return -1;
		return 1;
	}

    /**
     * L'expert vient de cliquer sur le bouton "Modifier expertise"
     * Il entre son expertise et éventuellement l'envoie
     *
     * @Route("/{id}/modifier", name="expertise_modifier")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_EXPERT')")
     */
    public function modifierAction(Request $request, Expertise $expertise)
    {
		$ss = $this->ss;
		$sv = $this->sv;
		$sp = $this->sp;
		$sj = $this->sj;
		$ac = $this->ac;
		$sval = $this->vl;
		$tok = $this->tok;
		$em = $this->getDoctrine()->getManager();
		
        // ACL
        $moi = $tok->getUser();
        if( is_string( $moi ) ) $sj->throwException(__METHOD__ . ":" . __LINE__ . " personne connecté");
        elseif( $expertise->getExpert() == null ) $sj->throwException(__METHOD__ . ":" . __LINE__ . " aucun expert pour l'expertise " . $expertise );
        elseif( ! $expertise->getExpert()->isEqualTo( $moi ) ) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . "  " . $moi .
                " n'est pas un expert de l'expertise " . $expertise . ", c'est " . $expertise->getExpert() );
        }

	// Si expertise déjà faite on revient à la liste
	if ($expertise->getDefinitif())
	{
		return $this->redirectToRoute('expertise_liste');
	}
		
        $expertiseRepository = $em->getRepository(Expertise::class);
        $session    = $ss->getSessionCourante();
        $commGlobal = $session->getcommGlobal();
        $anneeCour  = 2000 +$session->getAnneeSession();
        $anneePrec  = $anneeCour - 1;

        $version = $expertise->getVersion();
        if( $version == null )
	{
            $sj->throwException(__METHOD__ . ":" . __LINE__ . "  " . $expertise . " n'a pas de version !" );
	}
        // Le comportement diffère suivant le type de projet

	// Version est-elle nouvelle ?
	$isnouvelle = $sv->isNouvelle($version);
	
	// $peut_envoyer -> Si true, on autorise le bouton Envoyer
	$msg_explain = '';
	$projet      = $version -> getProjet();
        $projet_type = $projet  -> getTypeProjet();
        $etat_session= $session -> getEtatSession();

	// Projets au fil de l'eau avec plusieurs expertises:
	//    Si je suis président, on va chercher ces expertises pour affichage
	//    On vérifie leur état (définitive ou pas)
	$autres_expertises = [];
	$toutes_definitives= true;
	if ($projet_type == Projet::PROJET_FIL && $ac->isGranted('ROLE_PRESIDENT') )
	{
	    $expertiseRepository = $em->getRepository(Expertise::class);
	    $autres_expertises   = $expertiseRepository -> findExpertisesForVersion($version,$moi);
	    foreach ($autres_expertises as $e)
	    {
		if (! $e->getDefinitif())
		{
		    $toutes_definitives = false;
		    break;
		}
	    }
	}

        switch ($projet_type)
	{
	    // Si c'est un projet de type PROJET_SESS, le bouton ENVOYER n'est disponible 
	    // QUE si la session est en états ATTENTE ou ACTIF
	    case Projet::PROJET_SESS:
		if ($session -> getEtatSession() == Etat::EN_ATTENTE || $session -> getEtatSession() == Etat::ACTIF)
		{
		    // bouton envoyer disponible
		    $peut_envoyer = true;
		}
		else
		{
		    // bouton envoyer pas disponible
		    $peut_envoyer = false;
		}
		break;
	    
	    case Projet::PROJET_TEST:
		// Si c'est un projet de type PROJET_TEST, le bouton ENVOYER est toujours disponible
		$peut_envoyer = true;
		break;
		
	    case Projet::PROJET_FIL:
		// Si c'est un projet de type PROJET_FIL, le bouton ENVOYER est disponible (presque) tout le temps
		if ($etat_session == Etat::EDITION_DEMANDE)
		{
		    $msg_explain = "Vous ne pouvez pas actuellement finaliser votre expertise, car la session est en phase de \"édition des demandes\"";
		    $peut_envoyer = false;
		}
		elseif ($etat_session == Etat::EDITION_EXPERTISE)
		{
		    $msg_explain = "Vous ne pouvez pas actuellement finaliser votre expertise, car la session est en phase d'\"édition des expertises\" et le \"commentaire de session\" n'est pas entré";
		    $peut_envoyer = false;
		}
		elseif ($toutes_definitives == false)
		{
		    $msg_explain = "Vous ne pouvez pas actuellement finaliser votre expertise, il vous faut attendre que les autres experts aient terminé leur expertise";
		    $peut_envoyer = false;
		}
		else
		{
		    $peut_envoyer = true;
		}
		break;
	}

	// Création du formulaire
	$editForm = $this->createFormBuilder($expertise)
	    ->add('commentaireInterne', TextAreaType::class, [ 'required' => false ] )
	    ->add('validation', ChoiceType::class ,
		[
		'multiple' => false,
		'choices'   =>  [ 'Accepter' => 1, 'Accepter, avec zéro heure' => 2, 'Refuser définitivement et fermer le projet' => 0,],
		]);

		// Projet au fil de l'eau, le commentaire externe est réservé au président !
		// On utilise un champ caché, de cette manière le formulaire sera valide
		if ( $projet_type != Projet::PROJET_FIL || $ac->isGranted('ROLE_PRESIDENT'))
		{
		    $commentaireExterne = true;
		    $editForm->add('commentaireExterne', TextAreaType::class, [ 'required' => false ] );
		}
		else
		{
    		    $commentaireExterne = false;
		    //$editForm->add('commentaireExterne', HiddenType::class, [ 'data' => 'Commentaire externe réservé au Comité' ] );
		}

	// Par défaut on attribue les heures demandées !
        if( $expertise->getNbHeuresAtt() == 0 )
	{
            $editForm->add('nbHeuresAtt', IntegerType::class , ['required'  =>  false, 'data' => $version->getDemHeures(), ]);
	}
        else
	{
            $editForm->add('nbHeuresAtt', IntegerType::class , ['required'  =>  false, ]);
	}

	// En session B, on propose une attribution spéciale pour heures d'été
	// TODO A affiner car en septembre avec des projets PROJET_FIL en sera toujours en session  B et c'est un peu couillon de demander cela
	if ( $projet_type != Projet::PROJET_TEST )
	if ($session->getTypeSession())
	    $editForm -> add('nbHeuresAttEte');

	// Les boutons d'enregistrement ou d'envoi
	$editForm = $editForm->add('enregistrer', SubmitType::class, ['label' =>  'Enregistrer' ]);
        if( $peut_envoyer == true)
        {
            $editForm   =   $editForm->add('envoyer',   SubmitType::class, ['label' =>  'Envoyer' ]);
	}
	$editForm->add( 'annuler', SubmitType::Class, ['label' =>  'Annuler' ]);
        $editForm->add( 'fermer',  SubmitType::Class );

        $editForm = $editForm->getForm();
        
        $editForm->handleRequest($request);

        if( $editForm->isSubmitted() && ! $editForm->isValid() )
	{
             $sj->warningMessage(__METHOD__ . " form error " .  Functions::show($editForm->getErrors() ) );
	 }

        // Bouton ANNULER
        if( $editForm->isSubmitted() && $editForm->get('annuler')->isClicked() )
        {
	    return $this->redirectToRoute('expertise_liste');
	}

	// Boutons ENREGISTRER, FERMER ou ENVOYER
        $erreur  = 0;
        $erreurs = [];
        if ($editForm->isSubmitted() /* && $editForm->isValid()*/ )
        {
            $erreurs = Functions::dataError( $sval, $expertise);
            $validation = $expertise->getValidation();
            if( $validation != 1 )
	    {
                $expertise->setNbHeuresAtt(0);
                $expertise->setNbHeuresAttEte(0);
                if ( $validation == 2 && $projet_type == Projet::PROJET_TEST )
                {
                    $erreurs[] = "Pas possible de refuser un projet test juste pour cette session";
                }
	    }

            $em->persist( $expertise );
            $em->flush();

	    // Bouton FERMER
	    if ($editForm->get('fermer')->isClicked())
	    {
		return $this->redirectToRoute('expertise_liste');
	    }
				
            // Bouton ENVOYER --> Vérification des champs non renseignés puis demande de confirmation
            if( $peut_envoyer && $editForm->get('envoyer')->isClicked() && $erreurs == null )
	    {
		return $this->redirectToRoute('expertise_validation', [ 'id' => $expertise->getId() ]);
	    }
        }

        $toomuch = false;
        if ($session->getTypeSession() && ! $expertise->getVersion()->isProjetTest() )
        {
            $version_prec = $expertise->getVersion()->versionPrecedente();
            $attr_a       = ($version_prec==null) ? 0 : $version_prec->getAttrHeures();
            $dem_b        = $expertise->getVersion()->getDemHeures();
            $toomuch      = $sv->is_demande_toomuch($attr_a,$dem_b);
        }

	// LA SUITE DEPEND DU TYPE DE PROJET !
	// Le workflow n'est pas le même suivant le type de projet, donc l'expertise non plus.
	switch ($projet_type)
	{
		case Projet::PROJET_SESS:
			$twig = 'expertise/modifier_projet_sess.html.twig';
			break;
		case Projet::PROJET_TEST:
			$twig = 'expertise/modifier_projet_test.html.twig';
			break;
		case Projet::PROJET_FIL:
			$twig = 'expertise/modifier_projet_fil.html.twig';
			break;
	}

	// Dans le cas de projets tests, $expertises peut être vide même s'il y a un projet test dans la liste
	// (session B et projet test non expertisé en session A)
	$expertises = $expertiseRepository->findExpertisesByExpert($moi, $session);
	uasort($expertises,"self::expprjfirst");

	if (count($expertises)!=0)
	{
	    $k = array_search($expertise,$expertises);
	    if ($k==0) 
	    {
		$prev = null;
	    }
	    else
	    {
		$prev = $expertises[$k-1];
	    }
	    $next = null;
	    if ($k==count($expertises)-1)
	    {
		$next = null;
	    }
	    else
	    {
		$next = $expertises[$k+1];
	    }
	}
	else
	{
	    $prev = null;
	    $next = null;
	}
		
	// Rapport d'activité
	$rapport = $sp -> getRapport($projet, $version->getAnneeSession());

        return $this->render($twig,
	[
            'isnouvelle'        => $isnouvelle,
            'expertise'         => $expertise,
            'autres_expertises' => $autres_expertises,
            'msg_explain'       => $msg_explain,
            'version'           => $expertise->getVersion(),
            'edit_form'         => $editForm->createView(),
            'anneePrec'         => $anneePrec,
            'anneeCour'         => $anneeCour,
            'session'           => $session,
            'peut_envoyer'      => $peut_envoyer,
	    'commentaireExterne'=> $commentaireExterne,
            'erreurs'           => $erreurs,
            'toomuch'           => $toomuch,
            'prev'              => $prev,
            'next'              => $next, 
            'rapport'           => $rapport
	]);
    }

    /**
     *
     * L'expert vient de cliquer sur le bouton "Envoyer expertise"
     * On lui envoie un écran de confirmation
     *
     * @Route("/{id}/valider", name="expertise_validation")
     * @Method({"GET","POST"})
     * @Security("is_granted('ROLE_EXPERT')")
     */
    public function validationAction(Request $request, Expertise $expertise)
    {
		$sn = $this->sn;
		$sj = $this->sj;
		$ac = $this->ac;
		$em = $this->getDoctrine()->getManager();
		$tok = $this->tok;

	    // ACL
	    $moi = $tok->getUser();
	    if( is_string( $moi ) ) $sj->throwException(__METHOD__ . ":" . __LINE__ . " personne connecté");
	    elseif( $expertise->getExpert() == null ) $sj->throwException(__METHOD__ . ":" . __LINE__ . " aucun expert pour l'expertise " . $expertise );
	    elseif( ! $expertise->getExpert()->isEqualTo( $moi ) )
	        $sj->throwException(__METHOD__ . ":" . __LINE__ . "  " . $moi .
	            " n'est pas un expert de l'expertise " . $expertise . ", c'est " . $expertise->getExpert());


	    $editForm = $this->createFormBuilder($expertise)
	                ->add('confirmer',   SubmitType::class, ['label' =>  'Confirmer' ])
	                ->add('annuler',   SubmitType::class, ['label' =>  'Annuler' ])
	                ->getForm();

	    $editForm->handleRequest($request);

	    if( $editForm->isSubmitted()  )
        {
			// Bouton Annuler
	        if( $editForm->get('annuler')->isClicked() )
	            return $this->redirectToRoute('expertise_modifier', [ 'id' => $expertise->getId() ] );

			// Bouton Confirmer
			// Si projet au fil de l'eau mais qu'on n'est pas président, on n'envoie pas de signal
			// Dans tous les autres cas on envoie un signal CLK_VAL_EXP_XXX
			//
			$type_projet = $expertise->getVersion()->getProjet()->getTypeProjet();
			if ( $type_projet != Projet::PROJET_FIL || $ac->isGranted('ROLE_PRESIDENT') )
			{
		        $expertise->getVersion()->setAttrHeures($expertise->getNbHeuresAtt() );
		        $expertise->getVersion()->setAttrHeuresEte($expertise->getNbHeuresAttEte() );
		        $expertise->getVersion()->setAttrAccept($expertise->getValidation()  );

		        $validation =  $expertise->getValidation();

		        $rtn = null;
		        if( $validation == 1 )      $signal = Signal::CLK_VAL_EXP_OK;
		        elseif( $validation == 2 )  $signal = Signal::CLK_VAL_EXP_CONT;
		        elseif( $validation == 0 )  $signal = Signal::CLK_VAL_EXP_KO;

		        $workflow = $this->pw;
		        $rtn      = $workflow->execute( $signal, $expertise->getVersion()->getProjet() );
		        if( $rtn != true )
		            $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " Transition avec " .  Signal::getLibelle( $signal )
		            . "(" . $signal . ") pour l'expertise " . $expertise . " avec rtn = " . Functions::show($rtn) );
		        else
		            $expertise->setDefinitif(true);

		        $em->persist( $expertise );
		        $em->flush();
			}
			else
			{
				$expertise->setDefinitif(true);
		        $em->persist( $expertise );
		        $em->flush();

		        // Envoi d'une notification aux présidents
		        $dest = $sn->mailUsers([ 'P' ]);
		        $params = [ 'object' => $expertise ];
		        $sn->sendMessage ('notification/expertise_projet_fil_pour_president-sujet.html.twig',
		        				  'notification/expertise_projet_fil_pour_president-contenu.html.twig',
		        				  $params,
		        				  $dest);
			}
	        return $this->redirectToRoute('expertise_liste');
        }

		// LA SUITE DEPEND DU TYPE DE PROJET !
		// Le workflow n'est pas le même suivant le type de projet, donc l'expertise non plus.

		$version = $expertise->getVersion();
        $projet_type = $version->getProjet()->getTypeProjet();
		switch ($projet_type)
		{
			case Projet::PROJET_SESS:
				$twig = 'expertise/valider_projet_sess.html.twig';
				break;
			case Projet::PROJET_TEST:
				$twig = 'expertise/valider_projet_test.html.twig';
				break;
			case Projet::PROJET_FIL:
				$twig = 'expertise/valider_projet_fil.html.twig';
				break;
		}

        return $this->render($twig,
            [
            'expertise'  => $expertise,
            'version'    => $expertise->getVersion(),
            'edit_form'  => $editForm->createView(),
            ]);
    }
    /**
     * Deletes a expertise entity.
     *
     * @Route("/{id}", name="expertise_delete")
     * @Method("DELETE")
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function deleteAction(Request $request, Expertise $expertise)
    {
        $form = $this->createDeleteForm($expertise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($expertise);
            $em->flush($expertise);
        }

        return $this->redirectToRoute('expertise_index');
    }

    /**
     * Creates a form to delete a expertise entity.
     *
     * @param Expertise $expertise The expertise entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Expertise $expertise)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('expertise_delete', array('id' => $expertise->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
