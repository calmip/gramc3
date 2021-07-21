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
use App\Utils\Etat;
use App\Utils\Signal;
use App\Utils\Functions;
use App\AffectationExperts\AffectationExperts;
use App\AffectationExperts\AffectationExpertsRallonge;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

//use App\App;

/**
 * Rallonge controller.
 * @Route("rallonge")
 */
class RallongeController extends AbstractController
{
    private $sj;
    private $sm;
    private $sp;
    private $ss;
    private $sr;
    private $sv;
    private $rw;
    private $ff;
    private $vl;

    public function __construct(
        ServiceJournal $sj,
        ServiceMenus $sm,
        ServiceProjets $sp,
        ServiceSessions $ss,
        ServiceExpertsRallonge $sr,
        ServiceVersions $sv,
        RallongeWorkflow $rw,
        FormFactoryInterface $ff,
        ValidatorInterface $vl
    ) {
        $this->sj = $sj;
        $this->sm = $sm;
        $this->sp = $sp;
        $this->ss = $ss;
        $this->sr = $sr;
        $this->sv = $sv;
        $this->rw = $rw;
        $this->ff = $ff;
        $this->vl = $vl;
    }

    /**
     * A partir d'une rallonge, renvoie version, projet, session
     * 
     *************************************/
     private function getVerProjSess(Rallonge $rallonge) 
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
     * @Route("/", name="rallonge_index")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $rallonges = $em->getRepository('App:Rallonge')->findAll();

        return $this->render('rallonge/index.html.twig', array(
            'rallonges' => $rallonges,
        ));
    }

    /**
     * Creates a new rallonge entity.
     *
     * @Route("/new", name="rallonge_new")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $rallonge = new Rallonge();
        $form = $this->createForm('App\Form\RallongeType', $rallonge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
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
     * @Route("/{id}/creation", name="rallonge_creation")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function creationAction(Request $request, Projet $projet, LoggerInterface $lg)
    {
        $sm = $this->sm;
        $ss = $this->ss;
        $sj = $this->sj;
        $sp = $this->sp;
        $em = $this->getDoctrine()->getManager();

        // ACL
        if ($sm->rallonge_creation($projet)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de créer une nouvelle rallonge pour le projet" . $projet .
                " parce que : " . $sm->rallonge_creation($projet)['raison']);
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
        }

        Functions::sauvegarder($rallonge, $em, $lg);

        return $this->render(
            'rallonge/creation.html.twig',
            [
                'projet'   => $projet,
                'rallonge' => $rallonge,
                ]
        );
    }

    /**
     * Finds and displays a rallonge entity.
     *
     * @Route("/{id}/show", name="rallonge_show")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function showAction(Rallonge $rallonge)
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
     * @Route("/{id}/edit", name="rallonge_edit")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Rallonge $rallonge)
    {
        $deleteForm = $this->createDeleteForm($rallonge);
        $editForm = $this->createForm('App\Form\RallongeType', $rallonge);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

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
     * @Route("/{id}/consulter", name="rallonge_consulter")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * @Method("GET")
     */
    public function consulterAction(Request $request, Rallonge $rallonge)
    {
        $sm = $this->sm;
        $sp = $this->sp;
        $sj = $this->sj;

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        // ACL
        if (! $sp->projetACL($projet) || $projet == null) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        $menu[]   = $sm->rallonge_modifier($rallonge);
        $menu[]   = $sm->rallonge_envoyer($rallonge);

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
    * @Route("/{id}/modifier", name="rallonge_modifier")
    * @Security("is_granted('ROLE_DEMANDEUR')")
    * @Method({"GET", "POST"})
    */
    public function modifierAction(Request $request, Rallonge $rallonge)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;
        $em = $this->getDoctrine()->getManager();

        // ACL
        if ($sm->rallonge_modifier($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible de modifier la rallonge " . $rallonge->getIdRallonge().
                " parce que : " . $sm->rallonge_modifier($rallonge)['raison']);
        }

        $editForm = $this->createFormBuilder($rallonge)
            ->add('demHeures', IntegerType::class, [ 'required'       =>  false ])
            ->add('prjJustifRallonge', TextAreaType::class, [ 'required'       =>  false ])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer' ])
            ->add('fermer', SubmitType::class, ['label' => 'Fermer' ])
            ->getForm();

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        $erreurs = [];
        $editForm->handleRequest($request);
        if ($editForm->isSubmitted()) {
            $erreurs = Functions::dataError($sval, $rallonge);
            $em->flush();

            if ($editForm->get('fermer')->isClicked()) {
                $menu[]   = $sm->rallonge_modifier($rallonge);
                $menu[]   = $sm->rallonge_envoyer($rallonge);
                return $this->render(
                    'rallonge/fermer.html.twig',
                    [
                    'rallonge'  => $rallonge,
                    'projet'    => $projet,
                    'session'   => $session,
                    'erreurs'   => $erreurs,
                    'menu'      => $menu,
                 ]
                );
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
    * Displays a form to edit an existing rallonge entity.
    *
    * @Route("/{id}/expertiser", name="rallonge_expertiser")
    * @Security("is_granted('ROLE_EXPERT')")
    * @Method({"GET", "POST"})
    */
    public function expertiserAction(Request $request, Rallonge $rallonge)
    {
        $sm = $this->sm;
        $ss = $this->ss;
        $sj = $this->sj;
        $sval = $this->vl;
        $em = $this->getDoctrine()->getManager();

        // ACL
        if ($sm->rallonge_expertiser($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible d'expertiser la rallonge " . $rallonge->getIdRallonge().
                " parce que : " . $sm->rallonge_expertiser($rallonge)['raison']);
        }

        $editForm = $this->createFormBuilder($rallonge)
                ->add('commentaireInterne', TextAreaType::class, [ 'required'       =>  false ])
                ->add('validation', ChoiceType::class, ['expanded' => true, 'multiple' => false, 'choices' => [ 'Accepter' => true, 'Refuser' => false ]])
                ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer' ])
                ->add('annuler', SubmitType::class, ['label' => 'Annuler' ])
                ->add('fermer', SubmitType::class, ['label' => 'Fermer' ])
                ->add('envoyer', SubmitType::class, ['label' => 'Envoyer' ]);

        //if( $rallonge->getNbHeuresAtt() == 0 )
        //{
        //    $editForm->add('nbHeuresAtt', IntegerType::class , ['required'  =>  false, 'data' => $rallonge->getDemHeures(), ]);
        //}
        //else
        //{
        $editForm->add('nbHeuresAtt', IntegerType::class, ['required'  =>  false, ]);
        //}

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
            $erreurs = Functions::dataError($sval, $rallonge, ['expertise']);
            $validation = $rallonge->getValidation();
            //if( $validation != 1 )
            //{
            //    $rallonge->setNbHeuresAtt(0);
            //}

            $em->persist($rallonge);
            $em->flush();

            // Bouton FERMER
            if ($editForm->get('fermer')->isClicked()) {
                return $this->redirectToRoute('expertise_liste');
            }

            // bouton ENVOYER
            if ($editForm->get('envoyer')->isClicked()) {
                return $this->redirectToRoute('avant_rallonge_envoyer_president', [ 'id' => $rallonge->getId() ]);
            }
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

    private function getFinaliserForm(Rallonge $rallonge)
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
            ->add('envoyer', SubmitType::class, ['label' => 'Envoyer' ])
            ->getForm();
    }

    /**
     * Displays a form to edit an existing rallonge entity.
     *
     * @Route("/{id}/avant_finaliser", name="avant_rallonge_finaliser")
     * @Security("is_granted('ROLE_PRESIDENT')")
     * @Method({"GET", "POST"})
     */
    public function avantFinaliserAction(Request $request, Rallonge $rallonge, LoggerInterface $lg)
    {
        $ss = $this->ss;
        $sj = $this->sj;
        $sval = $this->vl;
        $em = $this->getdoctrine()->getManager();

        $erreurs = [];
        $validation =   $rallonge->getValidation(); //  tout cela juste à cause de validation disabled

        $editForm = $this->getFinaliserForm($rallonge);

        $editForm->handleRequest($request);

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        //if( ! $rallonge->isFinalisable() )
        //    $sj->throwException(__METHOD__ . ":" . __LINE__ . " rallonge " . $rallonge . " n'est pas en attente !");

        if ($editForm->isSubmitted()) {
            $rallonge->setValidation($validation); //  tout cela juste à cause de validation disabled
            $erreurs = Functions::dataError($sval, $rallonge, ['president']);

            Functions::sauvegarder($rallonge, $em, $lg);

            $workflow = $this->rw;
            if (! $workflow->canExecute(Signal::CLK_VAL_PRS, $rallonge)) {
                $erreur = "La finalisation de la rallonge " . $rallonge .
                    " refusée par le workflow, la rallonge est dans l'état " . Etat::getLibelle($rallonge->getEtatRallonge());
                $sj->errorMessage(__METHOD__ . ":" . __LINE__ . ' ' . $erreur);
                $erreurs[] = $erreur;
            } elseif ($editForm->get('envoyer')->isClicked() && $erreurs == null) {
                //$workflow->execute( Signal::CLK_VAL_PRS, $rallonge );
                return $this->render(
                    'rallonge/finaliser.html.twig',
                    [
                    'erreurs'   => $erreurs,
                    'projet'    => $projet,
                    'session'   => $session,
                    'rallonge'  => $rallonge,
                    ]
                );
            }
            //else
            //    return $this->redirectToRoute('avant_rallonge_finaliser', [ 'id' => $rallonge->getId() ] );
        }

        $editForm = $this->getFinaliserForm($rallonge);  //  tout cela juste à cause de validation disabled

        $session    = $ss->getSessionCourante();
        $anneeCour  = 2000 +$session->getAnneeSession();
        $anneePrec  = $anneeCour - 1;

        return $this->render(
            'rallonge/avant_finaliser.html.twig',
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
     * Displays a form to edit an existing rallonge entity.
     *
     * @Route("/{id}/avant_envoyer_president", name="avant_rallonge_envoyer_president")
     * @Security("is_granted('ROLE_EXPERT')")
     * @Method("GET")
     */
    public function avantEnvoyerPresidentAction(Request $request, Rallonge $rallonge)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;

        // ACL
        if ($sm->rallonge_expertiser($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible d'envoyer la demande " . $rallonge->getIdRallonge().
                " au président parce que : " . $sm->rallonge_expertiser($rallonge)['raison']);
        }

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        $erreurs = Functions::dataError($sval, $rallonge, ['expertise']);

        return $this->render(
            'rallonge/avant_envoyer_president.html.twig',
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
     * @Route("/{id}/avant_envoyer", name="avant_rallonge_envoyer")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * @Method("GET")
     */
    public function avantEnvoyerAction(Request $request, Rallonge $rallonge)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;

        // ACL
        if ($sm->rallonge_envoyer($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible d'envoyer la rallonge " . $rallonge->getIdRallonge().
                " à l'expert parce que : " . $sm->rallonge_envoyer($rallonge)['raison']);
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
     * @Route("/{id}/envoyer", name="rallonge_envoyer")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * @Method("GET")
     */
    public function envoyerAction(Request $request, Rallonge $rallonge)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;

        // ACL
        if ($sm->rallonge_envoyer($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible de modifier la rallonge " . $rallonge->getIdRallonge().
                " parce que : " . $sm->rallonge_envoyer($rallonge)['raison']);
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
     * @Route("/{id}/finaliser", name="rallonge_finaliser")
     * @Security("is_granted('ROLE_PRESIDENT')")
     * @Method("GET")
     */
    public function finaliserAction(Request $request, Rallonge $rallonge)
    {
        $sj = $this->sj;
        $sval = $this->vl;

        $erreurs = Functions::dataError($sval, $rallonge);
        $workflow = $this->rw;

        if ($erreurs != null) {
            $sj->warningMessage(__METHOD__ . ":" . __LINE__ ." La finalisation de la rallonge " . $rallonge . " refusée à cause des erreurs !");
            return $this->redirectToRoute('avant_rallonge_finaliser', [ 'id' => $rallonge->getId() ]);
        } elseif (! $workflow->canExecute(Signal::CLK_VAL_PRS, $rallonge)) {
            $sj->warningMessage(__METHOD__ . ":" . __LINE__ ." La finalisation de la rallonge " . $rallonge .
                " refusée par le workflow, la rallonge est dans l'état " . Etat::getLibelle($rallonge->getEtatRallonge()));
            return $this->redirectToRoute('avant_rallonge_finaliser', [ 'id' => $rallonge->getId() ]);
        }

        if ($rallonge->getValidation() == true) {
            $workflow->execute(Signal::CLK_VAL_PRS, $rallonge);
        } else {
            $workflow->execute(Signal::CLK_FERM, $rallonge);
        }

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        return $this->render(
            'rallonge/rallonge_finalisee.html.twig',
            [
            'erreurs'   => $erreurs,
            'rallonge'  => $rallonge,
            'projet'    => $projet,
            'session'   => $session,
        ]
        );
    }


    /**
        * Displays a form to edit an existing rallonge entity.
        *
        * @Route("/{id}/envoyer_president", name="rallonge_envoyer_president")
        * @Security("is_granted('ROLE_EXPERT')")
        * @Method("GET")
        */
    public function envoyerPresidentAction(Request $request, Rallonge $rallonge)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval = $this->vl;

        // ACL
        if ($sm->rallonge_expertiser($rallonge)['ok'] == false) {
            $sj->throwException(__METHOD__ . " impossible d'envoyer la demande " . $rallonge->getIdRallonge().
                " au président parce que : " . $sm->rallonge_expertiser($rallonge)['raison']);
        }

        $erreurs = Functions::dataError($sval, $rallonge, ['expertise']);
        $workflow = $this->rw;

        if ($erreurs != null) {
            $sj->warningMessage(__METHOD__ . ":" . __LINE__ ." L'envoi au président de la rallonge " . $rallonge . " refusé à cause des erreurs !");
            return $this->redirectToRoute('avant_rallonge_envoyer_president', [ 'id' => $rallonge->getId() ]);
        } elseif (! $workflow->canExecute(Signal::CLK_VAL_EXP_OK, $rallonge)) {
            $sj->warningMessage(__METHOD__ . ":" . __LINE__ ." L'envoi au président de la rallonge " . $rallonge .
                " refusé par le workflow, la rallonge est dans l'état " . Etat::getLibelle($rallonge->getEtatRallonge()));
            return $this->redirectToRoute('avant_rallonge_envoyer_presdient', [ 'id' => $rallonge->getId() ]);
        }

        if ($rallonge->getValidation() == true) {
            $workflow->execute(Signal::CLK_VAL_EXP_OK, $rallonge);
        } elseif ($rallonge->getValidation() == false) {
            $workflow->execute(Signal::CLK_VAL_EXP_KO, $rallonge);
        } else {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " rallonge " . $rallonge . " contient une validation erronée !");
        }

        [ $version, $projet, $session ] = $this->getVerProjSess($rallonge);

        return $this->render(
            'rallonge/envoyer_president.html.twig',
            [
            'rallonge'  => $rallonge,
            'projet'    => $projet,
            'session'   => $session,
            ]
        );
    }

    /**
     * Affectation des experts
     *
     * @Route("/affectation", name="rallonge_affectation")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function affectationAction(Request $request)
    {
		$em = $this->getDoctrine()->getManager();
		$sp = $this->sp;
	    $sv = $this->sv;
		$affectationExperts = $this->sr;

	    //$sessions = $em->getRepository(Session::class) ->findBy( ['etatSession' => Etat::ACTIF ] );
	    $sessions = $em->getRepository(Session::class) -> get_sessions_non_terminees();
	    if ( isset( $sessions[0] ) )
	        $session1 = $sessions[0];
	    else
	        $session1 = null;
	    $session = $session1;

	    if ( isset( $sessions[1] ) )
        {
	        $session2 = $sessions[1];
	        $session  = $session2;
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
            $affectationExperts->traitementFormulaires($request);
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

    // Cette fonction est utilisée par affectationAction,
    // elle permet d'écrire les rallonges de manière ordonnée
    // D'abord les projets qui ont une rallonge en état "non actif"
    //
    private static function cmpProjetsByRallonges($a, $b)
    {
        if ($a['rstate'] == $b['rstate']) {
            return 0;
        }
        return ($a['rstate'] < $b['rstate']) ? -1 : 1;
    }



    /**
     * Deletes a rallonge entity.
     *
     * @Route("/{id}", name="rallonge_delete")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Rallonge $rallonge)
    {
        $form = $this->createDeleteForm($rallonge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
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
    private function createDeleteForm(Rallonge $rallonge)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('rallonge_delete', array('id' => $rallonge->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
