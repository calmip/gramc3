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

use App\Utils\Functions;
use App\Utils\Menu;
use App\GramcServices\Etat;
use App\GramcServices\Signal;
use App\GramcServices\ServiceJournal;
use App\GramcServices\Workflow\Projet\ProjetWorkflow;
use App\GramcServices\Workflow\Version\VersionWorkflow;
use App\GramcServices\Workflow\Session\SessionWorkflow;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Form\FormFactoryInterface;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Workflow controller pour faire des tests.
 * @Security("is_granted('ROLE_ADMIN')")
 * @Route("workflow")
 */
class WorkflowController extends AbstractController
{
    public function __construct(
        private ServiceJournal $sj,
        private ProjetWorkflow $pw,
        private SessionWorkflow $sw,
        private FormFactoryInterface $ff,
        private EntityManagerInterface $em
    ) { }

    /**
     * entry.
     *
     * @Route("/", name="workflow_index",methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function indexAction(Request $request, LoggerInterface $lg): Response
    {
        $ff = $this->ff;
        $sj = $this->sj;
        $em = $this->em;
        $signal_view_forms = [];
        $etat_view_forms = [];

        $projets = [];
        foreach ($em->getRepository(Projet::class)->findAll() as $projet) {
            if ($projet->getEtatProjet() != Etat::TERMINE) {
                $projets[] = $projet;
            }
        }

        $sessions = array_slice($em->getRepository(Session::class)->findAll(), -4);
        $menu   = [];

        foreach ($sessions as $session) {
            $signal_forms[$session->getIdSession()] = Functions::getFormBuilder($ff, 'signal' . $session->getIdSession())
            ->add(
                'signal',
                ChoiceType::class,
                [
                    'multiple' => false,
                    'required'  => true,
                    'label'     => '',
                    'choices' =>
                                [
                                'DAT_DEB_DEM'   =>   Signal::DAT_DEB_DEM,
                                'DAT_FIN_DEM'   =>   Signal::DAT_FIN_DEM,
                                'CLK_ATTR_PRS'  =>   Signal::CLK_ATTR_PRS,
                                'CLK_SESS_DEB'       =>   Signal::CLK_SESS_DEB,
                                'CLK_SESS_FIN'       =>   Signal::CLK_SESS_FIN,
                                ],
                    ]
            )
            ->add('submit', SubmitType::class, ['label' => 'Envoyer le signal à la session ' . $session->getIdSession()], ['required'  => false ])
            ->getForm();

            $signal_forms[$session->getIdSession()]->handleRequest($request);
            $signal_view_forms[$session->getIdSession()] = $signal_forms[$session->getIdSession()]->createView();

            if ($signal_forms[$session->getIdSession()]->isSubmitted() && $signal_forms[$session->getIdSession()]->isValid()) {
                $signal = $signal_forms[$session->getIdSession()]->getData()['signal'];

                $sessionWorkflow    =   $this->sw;
                $rtn = $sessionWorkflow->execute($signal, $session);
                if ($rtn == true) {
                    $sj->debugMessage('WorkflowController : signal ' . Signal::getLibelle($signal). " a été appliqué avec succès sur " . $session);
                } else {
                    $sj->debugMessage('WorkflowController : signal ' . Signal::getLibelle($signal). " a été appliqué avec erreur sur " . $session);
                }
                return $this->redirectToRoute('workflow_index');
            }


            ///////////////////////////////

            $etat_forms[$session->getIdSession()] = Functions::getFormBuilder($ff, 'etat' . $session->getIdSession())
            ->add(
                'etat',
                ChoiceType::class,
                [
                    'multiple' => false,
                    'required'  => true,
                    'label'     => '',
                    'data'      => $session->getEtatSession(),
                    'choices' =>
                                [
                                'CREE_ATTENTE'                  =>   Etat::CREE_ATTENTE,
                                'EDITION_DEMANDE'               =>   Etat::EDITION_DEMANDE,
                                'EDITION_EXPERTISE'             =>   Etat::EDITION_EXPERTISE,
                                'EN_ATTENTE'                    =>   Etat::EN_ATTENTE,
                                'ACTIF'                         =>   Etat::ACTIF,
                                'TERMINE'                       =>   Etat::TERMINE,
                                ],
                    ]
            )
            ->add('submit', SubmitType::class, ['label' => "Changer l'état de la session " . $session->getIdSession()], ['required'  => false ])
            ->getForm();

            $etat_forms[$session->getIdSession()]->handleRequest($request);
            $etat_view_forms[$session->getIdSession()] = $etat_forms[$session->getIdSession()]->createView();

            if ($etat_forms[$session->getIdSession()]->isSubmitted() && $etat_forms[$session->getIdSession()]->isValid()) {
                $session->setEtatSession($etat_forms[$session->getIdSession()]->getData()['etat']);
                Functions::sauvegarder($session, $em, $lg);
                return $this->redirectToRoute('workflow_index');
            }
        }

        return $this->render(
            'workflow/index.html.twig',
            [
            'projets'  => $projets,
            'sessions' => $sessions,
            'signal_view_forms' => $signal_view_forms,
            'etat_view_forms'   => $etat_view_forms,
            'menu'     => $menu,
            ]
        );
    }

    /**
    *
    * @Route("/{id}/modify", name="worklow_modifier_session",methods={"GET","POST"})
    * Method({"GET", "POST"})
    */
    public function modifySessionAction(Request $request, Session $session, LoggerInterface $lg): Response
    {
        $sj = $this->sj;
        $ff = $this->ff;
        $em = $this->em;

        $session_form = Functions::createFormBuilder($ff)
            ->add(
                'signal',
                ChoiceType::class,
                [
                    'multiple' => false,
                    'required'  => true,
                    'label'     => 'Signal',
                    'choices' =>
                                [
                                'DAT_DEB_DEM'   =>   Signal::DAT_DEB_DEM,
                                'DAT_FIN_DEM'   =>   Signal::DAT_FIN_DEM,
                                'CLK_ATTR_PRS'  =>   Signal::CLK_ATTR_PRS,
                                'CLK_SESS_DEB'       =>   Signal::CLK_SESS_DEB,
                                'CLK_SESS_FIN'       =>   Signal::CLK_SESS_FIN,
                                ],
                    ]
            )
        ->add('submit', SubmitType::class, ['label' => 'Envoyer le signal'], ['required'  => false ])
        ->getForm();

        $session_form->handleRequest($request);

        if ($session_form->isSubmitted() && $session_form->isValid()) {
            $signal = $session_form->getData()['signal'];

            //$sessionWorkflow    =   new SessionWorkflow();
            $sessionWorkflow    =   $this->sw;
            $rtn = $sessionWorkflow->execute($signal, $session);
            if ($rtn == true) {
                $sj->debugMessage('WorkflowController : signal ' . Signal::getLibelle($signal). " a été appliqué avec succès");
            } else {
                $sj->debugMessage('WorkflowController : signal ' . Signal::getLibelle($signal). " a été appliqué avec erreur");
            }
            return $this->redirectToRoute('worklow_modifier_session', [ 'id' => $session->getIdSession() ]);
        }

        ////////////////////////////

        $etat_form = Functions::createFormBuilder($ff)
            ->add(
                'etat',
                ChoiceType::class,
                [
                    'multiple' => false,
                    'required'  =>  true,
                    'label'     => 'État',
                    'data'      => $session->getEtatSession(),
                    'choices' =>
                                [
                                'CREE_ATTENTE'                  =>   Etat::CREE_ATTENTE,
                                'EDITION_DEMANDE'               =>   Etat::EDITION_DEMANDE,
                                'EDITION_EXPERTISE'             =>   Etat::EDITION_EXPERTISE,
                                'EN_ATTENTE'                    =>   Etat::EN_ATTENTE,
                                'ACTIF'                         =>   Etat::ACTIF,
                                'TERMINE'                       =>   Etat::TERMINE,
                                ],
                    ]
            )
        ->add('submit', SubmitType::class, ['label' => "Changer l'état"])
        ->getForm();

        $etat_form->handleRequest($request);

        if ($etat_form->isSubmitted() &&  $etat_form->isValid()) {
            $session->setEtatSession($etat_form->getData()['etat']);
            Functions::sauvegarder($session, $em, $lg);
            return $this->redirectToRoute('worklow_modifier_session', [ 'id' => $session->getIdSession() ]);
        }

        ////////////////////////////

        return $this->render(
            'workflow/modify_session.html.twig',
            [
            'session' => $session,
            'session_form' => $session_form->createView(),
            'etat_form' => $etat_form->createView(),
            ]
        );
    }

    /**
     *
     * @Route("/{id}/signal", name="workflow_signal_projet",methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function signalProjetAction(Request $request, Projet $projet, LoggerInterface $lg): Response
    {
        $sj = $this->sj;
        $ff = $this->ff;
        $em = $this->em;


        $versions = $projet->getVersion();
        $sessions = [];
        $old_sessions = [];
        foreach ($versions as $version) {
            $old_sessions[] = $version->getSession();
        }

        foreach (array_slice($em->getRepository(Session::class)->findAll(), -4) as $session) {
            if (! in_array($session, $old_sessions)) {
                $sessions[] = $session;
            }
        }

        $form = Functions::getFormBuilder($ff, 'session')
            ->add(
                'session',
                EntityType::class,
                [
                    'multiple' => false,
                    'class' => Session::class,
                    'required'  =>  true,
                    'label'     => 'Session',
                    'choices' =>  $sessions,
                    'choice_label' => function ($session) {
                        return $session->getIdSession();
                    }
                    ]
            )
        ->add('submit', SubmitType::class, ['label' => 'Nouvelle version'])
        ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted()&& $form->isValid()) {
            $session = $form->getData()['session'];
            if ($session != null) {
                $version = new Version();
                $version->setSession($session);
                $version->setIdVersion($session->getIdSession() . $projet->getIdProjet());
                $version->setProjet($projet);
                Functions::sauvegarder($version, $em, $lg);
                return $this->redirectToRoute('workflow_signal_projet', [ 'id' => $projet->getIdProjet() ]);
            }
        }

        //////////////////////

        $signal_form  = Functions::getFormBuilder($ff, 'signal')
            ->add(
                'signal',
                ChoiceType::class,
                [
                    'multiple' => false,
                    'required'  =>  true,
                    'label'     => 'Signal',
                    'choices' =>
                                [
                                'CLK_DEMANDE'   =>   Signal::CLK_DEMANDE,
                                'CLK_VAL_DEM'   =>   Signal::CLK_VAL_DEM,
                                'CLK_VAL_EXP_OK'  =>   Signal::CLK_VAL_EXP_OK,
                                'CLK_VAL_EXP_KO'  =>   Signal::CLK_VAL_EXP_KO,
                                'CLK_VAL_EXP_CONT'=>   Signal::CLK_VAL_EXP_CONT,
                                'CLK_ARR'       =>   Signal::CLK_ARR,
                                'CLK_SESS_DEB'       =>   Signal::CLK_SESS_DEB,
                                'CLK_SESS_FIN'       =>   Signal::CLK_SESS_FIN,
                                'CLK_FERM'      =>   Signal::CLK_FERM,
                                ],
                    ]
            )
        ->add('submit', SubmitType::class, ['label' => 'Envoyer le signal au projet'])
        ->getForm();

        $signal_form->handleRequest($request);
        if ($signal_form->isSubmitted()&& $signal_form->isValid()) {
            $signal = $signal_form->getData()['signal'];


            //$projetWorkflow    =   new ProjetWorkflow();
            $projetWorkflow    =   $this->pw;
            $rtn = $projetWorkflow->execute($signal, $projet);
            if ($rtn == true) {
                $sj->debugMessage('WorkflowController : signal ' . Signal::getLibelle($signal). " a été appliqué avec succès sur le projet " . $projet);
            } elseif ($rtn == false) {
                $sj->debugMessage('WorkflowController : signal ' .Signal::getLibelle($signal) . " a été appliqué avec erreur sur le projet " . $projet);
            } elseif (is_array($rtn)) {
                $message = 'WorkflowController : signal ' . $signal;
                foreach ($rtn as $return) {
                    $message .= "(" . $return['signal'] . ":" . $return['object'] . ")";
                }
                $sj->debugMessage($message);
            }

            return $this->redirectToRoute('workflow_signal_projet', [ 'id' => $projet->getIdProjet() ]);
        }

        //////////////////////////////////
        $projet_form = Functions::getFormBuilder($ff, 'projet')
            ->add(
                'etat',
                ChoiceType::class,
                [
                    'multiple' => false,
                    'required'  =>  true,
                    'label'     => 'État',
                    'data'      => $projet->getEtatProjet(),
                    'choices' =>
                                [
                                'RENOUVELABLE'                  =>   Etat::RENOUVELABLE,
                                'NON_RENOUVELABLE'              =>   Etat::NON_RENOUVELABLE,
                                'EDITION_DEMANDE'               =>   Etat::EDITION_DEMANDE,
                                'EDITION_EXPERTISE'             =>   Etat::EDITION_EXPERTISE,
                                'EN_ATTENTE'                    =>   Etat::EN_ATTENTE,
                                'ACTIF'                         =>   Etat::ACTIF,
                                'TERMINE'                       =>   Etat::TERMINE,
                                'EN_STANDBY'                    =>   Etat::EN_STANDBY,
                                'EN_SURSIS'                     =>   Etat:: EN_SURSIS,
                                'ANNULE'                        =>   Etat::ANNULE,
                                ],
                    ]
            )
        ->add('submit', SubmitType::class, ['label' => "Changer l'état du projet " . $projet->getIdProjet() ])
        ->getForm();

        $projet_form->handleRequest($request);

        if ($projet_form->isSubmitted() &&  $projet_form->isValid()) {
            $projet->setEtatProjet($projet_form->getData()['etat']);
            Functions::sauvegarder($projet, $em, $lg);
            return $this->redirectToRoute('workflow_signal_projet', [ 'id' => $projet->getIdProjet() ]);
        }

        //////////////////////////////////

        $versions = $projet->getVersion();

        $etat_view_forms = [];

        foreach ($versions as $version) {
            $etat_forms[$version->getIdVersion()] = Functions::getFormBuilder($ff, 'version' . $version->getIdVersion())
            ->add(
                'etat',
                ChoiceType::class,
                [
                    'multiple' => false,
                    'required'  =>  true,
                    'label'     => 'État',
                    'data'      => $version->getEtatVersion(),
                    'choices' =>
                                [
                                'EDITION_DEMANDE'               =>   Etat::EDITION_DEMANDE,
                                'EDITION_EXPERTISE'             =>   Etat::EDITION_EXPERTISE,
                                'EN_ATTENTE'                    =>   Etat::EN_ATTENTE,
                                'ACTIF'                         =>   Etat::ACTIF,
                                'TERMINE'                       =>   Etat::TERMINE,
                                'ANNULE'                        =>   Etat::ANNULE,
                                ],
                    ]
            )
        ->add('submit', SubmitType::class, ['label' => "Changer l'état de la version " . $version->getIdVersion() ])
        ->getForm();

            $etat_forms[$version->getIdVersion()]->handleRequest($request);
            $etat_view_forms[$version->getIdVersion()] = $etat_forms[$version->getIdVersion()]->createView();

            if ($etat_forms[$version->getIdVersion()]->isSubmitted() &&  $etat_forms[$version->getIdVersion()]->isValid()) {
                $version->setEtatVersion($etat_forms[$version->getIdVersion()]->getData()['etat']);
                Functions::sauvegarder($version, $em, $lg);
                return $this->redirectToRoute('workflow_signal_projet', [ 'id' => $projet->getIdProjet() ]);
            }
        }

        return $this->render(
            'workflow/add_version.html.twig',
            [
            'projet' => $projet,
            'versions' => $versions,
            'form' => $form->createView(),
            'signal_form'   => $signal_form->createView(),
            'etat_view_forms'   => $etat_view_forms,
            'projet_form'   => $projet_form->createView(),
            ]
        );
    }

    /**
    *
    * @Route("/{id}/reset", name="workflow_reset_version",methods={"GET","POST"})
    * Method({"GET", "POST"})
    */
    public function resetVersionAction(Request $request, Version $version, LoggerInterface $lg): Response
    {
        $em = $this->em;

        $version->setEtatVersion(Etat::EDITION_DEMANDE);
        Functions::sauvegarder($version, $em, $lg);
        return $this->redirectToRoute('workflow_signal_projet', [ 'id' => $version->getProjet()->getIdProjet() ]);
    }
}
