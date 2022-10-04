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

use Psr\Log\LoggerInterface;

use App\Entity\Rallonge;
use App\Entity\Session;
use App\Entity\Version;
use App\Entity\Individu;
use App\Entity\Projet;
use App\Entity\Thematique;

use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceExperts\ServiceExpertsRallonge;
use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServiceVersions;
use App\GramcServices\Workflow\Rallonge\RallongeWorkflow;
use App\GramcServices\Etat;
use App\GramcServices\Signal;
use App\Utils\Functions;
use App\AffectationExperts\AffectationExperts;
use App\AffectationExperts\AffectationExpertsRallonge;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Rallonge controller.
 * @Route("rallonge")
 */
class RallongeController extends AbstractController
{
    public function __construct(
        private ServiceJournal $sj,
        private ServiceMenus $sm,
        private ServiceProjets $sp,
        private ServiceSessions $ss,
        private ServiceExpertsRallonge $sr,
        private ServiceVersions $sv,
        private RallongeWorkflow $rw,
        private FormFactoryInterface $ff,
        private ValidatorInterface $vl,
        private EntityManagerInterface $em
    ) {}

    /**
     * A partir d'une rallonge, renvoie version, projet, session
     *
     *************************************/
    private function getVerProjSess(Rallonge $rallonge): array
    {
        $version = $rallonge->getVersion();
        $projet = null;
        $session = null;
        if ($version != null) {
            $projet  = $version->getProjet();
            $session = $version->getSession();
        } else {
            $this->sj->throwException(__METHOD__ . ":" . __LINE__ . " rallonge " . $rallonge . " n'est pas associée à une version !");
        }
        return [ $version, $projet, $session ];
    }

    /**
     * Lists all rallonge entities.
     *
     * @Route("/", name="rallonge_index", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function indexAction(): Response
    {
        $em = $this->em;

        $rallonges = $em->getRepository(Rallonge::class)->findAll();

        return $this->render('rallonge/index.html.twig', array(
            'rallonges' => $rallonges,
        ));
    }

    /**
     * Creates a new rallonge entity.
     *
     * @Route("/new", name="rallonge_new", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request): Response
    {
        $rallonge = new Rallonge();
        $form = $this->createForm('App\Form\RallongeType', $rallonge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($rallonge);
            $em->flush();

            return $this->redirectToRoute('rallonge_show', array('id' => $rallonge->getId()));
        }

        return $this->render('rallonge/new.html.twig', array(
            'rallonge' => $rallonge,
            'form' => $form->createView(),
        ));
    }

    /**
     * Creates a new rallonge entity.
     *
     * @Route("/{id}/creation", name="nouvelle_rallonge", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function creationAction(Request $request, Projet $projet, LoggerInterface $lg): Response
    {
        $sm = $this->sm;
        $ss = $this->ss;
        $sj = $this->sj;
        $sp = $this->sp;
        $em = $this->em;

        // ACL
        if ($sm->nouvelleRallonge($projet)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de créer une nouvelle rallonge pour le projet" . $projet .
                " parce que : " . $sm->nouvelleRallonge($projet)['raison']);
        }
        //return new Response( Functions::show( $em->getRepository(Rallonge::class)->findRallongesOuvertes($projet)   ) );

        $version  = $sp->versionActive($projet);

        $rallonge = new Rallonge();
        $rallonge->setVersion($version);
        $rallonge->setObjectState(Etat::CREE_ATTENTE);

        $session = $ss->getSessionCourante();

        $count   = count($version->getRallonge()) + 1;
        $rallonge->setIdRallonge($version->getIdVersion() . 'R' . $count);

        $workflow = $this->rw;
        $rtn      = $workflow->execute(Signal::CLK_DEMANDE, $rallonge);
        if ($rtn == false) {
            $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " Impossible d'envoyer le signal CLK_DEMANDE à la rallonge " . $rallonge);
            $request->getSession()->getFlashbag()->add("flash erreur","Rallonge créée, mais responsable probablement pas notifié - Veuillez vérifier");
        }

        Functions::sauvegarder($rallonge, $em, $lg);

        $request->getSession()->getFlashbag()->add("flash info","Rallonge créée, responsable notifié");
        return $this->redirectToRoute('consulter_version', ['id' => $projet->getIdProjet(), 'version' => $version->getId()]);
    }

    /**
     * Finds and displays a rallonge entity.
     *
     * @Route("/{id}/show", name="rallonge_show", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function showAction(Rallonge $rallonge): Response
    {
        $deleteForm = $this->createDeleteForm($rallonge);

        return $this->render('rallonge/show.html.twig', array(
            'rallonge' => $rallonge,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing rallonge entity.
     *
     * @Route("/{id}/edit", name="rallonge_edit", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function editAction(Request $request, Rallonge $rallonge): Response
    {
        $deleteForm = $this->createDeleteForm($rallonge);
        $editForm = $this->createForm('App\Form\RallongeType', $rallonge);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('rallonge_edit', array('id' => $rallonge->getId()));
        }

        return $this->render('rallonge/edit.html.twig', array(
            'rallonge' => $rallonge,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing rallonge entity.
     *
     * @Route("/{id}/consulter", name="rallonge_consulter", methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     */
    public function consulterAction(Request $request, Rallonge $rallonge): Response
    {
        $sm = $this->sm;
        $sp = $this->sp;
        $sj = $this->sj;

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        // ACL
        if (! $sp->projetACL($projet) || $projet == null) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        $menu[]   = $sm->modifierRallonge($rallonge);
        $menu[]   = $sm->envoyerEnExpertiseRallonge($rallonge);

        return $this->render(
            'rallonge/consulter.html.twig',
            [
            'rallonge'  => $rallonge,
            'session'   => $session,
            'projet'    => $projet,
            'version'   => $version,
            'menu'      => $menu
            ]
        );
    }

    /**
    * Displays a form to edit an existing rallonge entity.
    *
    * @Route("/{id}/modifier", name="modifier_rallonge", methods={"GET","POST"})
    * @Security("is_granted('ROLE_DEMANDEUR')")
    * Method({"GET", "POST"})
    */
    public function modifierAction(Request $request, Rallonge $rallonge): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;
        $em = $this->em;

        // ACL
        if ($sm->modifierRallonge($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible de modifier la rallonge " . $rallonge->getIdRallonge().
                " parce que : " . $sm->modifierRallonge($rallonge)['raison']);
        }

        $editForm = $this->createFormBuilder($rallonge)
            ->add('demHeures', IntegerType::class, [ 'required'       =>  false ])
            ->add('prjJustifRallonge', TextAreaType::class, [ 'required'       =>  false ])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer' ])
            ->add('fermer', SubmitType::class, ['label' => 'Fermer' ])
            ->add('annuler', SubmitType::class, ['label' => 'Annuler' ])
            ->getForm();

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        $erreurs = [];
        $editForm->handleRequest($request);
        if ($editForm->isSubmitted()) {
            
            if ($editForm->get('annuler')->isClicked()) {
                return $this->redirectToRoute('rallonge_consulter', [ 'id' => $rallonge->getIdRallonge() ]);
            }
            
            $erreurs = Functions::dataError($sval, $rallonge);
            $em->flush();
            $request->getSession()->getFlashbag()->add("flash info","Rallonge enregistrée");

            if ($editForm->get('fermer')->isClicked()) {
                return $this->redirectToRoute('rallonge_consulter', [ 'id' => $rallonge->getIdRallonge() ]);
            }
        }
        return $this->render(
            'rallonge/modifier.html.twig',
            [
            'rallonge'  => $rallonge,
            'projet'    => $projet,
            'session'   => $session,
            'edit_form' => $editForm->createView(),
            'erreurs'   => $erreurs,
        ]
        );
    }

    /**
    * Expertise d'une rallonge par un expert
    *
    * @Route("/{id}/expertiser", name="expertiser_rallonge", methods={"GET","POST"})
    * @Security("is_granted('ROLE_EXPERT')")
    * Method({"GET", "POST"})
    */
    public function expertiserAction(Request $request, Rallonge $rallonge): Response
    {
        $sm = $this->sm;
        $ss = $this->ss;
        $sj = $this->sj;
        $sval = $this->vl;
        $em = $this->em;

        // ACL
        if ($sm->expertiserRallonge($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible d'expertiser la rallonge " . $rallonge->getIdRallonge().
                " parce que : " . $sm->expertiserRallonge($rallonge)['raison']);
        }

        $editForm = $this->createFormBuilder($rallonge)
                ->add('commentaireInterne', TextAreaType::class, [ 'required'       =>  false ])
                ->add('validation', ChoiceType::class, ['expanded' => true, 'multiple' => false, 'choices' => [ 'Accepter' => true, 'Refuser' => false ]])
                ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer' ])
                ->add('annuler', SubmitType::class, ['label' => 'Annuler' ])
                ->add('fermer', SubmitType::class, ['label' => 'Fermer' ])
                ->add('envoyer', SubmitType::class, ['label' => 'Envoyer' ])
                ->add('nbHeuresAtt', IntegerType::class, ['required'  =>  false, ]);

        $editForm = $editForm->getForm();

        $erreurs = [];
        $editForm->handleRequest($request);

        $version = $rallonge->getVersion();
        if ($version != null) {
            $projet = $version->getProjet();
            $session = $version->getSession();
        } else {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " rallonge " . $rallonge . " n'est pas associée à une version !");
        }

        // Bouton ANNULER
        if ($editForm->isSubmitted() && $editForm->get('annuler')->isClicked()) {
            return $this->redirectToRoute('expertise_liste');
        }

        // Boutons ENREGISTRER, FERMER ou ENVOYER
        if ($editForm->isSubmitted()) {

            // S'il y a des erreurs on les affichera !
            $erreurs = Functions::dataError($sval, $rallonge, ['expertise']);

            $em->persist($rallonge);
            $em->flush();

            // Bouton FERMER
            if ($editForm->get('fermer')->isClicked()) {
                return $this->redirectToRoute('expertise_liste');
            }

            // Bouton ENVOYER
            if ($editForm->get('envoyer')->isClicked() && $erreurs == null) {
                return $this->redirectToRoute('rallonge_envoyer_president', [ 'id' => $rallonge->getId() ]);
            }

            // Bouton ENREGISTRER
            // rien de spécial à faire
        }

        $session   = $ss->getSessionCourante();
        $anneeCour = 2000 +$session->getAnneeSession();
        $anneePrec = $anneeCour - 1;

        return $this->render(
            'rallonge/expertiser.html.twig',
            [
                'rallonge'  => $rallonge,
                'edit_form' => $editForm->createView(),
                'erreurs'   => $erreurs,
                'anneePrec' => $anneePrec,
                'anneeCour' => $anneeCour
                ]
        );
    }

    ////////////////////////////////////////////////////////////////////////////////////////////

    private function getFinaliserForm(Rallonge $rallonge): FormInterface
    {
        $nbHeuresAttrib = [ 'required' => false ];
        if ($rallonge->getValidation() === false) {
            $nbHeuresAttrib['disabled'] = 'disabled';
        }
        return $this->createFormBuilder($rallonge)
            ->add('nbHeuresAtt', IntegerType::class, $nbHeuresAttrib)
            ->add('commentaireInterne', TextAreaType::class, [ 'required' => false ])
            ->add('commentaireExterne', TextAreaType::class, [ 'required' => false ])
            ->add(
                'validation',
                ChoiceType::class,
                [
                    'expanded' => true,
                    'multiple' => false,
                    'choices' => [ 'Accepter' => true, 'Refuser' => false ],
                    'choice_attr' => function ($key, $val, $index) {
                        return ['disabled' => 'disabled'];
                    },
                    ]
            )
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer' ])
            ->add('annuler', SubmitType::class, ['label' => 'Annuler' ])
            ->add('fermer', SubmitType::class, ['label' => 'Fermer' ])
           ->add('envoyer', SubmitType::class, ['label' => 'Envoyer' ])
            ->getForm();
    }

    /**
     * Displays a form to edit an existing rallonge entity.
     *
     * @Route("/{id}/finaliser", name="rallonge_finaliser", methods={"GET","POST"})
     * @Security("is_granted('ROLE_PRESIDENT')")
     * Method({"GET", "POST"})
     */
    public function finaliserAction(Request $request, Rallonge $rallonge, LoggerInterface $lg): Response
    {
        $ss = $this->ss;
        $sj = $this->sj;
        $sval = $this->vl;
        $em = $this->em;
        $workflow = $this->rw;

        $erreurs = [];
        $validation = $rallonge->getValidation(); //  tout cela juste à cause de validation disabled

        $editForm = $this->getFinaliserForm($rallonge);

        $editForm->handleRequest($request);

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        // Bouton ANNULER
        if ($editForm->isSubmitted() && $editForm->get('annuler')->isClicked()) {
            return $this->redirectToRoute('rallonge_affectation');
        }

        if ($editForm->isSubmitted()) {
            $erreurs = Functions::dataError($sval, $rallonge, ['president']);


            // Boutons ENREGISTRER, FERMER ou ENVOYER
            if ($editForm->isSubmitted()) {
                $rallonge->setValidation($validation); // Bouton validation disabled
                
                // S'il y a des erreurs on les affichera !
                $erreurs = Functions::dataError($sval, $rallonge, ['president']);
    
                Functions::sauvegarder($rallonge, $em, $lg);
    
                // Bouton FERMER
                if ($editForm->get('fermer')->isClicked()) {
                    return $this->redirectToRoute('rallonge_affectation');
                }
    
                // Bouton ENVOYER
                if ($editForm->get('envoyer')->isClicked() && $erreurs == null) {
                    return $this->redirectToRoute('rallonge_envoyer_responsable', [ 'id' => $rallonge->getId() ]);
                }
    
                // Bouton ENREGISTRER
                // rien de spécial à faire
            }
        }
        $session    = $ss->getSessionCourante();
        $anneeCour  = 2000 +$session->getAnneeSession();
        $anneePrec  = $anneeCour - 1;

        return $this->render(
            'rallonge/finaliser.html.twig',
            [
                'erreurs'   => $erreurs,
                'rallonge'  => $rallonge,
                'edit_form' => $editForm->createView(),
                'anneePrec' => $anneePrec,
                'anneeCour' => $anneeCour
            ]
        );
    }

    /**
     * Affiche un écran de confirmation, et si OK envoie l'expertise au président
     * 
     * @Route("/{id}/envoyer_president", name="rallonge_envoyer_president", methods={"GET","POST"})
     * @Security("is_granted('ROLE_EXPERT')")
     * Method("GET")
     */
    public function EnvoyerPresidentAction(Request $request, Rallonge $rallonge): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;
        $workflow = $this->rw;
        
        // ACL
        if ($sm->expertiserRallonge($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible d'envoyer la demande " . $rallonge->getIdRallonge().
                " au président parce que : " . $sm->expertiserRallonge($rallonge)['raison']);
        }

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        $erreurs = Functions::dataError($sval, $rallonge, ['president']);

        $editForm = $this->createFormBuilder($rallonge)
            ->add('confirmer', SubmitType::class, ['label' =>  'Confirmer' ])
            ->add('annuler', SubmitType::class, ['label' =>  'Annuler' ])
            ->getForm();
        $editForm->handleRequest($request);
        if ($editForm->isSubmitted())
        {
            // Bouton Annuler
            if ($editForm->get('annuler')->isClicked()) {
                $request->getSession()->getFlashbag()->add("flash erreur","L'expertise de la rallonge $rallonge n'a pas été envoyée");
                return $this->redirectToRoute('expertiser_rallonge', [ 'id' => $rallonge->getId() ]);
            }

            // Bouton Confirmer
            if ($rallonge->getValidation() == true)
            {
                $workflow->execute(Signal::CLK_VAL_EXP_OK, $rallonge);
            }
            elseif ($rallonge->getValidation() == false)
            {
                $workflow->execute(Signal::CLK_VAL_EXP_KO, $rallonge);
            }
            else
            {
                $sj->throwException(__METHOD__ . ":" . __LINE__ . " rallonge " . $rallonge . " contient une validation erronée !");
            }
            $request->getSession()->getFlashbag()->add("flash info","L'expertise de la rallonge $rallonge a été envoyée");
            return $this->redirectToRoute('expertise_liste');
        }

        // Ecran de confirmation: On utilise le même twig que pour l'expertise des projets
        return $this->render(
            'expertise/valider_projet_sess.html.twig',
            [
            'erreurs'    => $erreurs,
            'expertise'  => $rallonge,
            'version'    => $rallonge->getVersion(),
            'edit_form'  => $editForm->createView(),
            ]
        );

/*
        return $this->render(
            'rallonge/avant_envoyer_president.html.twig',
            [
            'rallonge'  => $rallonge,
            'projet'    => $projet,
            'session'   => $session,
            'erreurs'   => $erreurs,
            ]
        );
*/
    }



    /**
     * Displays a form to edit an existing rallonge entity.
     *
     * TODO - VIRER CETTE FONCTION
     *
     * @Route("/{id}/avant_envoyer", name="avant_rallonge_envoyer", methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     */
    public function avantEnvoyerAction(Request $request, Rallonge $rallonge): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;

        // ACL
        if ($sm->envoyerEnExpertiseRallonge($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible d'envoyer la rallonge " . $rallonge->getIdRallonge().
                " à l'expert parce que : " . $sm->envoyerEnExpertiseRallonge($rallonge)['raison']);
        }

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        $erreurs = Functions::dataError($sval, $rallonge);
        return $this->render(
            'rallonge/avant_envoyer.html.twig',
            [
            'rallonge'  => $rallonge,
            'projet'    => $projet,
            'session'   => $session,
            'erreurs'   => $erreurs,
            ]
        );
    }

    /**
     * Displays a form to edit an existing rallonge entity.
     *
     * @Route("/{id}/envoyer", name="rallonge_envoyer", methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     */
    public function envoyerAction(Request $request, Rallonge $rallonge): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;

        // ACL
        if ($sm->envoyerEnExpertiseRallonge($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible de modifier la rallonge " . $rallonge->getIdRallonge().
                " parce que : " . $sm->envoyerEnExpertiseRallonge($rallonge)['raison']);
        }

        $erreurs = Functions::dataError($sval, $rallonge);
        $workflow = $this->rw;

        if ($erreurs != null) {
            $sj->warningMessage(__METHOD__ . ":" . __LINE__ ." L'envoi à l'expert de la rallonge " . $rallonge . " refusé à cause des erreurs !");
            return $this->redirectToRoute('avant_rallonge_envoyer', [ 'id' => $rallonge->getId() ]);
        } elseif (! $workflow->canExecute(Signal::CLK_VAL_DEM, $rallonge)) {
            $sj->warningMessage(__METHOD__ . ":" . __LINE__ ." L'envoi à l'expert de la rallonge " . $rallonge .
                " refusé par le workflow, la rallonge est dans l'état " . Etat::getLibelle($rallonge->getEtatRallonge()));
            return $this->redirectToRoute('avant_rallonge_envoyer', [ 'id' => $rallonge->getId() ]);
        }

        $workflow->execute(Signal::CLK_VAL_DEM, $rallonge);

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        return $this->render(
            'rallonge/envoyer.html.twig',
            [
            'rallonge'  => $rallonge,
            'projet'    => $projet,
            'session'   => $session,
        ]
        );
    }

    /**
     * Displays a form to edit an existing rallonge entity.
     *
     * @Route("/{id}/envoyerResponsable", name="rallonge_envoyer_responsable", methods={"GET","POST"})
     * @Security("is_granted('ROLE_PRESIDENT')")
     * Method("GET")
     */
    public function envoyerResponsableAction(Request $request, Rallonge $rallonge): Response
    {
        $sj = $this->sj;
        $sval = $this->vl;
        $workflow = $this->rw;

        if (! $workflow->canExecute(Signal::CLK_VAL_PRS, $rallonge)) {
            $sj->warningMessage(__METHOD__ . ":" . __LINE__ ." La finalisation de la rallonge " . $rallonge .
                " refusée par le workflow, la rallonge est dans l'état " . Etat::getLibelle($rallonge->getEtatRallonge()));
            return $this->redirectToRoute('rallonge_finaliser', [ 'id' => $rallonge->getId() ]);
        }

        $rallonge->setAttrHeures($rallonge->getNbHeuresAtt());

        // [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);
        // $erreurs = Functions::dataError($sval, $rallonge, ['president']);

        $editForm = $this->createFormBuilder($rallonge)
            ->add('confirmer', SubmitType::class, ['label' =>  'Confirmer' ])
            ->add('annuler', SubmitType::class, ['label' =>  'Annuler' ])
            ->getForm();

        $editForm->handleRequest($request);
        if ($editForm->isSubmitted() && $editForm->isValid())
        {
            // Bouton Annuler
            if ($editForm->get('annuler')->isClicked()) {
                $request->getSession()->getFlashbag()->add("flash erreur","La rallonge $rallonge n'a pas été finalisée");
                return $this->redirectToRoute('rallonge_finaliser', [ 'id' => $rallonge->getId() ]);
            }

            // Bouton Confirmer
            if ($rallonge->getValidation() === true)
            {
                $workflow->execute(Signal::CLK_VAL_PRS, $rallonge);
            }
            elseif ($rallonge->getValidation() === false)
            {
                $workflow->execute(Signal::CLK_FERM, $rallonge);
            }
            else
            {
                $sj->throwException(__METHOD__ . ":" . __LINE__ . " rallonge " . $rallonge . " contient une validation erronée !");
            }
            $request->getSession()->getFlashbag()->add("flash info","L'expertise de la rallonge $rallonge a été envoyée au responsable");
            return $this->redirectToRoute('rallonge_affectation');
        }

        // Ecran de confirmation: On utilise le même twig que pour l'expertise des projets
        return $this->render(
            'expertise/valider_projet_sess.html.twig',
            [
            //'erreurs'    => $erreurs,
            'expertise'  => $rallonge,
            'version'    => $rallonge->getVersion(),
            'edit_form'  => $editForm->createView(),
            ]
        );
    }

    /**
     * Affectation des experts
     *
     * @Route("/affectation", name="rallonge_affectation", methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function affectationAction(Request $request): Response
    {
        $em = $this->em;
        $sp = $this->sp;
        $sv = $this->sv;
        $affectationExperts = $this->sr;

        //$sessions = $em->getRepository(Session::class) ->findBy( ['etatSession' => Etat::ACTIF ] );
        $sessions = $em->getRepository(Session::class) -> get_sessions_non_terminees();
        if (isset($sessions[0])) {
            $session1 = $sessions[0];
        } else {
            $session1 = null;
        }
        $session = $session1;

        if (isset($sessions[1])) {
            $session2 = $sessions[1];
            $session  = $session2;
        }
        else {
            $session2 = null;
        }

        $annee = $session->getAnneeSession();

        $all_rallonges = $em -> getRepository(Rallonge::class)->findSessionRallonges($sessions);

        $affectationExperts->setDemandes($all_rallonges);

        //
        // 1ere etape = Traitement des formulaires qui viennent d'être soumis
        //              Puis on redirige sur la page
        //
        $form_buttons = $affectationExperts->getFormButtons();
        $form_buttons->handleRequest($request);
        if ($form_buttons->isSubmitted()) {
            $rvl = $affectationExperts->traitementFormulaires($request);
            if ($rvl)
            {
                $request->getSession()->getFlashbag()->add("flash info","Affectations OK, experts notifiés");
            }
            else
            {
                $request->getSession()->getFlashbag()->add("flash info","Affectations OK");
            }
            return $this->redirectToRoute('rallonge_affectation');
        }

        // 2nde étape = Création des formulaires pour affichage et génération des données de "stats"
        //              On utilise $proj, un tableau associatif indexé par id_projet
        $proj = [];
        foreach ($all_rallonges as $rallonge) {
            $version   = $rallonge->getVersion();
            $projet    = $version->getProjet();
            $id_projet = $projet->getIdProjet();
            if (! isset($proj[$id_projet])) {
                $p = [];
                $proj[$id_projet] = $p;
                $proj[$id_projet ]['projet']      = $projet;
                $proj[$id_projet ]['version']     = $version;
                $proj[ $id_projet ]['rallonges']  = [];
                $proj[ $id_projet ]['etat']       = $sp->getMetaEtat($projet);
                $proj[ $id_projet ]['etatProjet']         = $projet->getEtatProjet();
                $proj[ $id_projet ]['libelleEtatProjet']  = Etat::getLibelle($projet->getEtatProjet());
                $proj[ $id_projet ]['etatVersion']        = $version->getEtatVersion();
                $proj[ $id_projet ]['libelleEtatVersion'] = Etat::getLibelle($version->getEtatVersion());
                $proj[ $id_projet ]['conso']      = $sp->getConsoCalculVersion($version);
                $expert = $rallonge->getExpert();
                if ($rallonge->getExpert() != null) {
                    $proj[$id_projet]['affecte'] = true;
                } else {
                    $proj[$id_projet]['affecte'] = false;
                }

                if ($sv->isNouvelle($version)) {
                    $proj[ $id_projet ]['NR'] = 'N';
                } else {
                    $proj[ $id_projet ]['NR'] = '';
                }
            }
            $proj[ $id_projet ]['rallonges'][] = $rallonge;
        }

        // 3 ème étape = Mise en forme pour l'affichage
        // 				 Dans rowspan = le nombre de rallonges + 1
        // 				 Dans rstate  = l'état de la dernière rallonge
        //
        // On recopie $proj dans $projets, qui pourra être trié
        $projets = [];
        foreach ($proj as $key => $projet) {
            $nr = count($projet['rallonges']);
            $projet['rowspan'] = $nr + 1;
            $projet['rstate']  = intval($projet['rallonges'][$nr-1]->getEtatRallonge());
            $projets[] = $projet;
        }

        // On trie les projets en fonction de l'état de la dernière rallonge
        usort($projets, "self::cmpProjetsByRallonges");
        //dd($projets);


        $forms = $affectationExperts->getExpertsForms();
        $stats = $affectationExperts->getStats();
        $titre = "Affectation des experts aux rallonges de l'année 20$annee";
        
        return $this->render(
            'rallonge/affectation.html.twig',
            [
            'projets'  => $projets,
            'forms'    => $forms,
            'session1' => $session1,
            'session2' => $session2,
            'stats'    => $stats,
        ]
        );
    }

   private static function cmpProjetsByRallonges(array $a, array $b): int
    {
        return (Etat::cmpEtatExpertiseRall($a['rstate'],$b['rstate']));
    }

    /**
     * Deletes a rallonge entity.
     *
     * @Route("/{id}", name="rallonge_delete", methods={"DELETE"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("DELETE")
     */
    public function deleteAction(Request $request, Rallonge $rallonge): Response
    {
        $form = $this->createDeleteForm($rallonge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->remove($rallonge);
            $em->flush();
        }

        return $this->redirectToRoute('rallonge_index');
    }

    /**
     * Creates a form to delete a rallonge entity.
     *
     * @param Rallonge $rallonge The rallonge entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Rallonge $rallonge):FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('rallonge_delete', array('id' => $rallonge->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
