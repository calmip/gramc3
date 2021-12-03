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
//use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
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

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use Twig\Environment;

// Pour le tri numérique sur les années, en commençant par la plus grande - cf. resumesAction
function cmpProj($a, $b)
{
    return intval($a['annee']) < intval($b['annee']);
}

/**
 * Projet controller.
 *
 * @Route("projet")
 */
 // Tous ces controleurs sont exécutés au moins par OBS, certains par ADMIN seulement
 // et d'autres par DEMANDEUR

class ProjetController extends AbstractController
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
        $this->tw  = $tw;
        $this->ac  = $ac;
    }

    private static $count;

    /**
     * Lists all projet entities.
     *
     * @Route("/", name="projet_index", methods={"GET"})
     * Method("GET")
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
     * @Route("/old", name="projet_nettoyer", methods={"GET","POST"})
     * Method({"GET","POST"})
     */
    public function oldAction(Request $request)
    {
        $sd = $this->sd;
        $sj = $this->sj;
        $sp = $this->sp;
        $ff = $this->ff;
        $em = $this->getDoctrine()->getManager();

        $list = [];
        $mauvais_projets = [];
        static::$count = [];
        $annees =   [];

        $all_projets = $em->getRepository(Projet::class)->findAll();
        foreach ($all_projets as $projet) {
            $derniereVersion    =  $projet->derniereVersion();
            if ($derniereVersion == null) {
                $mauvais_projets[$projet->getIdProjet()]    =   $projet;
                $annee = 0;
            } else {
                $annee = $projet->derniereVersion()->getAnneeSession();
            }
            $list[$annee][] = $projet;
        }
        foreach ($list as $annee => $projets) {
            static::$count[$annee]  =   count($projets);
            $annees[]       =   $annee;
        }

        asort($annees);
        $form = Functions::createFormBuilder($ff)
            ->add(
                'annee',
                ChoiceType::class,
                [
                    'required'  =>  false,
                    'label'     => ' Année ',
                    'choices'   =>  $annees,
                    'choice_label' => function ($choiceValue, $key, $value) {
                        return  $choiceValue . '  (' . ProjetController::$count[$choiceValue] . ' projets)';
                    }
                    ]
            )
        ->add('supprimer projets', SubmitType::class, ['label' => ""])
        ->add('supprimer utilisateurs sans projet', SubmitType::class, ['label' => ""])
        ->add('nettoyer le journal', SubmitType::class, ['label' => ""])
        ->getForm();

        $date = clone $sd;
        $date->sub(\DateInterval::createFromDateString('1 year'));
        $individus = $em->getRepository(Individu::class)->liste_avant($date);
        $utilisateurs_a_effacer = $sp->utilisateurs_a_effacer($individus);
        $individus_effaces = [];
        $projets_effaces = [];
        $old_journal = null;
        $journal = false;

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $form->get('supprimer utilisateurs sans projet')->isClicked()) {
            $individus_effaces = $sp->effacer_utilisateurs($utilisateurs_a_effacer);
            $utilisateurs_a_effacer = [];
        //return new Response( Functions::show( $individus_effaces ) );
        } elseif ($form->isSubmitted() && $form->isValid() && $form->get('nettoyer le journal')->isClicked()) {
            if ($this->container->hasParameter('old_journal')) {
                $old_journal = intval($this->getParameter('old_journal'));
                if ($old_journal > 0) {
                    $date = clone $sd;
                    $date->sub(\DateInterval::createFromDateString($old_journal . ' year'));
                    // return new Response( Functions::show( [$old_journal,$date] ) );
                    $journal = $em->getRepository(Journal::class)->liste_avant($date);
                    foreach ($journal as $item) {
                        $em->remove($item);
                    }
                    $em->flush();
                    $journal = true;
                } else {
                    $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " La valeur du paramètre old_journal est " . $old_journal);
                }
            } else {
                $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " Le paramètre old_journal manque");
            }
        } elseif ($form->isSubmitted() && $form->isValid() && $form->get('supprimer projets')->isClicked()) {
            $annee = $form->getData()['annee']; // par exemple 2014

            $key = array_search($annee, $annees); // supprimer l'année de la liste du formulaire
            if ($key !== false) {
                unset($annees[$key]);
            }

            $individus = [];
            foreach ($list[$annee] as $projet) {
                foreach ($projet->getVersion() as $version) {
                    foreach ($version->getCollaborateurs() as $collaborateur) {
                        $individus[$collaborateur->getIdIndividu()]    =  $collaborateur;
                    }
                    foreach ($version->getExpertise() as $expertise) {
                        if ($expertise->getExpert() != null) {
                            $individus[$expertise->getExpert()->getIdIndividu()]    =  $expertise->getExpert();
                        }
                    }
                    foreach ($version->getRallonge() as $rallonge) {
                        if ($rallonge->getExpert() != null) {
                            $individus[$rallonge->getExpert()->getIdIndividu()]    =  $rallonge->getExpert();
                        }
                    }
                }
            }

            // effacer des structures
            foreach ($list[$annee] as $projet) {
                $em->persist($projet);
                //$projet->setVersionDerniere( null );
                //$projet->setVersionActive( null );
                $em->flush();

                // effacer des documents
                $sp->erase_directory($this->getParameter('rapport_directory'), $projet);
                $sp->erase_directory($this->getParameter('signature_directory'), $projet);
                $sp->erase_directory($this->getParameter('fig_directory'), $projet);

                //continue; //DEBUG

                foreach ($projet->getVersion() as $version) {
                    $em->persist($version);
                    foreach ($version->getCollaborateurVersion() as $item) {
                        $em->remove($item);
                        //$em->flush();
                    }

                    foreach ($version->getExpertise() as $item) {
                        $em->remove($item);
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

                    $em->remove($version);
                }

                $versions = $em->getRepository(Version::class)->findBy(['projet' => $projet]);
                foreach ($versions as $item) {
                    $em->remove($item);
                }

                $loginname = strtolower($projet->getIdProjet());
                foreach ($em->getRepository(Compta::class)->findBy(['loginname' => $loginname]) as $item) {
                    $em->remove($item);
                }

                foreach ($projet->getRapportActivite() as $rapport) {
                    $em->remove($rapport);
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
                $em->remove($projet);
            }

            //return new Response(Functions::show( $projets_effaces ) ); //DEBUG

            $em->flush();

            // effacer des anciens utilisateurs

            $individus_effaces = $sp->effacer_utilisateurs($individus);

            //return new Response( Functions::show( $individus_effaces ) );
        }
        //return new Response( Functions::show( $annees ) );

        $form = Functions::createFormBuilder($ff)
            ->add(
                'annee',
                ChoiceType::class,
                [
                    'required'  =>  false,
                    'label'     => ' Année ',
                    'choices'   =>  $annees,
                    'choice_label' => function ($choiceValue, $key, $value) {
                        return  $choiceValue . '  (' . ProjetController::$count[$choiceValue] . ' projets)';
                    }
                    ]
            )
        ->add('supprimer projets', SubmitType::class, ['label' => ""])
        ->add('supprimer utilisateurs sans projet', SubmitType::class, ['label' => ""])
        ->add('nettoyer le journal', SubmitType::class, ['label' => ""])
        ->getForm();

        return $this->render(
            'projet/old.html.twig',
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
            ]
        );

        //return new Response( Functions::show( $mauvais_projets ) );
    //return new Response( Functions::show( $count ) );
    //return new Response( Functions::show( $list ) );
    }

    /**
     * Projets par session en CSV
     *
     * @Route("/{id}/session_csv", name="projet_session_csv", methods={"GET","POST"})
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     */
    public function sessionCSVAction(Session $session)
    {
        $em = $this->getDoctrine()->getManager();
        $sp = $this->sp;
        $sv = $this->sv;
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
        if ($this->getParameter('noconso')==false) {
            $ligne[] = 'heures consommées';
        }
        $sortie     .=   join("\t", $ligne) . "\n";

        $versions = $em->getRepository(Version::class)->findSessionVersions($session);
        foreach ($versions as $version) {
            $responsable    =   $version->getResponsable();
            $ligne  =
                    [
                    ($sv->isNouvelle($version) == true) ? 'OUI' : '',
                    $version->getProjet()->getIdProjet(),
                    $sp->getMetaEtat($version->getProjet()),
                    Functions::string_conversion($version->getPrjTitre()),
                    Functions::string_conversion($version->getPrjThematique()),
                    Functions::string_conversion($version->getPrjRattachement()),
                    $version->getPrjGenciDari(),
                    $responsable->getMail(),
                    Functions::string_conversion($responsable->getPrenom()),
                    Functions::string_conversion($responsable->getNom()),
                    Functions::string_conversion($version->getPrjLLabo()),
                    ($version->getResponsable()->getExpert()) ? '*******' : $version->getExpert(),
                    $version->getDemHeures(),
                    $version->getAttrHeures(),
                    ];
            if ($this->getParameter('noconso')==false) {
                $ligne[]= $sp->getConsoCalculVersion($version);
            }

            $sortie     .=   join("\t", $ligne) . "\n";
        }
        return Functions::csv($sortie, 'projet_session.csv');
    }

    /**
     * Lists all projet entities.
     *
     * @Route("/tous_csv", name="projet_tous_csv", methods={"GET","POST"})
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     */
    public function tousCSVAction()
    {
        $sd = $this->sd;
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

        $sortie     =   "Projets enregistrés dans gramc à la date du " . $sd->format('d-m-Y') . "\n" . join("\t", $entetes) . "\n";

        $projets = $em->getRepository(Projet::class)->findBy([], ['idProjet' => 'DESC' ]);
        foreach ($projets as $projet) {
            $version        =   $projet->getVersionDerniere();
            $responsable    =   $version->getResponsable();
            $info           =   $em->getRepository(Version::class)->info($projet);

            $ligne  =
                [
                $projet->getIdProjet(),
                Etat::getLibelle($projet->getEtatProjet()),
                Functions::string_conversion($version->getPrjTitre()),
                Functions::string_conversion($version->getPrjThematique()),
                $responsable->getMail(),
                Functions::string_conversion($responsable->getPrenom()),
                Functions::string_conversion($responsable->getNom()),
                Functions::string_conversion($version->getPrjLLabo()),
                $info[1],
                $version->getSession()->getIdSession(),
                $info[2],
                $info[3],
                ];
            $sortie     .=   join("\t", $ligne) . "\n";
        }

        return Functions::csv($sortie, 'projet_gramc.csv');
    }

    /**
     * fermer un projet
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/fermer", name="fermer_projet", methods={"GET","POST"})
     * Method({"GET","POST"})
     */
    public function fermerAction(Projet $projet, Request $request)
    {
        if ($request->isMethod('POST')) {
            $confirmation = $request->request->get('confirmation');

            if ($confirmation == 'OUI') {
                $workflow = $this->pw;
                if ($workflow->canExecute(Signal::CLK_FERM, $projet)) {
                    $workflow->execute(Signal::CLK_FERM, $projet);
                }
            }
            return $this->redirectToRoute('projet_tous'); // NON - on ne devrait jamais y arriver !
        } else {
            return $this->render(
                'projet/dialog_fermer.html.twig',
                [
            'projet' => $projet,
            ]
            );
        }
    }

    /**
     * Conserver un projet en standby
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/nepasterminer", name="nepasterminer_projet", methods={"GET"})
     * Method({"GET"})
     */
    public function nepasterminerAction(Projet $projet, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $projet->setNepasterminer(true);
        $em->persist($projet);
        $em->flush();
        return $this->render(
            'projet/nepasterminer.html.twig',
            [
            'projet' => $projet,
            ]
        );
    }

    /**
     * Permettre la fermeture d'un projet
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/onpeutterminer", name="onpeutterminer_projet", methods={"GET","POST"})
     * Method({"GET","POST"})
     */
    public function onpeutterminerAction(Projet $projet, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $projet->setNepasterminer(false);
        $em->persist($projet);
        $em->flush();
        return $this->render(
            'projet/onpeutterminer.html.twig',
            [
            'projet' => $projet,
            ]
        );
    }

    /**
     * back une version
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/back", name="back_version", methods={"GET","POST"})
     * Method({"GET","POST"})
     */
    public function backAction(Version $version, Request $request)
    {
        if ($request->isMethod('POST')) {
            $confirmation = $request->request->get('confirmation');

            if ($confirmation == 'OUI') {
                $workflow = $this->pw;
                if ($workflow->canExecute(Signal::CLK_ARR, $version->getProjet())) {
                    $workflow->execute(Signal::CLK_ARR, $version->getProjet());
                    // Supprime toutes les expertises
                    $expertises = $version->getExpertise()->toArray();
                    $em = $this->getDoctrine()->getManager();
                    foreach ($expertises as $e) {
                        $em->remove($e);
                    }
                    $em->flush();
                }
            }
            return $this->redirectToRoute('projet_session'); // NON - on ne devrait jamais y arriver !
        } else {
            return $this->render(
                'projet/dialog_back.html.twig',
                [
            'version' => $version,
            ]
            );
        }
    }

    /**
     * L'admin a cliqué sur le bouton Forward pour envoyer une version à l'expert
     * à la place du responsable
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/fwd", name="fwd_version", methods={"GET","POST"})
     * Method({"GET","POST"})
     */
    public function fwdAction(Version $version, Request $request, LoggerInterface $lg)
    {
        $se = $this->se;
        $em = $this->getDoctrine()->getManager();
        if ($request->isMethod('POST')) {
            $confirmation = $request->request->get('confirmation');

            if ($confirmation == 'OUI') {
                $workflow = $this->pw;
                if ($workflow->canExecute(Signal::CLK_VAL_DEM, $version->getProjet())) {
                    $workflow->execute(Signal::CLK_VAL_DEM, $version->getProjet());

                    // Crée une nouvelle expertise avec proposition d'experts
                    $se->newExpertiseIfPossible($version);
                }
            }
            return $this->redirectToRoute('projet_session');
        } else {
            return $this->render(
                'projet/dialog_fwd.html.twig',
                [
            'version' => $version,
            ]
            );
        }
    }

    /**
     * Liste tous les projets qui ont une version lors de cette session
     *
     * @Route("/session", name="projet_session", methods={"GET","POST"})
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     */
    public function sessionAction(Request $request)
    {
        $em             = $this->getDoctrine()->getManager();
        $ss             = $this->ss;
        $sp             = $this->sp;
        $sv             = $this->sv;

        $session        = $ss->getSessionCourante();
        $data           = $ss->selectSession($this->createFormBuilder(['session'=>$session]), $request); // formulaire
        $session        = $data['session']!=null ? $data['session'] : $session;
        $form           = $data['form'];
        $versions       = $em->getRepository(Version::class)->findSessionVersions($session);

        $demHeures      = 0;
        $attrHeures     = 0;
        $nombreProjets  = count($versions);
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
        if ($thematiques == null) {
            new Response('Aucune thématique !');
        }

        $idThematiques = [];
        $statsThematique = [];
        foreach ($thematiques as $thematique) {
            $statsThematique[$thematique->getLibelleThematique()] = 0;
            $idThematiques[$thematique->getLibelleThematique()] = $thematique->getIdThematique();
        }

        // Les rattachements
        $rattachements = $em->getRepository(Rattachement::class)->findAll();
        if ($rattachements == null) {
            $rattachements = [];
        }

        $statsRattachement = [];
        $idRattachements   = [];
        foreach ($rattachements as $rattachement) {
            $statsRattachement[$rattachement->getLibelleRattachement()] = 0;
            $idRattachements[$rattachement->getLibelleRattachement()] = $rattachement->getIdRattachement();
        }

        //$items  =   [];
        $versions_suppl = [];
        foreach ($versions as $version) {
            $id_version                   = $version->getIdVersion();
            $projet                       = $version->getProjet();
            $version_suppl                = [];
            $version_suppl['metaetat']    = $sp->getMetaEtat($projet);
            $version_suppl['consocalcul'] = $sp->getConsoCalculVersion($version);
            $version_suppl['isnouvelle']  = $sv->isNouvelle($version);
            $version_suppl['issigne']     = $sv->isSigne($version);
            $version_suppl['sizesigne']   = $sv->getSizeSigne($version);

            $annee_rapport = $version->getAnneeSession()-1;
            $version_suppl['rapport']     = $sp->getRapport($projet, $annee_rapport);
            $version_suppl['size_rapport']= $sp->getSizeRapport($projet, $annee_rapport);

            //Modif Callisto Septembre 2019
            $typeMetadata = $version -> getDataMetaDataFormat();
            $nombreDatasets = $version -> getDataNombreDatasets();
            $tailleDatasets = $version -> getDataTailleDatasets();
            $demHeures  +=  $version->getDemHeures();
            $attrHeures +=  $version->getAttrHeures();
            if ($sv->isNouvelle($version) == true) {
                $nombreNouveaux++;
            }
            if ($version->getPrjThematique() != null) {
                $statsThematique[$version->getPrjThematique()->getLibelleThematique()]++;
            }
            if ($version->getPrjRattachement() != null) {
                $statsRattachement[$version->getPrjRattachement()->getLibelleRattachement()]++;
            }

            if ($sv->isSigne($version)) {
                $nombreSignes++;
            }
            if ($sp->hasRapport($projet, $annee_rapport)) {
                $nombreRapports++;
            }
            if ($version->hasExpert()) {
                $nombreExperts++;
            }
            //if( $version->getAttrAccept() ) $nombreAcceptes++;
            if ($version_suppl['metaetat'] == 'ACCEPTE') {
                $nombreAcceptes++;
            }
            if ($version->getProjet() != null && $version->getProjet()->getEtatProjet() == $termine) {
                $nombreTermines++;
            }

            $etat = $version->getEtatVersion();
            $type = $version->getProjet()->getTypeProjet();


            // TODO Que c'est compliqué !
            //      Plusieurs workflows => pas d'états différents
            //      Utiliser un tableau
            if ($type == Projet::PROJET_TEST) {
                if ($etat == Etat::EDITION_TEST) {
                    $nombreEditionTest++;
                } elseif ($etat == Etat::EXPERTISE_TEST) {
                    $nombreExpertiseTest++;
                }
            } elseif ($type == Projet::PROJET_FIL) {
                if ($etat == Etat::EDITION_TEST) {
                    $nombreEditionFil++;
                } elseif ($etat == Etat::EXPERTISE_TEST) {
                    $nombreExpertiseFil++;
                }
            } elseif ($type == Projet::PROJET_SESS) {
                if ($etat == Etat::EDITION_DEMANDE) {
                    $nombreEdition++;
                } elseif ($etat == Etat::EDITION_EXPERTISE) {
                    $nombreExpertise++;
                }
            };

            if ($etat == Etat::ACTIF) {
                $nombreActif++;
            } elseif ($etat == Etat::NOUVELLE_VERSION_DEMANDEE) {
                $nombreNouvelleDem++;
            } elseif ($etat == Etat::EN_ATTENTE) {
                $nombreAttente++;
            } elseif ($etat == Etat::TERMINE) {
                $nombreTermine++;
            } elseif ($etat == Etat::ANNULE) {
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

        foreach ($thematiques as $thematique) {
            if ($statsThematique[$thematique->getLibelleThematique()]    ==   0) {
                unset($statsThematique[$thematique->getLibelleThematique()]);
                unset($idThematiques[$thematique->getLibelleThematique()]);
            }
        }

        return $this->render(
            'projet/session.html.twig',
            [
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
            'showRapport'         => (substr($session->getIdSession(), 2, 1) == 'A') ? true : false,
        ]
        );
    }

    /**
     * Résumés de tous les projets qui ont une version cette annee
     *
     * Param : $annee
     *
     * @Security("is_granted('ROLE_OBS')")
     * @Route("/{annee}/resumes", name="projet_resumes", methods={"GET","POST"})
     * Method({"GET","POST"})
     *
     */
    public function resumesAction($annee)
    {
        $sp    = $this->sp;
        $sj    = $this->sj;

        $paa   = $sp->projetsParAnnee($annee);
        $prjs  = $paa[0];
        $total = $paa[1];

        // construire une structure de données:
        //     - tableau associatif indexé par la métathématique
        //     - Pour chaque méta thématique liste des projets correspondants
        //       On utilise version B si elle existe, version A sinon
        //       On garde titre, les deux dernières publications, résumé
        $projets = [];
        foreach ($prjs as $p) {
            $v = empty($p['vb']) ? $p['va'] : $p['vb'];

            // On saute les projets en édition !
            if ($v->getEtatVersion() == Etat::EDITION_DEMANDE || $v->getEtatVersion() == Etat::EDITION_TEST) {
                continue;
            }
            $thematique= $v->getPrjThematique();
            $metathema = null;
            if ($thematique==null) {
                $sj->warningMessage(__METHOD__ . ':' . __LINE__ . " version " . $v . " n'a pas de thématique !");
            } else {
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
            $a = substr($a, 1, 2);
            $a = 2000 + intval($a);
            $prj['annee'] = $a;
            $publis = array_slice($v->getProjet()->getPubli()->toArray(), -2, 2);
            //$publis = array_slice($publis, -2, 2); // On garde seulement les deux dernières
            $prj['publis'] = $publis;
            $prj['porteur'] = $v->getResponsable()->getPrenom().' '.$v->getResponsable()->getNom();
            $prjm[] = $prj;
        };

        // Tris des tableaux par thématique du plus récent au plus ancien
        foreach ($projets as $metathema => &$prjm) {
            usort($prjm, "App\Controller\cmpProj");
        }

        return $this->render(
            'projet/resumes.html.twig',
            [
                'annee'     => $annee,
                'projets'   => $projets,
                ]
        );
    }

    /**
     *
     * Liste tous les projets qui ont une version cette annee
     *
     * @Route("/annee", name="projet_annee", methods={"GET","POST"})
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     */

    public function anneeAction(Request $request)
    {
        $sd = $this->sd;
        $ss = $this->ss;
        $data  = $ss->selectAnnee($request); // formulaire
        $annee = $data['annee'];

        $isRecupPrintemps = $sd->isRecupPrintemps($annee);
        $isRecupAutomne   = $sd->isRecupAutomne($annee);

        $sp      = $this->sp;
        $paa     = $sp->projetsParAnnee($annee, $isRecupPrintemps, $isRecupAutomne);
        $projets = $paa[0];
        $total   = $paa[1];
        $rattachements = $total['rattachements'];

        // Les sessions de l'année - On considère que le nombre d'heures par année est fixé par la session A de l'année
        // donc on ne peut pas changer de machine en cours d'année.
        // ça va peut-être changer un jour, ça n'est pas terrible !
        $sessions = $ss->sessionsParAnnee($annee);
        if (count($sessions)==0) {
            $hparannee=0;
        } else {
            $hparannee= $sessions[0]->getHParAnnee();
        }

        return $this->render(
            'projet/annee.html.twig',
            [
            'form' => $data['form']->createView(), // formulaire
            'annee'     => $annee,
            //'mois'    => $mois,
            'projets'   => $projets,
            'total'     => $total,
            'rattachements' => $rattachements,
            'showRapport'   => false,
            'isRecupPrintemps' => $isRecupPrintemps,
            'isRecupAutomne'   => $isRecupAutomne,
            'heures_par_an'    => $hparannee
            ]
        );
    }

    /**
     *
     * Liste tous les projets avec des demandes de stockage ou partage de données
     *
     * NB - Utile pour Calmip, si c'est inutile pour les autres mesoc il faudra
     *      mettre cette fonction ailleurs !
     *
     * @Route("/donnees", name="projet_donnees", methods={"GET","POST"})
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     */

    public function donneesAction(Request $request)
    {
        $ss    = $this->ss;
        $sp    = $this->sp;
        $data  = $ss->selectAnnee($request); // formulaire
        $annee = $data['annee'];

        list($projets, $total) = $sp->donneesParProjet($annee);

        return $this->render(
            'projet/donnees.html.twig',
            ['form'    => $data['form']->createView(), // formulaire
             'annee'   => $annee,
             'projets' => $projets,
             'total'   => $total,
             ]
        );
    }

    /**
     * Données en CSV
     *
     * @Route("{annee}/donnees_csv", name="projet_donnees_csv", methods={"GET","POST"})
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     */
    public function donneesCSVAction($annee)
    {
        $sp                  = $this->sp;
        list($projets, $total)= $sp->donneesParProjet($annee);

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

        $sortie     =   join("\t", $header) . "\n";
        foreach ($projets as $prj_array) {
            $line   = [];
            $p = $prj_array['p'];
            $line[] = $p->getIdProjet();
            $d      = "";
            if ($prj_array['stk']===true) {
                $d  = "S ";
            }
            if ($prj_array['ptg']===true) {
                $d .= "P";
            }
            if ($prj_array['stk']===false && $prj_array['ptg']===false) {
                $d = "N";
            }
            $line[] = $d;
            $line[] = $p->getTitre();
            $line[] = $p->getThematique();
            $line[] = $p->getResponsable()->getMail();
            $line[] = $p->getResponsable()->getNom();
            $line[] = $p->getResponsable()->getPrenom();
            $line[] = $p->getLaboratoire();
            $line[] = '"'.str_replace(["\n","\r\n","\t"], [' ',' ',' '], $prj_array['sondJustifDonnPerm']).'"';
            $line[] = $prj_array['sondVolDonnPerm'];
            $line[] = $prj_array['qt'];
            if (strpos($prj_array['sondVolDonnPerm'], 'sais pas')===false && strpos($prj_array['sondVolDonnPerm'], '<')===false) {
                if (intval($prj_array['sondVolDonnPerm']) != intval($prj_array['qt'])) {
                    $line[] = 1;
                } else {
                    $line[] = 0;
                }
            } else {
                if (intval($prj_array['qt']) != 1) {
                    $line[] = 1;
                } else {
                    $line[] = 0;
                }
            }
            $line[] = $prj_array['c'];
            $line[] = $prj_array['dataMetaDataFormat'];
            $line[] = $prj_array['dataNombreDatasets'];
            $line[] = $prj_array['dataTailleDatasets'];
            $sortie .=   join("\t", $line) . "\n";
        }
        return Functions::csv($sortie, 'donnees'.$annee.'.csv');
    }

    /**
     * Projets de l'année en CSV
     *
     * @Route("/{annee}/annee_csv", name="projet_annee_csv", methods={"GET","POST"})
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     */
    public function anneeCSVAction($annee)
    {
        $sp      = $this->sp;
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

        $sortie     .=   join("\t", $header) . "\n";
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
            $line[] = empty($va) ? '' : $va->getDemHeures();
            $line[] = empty($vb) ? '' : $vb->getDemHeures();
            $line[] = empty($va) ? '' : $va->getAttrHeures();
            $line[] = empty($vb) ? '' : $vb->getAttrHeures();
            $line[] = $prj_array['r'];
            $line[] = -$prj_array['penal_a'];
            $line[] = -$prj_array['penal_b'];
            $line[] = $prj_array['attrib'];
            $line[] = $prj_array['q'];
            $line[] = $prj_array['c'];
            $line[] = $prj_array['g'];

            $sortie     .=   join("\t", $line) . "\n";
        }
        return Functions::csv($sortie, 'projets_'.$annee.'.csv');
    }

    /**
     * download rapport
     * @Security("is_granted('ROLE_DEMANDEUR') or is_granted('ROLE_OBS')")
     * @Route("/{id}/rapport/{annee}", defaults={"annee"=0}, name="rapport", methods={"GET"})
     * Method("GET")
     */
    public function rapportAction(Version $version, Request $request, $annee)
    {
        $sp = $this->sp;
        $sj = $this->sj;

        if (! $sp->projetACL($version->getProjet())) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        if ($annee == 0) {
            // Si on ne précise pas on prend le rapport de l'année précédente
            // (pour les sessions A)
            $annee    = $version->getAnneeSession()-1;
        }
        $filename = $sp->getRapport($version->getProjet(), $annee);

        //return new Response($filename);

        if (file_exists($filename)) {
            return Functions::pdf(file_get_contents($filename));
        } else {
            $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " fichier du rapport d'activité \"" . $filename . "\" n'existe pas");
            return Functions::pdf(null);
        }
    }

    /**
     * download signature
     *
     * @Route("/{id}/signature", name="signature", methods={"GET"})
     * @Security("is_granted('ROLE_OBS')")
     * Method("GET")
     */
    public function signatureAction(Version $version, Request $request)
    {
        $sv = $this->sv;
        return Functions::pdf($sv->getSigne($version));
    }

    /**
     * download doc attaché
     *
     * @Route("/{id}/document", name="document", methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR') or is_granted('ROLE_OBS')")
     * Method("GET")
     */
    public function documentAction(Version $version, Request $request)
    {
        $sv = $this->sv;
        return Functions::pdf($sv->getDocument($version));
    }

    /**
     * Lists all projet entities.
     *
     * @Route("/tous", name="projet_tous", methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_OBS')")
     */
    public function tousAction()
    {
        $em      = $this->getDoctrine()->getManager();
        $projets = $em->getRepository(Projet::class)->findAll();
        $sp      = $this->sp;

        foreach (['termine','standby','agarder','accepte','refuse','edition','expertise','nonrenouvele'] as $e) {
            $etat_projet[$e]         = 0;
            $etat_projet[$e.'_test'] = 0;
        }

        $data = [];

        $collaborateurVersionRepository = $em->getRepository(CollaborateurVersion::class);
        $versionRepository              = $em->getRepository(Version::class);
        $projetRepository               = $em->getRepository(Projet::class);

        foreach ($projets as $projet) {
            $info     = $versionRepository->info($projet); // les stats du projet
            $version  = $projet->getVersionDerniere();
            $metaetat = strtolower($sp->getMetaEtat($projet));

            if ($projet->getTypeProjet() == Projet::PROJET_TEST) {
                $etat_projet[$metaetat.'_test'] += 1;
            } else {
                $etat_projet[$metaetat] += 1;
            }

            $data[] = [
                    'projet'       => $projet,
                    'renouvelable' => $projet->getEtatProjet()==Etat::RENOUVELABLE,
                    'metaetat'     => $metaetat,
                    'version'      => $version,
                    'etat_version' => ($version != null) ? Etat::getLibelle($version->getEtatVersion()) : 'SANS_VERSION',
                    'count'        => $info[1],
                    'dem'          => $info[2],
                    'attr'         => $info[3],
                    'responsable'  => $collaborateurVersionRepository->getResponsable($projet),
            ];
        }

        $etat_projet['total']      = $projetRepository->countAll();
        $etat_projet['total_test'] = $projetRepository->countAllTest();

        return $this->render(
            'projet/projets_tous.html.twig',
            [
            'etat_projet'   =>  $etat_projet,
            'data' => $data,
        ]
        );
    }

    /**
     * Lists all projet entities.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/gerer", name="gerer_projets", methods={"GET"})
     * Method("GET")
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
     * @Route("/new", name="projet_new", methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $projet = new Projet(Projet::PROJET_SESS);
        $form = $this->createForm('App\Form\ProjetType', $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($projet);
            $em->flush();

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
     * @Route("/avant_nouveau/{type}", name="avant_nouveau_projet", methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     *
     */
    public function avantNouveauAction(Request $request, $type)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $ss = $this->ss;
        $token = $this->token;

        if ($sm->nouveau_projet($type)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de créer un nouveau projet parce que " . $sm->nouveau_projet($type)['raison']);
        }

        $session = $ss->getSessionCourante();
        $projetRepository = $this->getDoctrine()->getManager()->getRepository(Projet::class);
        $id_individu      = $token->getUser()->getIdIndividu();
        $renouvelables    = $projetRepository-> getProjetsCollab($id_individu, true, true, true);

        if ($renouvelables == null) {
            return  $this->redirectToRoute('nouveau_projet', ['type' => $type]);
        }

        return $this->render(
            'projet/avant_nouveau_projet.html.twig',
            [
            'renouvelables' => $renouvelables,
            'type'          => $type,
        'session'       => $session
    ]
        );
    }

    /**
     * Création d'un nouveau projet
     *
     * @Route("/nouveau/{type}", name="nouveau_projet", methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     *
     */
    public function nouveauAction(Request $request, $type)
    {
        $sd = $this->sd;
        $sm = $this->sm;
        $ss = $this->ss;
        $sp = $this->sp;
        $sv = $this->sv;
        $sj = $this->sj;
        $token = $this->token;
        $em = $this->getDoctrine()->getManager();

        // Si changement d'état de la session alors que je suis connecté !
           // + contournement d'un problème lié à Doctrine
        $request->getSession()->remove('SessionCourante'); // remove cache

        // NOTE - Pour ce controleur, on identifie les types par un chiffre (voir Entity/Projet.php)
        $m = $sm->nouveau_projet("$type");
        if ($m == null || $m['ok']==false) {
            $raison = $m===null ? "ERREUR AVEC LE TYPE $type - voir le paramètre prj_type" : $m['raison'];
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de créer un nouveau projet parce que $raison");
        }

        $session  = $ss -> getSessionCourante();
        $prefixes = $this->getParameter('prj_prefix');
        if (!isset($prefixes[$type]) || $prefixes[$type]==="") {
            $sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Pas de préfixe pour le type $type. Voir le paramètre prj_prefix");
            return $this->redirectToRoute('accueil');
        }

        // Création du projet
        $annee    = $session->getAnneeSession();
        $projet   = new Projet($type);
        $projet->setIdProjet($sp->NextProjetId($annee, $type));
        $projet->setNepasterminer(false);
        switch ($type) {
        case Projet::PROJET_SESS:
        case Projet::PROJET_FIL:
        $projet->setEtatProjet(Etat::RENOUVELABLE);
        break;
        case Projet::PROJET_TEST:
        $projet->setEtatProjet(Etat::NON_RENOUVELABLE);
        break;
        default:
           $sj->throwException(__METHOD__ . ":" . __LINE__ . " mauvais type de projet " . Functions::show($type));
    }

        // Ecriture du projet dans la BD
        $em->persist($projet);
        $em->flush();

        // Création de la première (et dernière) version
        $version    =   new Version();
        $version->setIdVersion($session->getIdSession() . $projet->getIdProjet());
        $version->setProjet($projet);
        $version->setSession($session);
        $sv->setLaboResponsable($version, $token->getUser());

        if ($type == Projet::PROJET_TEST) {
            $version->setEtatVersion(Etat::EDITION_TEST);
        } else {
            $version->setEtatVersion(Etat::EDITION_DEMANDE);
        }

        // Ecriture de la version dans la BD
        $em->persist($version);
        $em->flush();

        // La dernière version est fixée par l'EventListener
        // $projet->setVersionDerniere($version);
        // $em->persist( $projet);
        // $em->flush();

        // Affectation de l'utilisateur connecté en tant que responsable
        $moi = $token->getUser();
        $collaborateurVersion = new CollaborateurVersion($moi);
        $collaborateurVersion->setVersion($version);
        $collaborateurVersion->setResponsable(true);

        // Ecriture de collaborateurVersion dans la BD
        $em->persist($collaborateurVersion);
        $em->flush();

        return $this->redirectToRoute('modifier_version', [ 'id' => $version->getIdVersion() ]);
    }

    /**
     * Affichage graphique de la consommation d'un projet
     *    Affiche un menu permettant de choisir quelle consommation on veut voir afficher
     *
     *
     * @Route("/{id}/conso/{annee}/annee/{loginname}/loginname", name="projet_conso",
     *        defaults={"loginname" = "nologin"},
     *        methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */

    public function consoAction(Projet $projet, $loginname="nologin", $annee=null)
    {
        $sp = $this->sp;
        $sj = $this->sj;


        // Seuls les collaborateurs du projet ont accès à la consommation
        if (! $sp->projetACL($projet)) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        // Si année non spécifiée on prend l'année la plus récente du projet
        if ($annee == null) {
            $version    =   $projet->derniereVersion();
            $annee = '20' . substr($version->getIdVersion(), 0, 2);
        }

        return $this->render(
            'projet/conso_menu.html.twig',
            ['projet'=>$projet,
                             'annee'=>$annee,
                             'loginname'=>$loginname,
                             'types'=>['group','user'],
                             'titres'=>['group' => 'Les consos du projet',
                                        'user' => 'Mes consommations']
                             ]
        );
    }

    /**
     * Affichage graphique de la consommation d'un projet
     *
     *      utype = type d'utilisateur - user ou group !
     *
     * @Route("/{id}/projet/{utype}/utype/{ress_id}/ress_id/{loginname}/loginname/{annee}/annee/conso_ressource",
     *         defaults={"loginname" = "nologin"},
     *         name="projet_conso_ressource",
     *         methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */

    public function consoRessourceAction(Projet $projet, $utype, $ress_id, $loginname, $annee)
    {
        $em = $this->getDoctrine()->getManager();
        $sp = $this->sp;
        $sj = $this->sj;


        $dessin_heures = $this -> gcl;
        $compta_repo   = $em->getRepository(Compta::class);
        $id_projet     = strtolower($projet->getIdProjet());

        // Seuls les collaborateurs du projet ont accès à la consommation
        if (! $sp->projetACL($projet)) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        // Verification du paramètre $utype
        $ntype = null;
        if ($utype == 'user') {
            $ntype = 1;
        } elseif ($utype == 'group') {
            $ntype = 2;
        } else {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec utype '.$utype);
        }

        // Si année non spécifiée on prend l'année la plus récente du projet

        $version = null;
        if ($annee == null) {
            $version    =   $projet->derniereVersion();
            $annee = '20' . substr($version->getIdVersion(), 0, 2);
        }

        $debut = new \DateTime($annee . '-01-01');
        $fin   = new \DateTime($annee . '-12-31');

        $ressource = $this->getParameter('ressources_conso_'.$utype)[$ress_id];
        //$sj->debugMessage(__METHOD__.':'.__LINE__. " projet $projet - $utype - ressource = ".print_r($ressource,true));

        // Détermination de loginname: soit le nom du projet soit le nom de login de l'utilisateur connecté
        if ($utype === 'group') {
            $conso_loginname = $id_projet;
        } else {
            $conso_loginname = $loginname;
        }

        // Génération du graphe de conso heures cpu et heures gpu
        // Note - type ici n'a rien à voir avec le paramètre $utype
        if ($ressource['type'] == 'calcul') {
            $id_projet     = $projet->getIdProjet();
            $db_conso      = $compta_repo->conso($conso_loginname, $annee, $ntype);
            $struct_data   = $dessin_heures->createStructuredData($debut, $fin, $db_conso);
            $dessin_heures->resetConso($struct_data);
            $image_conso     = $dessin_heures->createImage($struct_data)[0];
        }
        // Génération du graphe de conso stockage
        elseif ($ressource['type'] == 'stockage') {
            $db_work     = $compta_repo->consoStockage($conso_loginname, $ressource, $annee, $ntype);
            $dessin_work = $this -> gstk;
            $struct_data = $dessin_work->createStructuredData($debut, $fin, $db_work, $ressource['unite']);
            $image_conso = $dessin_work->createImage($struct_data, $ressource)[0];
        } else {
            $image_conso = '';
        }

        $twig     = $this->tw;
        $template = $twig->createTemplate('<img src="data:image/png;base64, {{ image_conso }}" alt="" title="" />');
        $html     = $twig->render($template, [ 'image_conso' => $image_conso ]);

        return new Response($html);
    }

    /**
     * Affichage graphique de la consommation de TOUS les projets
     *
     * @Route("/{ressource/{ressource}/tousconso/{annee}/{mois}", name="tous_projets_conso", methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_ADMIN')")
     */

    public function consoTousAction($ressource, $annee, $mois=false)
    {
        $em = $this->getDoctrine()->getManager();

        if ($ressource != 'cpu' && $ressource != 'gpu') {
            return "";
        }

        $db_conso = $em->getRepository(Compta::class)->consoTotale($annee, $ressource);

        $debut = new \DateTime($annee . '-01-01');
        $fin   = new \DateTime($annee . '-12-31');

        $dessin_heures = $this->gall;

        if ($mois == true) {
            $struct_data = $dessin_heures->createStructuredDataMensuelles($annee, $db_conso);
            $dessin_heures->derivConso($struct_data);
        } else {
            $struct_data = $dessin_heures->createStructuredData($debut, $fin, $db_conso);
            $dessin_heures->resetConso($struct_data);
        }
        $image_conso     = $dessin_heures->createImage($struct_data)[0];

        $twig     = $this->tw;
        $template = $twig->createTemplate('<img src="data:image/png;base64, {{ ImageConso }}" alt="Heures cpu/gpu" title="Heures cpu et gpu" />');
        $html     = $twig->render($template, [ 'ImageConso' => $image_conso ]);

        return new Response($html);
    }

    /**
     * Finds and displays a projet entity.
     *
     * @Route("/modele", name="telecharger_modele", methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function telechargerModeleAction()
    {
        return $this->render('projet/telecharger_modele.html.twig');
    }
}
