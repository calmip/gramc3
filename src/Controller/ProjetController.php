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
use App\Entity\Thematique;
use App\Entity\Rattachement;
use App\Entity\Expertise;
use App\Entity\Individu;
use App\Entity\Sso;
use App\Entity\CompteActivation;
use App\Entity\Journal;
use App\Entity\Compta;

use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

//use App\App;
use App\Utils\Functions;
use App\Utils\Etat;
use App\Utils\Signal;
use App\GramcServices\Workflow\Projet\ProjetWorkflow;
use App\GramcServices\Workflow\Version\VersionWorkflow;
//use App\Utils\GramcDate;

use App\GramcServices\GramcGraf\Calcul;
use App\GramcServices\GramcGraf\CalculTous;
use App\GramcServices\GramcGraf\Stockage;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

// Pour le tri numérique sur les années, en commençant par la plus grande - cf. resumesAction
function cmpProj($a,$b) { return intval($a['annee']) < intval($b['annee']); }

/**
 * Projet controller.
 *
 * @Route("projet")
 */
 // Tous ces controleurs sont exécutés au moins par OBS, certains par ADMIN seulement
 // et d'autres par DEMANDEUR

class ProjetController extends Controller
{

    private static $count;

    /**
     * Lists all projet entities.
     *
     * @Route("/", name="projet_index")
     * @Method("GET")
	 * @Security("is_granted('ROLE_OBS')")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $projets = $em->getRepository('App:Projet')->findAll();

        return $this->render('projet/index.html.twig', array(
            'projets' => $projets,
        ));
    }

    /**
     * Delete old data.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/old", name="projet_nettoyer")
     * @Method({"GET","POST"})
     */
    public function oldAction(Request $request)
    {
		$sd = $this->get('app.gramc.date');
		$sj = $this->get('app.gramc.ServiceJournal');
		$sp = $this->get('app.gramc.ServiceProjets');
		$ff = $this->get('form.factory');
		$em = $this->getDoctrine()->getManager();
		
	    $list = [];
	    $mauvais_projets = [];
	    static::$count = [];
	    $annees =   [];

    $all_projets = $em->getRepository(Projet::class)->findAll();
    foreach( $all_projets as $projet )
	{
        $derniereVersion    =  $projet->derniereVersion();
        if(  $derniereVersion == null )
		{
            $mauvais_projets[$projet->getIdProjet()]    =   $projet;
		}
        else
        {
            $annee = $projet->derniereVersion()->getAnneeSession();
		}
        $list[$annee][] = $projet;
	}
    foreach( $list as $annee => $projets )
        {
        static::$count[$annee]  =   count($projets);
        $annees[]       =   $annee;
        }

    asort($annees );
    $form = Functions::createFormBuilder($ff)
            ->add('annee',   ChoiceType::class,
                    [
                    'required'  =>  false,
                    'label'     => ' Année ',
                    'choices'   =>  $annees,
                    'choice_label' => function ($choiceValue, $key, $value )
                        {
                        return  $choiceValue . '  (' . ProjetController::$count[$choiceValue] . ' projets)';
                        }
                    ])
        ->add('supprimer projets', SubmitType::class, ['label' => ""])
        ->add('supprimer utilisateurs sans projet', SubmitType::class, ['label' => ""])
        ->add('nettoyer le journal', SubmitType::class, ['label' => ""])
        ->getForm();

    $date = clone $sd;
    $date->sub( \DateInterval::createFromDateString('1 year') );
    $individus = $em->getRepository(Individu::class)->liste_avant( $date );
    $utilisateurs_a_effacer = $sp->utilisateurs_a_effacer($individus);
    $individus_effaces = [];
    $projets_effaces = [];
    $old_journal = null;
    $journal = false;

    $form->handleRequest($request);

    if( $form->isSubmitted() && $form->isValid() && $form->get('supprimer utilisateurs sans projet')->isClicked() )
	{
        $individus_effaces = $sp->effacer_utilisateurs($utilisateurs_a_effacer);
        $utilisateurs_a_effacer = [];
        //return new Response( Functions::show( $individus_effaces ) );
	}
    elseif( $form->isSubmitted() && $form->isValid() && $form->get('nettoyer le journal')->isClicked() )
	{
        if( $this->container->hasParameter('old_journal') )
		{
            $old_journal = intval( $this->getParameter('old_journal') );
            if( $old_journal > 0 )
		    {
                $date = clone $sd;
                $date->sub( \DateInterval::createFromDateString($old_journal . ' year') );
                // return new Response( Functions::show( [$old_journal,$date] ) );
                $journal = $em->getRepository(Journal::class)->liste_avant( $date );
                foreach( $journal as $item) $em->remove( $item );
                $em->flush();
                $journal = true;
			}
            else
            {
                $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " La valeur du paramètre old_journal est " . $old_journal);
            }
		}
        else
        {
            $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " Le paramètre old_journal manque");
		}
	}
    elseif( $form->isSubmitted() && $form->isValid() && $form->get('supprimer projets')->isClicked() )
	{
        $annee = $form->getData()['annee']; // par exemple 2014

        $key = array_search($annee,$annees); // supprimer l'année de la liste du formulaire
        if( $key !== false)   unset($annees[$key]);

        $individus = [];
        foreach( $list[$annee] as $projet )
            foreach( $projet->getVersion() as $version )
                {
                foreach( $version->getCollaborateurs() as $collaborateur )
                    $individus[$collaborateur->getIdIndividu()]    =  $collaborateur;
                foreach( $version->getExpertise() as $expertise )
                    if( $expertise->getExpert() != null )
                        $individus[$expertise->getExpert()->getIdIndividu()]    =  $expertise->getExpert();
                foreach( $version->getRallonge() as $rallonge )
                    if( $rallonge->getExpert() != null )
                        $individus[$rallonge->getExpert()->getIdIndividu()]    =  $rallonge->getExpert();

                }

        // effacer des structures
        foreach( $list[$annee] as $projet )
		{
            $em->persist( $projet );
            //$projet->setVersionDerniere( null );
            //$projet->setVersionActive( null );
            $em->flush();

            // effacer des documents
            $sp->erase_directory( $this->getParameter('rapport_directory'), $projet);
            $sp->erase_directory( $this->getParameter('signature_directory'), $projet );
            $sp->erase_directory( $this->getParameter('fig_directory'), $projet );

            //continue; //DEBUG

            foreach( $projet->getVersion() as $version )
			{
                $em->persist( $version );
                foreach( $version->getCollaborateurVersion() as $item )
				{
                    $em->remove( $item );
                    //$em->flush();
				}

                foreach( $version->getExpertise() as $item )
				{
                    $em->remove( $item );
                    //$em->flush();
				}
                /*
                $expertises = $em->getRepository(Expertise::class)->findBy(['version' => $version]);
                foreach( $expertises as $item )
                    {
                    $em->remove( $item );
                    $em->flush();
                    }
                 */

                $em->remove( $version );
			}

            $versions = $em->getRepository(Version::class)->findBy(['projet' => $projet]);
			foreach( $versions as $item )
			{
				$em->remove( $item );
			}

			$loginname = strtolower($projet->getIdProjet());
            foreach( $em->getRepository(Compta::class)->findBy(['loginname' => $loginname]) as $item )
            {
				$em->remove( $item );
			}

            foreach( $projet->getRapportActivite() as $rapport )
			{
                $em->remove( $rapport );
			}

            /*
            if( $projet->derniereVersion() != null )
                {
                $version = $projet->getVersionDerniere();
                $em->persist( $version );
                $em->remove( $version );
                }
            */

            //Functions::erase_parameter_directory( 'fig_directory', $projet->getIdProjet() );
            $projets_effaces[] = $projet;
            $sj->infoMessage('Le projet ' . $projet . ' a été effacé ');
            $em->remove( $projet );
		}

        //return new Response(Functions::show( $projets_effaces ) ); //DEBUG

        $em->flush();

        // effacer des anciens utilisateurs

        $individus_effaces = $sp->effacer_utilisateurs($individus);

        //return new Response( Functions::show( $individus_effaces ) );
	}
    //return new Response( Functions::show( $annees ) );

    $form = Functions::createFormBuilder($ff)
            ->add('annee',   ChoiceType::class,
                    [
                    'required'  =>  false,
                    'label'     => ' Année ',
                    'choices'   =>  $annees,
                    'choice_label' => function ($choiceValue, $key, $value )
                        {
                        return  $choiceValue . '  (' . ProjetController::$count[$choiceValue] . ' projets)';
                        }
                    ])
        ->add('supprimer projets', SubmitType::class, ['label' => ""])
        ->add('supprimer utilisateurs sans projet', SubmitType::class, ['label' => ""])
        ->add('nettoyer le journal', SubmitType::class, ['label' => ""])
        ->getForm();

    return $this->render('projet/old.html.twig',
            [
            'annees' => $annees,
            'count' => static::$count,
            'projets'   =>  $list,
            'projets_effaces'   => $projets_effaces,
            'mauvais_projets'   =>  $mauvais_projets,
            'utilisateurs_a_effacer'    => $utilisateurs_a_effacer,
            'individus_effaces'    =>  $individus_effaces,
            'form'  =>  $form->createView(),
            'journal' => $journal,
            'old_journal' => $old_journal,
            ]);

    //return new Response( Functions::show( $mauvais_projets ) );
    //return new Response( Functions::show( $count ) );
    //return new Response( Functions::show( $list ) );
    }

    /**
     * Projets par session en CSV
     *
     * @Route("/{id}/session_csv", name="projet_session_csv")
     * @Method({"GET","POST"})
	 * @Security("is_granted('ROLE_OBS')")
     */
    public function sessionCSVAction(Session $session)
    {
		$em = $this->getDoctrine()->getManager();
		$sp = $this->get('app.gramc.ServiceProjets');
		$sv = $this->get('app.gramc.ServiceVersions');
	    $sortie = 'Projets de la session ' . $session->getId() . "\n";
	    $ligne  =   [
	                'Nouveau',
	                'id_projet',
	                'état',
	                'titre',
	                'thématique',
	                'rattachement',
	                'dari',
	                'courriel',
	                'prénom',
	                'nom',
	                'laboratoire',
	                'expert',
	                'heures demandées',
	                'heures attribuées',
	                ];
		if ($this->getParameter('noconso')==false)
		{
			$ligne[] = 'heures consommées';
		}
	    $sortie     .=   join("\t",$ligne) . "\n";

	    $versions = $em->getRepository(Version::class)->findSessionVersions($session);
	    foreach ( $versions as $version )
		{
			$responsable    =   $version->getResponsable();
			$ligne  =
					[
					( $sv->isNouvelle($version) == true ) ? 'OUI' : '',
					$version->getProjet()->getIdProjet(),
					$sp->getMetaEtat($version->getProjet()),
					Functions::string_conversion($version->getPrjTitre() ),
					Functions::string_conversion($version->getPrjThematique() ),
					Functions::string_conversion($version->getPrjRattachement() ),
					$version->getPrjGenciDari(),
					$responsable->getMail(),
					Functions::string_conversion($responsable->getPrenom() ),
					Functions::string_conversion($responsable->getNom() ),
					Functions::string_conversion($version->getPrjLLabo() ),
					( $version->getResponsable()->getExpert() ) ? '*******' : $version->getExpert(),
					$version->getDemHeures(),
					$version->getAttrHeures(),
					];
			if ($this->getParameter('noconso')==false)
			{
				$ligne[]= $sp->getConsoCalculVersion($version);
			}

			$sortie     .=   join("\t",$ligne) . "\n";
		}
	    return Functions::csv($sortie,'projet_session.csv');
    }

    /**
     * Lists all projet entities.
     *
     * @Route("/tous_csv", name="projet_tous_csv")
     * @Method({"GET","POST"})
	 * @Security("is_granted('ROLE_OBS')")
     */
    public function tousCSVAction()
    {
		$sd = $this->get('app.gramc.date');
		$em = $this->getDoctrine()->getManager();
		
        $entetes =
                [
                "Numéro",
                "État",
                "Titre",
                "Thématique",
                "Courriel",
                "Prénom",
                "Nom",
                "Laboratoire",
                "Nb de versions",
                "Dernière session",
                "Heures demandées cumulées",
                "Heures attribuées cumulées",
                ];

	    $sortie     =   "Projets enregistrés dans gramc à la date du " . $sd->format('d-m-Y') . "\n" . join("\t",$entetes) . "\n";
	
	    $projets = $em->getRepository(Projet::class)->findBy( [],['idProjet' => 'DESC' ] );
	    foreach ( $projets as $projet )
            {
            $version        =   $projet->getVersionDerniere();
            $responsable    =   $version->getResponsable();
            $info           =   $em->getRepository(Version::class)->info($projet);

            $ligne  =
                [
                $projet->getIdProjet(),
                Etat::getLibelle( $projet->getEtatProjet() ),
                Functions::string_conversion($version->getPrjTitre() ),
                Functions::string_conversion($version->getPrjThematique() ),
                $responsable->getMail(),
                Functions::string_conversion($responsable->getPrenom() ),
                Functions::string_conversion($responsable->getNom() ),
                Functions::string_conversion($version->getPrjLLabo() ),
                $info[1],
                $version->getSession()->getIdSession(),
                $info[2],
                $info[3],
                ];
            $sortie     .=   join("\t",$ligne) . "\n";
            }

    return Functions::csv($sortie,'projet_gramc.csv');

    }

    /**
     * fermer un projet
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/fermer", name="fermer_projet")
     * @Method({"GET","POST"})
     */
    public function fermerAction(Projet $projet, Request $request)
    {
        if( $request->isMethod('POST') )
		{
            $confirmation = $request->request->get('confirmation');

            if( $confirmation == 'OUI' )
			{
                $workflow = $this->get('app.gramc.ProjetWorkflow');
                if( $Workflow->canExecute( Signal::CLK_FERM, $projet) )
                     $Workflow->execute( Signal::CLK_FERM, $projet);
			}
            return $this->redirectToRoute('projet_tous'); // NON - on ne devrait jamais y arriver !
		}
        else
           return $this->render('projet/dialog_fermer.html.twig',
            [
            'projet' => $projet,
            ]);
    }

    /**
     * back une version
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/back", name="back_version")
     * @Method({"GET","POST"})
     */
    public function backAction(Version $version, Request $request)
    {
        if( $request->isMethod('POST') )
		{
            $confirmation = $request->request->get('confirmation');

            if( $confirmation == 'OUI' )
			{
		        $workflow = $this->get('app.gramc.ProjetWorkflow');
                if( $workflow->canExecute( Signal::CLK_ARR, $version->getProjet() ) )
                {
                     $workflow->execute( Signal::CLK_ARR, $version->getProjet());
                     // Supprime toutes les expertises
                     $expertises = $version->getExpertise()->toArray();
		             $em = $this->getDoctrine()->getManager();
                     foreach ($expertises as $e)
                     {
						 $em->remove($e);
					 }
					 $em->flush();
				}
			}
            return $this->redirectToRoute('projet_session'); // NON - on ne devrait jamais y arriver !
		}
        else
           return $this->render('projet/dialog_back.html.twig',
            [
            'version' => $version,
            ]);
    }

    /**
     * L'admin a cliqué sur le bouton Forward pour envoyer une version à l'expert
     * à la place du responsable
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/fwd", name="fwd_version")
     * @Method({"GET","POST"})
     */
    public function fwdAction(Version $version, Request $request, LoggerInterface $lg)
    {
		$em = $this->getDoctrine()->getManager();
        if( $request->isMethod('POST') )
		{
            $confirmation = $request->request->get('confirmation');

            if( $confirmation == 'OUI' )
		    {
				$workflow = $this->get('app.gramc.ProjetWorkflow');
                if( $workflow->canExecute( Signal::CLK_VAL_DEM, $version->getProjet() ) )
                {
				    $workflow->execute( Signal::CLK_VAL_DEM, $version->getProjet());
		
				    // On crée une expertise pour ce projet, mais on n'affecte pas d'experts
				    $expertise  =   new Expertise();
				    $expertise->setVersion( $version );
	    		    Functions::sauvegarder( $expertise, $em, $lg );
				}
		    }
            return $this->redirectToRoute('projet_session');
		}
        else
        {
           return $this->render('projet/dialog_fwd.html.twig',
            [
            'version' => $version,
            ]);
		}
    }

    /**
     * Liste tous les projets qui ont une version lors de cette session
     *
     * @Route("/session", name="projet_session")
     * @Method({"GET","POST"})
	 * @Security("is_granted('ROLE_OBS')")
     */
    public function sessionAction(Request $request)
    {
		$em             = $this->getDoctrine()->getManager();
		$ss             = $this->get('app.gramc.ServiceSessions');
        $sp             = $this->get('app.gramc.ServiceProjets');
        $sv             = $this->get('app.gramc.ServiceVersions');
		
		$session        = $ss->getSessionCourante();
        $data           = $ss->selectSession($this->createFormBuilder(['session'=>$session]),$request); // formulaire
        $session        = $data['session']!=null?$data['session']:$session;
        $form           = $data['form'];
        $versions       = $em->getRepository(Version::class)->findSessionVersions($session);

        $demHeures      = 0;
        $attrHeures     = 0;
        $nombreProjets  = count( $versions );
        $nombreNouveaux = 0;
        $nombreSignes   = 0;
        $nombreRapports = 0;
        $nombreExperts  = 0;
        $nombreAcceptes = 0;

        $nombreEditionTest   = 0;
        $nombreExpertiseTest = 0;
        $nombreEditionFil    = 0;
        $nombreExpertiseFil  = 0;
        $nombreEdition       = 0;
        $nombreExpertise     = 0;
        $nombreAttente       = 0;
        $nombreActif         = 0;
        $nombreNouvelleDem   = 0;
        $nombreTermine       = 0;
        $nombreAnnule        = 0;


        $termine        = Etat::getEtat('TERMINE');
        $nombreTermines = 0;

		// Les thématiques
        $thematiques = $em->getRepository(Thematique::class)->findAll();
        if( $thematiques == null ) new Response('Aucune thématique !');

        foreach( $thematiques as $thematique )
        {
            $statsThematique[$thematique->getLibelleThematique()]    =   0;
            $idThematiques[$thematique->getLibelleThematique()]      =   $thematique->getIdThematique();
        }

		// Les rattachements
        $rattachements = $em->getRepository(Rattachement::class)->findAll();
        if( $rattachements == null ) new Response('Aucun rattachement !');

        foreach( $rattachements as $rattachement )
        {
            $statsRattachement[$rattachement->getLibelleRattachement()]    =   0;
            $idRattachements[$rattachement->getLibelleRattachement()]      =   $rattachement->getIdRattachement();
        }

        //$items  =   [];
        $versions_suppl = [];
        foreach( $versions as $version )
        {
			$id_version                   = $version->getIdVersion();
			$projet                       = $version->getProjet();
			$version_suppl                = [];
			$version_suppl['metaetat']    = $sp->getMetaEtat($projet);
			$version_suppl['consocalcul'] = $sp->getConsoCalculVersion($version);
			$version_suppl['isnouvelle']  = $sv->isNouvelle($version);
			$version_suppl['issigne']     = $sv->isSigne($version);
			$version_suppl['sizesigne']   = $sv->getSizeSigne($version);

			$annee_rapport = $version->getAnneeSession()-1;
			$version_suppl['rapport']     = $sp->getRapport($projet,$annee_rapport);
			$version_suppl['size_rapport']= $sp->getSizeRapport($projet,$annee_rapport);
			
			//Modif Callisto Septembre 2019
			$typeMetadata = $version -> getDataMetaDataFormat();
			$nombreDatasets = $version -> getDataNombreDatasets();
			$tailleDatasets = $version -> getDataTailleDatasets();
            $demHeures  +=  $version->getDemHeures();
            $attrHeures +=  $version->getAttrHeures();
            if( $sv->isNouvelle($version) == true ) $nombreNouveaux++;
            if( $version->getPrjThematique() != null )
                $statsThematique[$version->getPrjThematique()->getLibelleThematique()]++;
            if( $version->getPrjRattachement() != null )
                $statsRattachement[$version->getPrjRattachement()->getLibelleRattachement()]++;
                
            if( $sv->isSigne($version) ) $nombreSignes++;
            if( $sp->hasRapport($projet,$annee_rapport)) $nombreRapports++;
            if( $version->hasExpert() )     $nombreExperts++;
            //if( $version->getAttrAccept() ) $nombreAcceptes++;
            if( $version_suppl['metaetat'] == 'ACCEPTE' )
                $nombreAcceptes++;
            if( $version->getProjet() != null && $version->getProjet()->getEtatProjet() == $termine ) $nombreTermines++;

            $etat = $version->getEtatVersion();
            $type = $version->getProjet()->getTypeProjet();


			// TODO Que c'est compliqué !
			//      Plusieurs workflows => pas d'états différents
			//      Utiliser un tableau
			if ($type == Projet::PROJET_TEST)
			{
				if ($etat == Etat::EDITION_TEST )
				{
					$nombreEditionTest++;
				}
				elseif ( $etat == Etat::EXPERTISE_TEST )
				{
					$nombreExpertiseTest++;
				}
			}
			elseif ($type == Projet::PROJET_FIL)
			{
				if ($etat == Etat::EDITION_TEST )
				{
					$nombreEditionFil++;
				}
				elseif ( $etat == Etat::EXPERTISE_TEST )
				{
					$nombreExpertiseFil++;
				}
			}
			elseif ($type == Projet::PROJET_SESS)
			{
				if ($etat == Etat::EDITION_DEMANDE )
				{
					$nombreEdition++;
				}
				elseif ( $etat == Etat::EDITION_EXPERTISE )
				{
					$nombreExpertise++;
				}
			};

			if ($etat == Etat::ACTIF )
			{
				$nombreActif++;
			}
			elseif ( $etat == Etat::NOUVELLE_VERSION_DEMANDEE )
			{
				$nombreNouvelleDem++;
			}
			elseif ( $etat == Etat::EN_ATTENTE )
			{
				$nombreAttente++;
			}
			elseif ( $etat == Etat::TERMINE )
			{
				$nombreTermine++;
			}
			elseif ( $etat == Etat::ANNULE )
			{
				$nombreAnnule++;
			};

            //$items[]    =
            //        [
            //        'version'       =>  $version,
            //        'sizeSigne'     =>  $version->getSizeSigne(),
            //        //'sizeRapport'   =>  $version->getSizeRapport(),//
            //        ];
            $versions_suppl[$id_version] = $version_suppl;
        }

        foreach( $thematiques as $thematique )
        {
            if( $statsThematique[$thematique->getLibelleThematique()]    ==   0 )
            {
                unset( $statsThematique[$thematique->getLibelleThematique()] );
                unset( $idThematiques[$thematique->getLibelleThematique()] );
            }
        }

        return $this->render('projet/session.html.twig',
        [
			//'typeMetadata'			=>	$typeMetadata,
			//'nombreDatasets'		=>	$nombreDatasets,
			//'tailleDatasets'		=>	$tailleDatasets,
            'nombreEditionTest'   => $nombreEditionTest,
            'nombreExpertiseTest' => $nombreExpertiseTest,
            'nombreEdition'       => $nombreEdition,
            'nombreExpertise'     => $nombreExpertise,
            'nombreAttente'       => $nombreAttente,
            'nombreActif'         => $nombreActif,
            'nombreNouvelleDem'   => $nombreNouvelleDem,
            'nombreTermine'       => $nombreTermine,
            'nombreAnnule'        => $nombreAnnule,
            'nombreEditionFil'    => $nombreEditionFil,
            'nombreExpertiseFil'  => $nombreExpertiseFil,
            'form'                => $form->createView(), // formulaire
            'idSession'           => $session->getIdSession(), // formulaire
            'session'             => $session,
            'versions'            => $versions,
            'versions_suppl'      => $versions_suppl,
            'nombreNouveaux'      => $nombreNouveaux,
            'demHeures'           => $demHeures,
            'attrHeures'          => $attrHeures,
            'nombreProjets'       => $nombreProjets,
            'nombreNouveaux'      => $nombreNouveaux,
            'thematiques'         => $statsThematique,
            'idThematiques'       => $idThematiques,
            'rattachements'       => $statsRattachement,
            'idRattachements'     => $idRattachements,
            'nombreSignes'        => $nombreSignes,
            'nombreRapports'      => $nombreRapports,
            'nombreExperts'       => $nombreExperts,
            'nombreAcceptes'      => $nombreAcceptes,
            'nombreTermines'      => $nombreTermines,
            'showRapport'         => (substr($session->getIdSession(), 2, 1 ) == 'A')? true : false,
        ]);
    }

    /**
     * Résumés de tous les projets qui ont une version cette annee
     *
     * Param : $annee
     *
     * @Security("is_granted('ROLE_OBS')")
     * @Route("/{annee}/resumes", name="projet_resumes")
     * @Method({"GET","POST"})
     *
     */
    public function resumesAction($annee)
    {
		$sp    = $this->get('app.gramc.ServiceProjets');
		$sj    = $this->get('app.gramc.ServiceJournal');
		
        $paa   = $sp->projetsParAnnee($annee);
        $prjs  = $paa[0];
        $total = $paa[1];

        // construire une structure de données:
        //     - tableau associatif indexé par la métathématique
        //     - Pour chaque méta thématique liste des projets correspondants
        //       On utilise version B si elle existe, version A sinon
        //       On garde titre, les deux dernières publications, résumé
        $projets = [];
        foreach ($prjs as $p)
        {
            $v = empty($p['vb']) ? $p['va'] : $p['vb'];

            // On saute les projets en édition !
            if ($v->getEtatVersion() == Etat::EDITION_DEMANDE || $v->getEtatVersion() == Etat::EDITION_TEST) continue;
            $thematique= $v->getPrjThematique();
            if ($thematique==null)
            {
				$sj->warningMessage(__METHOD__ . ':' . __LINE__ . " version " . $v . " n'a pas de thématique !");
			}
			else
			{
				$metathema = $thematique->getMetaThematique()->getLibelle();
			}

            if (! isset($projets[$metathema])) {
                $projets[$metathema] = [];
            }
            $prjm = &$projets[$metathema];
            $prj  = [];
            $prj['id'] = $v->getProjet()->getIdProjet();
            $prj['titre'] = $v->getPrjTitre();
            $prj['resume']= $v->getPrjResume();
            $prj['laboratoire'] = $v->getLabo();
            $a = $v->getProjet()->getIdProjet();
            $a = substr($a,1,2);
            $a = 2000 + $a;
            $prj['annee'] = $a;
            $publis = array_slice($v->getProjet()->getPubli()->toArray(),-2,2);
            //$publis = array_slice($publis, -2, 2); // On garde seulement les deux dernières
            $prj['publis'] = $publis;
            $prj['porteur'] = $v->getResponsable()->getPrenom().' '.$v->getResponsable()->getNom();
            $prjm[] = $prj;
        };

        // Tris des tableaux par thématique du plus récent au plus ancien
        foreach ($projets as $metathema => &$prjm)
        {
            usort($prjm,"App\Controller\cmpProj");
        }

        return $this->render('projet/resumes.html.twig',
                [
                'annee'     => $annee,
                'projets'   => $projets,
                ]);
    }

    /**
     *
     * Liste tous les projets qui ont une version cette annee
     *
     * @Route("/annee", name="projet_annee")
     * @Method({"GET","POST"})
	 * @Security("is_granted('ROLE_OBS')")
     */

    public function anneeAction(Request $request)
    {
		$sd = $this->get('app.gramc.date');
		$ss = $this->get('app.gramc.ServiceSessions');
        $data  = $ss->selectAnnee($request); // formulaire
        $annee = $data['annee'];

        $isRecupPrintemps = $sd->isRecupPrintemps($annee);
        $isRecupAutomne   = $sd->isRecupAutomne($annee);

		$sp      = $this->get('app.gramc.ServiceProjets');
        $paa     = $sp->projetsParAnnee($annee,$isRecupPrintemps, $isRecupAutomne);
        $projets = $paa[0];
        $total   = $paa[1];

        // Les sessions de l'année - On considère que le nombre d'heures par année est fixé par la session A de l'année
        // donc on ne peut pas changer de machine en cours d'année.
        // ça va peut-être changer un jour, ça n'est pas terrible !
        $sessions = $ss->sessionsParAnnee($annee);
        if (count($sessions)==0) {
            $hparannee=0;
        } else {
            $hparannee= $sessions[0]->getHParAnnee();
        }

        return $this->render('projet/annee.html.twig',
                [
                'form' => $data['form']->createView(), // formulaire
                'annee'     => $annee,
                //'mois'    => $mois,
                'projets'   => $projets,
                'total'     => $total,
                'showRapport' => false,
                'isRecupPrintemps' => $isRecupPrintemps,
                'isRecupAutomne'   => $isRecupAutomne,
                'heures_par_an'    => $hparannee
                ]);
    }

    /**
     *
     * Liste tous les projets avec des demandes de stockage ou partage de données
     *
     * NB - Utile pour Calmip, si c'est inutile pour les autres mesoc il faudra
     *      mettre cette fonction ailleurs !
     *
     * @Route("/donnees", name="projet_donnees")
     * @Method({"GET","POST"})
	 * @Security("is_granted('ROLE_OBS')")
     */

    public function donneesAction(Request $request)
    {
		$ss    = $this->get('app.gramc.ServiceSessions');
		$sp    = $this->get('app.gramc.ServiceProjets');
        $data  = $ss->selectAnnee($request); // formulaire
        $annee = $data['annee'];

		list($projets,$total) = $sp->donneesParProjet($annee);

		return $this->render('projet/donnees.html.twig',
			['form'    => $data['form']->createView(), // formulaire
			 'annee'   => $annee,
			 'projets' => $projets,
			 'total'   => $total,
			 ]);
	}

    /**
     * Données en CSV
     *
     * @Route("{annee}/donnees_csv", name="projet_donnees_csv")
     * @Method({"GET","POST"})
	 * @Security("is_granted('ROLE_OBS')")
     */
    public function donneesCSVAction($annee)
    {
		$sp                  = $this->get('app.gramc.ServiceProjets');
		list($projets,$total)= $sp->donneesParProjet($annee);

        $header  = [
                    'projet',
                    'Demande',
                    'titre',
                    'thématique',
                    'courriel du resp',
                    'prénom',
                    'nom',
                    'laboratoire',
                    'justif',
                    'demande',
                    'quota',
                    'DIFF',
                    'occupation',
                    'meta',
                    'nombre',
                    'taille'
				   ];

        $sortie     =   join("\t",$header) . "\n";
        foreach ($projets as $prj_array) {
			$line   = [];
			$p = $prj_array['p'];
			$line[] = $p->getIdProjet();
			$d      = "";
			if ($prj_array['stk']===true) $d  = "S ";
			if ($prj_array['ptg']===true) $d .= "P";
			if ($prj_array['stk']===false && $prj_array['ptg']===false) $d = "N";
			$line[] = $d;
            $line[] = $p->getTitre();
            $line[] = $p->getThematique();
            $line[] = $p->getResponsable()->getMail();
            $line[] = $p->getResponsable()->getNom();
            $line[] = $p->getResponsable()->getPrenom();
            $line[] = $p->getLaboratoire();
            $line[] = '"'.str_replace(["\n","\r\n","\t"],[' ',' ',' '],$prj_array['sondJustifDonnPerm']).'"';
            $line[] = $prj_array['sondVolDonnPerm'];
            $line[] = $prj_array['qt'];
            if (strpos($prj_array['sondVolDonnPerm'],'sais pas')===false && strpos($prj_array['sondVolDonnPerm'],'<')===false)
            {
				if (intval($prj_array['sondVolDonnPerm']) != intval($prj_array['qt']))
				{
					$line[] = 1;
				}
				else
				{
					$line[] = 0;
				}
			}
			else
			{
				if (intval($prj_array['qt']) != 1)
				{
					$line[] = 1;
				}
				else
				{
					$line[] = 0;
				}
			}
			$line[] = $prj_array['c'];
			$line[] = $prj_array['dataMetaDataFormat'];
			$line[] = $prj_array['dataNombreDatasets'];
			$line[] = $prj_array['dataTailleDatasets'];
            $sortie .=   join("\t",$line) . "\n";
        }
        return Functions::csv($sortie,'donnees'.$annee.'.csv');
    }

    /**
     * Projets de l'année en CSV
     *
     * @Route("/{annee}/annee_csv", name="projet_annee_csv")
     * @Method({"GET","POST"})
	 * @Security("is_granted('ROLE_OBS')")
     */
    public function anneeCSVAction($annee)
    {
		$sp      = $this->get('app.gramc.ServiceProjets');
        $paa     = $sp->projetsParAnnee($annee);
        $projets = $paa[0];
        $total   = $paa[1];
        $sortie = '';

        $header  = [
                    'projets '.$annee,
                    'titre',
                    'thématique',
                    'rattachement',
                    'courriel du resp',
                    'prénom',
                    'nom',
                    'laboratoire',
                    'heures demandées A',
                    'heures demandées B',
                    'heures attribuées A',
                    'heures attribuées B',
                    'rallonges',
                    'pénalités A',
                    'pénalités B',
                    'heures attribuées',
                    'quota machine',
                    'heures consommées',
                    'heures gpu',
                    ];

        $sortie     .=   join("\t",$header) . "\n";
        foreach ($projets as $prj_array) {
            $p = $prj_array['p'];
            $va= $prj_array['va'];
            $vb= $prj_array['vb'];
            $line = [];
            $line[] = $p->getIdProjet();
            $line[] = $p->getTitre();
            $line[] = $p->getThematique();
			$line[] = $p->getRattachement();
            $line[] = $prj_array['resp']->getMail();
            $line[] = $prj_array['resp']->getNom();
            $line[] = $prj_array['resp']->getPrenom();
            $line[] = $prj_array['labo'];
            $line[] = empty($va)?'':$va->getDemHeures();
            $line[] = empty($vb)?'':$vb->getDemHeures();
            $line[] = empty($va)?'':$va->getAttrHeures();
            $line[] = empty($vb)?'':$vb->getAttrHeures();
            $line[] = $prj_array['r'];
            $line[] = -$prj_array['penal_a'];
            $line[] = -$prj_array['penal_b'];
            $line[] = $prj_array['attrib'];
            $line[] = $prj_array['q'];
            $line[] = $prj_array['c'];
            $line[] = $prj_array['g'];

            $sortie     .=   join("\t",$line) . "\n";
        }
        return Functions::csv($sortie,'projets_'.$annee.'.csv');
    }

    /**
     * download rapport
     * @Security("is_granted('ROLE_DEMANDEUR') or is_granted('ROLE_OBS')")
     * @Route("/{id}/rapport/{annee}", defaults={"annee"=0}, name="rapport")
     * @Method("GET")
     */
    public function rapportAction(Version $version, Request $request, $annee )
    {
		$sp = $this->get('app.gramc.ServiceProjets');
		$sj = $this->get('app.gramc.ServiceJournal');
		
        if( ! $sp->projetACL( $version->getProjet() ) )
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');

        if ($annee == 0 )
        {
			// Si on ne précise pas on prend le rapport de l'année précédente
			// (pour les sessions A)
	        $annee    = $version->getAnneeSession()-1;
		}
		$filename = $sp->getRapport( $version->getProjet(), $annee );

        //return new Response($filename);

        if(  file_exists( $filename ) )
		{
            return Functions::pdf( file_get_contents ($filename ) );
		}
        else
		{
            $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " fichier du rapport d'activité \"" . $filename . "\" n'existe pas");
            return Functions::pdf( null );
		}
    }

    /**
     * download signature
     *
     * @Route("/{id}/signature", name="signature")
     * @Security("is_granted('ROLE_OBS')")
     * @Method("GET")
     */
    public function signatureAction(Version $version, Request $request)
    {
        $sv = $this->get('app.gramc.ServiceVersions');
	    return Functions::pdf( $sv->getSigne($version) );
    }

    /**
     * Lists all projet entities.
     *
     * @Route("/tous", name="projet_tous")
     * @Method("GET")
	 * @Security("is_granted('ROLE_OBS')")
     */
    public function tousAction()
    {
		$em      = $this->getDoctrine()->getManager();
        $projets = $em->getRepository(Projet::class)->findAll();
        $sp      = $this->get('app.gramc.ServiceProjets');

		foreach (['termine','standby','accepte','refuse','edition','expertise','nonrenouvele'] as $e)
		{
			$etat_projet[$e]         = 0;
			$etat_projet[$e.'_test'] = 0;
		}

        $data = [];

        $collaborateurVersionRepository = $em->getRepository(CollaborateurVersion::class);
        $versionRepository              = $em->getRepository(Version::class);
        $projetRepository               = $em->getRepository(Projet::class);

        foreach ( $projets as $projet )
        {
            $info     = $versionRepository->info($projet); // les stats du projet
            $version  = $versionRepository->findVersionDerniere($projet);
            $metaetat = strtolower($sp->getMetaEtat($projet));

            if ( $projet->getTypeProjet() == Projet::PROJET_TEST )
            {
				$etat_projet[$metaetat.'_test'] += 1;
			}
			else
			{
				$etat_projet[$metaetat] += 1;
			}
			
            $data[] = [
                    'projet'       => $projet,
                    'metaetat'     => $metaetat,
                    'version'      => $version,
                    'etat_version' => ($version != null ) ? Etat::getLibelle( $version->getEtatVersion() ): 'SANS_VERSION',
                    'count'        => $info[1],
                    'dem'          => $info[2],
                    'attr'         => $info[3],
                    'responsable'  => $collaborateurVersionRepository->getResponsable($projet),
            ];
        }

        $etat_projet['total']      = $projetRepository->countAll();
        $etat_projet['total_test'] = $projetRepository->countAllTest();

        return $this->render('projet/projets_tous.html.twig',
        [
            'etat_projet'   =>  $etat_projet,
            'data' => $data,
        ]);
    }

    /**
     * Lists all projet entities.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/gerer", name="gerer_projets")
     * @Method("GET")
     */
    public function gererAction()
    {
		$em = $this->getDoctrine()->getManager();
        $projets = $em->getRepository(Projet::class)->findAll();

        return $this->render('projet/gerer.html.twig', array(
            'projets' => $projets,
        ));
    }

    /**
     * Creates a new projet entity.
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/new", name="projet_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $projet = new Projet(Projet::PROJET_SESS);
        $form = $this->createForm('App\Form\ProjetType', $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($projet);
            $em->flush($projet);

            return $this->redirectToRoute('projet_show', array('id' => $projet->getId()));
        }

        return $this->render('projet/new.html.twig', array(
            'projet' => $projet,
            'form' => $form->createView(),
        ));
    }

    /**
     * Envoie un écran de mise en garde avant de créer un nouveau projet
     *
     * @Route("/avant_nouveau/{type}", name="avant_nouveau_projet")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     *
     */
    public function avantNouveauAction(Request $request,$type)
    {
		$sm = $this->get('app.gramc.ServiceMenus');
		$sj = $this->get('app.gramc.ServiceJournal');
		$token = $this->get('security.token_storage')->getToken();

        if( $sm->nouveau_projet($type)['ok'] == false )
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de créer un nouveau projet parce que " . $sm->nouveau_projet($type)['raison'] );

	    $projetRepository = $this->getDoctrine()->getManager()->getRepository(Projet::class);
	    $id_individu      = $token->getUser()->getIdIndividu();
	    $renouvelables    = $projetRepository-> getProjetsCollab($id_individu);
        if( $renouvelables == null )   return  $this->redirectToRoute('nouveau_projet', ['type' => $type]);

        return $this->render('projet/avant_nouveau_projet.html.twig',
		[
            'renouvelables' => $renouvelables,
            'type'          => $type
		]);
    }

    /**
     * Création d'un nouveau projet
     *
     * @Route("/nouveau/{type}", name="nouveau_projet")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     *
     */
    public function nouveauAction(Request $request, $type)
    {
		$sd = $this->get('app.gramc.date');
		$sm = $this->get('app.gramc.ServiceMenus');
		$ss = $this->get('app.gramc.ServiceSessions');
		$sss= $this->get('session');
		$sp = $this->get('app.gramc.ServiceProjets');
		$sv = $this->get('app.gramc.ServiceVersions');
		$sj = $this->get('app.gramc.ServiceJournal');
		$token = $this->get('security.token_storage')->getToken();
		$em = $this->getDoctrine()->getManager();
		
		// Si changement d'état de la session alors que je suis connecté !
		// + contournement d'un problème lié à Doctrine
        $sss->remove('SessionCourante'); // remove cache

        // NOTE - Pour ce controleur, on identifie les types par un chiffre (voir Entity/Projet.php)
        $m = $sm->nouveau_projet("$type");
        if ($m == null || $m['ok']==false)
        {
			$raison = $m===null?"ERREUR AVEC LE TYPE $type - voir le paramètre prj_type":$m['raison'];
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de créer un nouveau projet parce que $raison");
         }

        $session  = $ss -> getSessionCourante();
		$prefixes = $this->getParameter('prj_prefix');
		if ( !isset ($prefixes[$type]) || $prefixes[$type]==="" )
	    {
			$sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Pas de préfixe pour le type $type. Voir le paramètre prj_prefix");
			return $this->redirectToRoute('accueil');
		}

		// Création du projet
		$annee    = $session->getAnneeSession();
        $projet   = new Projet($type);
        $projet->setIdProjet($sp->NextProjetId($annee,$type));
        switch ($type)
        {
			case Projet::PROJET_SESS:
			case Projet::PROJET_FIL:
	            $projet->setEtatProjet(Etat::RENOUVELABLE);
	            break;
	        case Projet::PROJET_TEST:
	            $projet->setEtatProjet(Etat::NON_RENOUVELABLE);
	            break;
	        default:
	           $sj->throwException(__METHOD__ . ":" . __LINE__ . " mauvais type de projet " . Functions::show( $type) );
		}

		// Création de la première (et dernière) version
        $version    =   new Version();
        $version->setIdVersion( $session->getIdSession() . $projet->getIdProjet() );
        $version->setProjet( $projet );

        //$projet->setVersionDerniere($version);
        $version->setSession( $session );
        $sv->setLaboResponsable($version, $token->getUser());
        //return new Response( Functions::show( $version ) );
        if( $type == Projet::PROJET_SESS )
            $version->setEtatVersion(Etat::EDITION_DEMANDE);
        else
            $version->setEtatVersion(Etat::EDITION_TEST);

		// Définition de $version en tant que versionDerniere du projet
		// PAS BESOIN CAR C'EST FAIT DANS L'EVENTLISTENER !
		// (pour la cohérence de la BD)
		////$projet->setVersionDerniere($version);
		
		// Affectation de l'utilisateur connecté en tant que responsable
        $moi = $token->getUser();
        $collaborateurVersion = new CollaborateurVersion( $moi );
        $collaborateurVersion->setVersion( $version );
        $collaborateurVersion->setResponsable( true );

		// Sauvegarde des données
        $em->persist( $projet );
        $em->persist( $version );
        $em->persist( $collaborateurVersion );
        $em->flush();

        return $this->redirectToRoute('modifier_version',[ 'id' => $version->getIdVersion() ] );

    }

    /**
     * Affichage graphique de la consommation d'un projet
     *     Affiche un menu permettant de choisir quelle consommation on veut voir afficher
     *
     * @Route("/{id}/conso/{annee}", name="projet_conso")
     * @Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */

    public function consoAction(Projet $projet, $annee = null)
    {
		$sp = $this->get('app.gramc.ServiceProjets');
		$sj = $this->get('app.gramc.ServiceJournal');


        // Seuls les collaborateurs du projet ont accès à la consommation
        if( ! $sp->projetACL( $projet ) )
        {
			$sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
		}

        // Si année non spécifiée on prend l'année la plus récente du projet
        if( $annee == null )
        {
            $version    =   $projet->derniereVersion();
            $annee = '20' . substr( $version->getIdVersion(), 0, 2 );
        }

        return $this->render('projet/conso_menu.html.twig', 
							['projet'=>$projet, 
							 'annee'=>$annee, 
							 'types'=>['group','user'],
							 'titres'=>['group' => 'Les consos de mon groupe',
										'user' => 'Mes consommations']
							 ]);
	}

    /**
     * Affichage graphique de la consommation d'un projet
     *
     *      utype = type d'utilisateur - user ou group !
     *
     * @Route("/{id}/{utype}/{ress_id}/{annee}/conso_ressource", name="projet_conso_ressource")
     * @Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */

    public function consoRessourceAction(Projet $projet, $utype, $ress_id, $annee = null)
    {
		$em = $this->getDoctrine()->getManager();
		$sp = $this->get('app.gramc.ServiceProjets');
		$sj = $this->get('app.gramc.ServiceJournal');


		$dessin_heures = $this -> get('app.gramc.graf_calcul');
		$compta_repo   = $em->getRepository(Compta::class);
		$projet_id     = strtolower($projet->getIdProjet());

        // Seuls les collaborateurs du projet ont accès à la consommation
        if( ! $sp->projetACL( $projet ) )
		{
			$sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
		}

        // Verification du paramètre $utype
        if ($utype != 'group' && $utype != 'user')
        {
			$sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec utype '.$utype);
		}

        // Si année non spécifiée on prend l'année la plus récente du projet
        if( $annee == null )
        {
            $version    =   $projet->derniereVersion();
            $annee = '20' . substr( $version->getIdVersion(), 0, 2 );
        }

        $debut = new \DateTime( $annee . '-01-01');
        $fin   = new \DateTime( $annee . '-12-31');

		$ressource = $this->getParameter('ressources_conso_'.$utype)[$ress_id];
		//$sj->debugMessage(__METHOD__.':'.__LINE__. " projet $projet - $utype - ressource = ".print_r($ressource,true));

		// Génération du graphe de conso heures cpu et heures gpu
		// Note - type ici n'a rien à voir avec le paramètre $utype
		if ($ressource['type'] == 'calcul')
		{
			$id_projet     = $projet->getIdProjet();
	        $db_conso      = $compta_repo->conso( $id_projet, $annee );
			$struct_data   = $dessin_heures->createStructuredData($debut,$fin,$db_conso);
			$dessin_heures->resetConso($struct_data);
	        $image_conso     = $dessin_heures->createImage($struct_data)[0];
		}
		elseif ($ressource['type'] == 'stockage')
		{
			$db_work     = $compta_repo->consoResPrj( $projet, $ressource, $annee );
			$dessin_work = $this -> get('app.gramc.graf_stockage');
	        $struct_data = $dessin_work->createStructuredData($debut,$fin,$db_work,$ressource['unite']);
	        $image_conso = $dessin_work->createImage($struct_data, $ressource)[0];
		}

		$twig     = $this->get('twig');
		$template = $twig->createTemplate('<img src="data:image/png;base64, {{ image_conso }}" alt="" title="" />');
		$html     = $twig->render($template, [ 'image_conso' => $image_conso ]);
		
        //$twig     = new \Twig_Environment( new \Twig_Loader_String(), array( 'strict_variables' => false ) );
        //$twig_src = '<img src="data:image/png;base64, {{ image_conso }}" alt="" title="" />';
        //$html = $twig->render( $twig_src,  [ 'image_conso' => $image_conso ] );

		return new Response($html);
    }

    /**
     * Affichage graphique de la consommation de TOUS les projets
     *
     * @Route("/{ressource/{ressource}/tousconso/{annee}/{mois}", name="tous_projets_conso")
     * @Method("GET")
     * @Security("is_granted('ROLE_ADMIN')")
     */

    public function consoTousAction($ressource,$annee,$mois=false)
    {
		$em = $this->getDoctrine()->getManager();
		
		if ( $ressource != 'cpu' && $ressource != 'gpu' )
		{
			return "";
		}

        $db_conso = $em->getRepository(Compta::class)->consoTotale( $annee, $ressource );

		$debut = new \DateTime( $annee . '-01-01');
		$fin   = new \DateTime( $annee . '-12-31');

        $dessin_heures = $this->get('app.gramc.graf_calcultous');

        if ($mois == true)
        {
	        $struct_data = $dessin_heures->createStructuredDataMensuelles($annee,$db_conso);
	        $dessin_heures->derivConso($struct_data);
		}
		else
        {
	        $struct_data = $dessin_heures->createStructuredData($debut,$fin,$db_conso);
	        $dessin_heures->resetConso($struct_data);
		}
        $image_conso     = $dessin_heures->createImage($struct_data)[0];

		$twig     = $this->get('twig');
		$template = $twig->createTemplate('<img src="data:image/png;base64, {{ ImageConso }}" alt="Heures cpu/gpu" title="Heures cpu et gpu" />');
		$html     = $twig->render($template, [ 'ImageConso' => $image_conso ]);

		return new Response($html);
    }

    /**
     * Montre projets d'un utilisateur
     *
     * @Route("/accueil", name="projet_accueil")
     * @Route("/accueil/", name="projet_accueil1")
     * @Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function accueilAction()
    {
		$sm                  = $this->get('app.gramc.ServiceMenus');
		$ss                  = $this->get('app.gramc.ServiceSessions');
		$sp					 = $this->get('app.gramc.ServiceProjets');
		$token               = $this->get('security.token_storage')->getToken();
		$em                  = $this->getDoctrine()->getManager();
	    $individu            = $token->getUser();
	    $id_individu         = $individu->getIdIndividu();

	    $projetRepository    = $em->getRepository(Projet::class);
	    $coll_ver            = $em->getRepository(CollaborateurVersion::class);

	    $list_projets_collab = $projetRepository-> getProjetsCollab($id_individu, false, true);
	    $list_projets_resp   = $projetRepository-> getProjetsCollab($id_individu, true, false);

	    $projets_term        = $projetRepository-> get_projets_etat( $id_individu, 'TERMINE' );

	    $session_actuelle    = $ss->getSessionCourante();

	    // projets responsable
	    $projets_resp  = [];
	    foreach ( $list_projets_resp as $projet )
		{
	        $versionActive  =   $sp->versionActive($projet);
	        if( $versionActive != null )
	        {
	            $rallonges = $versionActive ->getRallonge();
	            $cpt_rall  = count($rallonges->toArray());
	            $cv        = $coll_ver->findOneBy(['version' => $versionActive, 'collaborateur' => $individu]);
	            $login     = $cv->getLoginname();
	            $passwd    = $cv->getPassword();
	            $pwd_expir = $cv->getPassexpir();
	        }
	        else
	        {
	            $rallonges = null;
	            $cpt_rall  = 0;
	            $login     = null;
	            $passwd    = null;
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
	    foreach ( $list_projets_collab as $projet )
		{
	        $versionActive = $sp->versionActive($projet);
	        
	        if( $versionActive != null )
	        {
	            $rallonges = $versionActive ->getRallonge();
	            $cpt_rall  = count($rallonges->toArray());
	            $cv        = $coll_ver->findOneBy(['version' => $versionActive, 'collaborateur' => $individu]);
	            $login     = $cv->getLoginname();
	            $passwd    = $cv->getPassword();
	            $pwd_expir = $cv->getPassexpir();
			}
	        else
	        {
	            $rallonges = null;
				$cpt_rall  = 0;
	            $login     = null;
	            $passwd    = null;
	            $pwd_expir = null;
			}
	        $projets_collab[]   =
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

		$prefixes = $this->getParameter('prj_prefix');
		foreach (array_keys($prefixes) as $t)
		{
			$menu[] = $sm->nouveau_projet($t);
		}

	    return $this->render('projet/demandeur.html.twig',
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
     * @Route("/{id}/consulter", name="consulter_projet")
     * @Route("/{id}/consulter/{version}", name="consulter_version")
     * @Method({"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function consulterAction(Projet $projet, Version $version = null,  Request $request)
    {
		$sp = $this->get('app.gramc.ServiceProjets');
		$sj = $this->get('app.gramc.ServiceJournal');

        // choix de la version
        if( $version == null ) 
        {
            $version =  $projet->getVersionDerniere();
            if ( $version == null)
            {
				$sj->throwException(__METHOD__ . ':' . __LINE__ .' Projet ' . $projet . ': la dernière version est nulle !');
			}
		}
        else
            $projet =   $version->getProjet(); // nous devons être sûrs que le projet corresponde à la version

         if( ! $sp->projetACL( $projet ) )
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');

		// LA SUITE DEPEND DU TYPE DE PROJET !
		$type = $projet->getTypeProjet();
		switch ($type)
		{
			case Projet::PROJET_SESS:
				return $this->consulterType1($projet, $version, $request);
			case Projet::PROJET_TEST:
				return $this->consulterType2($projet, $version, $request);
			case Projet::PROJET_FIL:
				return $this->consulterType3($projet, $version, $request);
			default:
				$sj->errorMessage(__METHOD__ . " Type de projet inconnu: $type");
		}
    }

	// Consulter les projets de type 1 (projets PROJET_SESS)
    private function consulterType1(Projet $projet, Version $version, Request $request)
    {
		$sm = $this->get('app.gramc.ServiceMenus');
		$sp = $this->get('app.gramc.ServiceProjets');
		$ac = $this->get('security.authorization_checker');
		$sv = $this->get('app.gramc.ServiceVersions');
		$sj = $this->get('app.gramc.ServiceJournal');
		$ff = $this->get('form.factory');


	    $session_form = Functions::createFormBuilder($ff, ['version' => $version ] )
	        ->add('version',   EntityType::class,
	                [
	                'multiple' => false,
	                'class' => 'App:Version',
	                'required'  =>  true,
	                'label'     => '',
	                'choices' =>  $projet->getVersion(),
	                'choice_label' => function($version){ return $version->getSession(); }
	                ])
	    ->add('submit', SubmitType::class, ['label' => 'Changer'])
	    ->getForm();

	    $session_form->handleRequest($request);

	    if ( $session_form->isSubmitted() && $session_form->isValid() )
	    {
	        $version = $session_form->getData()['version'];
		}

	    if( $version != null )
	    {
	        $session = $version->getSession();
		}
	    else
	    {
	        $sj->throwException(__METHOD__ . ':' . __LINE__ .' projet ' . $projet . ' sans version');
		}

		$menu = [];
	    if ($ac->isGranted('ROLE_ADMIN'))
	    {
			$menu[] = $sm->rallonge_creation( $projet );
		}
	    $menu[] = $sm->changer_responsable($version);
	    $menu[] = $sm->renouveler_version($version);
	    $menu[] = $sm->modifier_version( $version );
	    $menu[] = $sm->envoyer_expert( $version );
	    $menu[] = $sm->modifier_collaborateurs( $version );
		$menu[] = $sm->donnees( $version );
	    $menu[] = $sm->telechargement_fiche( $version );
	    $menu[] = $sm->televersement_fiche( $version );

	    $etat_version = $version->getEtatVersion();
	    if( ($etat_version == Etat::ACTIF || $etat_version == Etat::TERMINE ) && ! $sp->hasRapport( $projet, $version->getAnneeSession() ) )
	    {
		    $menu[] = $sm->telecharger_modele_rapport_dactivite( $version );
	        $menu[] = $sm->televerser_rapport_annee( $version );
		}
		
	    $menu[]       = $sm->gerer_publications( $projet );
	    $img_expose_1 = $sv->imageProperties('img_expose_1', $version);
	    $img_expose_2 = $sv->imageProperties('img_expose_2', $version);
	    $img_expose_3 = $sv->imageProperties('img_expose_3', $version);

	    /*
	    if( $img_expose_1 == null )
	        $sj->debugMessage(__METHOD__.':'.__LINE__ ." img_expose1 null");
	    else
	        $sj->debugMessage(__METHOD__.':'.__LINE__ . " img_expose1 non null");
	    */

	    $img_justif_renou_1 = $sv->imageProperties('img_justif_renou_1', $version);
	    $img_justif_renou_2 = $sv->imageProperties('img_justif_renou_2', $version);
	    $img_justif_renou_3 = $sv->imageProperties('img_justif_renou_3', $version);

	    $toomuch = false;
	    if ($session->getLibelleTypeSession()=='B' && ! $sv->isNouvelle($version)) {
	        $version_prec = $version->versionPrecedente();
	        if ($version_prec->getAnneeSession() == $version->getAnneeSession()) {
	            $toomuch  = $sv -> is_demande_toomuch($version_prec->getAttrHeures(),$version->getDemHeures());
	        }
	    }
	    $rapport_1 = $sp -> getRapport($projet, $version->getAnneeSession() - 1);
	    $rapport   = $sp -> getRapport($projet, $version->getAnneeSession());
	    return $this->render('projet/consulter_projet_sess.html.twig',
            [
	            'projet'             => $projet,
	            'version_form'       => $session_form->createView(),
	            'version'            => $version,
	            'session'            => $session,
	            'menu'               => $menu,
	            'img_expose_1'       => $img_expose_1,
	            'img_expose_2'       => $img_expose_2,
	            'img_expose_3'       => $img_expose_3,
	            'img_justif_renou_1' => $img_justif_renou_1,
	            'img_justif_renou_2' => $img_justif_renou_2,
	            'img_justif_renou_3' => $img_justif_renou_3,
	            'conso_cpu'          => $sp->getConsoRessource($projet,'cpu',$version->getAnneeSession()),
	            'conso_gpu'          => $sp->getConsoRessource($projet,'gpu',$version->getAnneeSession()),
	            'rapport_1'          => $rapport_1,
	            'rapport'            => $rapport,
	            'toomuch'            => $toomuch
            ]
	            );
	}

	// Consulter les projets de type 2 (projets test)
	private function consulterType2 (Projet $projet, Version $version, Request $request)
	{
		$sm = $this->get('app.gramc.ServiceMenus');
		$sp = $this->get('app.gramc.ServiceProjets');
		$ac = $this->get('security.authorization_checker');

        if( $ac->isGranted('ROLE_ADMIN'))
        {
			$menu[] = $sm->rallonge_creation( $projet );
		}
        $menu[] = $sm->modifier_version( $version );
        $menu[] = $sm->envoyer_expert( $version );
        $menu[] = $sm->modifier_collaborateurs( $version );

        return $this->render('projet/consulter_projet_test.html.twig',
            [
            'projet'      => $projet,
            'version'     => $version,
            'consocalcul' => $sp->getConsoCalculVersion($version),
            'quotacalcul' => $sp->getQuotaCalculVersion($version),
            'menu'        => $menu,
            ]
            );
	}

	// Consulter les projets de type 3 (projets PROJET_FIL)
    private function consulterType3(Projet $projet, Version $version, Request $request)
    {
		$sm = $this->get('app.gramc.ServiceMenus');
		$sv = $this->get('app.gramc.ServiceVersions');
		$sp = $this->get('app.gramc.ServiceProjets');
		$sj = $this->get('app.gramc.ServiceJournal');
		$ac = $this->get('security.authorization_checker');
		$ff = $this->get('form.factory');

	    $session_form = Functions::createFormBuilder($ff, ['version' => $version ] )
	        ->add('version',   EntityType::class,
	                [
	                'multiple' => false,
	                'class' => 'App:Version',
	                'required'  =>  true,
	                'label'     => '',
	                'choices' =>  $projet->getVersion(),
	                'choice_label' => function($version){ return $version->getSession(); }
	                ])
	    ->add('submit', SubmitType::class, ['label' => 'Changer'])
	    ->getForm();

	    $session_form->handleRequest($request);

	    if ( $session_form->isSubmitted() && $session_form->isValid() )
	        $version = $session_form->getData()['version'];

	    if( $version != null )
	        $session = $version->getSession();
	    else
	        $sj->throwException(__METHOD__ . ':' . __LINE__ .' projet ' . $projet . ' sans version');

	    if( $ac->isGranted('ROLE_ADMIN')  ) $menu[] = $sm->rallonge_creation( $projet );
	    $menu[] =   $sm->changer_responsable($version);
	    $menu[] =   $sm->renouveler_version($version);
	    $menu[] =   $sm->modifier_version( $version );
	    $menu[] =   $sm->envoyer_expert( $version );
	    $menu[] =   $sm->modifier_collaborateurs( $version );
	    $menu[] =   $sm->telechargement_fiche( $version );
	    $menu[] =   $sm->televersement_fiche( $version );
	    $menu[] =   $sm->telecharger_modele_rapport_dactivite( $version );

	    $etat_version = $version->getEtatVersion();
	    if( ($etat_version == Etat::ACTIF || $etat_version == Etat::TERMINE ) && ! $sp->hasRapport( projet, $version->getAnneeSession() ) )
	        $menu[] =   $sm->televerser_rapport_annee( $version );

	    $menu[] =   $sm->gerer_publications( $projet );

	    $img_expose_1 = $sv->imageProperties('img_expose_1', $version);
	    $img_expose_2 = $sv->imageProperties('img_expose_2', $version);
	    $img_expose_3 = $sv->imageProperties('img_expose_3', $version);

	    /*
	    if( $img_expose_1 == null )
	        $sj->debugMessage(__METHOD__.':'.__LINE__ ." img_expose1 null");
	    else
	        $sj->debugMessage(__METHOD__.':'.__LINE__ . " img_expose1 non null");
	    */

	    $img_justif_renou_1 = $sv->imageProperties('img_justif_renou_1', $version);
	    $img_justif_renou_2 = $sv->imageProperties('img_justif_renou_2', $version);
	    $img_justif_renou_3 = $sv->imageProperties('img_justif_renou_3', $version);

	    $toomuch = false;
	    if ($session->getLibelleTypeSession()=='B' && ! $sv->isNouvelle($version)) {
	        $version_prec = $version->versionPrecedente();
	        if ($version_prec->getAnneeSession() == $version_prec->getAnneeSession()) {
	            $toomuch = $sv->is_demande_toomuch($version_prec->getAttrHeures(),$version->getDemHeures());
	        }
	    }

	    return $this->render('projet/consulter_projet_fil.html.twig',
	            [
	            'projet' => $projet,
	            'version_form'   => $session_form->createView(),
	            'version'   =>  $version,
	            'session'   =>  $session,
	            'menu'      =>  $menu,
	            'img_expose_1'  =>  $img_expose_1,
	            'img_expose_2'  =>  $img_expose_2,
	            'img_expose_3'  =>  $img_expose_3,
	            'img_justif_renou_1'    =>  $img_justif_renou_1,
	            'img_justif_renou_2'    =>  $img_justif_renou_2,
	            'img_justif_renou_3'    =>  $img_justif_renou_3,
	            'toomuch'               => $toomuch
	            ]
	            );
	}

    /**
     * Finds and displays a projet entity.
     *
     * @Route("/modele", name="telecharger_modele")
     * @Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function telechargerModeleAction()
    {
	    return $this->render('projet/telecharger_modele.html.twig');
    }

    /**
     * Finds and displays a projet entity.
     *
     * @Route("/{id}", name="projet_show")
     * @Route("/{id}/show", name="consulter_show_projet")
     * @Method("GET")
	 * @Security("is_granted('ROLE_OBS')")
	 */
    public function showAction(Projet $projet)
    {
        $deleteForm = $this->createDeleteForm($projet);

        return $this->render('projet/show.html.twig', array(
            'projet' => $projet,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing projet entity.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/edit", name="projet_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Projet $projet)
    {
        $deleteForm = $this->createDeleteForm($projet);
        $editForm = $this->createForm('App\Form\ProjetType', $projet);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('projet_edit', array('id' => $projet->getId()));
        }

        return $this->render('projet/edit.html.twig', array(
            'projet' => $projet,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a projet entity.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}", name="projet_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Projet $projet)
    {
        $form = $this->createDeleteForm($projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($projet);
            $em->flush($projet);
        }

        return $this->redirectToRoute('projet_index');
    }

    /**
     * Creates a form to delete a projet entity.
     *
     * @param Projet $projet The projet entity
     * @Security("is_granted('ROLE_ADMIN')")
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Projet $projet)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('projet_delete', array('id' => $projet->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
