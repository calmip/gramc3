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

use App\Entity\Individu;
use App\Entity\Thematique;
use App\Entity\Rattachement;
use App\Utils\Functions;

use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceExperts\ServiceExperts;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;

use App\Form\GererUtilisateurType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

// pour remplacer un utilisateur par un autre
use App\Entity\CollaborateurVersion;
use App\Entity\CompteActivation;
use App\Entity\Expertise;
use App\Entity\Journal;
use App\Entity\Rallonge;
use App\Entity\Session;
use App\Entity\Sso;
use App\Entity\Version;

/**
 * Individu controller.
 *
 * @Route("individu")
 */
class IndividuController extends AbstractController
{
    private $sj = null;
    private $se = null;
    private $ff = null;
    private $ac = null;


    public function __construct(
        ServiceExperts $se,
        ServiceJournal $sj,
        FormFactoryInterface $ff,
        AuthorizationCheckerInterface $ac
    ) {
        $this->se = $se;
        $this->sj = $sj;
        $this->ff = $ff;
        $this->ac = $ac;
    }

    /**
     * Supprimer utilisateur
     *
     * @Route("/{id}/supprimer", name="supprimer_utilisateur")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function supprimerUtilisateurAction(Request $request, Individu $individu)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($individu);
        $em->flush();
        return $this->redirectToRoute('individu_gerer');
    }

    /**
     * Remplacer utilisateur: on a demandé la suppression d'un utilisateur qui a des projets, expertises etc
     *
     * @Route("/{id}/remplacer", name="remplacer_utilisateur")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function remplacerUtilisateurAction(Request $request, Individu $individu)
    {
        $em = $this->getDoctrine()->getManager();
        $sj = $this->sj;
        $ff = $this->ff;

        $session = $request->getSession();

        $form = $ff
            ->createNamedBuilder('autocomplete_form', FormType::class, [])
            ->add(
                'submit',
                SubmitType::class,
                [
                 'label' => "Le nouvel utilisateur",
                 ]
            )
            ->getForm();

        // si on vient de modify, on préremplit le champ puis on retire new_mail de la session
        if ($session->has('new_mail')) {
            $form->add(
                'mail',
                TextType::class,
                [
                'required' => false, 'csrf_protection' => false, 'attr' => ['value' => $session->get('new_mail')],
                ]
            );
            $session->remove('new_mail');
        } else {
            $form->add(
                'mail',
                TextType::class,
                [
                'required' => false, 'csrf_protection' => false,
                ]
            );
        }

        $CollaborateurVersion       =   $em->getRepository(CollaborateurVersion::class)->findBy(['collaborateur' => $individu]);
        $CompteActivation           =   $em->getRepository(CompteActivation ::class)->findBy(['individu' => $individu]);
        $Expertise                  =   $em->getRepository(Expertise ::class)->findBy(['expert' => $individu]);
        $Journal                    =   $em->getRepository(Journal::class)->findBy(['individu' => $individu]);
        $Rallonge                   =   $em->getRepository(Rallonge::class)->findBy(['expert' => $individu]);
        $Session                    =   $em->getRepository(Session::class)->findBy(['president' => $individu]);
        $Sso                        =   $em->getRepository(Sso::class)->findBy(['individu' => $individu]);
        $Thematique                 =   $individu->getThematique();

        $erreurs  =   [];

        // utilisateur peu actif et qui ne peut pas se connecter peut être effacé
        if ($CollaborateurVersion == null && $Expertise == null
              && $Rallonge == null && $Session == null && count($Sso)==0) {

            foreach ($individu->getThematique() as $item) {
                $em->persist($item);
                $item->getExpert()->removeElement($individu);
            }

            foreach ($CompteActivation  as $item) {
                $em->remove($item);
            }

            foreach ($Sso  as $item) {
                $em->remove($item);
            }

            $sj->infoMessage('Utilisateur ' . $individu . ' (' .  $individu->getIdIndividu() . ') directement effacé ');

            $em->remove($individu);

            $em->flush();
            return $this->redirectToRoute('individu_gerer');
        }

        // utilisateur actif ou qui peut se connecter doit être remplacé
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $mail  =   $form->getData()['mail'];
            $new_individu   =   $em->getRepository(Individu::class)->findOneBy(['mail'=>$mail]);

            if ($new_individu != null) {

                // Supprimer les thématiques dont je suis expert, il faudra les recréer
                foreach ($individu->getThematique() as $item) {
                    $em->persist($item);
                    $item->getExpert()->removeElement($individu);
                }

                // Les projets dont je suis collaborateur - Attention aux éventuels doublons
                foreach ($CollaborateurVersion  as $item) {
                    if (! $item->getVersion()->isCollaborateur($new_individu)) {
                        $item->setCollaborateur($new_individu);
                    } else {
                        $em->remove($item);
                    }
                }

                // On fait reprendre les Sso par le nouvel individu
                $sso_de_new = $new_individu->getSso();
                $array_eppn=[];
                foreach ($new_individu->getSso() as $item) {
                    $array_eppn[] = $item->getEppn();
                }
                foreach ($Sso  as $item) {
                    if (!in_array($item->getEppn(),$array_eppn)) {
                        $item->setIndividu($new_individu);
                        $em->persist($item);
                    } else {
                        $em->remove($item);
                    }
                }

                // Mes expertises
                foreach ($Expertise  as $item) {
                    $item->setExpert($new_individu);
                }

                // Mes rallonges
                foreach ($Rallonge  as $item) {
                    $item->setExpert($new_individu);
                }

                // Les entrées de journal (sinon on ne pourra pas supprimer l'ancien individu)
                foreach ($Journal  as $item) {
                    $item->setIndividu($new_individu);
                }

                // ...
                foreach ($Session  as $item) {
                    $item->setPresident($new_individu);
                }

                // On ne sait jamais
                foreach ($CompteActivation  as $item) {
                    $em->remove($item);
                }

                $sj->infoMessage('Utilisateur ' . $individu . '(' .  $individu->getIdIndividu()
                    . ') remplacé par ' . $new_individu . ' (' .  $new_individu->getIdIndividu() . ')');

                $em->remove($individu);

                $em->flush();
                return $this->redirectToRoute('individu_gerer');
            } else {
                $erreurs[] = "Le mail du nouvel utilisateur \"" . $mail . "\" ne correspond à aucun utilisateur existant";
            }
        }

        return $this->render(
            'individu/remplacer.html.twig',
            [
                'form' => $form->createView(),
                'erreurs'                   => $erreurs,
                'CollaborateurVersion'   =>  $CollaborateurVersion,
                'CompteActivation'       =>  $CompteActivation,
                'Expertise'              =>  $Expertise ,
                'Journal '               =>  $Journal,
                'Rallonge'               =>  $Rallonge,
                'Session'                =>  $Session,
                'Sso'                    =>  $Sso,
                'individu'               =>  $individu,
                'Thematique'             =>  $Thematique->toArray(),
            ]
        );
    }

    /**
    * Deletes a individu entity (CRUD)
    *
    * @Route("/{id}/delete", name="individu_delete")
    * @Security("is_granted('ROLE_ADMIN')")
    * @Method("DELETE")
    */
    public function deleteAction(Request $request, Individu $individu)
    {
        $form = $this->createDeleteForm($individu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($individu);
            $em->flush($individu);
        }

        return $this->redirectToRoute('individu_index');
    }


    /**
     * Lists all individu entities.
     *
     * @Route("/", name="individu_index")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $individus = $em->getRepository('App:Individu')->findAll();

        return $this->render('individu/index.html.twig', array(
            'individus' => $individus,
        ));
    }

    /**
     * Creates a new individu entity.
     *
     * @Route("/new", name="individu_new")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $individu = new Individu();
        $form = $this->createForm('App\Form\IndividuType', $individu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($individu);
            $em->flush($individu);

            return $this->redirectToRoute('individu_show', array('id' => $individu->getId()));
        }

        return $this->render('individu/new.html.twig', array(
            'individu' => $individu,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a individu entity.
     *
     * @Route("/{id}/show", name="individu_show")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function showAction(Individu $individu)
    {
        $deleteForm = $this->createDeleteForm($individu);

        return $this->render('individu/show.html.twig', array(
            'individu' => $individu,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing individu entity.
     *
     * @Route("/{id}/edit", name="individu_edit")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Individu $individu)
    {
        $deleteForm = $this->createDeleteForm($individu);
        $editForm = $this->createForm('App\Form\IndividuType', $individu);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('individu_edit', array('id' => $individu->getId()));
        }

        return $this->render('individu/edit.html.twig', array(
            'individu' => $individu,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }



    /**
     * Creates a form to delete a individu entity.
     *
     * @param Individu $individu The individu entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Individu $individu)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('individu_delete', array('id' => $individu->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    ///////////////////////////////////////////////////////////////////////////////////////

    /**
    * Modifier profil
    *
    * @Route("/{id}/modifier_profil", name="modifier_profil")
    * @Security("is_granted('ROLE_ADMIN')")
    * @Method("GET")
    */
    public function modifierProfilAction(Request $request, Individu $individu)
    {
        $individu->setAdmin(true);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
    }

    /**
     * Displays a form to edit an existing individu entity.
     *
     * @Route("/{id}/modify", name="individu_modify")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, Individu $individu)
    {
        $em = $this->getDoctrine()->getManager();
        $repos = $em->getRepository(Individu::class);
        
        $editForm = $this->createForm('App\Form\IndividuType', $individu);

        $session = $request->getSession();
        //$current_mail = $session->get('current_mail');
        
        $editForm->handleRequest($request);
        if ($editForm->isSubmitted() /*&& $editForm->isValid()*/) {

            $exc = false;
            try
            {
                $em->flush();
            }
            catch (UniqueConstraintViolationException $e)
            {
                //dd("merde");
                $exc = true;
            };

            // Si exception, aller vers l'écran de remplacement
            if ( $exc) {
                $session->set('new_mail', $individu->getMail());
                return $this->redirectToRoute('remplacer_utilisateur', ['id' => $individu->getIdIndividu()]);
            } else {
                return $this->redirectToRoute('individu_gerer');
            }
        }

        //$session->set('current_mail',$individu->getMail());

        return $this->render(
            'individu/modif.html.twig',
            [
            'individu' => $individu,
            'form' => $editForm->createView(),
            ]
        );
    }

    /**
     * Ajouter un utilisateur
     *
     * @Route("/ajouter", name="individu_ajouter")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function ajouterAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $individu = new Individu();
        $editForm = $this->createForm('App\Form\IndividuType', $individu);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() /*&& $editForm->isValid()*/) {
            $individu->setCreationStamp(new \DateTime());
            $em->persist($individu);
            $em->flush();

            return $this->redirectToRoute('individu_gerer');
        }

        return $this->render(
            'individu/modif.html.twig',
            [
            'individu' => $individu,
            'form' => $editForm->createView(),
        ]
        );
    }
    /**
     * Devenir Admin
     *
     * @Route("/{id}/devenir_admin", name="devenir_admin")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function devenirAdminAction(Request $request, Individu $individu)
    {
        $individu->setAdmin(true);
        $individu->setObs(false);    // Pas la peine d'être Observateur si on est admin !

        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Cesser d'être Admin
     *
     * @Route("/{id}/plus_admin", name="plus_admin")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function plusAdminAction(Request $request, Individu $individu)
    {
        $individu->setAdmin(false);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
    * Devenir Obs
    *
    * @Route("/{id}/devenir_obs", name="devenir_obs")
    * @Security("is_granted('ROLE_ADMIN')")
    * @Method("GET")
    */
    public function devenirObsAction(Request $request, Individu $individu)
    {
        $individu->setObs(true);
        $individu->setAdmin(false); // Si on devient Observateur on n'est plus admin !
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Cesser d'être Obs
     *
     * @Route("/{id}/plus_obs", name="plus_obs")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function plusObsAction(Request $request, Individu $individu)
    {
        $individu->setObs(false);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
    * Devenir Sysadmin
    *
    * @Route("/{id}/devenir_sysadmin", name="devenir_sysadmin")
    * @Security("is_granted('ROLE_ADMIN')")
    * @Method("GET")
    */
    public function devenirSysadminAction(Request $request, Individu $individu)
    {
        $individu->setSysadmin(true);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Cesser d'être Sysadmin
     *
     * @Route("/{id}/plus_sysadmin", name="plus_sysadmin")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function plusSysadminAction(Request $request, Individu $individu)
    {
        $individu->setSysadmin(false);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Devenir President
     *
     * @Route("/{id}/devenir_president", name="devenir_president")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function devenirPresidentAction(Request $request, Individu $individu)
    {
        $individu->setPresident(true);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Cesser d'être President
     *
     * @Route("/{id}/plus_president", name="plus_president")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function plusPresidentAction(Request $request, Individu $individu)
    {
        $individu->setPresident(false);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Devenir Expert
     *
     * @Route("/{id}/devenir_expert", name="devenir_expert")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function devenirExpertAction(Request $request, Individu $individu)
    {
        $individu->setExpert(true);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Cesser d'être Expert
     *
     * @Route("/{id}/plus_expert", name="plus_expert")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function plusExpertAction(Request $request, Individu $individu)
    {
        $em = $this->getDoctrine()->getManager();
        $se = $this->se;

        $individu->setExpert(false);
        $em->persist($individu);

        // TODO - Appeler ICI noThematique les autres appels sont sans doute inutiles !
        $se->noThematique($individu);
        $em->flush();

        return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
    }


    /**
    * Activer
    *
    * @Route("/{id}/activer", name="activer_utilisateur")
    * @Security("is_granted('ROLE_ADMIN')")
    * @Method("GET")
    */
    public function activerAction(Request $request, Individu $individu)
    {
        $individu->setDesactive(false);
        $em = $this->getDoctrine()->getManager();
        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Desactiver utilisateur
     *
     * @Route("/{id}/desactiver", name="desactiver_utilisateur")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function desactiverAction(Request $request, Individu $individu)
    {
        $em = $this->getDoctrine()->getManager();

        $individu->setDesactive(true);

        $ssos = $individu->getSso();
        foreach ($ssos as $sso) {
            $em->remove($sso);
        }

        $em->persist($individu);
        $em->flush($individu);

        if ($request->isXmlHttpRequest()) {
            return $this->render('individu/ligne.html.twig', [ 'individu' => $individu ]);
        } else {
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Sudo (l'admin change d'identité)
     *
     * @Route("/{id}/sudo", name="sudo")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method("GET")
     */
    public function sudoAction(Request $request, Individu $individu)
    {
        $sj = $this->sj;
        $ac = $this->ac;

        if (! $ac->isGranted('ROLE_PREVIOUS_ADMIN')) {
            $sj->infoMessage("Controller : connexion de l'utilisateur " . $individu . ' en SUDO ');
            return new RedirectResponse($this->generateUrl('accueil', [ '_switch_user' => $individu->getId() ]));
        } else {
            $sj->warningMessage("Controller : connexion de l'utilisateur " . $individu . ' déjà en SUDO !');
            return $this->redirectToRoute('individu_gerer');
        }
    }

    /**
     * Affecter l'utilisateur à une ou des thematiques
     *
     * @Route("/{id}/thematique", name="choisir_thematique")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET", "POST"})
     */
    public function thematiqueAction(Request $request, Individu $individu)
    {
        $em   = $this->getDoctrine()->getManager();
        $form = $this->createFormBuilder($individu)
            ->add(
                'thematique',
                EntityType::class,
                [
                'multiple' => true,
                'expanded' => true,
                'class' => 'App:Thematique',
                ]
            )
            ->add(
                'rattachement',
                EntityType::class,
                [
                'multiple' => true,
                'expanded' => true,
                'class' => 'App:Rattachement',
                ]
            )
            ->add('submit', SubmitType::class, ['label' => 'modifier' ])
            ->add('reset', ResetType::class, ['label' => 'reset' ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // thématiques && Doctrine ManyToMany
            $all_thematiques = $em->getRepository(Thematique::class)->findAll();
            $my_thematiques = $individu->getThematique();

            foreach ($all_thematiques as $thematique) {
                if ($my_thematiques->contains($thematique)) {
                    $thematique->addExpert($individu);
                } else {
                    $thematique->removeExpert($individu);
                }
            }

            // rattachement && Doctrine ManyToMany
            $all_ratt = $em->getRepository(Rattachement::class)->findAll();
            $my_ratt = $individu->getRattachement();

            foreach ($all_ratt as $ratt) {
                if ($my_ratt->contains($ratt)) {
                    $ratt->addExpert($individu);
                } else {
                    $ratt->removeExpert($individu);
                }
            }
            $em->flush();
        }

        return $this->render(
            'individu/thematique.html.twig',
            [
            'individu' => $individu,
            'form' => $form->createView(),
        ]
        );
    }

    /**
     * Autocomplete: en lien avec l'autocomplete de jquery
     *
     * @Route("/mail_autocomplete", name="mail_autocomplete")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * @Method({"POST","GET"})
     */
    public function mailAutocompleteAction(Request $request)
    {
        $sj = $this->sj;
        $ff = $this->ff;
        $em = $this->getDoctrine()->getManager();
        $form = $ff
            ->createNamedBuilder('autocomplete_form', FormType::class, [])
            ->add('mail', TextType::class, [ 'required' => true, 'csrf_protection' => false])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()) { // nous ne pouvons pas ajouter $form->isValid() et nous ne savons pas pourquoi
            if (array_key_exists('mail', $form->getData())) {
                $data   =   $em->getRepository(Individu::class)->liste_mail_like($form->getData()['mail']);
            } else {
                $data  =   [ ['mail' => 'Problème avec AJAX dans IndividuController:mailAutocompleteAction' ] ];
            }

            $output = [];
            foreach ($data as $item) {
                if (array_key_exists('mail', $item)) {
                    $output[]   =   $item['mail'];
                }
            }

            $response = new Response(json_encode($output));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        // on complète le reste des informations

        $collaborateur    = new \App\Utils\IndividuForm();
        $form = $this->createForm('App\Form\IndividuFormType', $collaborateur, ['csrf_protection' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted()  && $form->isValid()) {
            $individu = $em->getRepository(Individu::class)->findOneBy(['mail' => $collaborateur->getMail() ]);
            //$individu = new Individu();
            if ($individu != null) {
                if ($individu->getMail() != null) {
                    $collaborateur->setMail($individu->getMail());
                }
                if ($individu->getPrenom() != null) {
                    $collaborateur->setPrenom($individu->getPrenom());
                }
                if ($individu->getNom()    != null) {
                    $collaborateur->setNom($individu->getNom());
                }
                if ($individu->getStatut() != null) {
                    $collaborateur->setStatut($individu->getStatut());
                }
                if ($individu->getLabo()   != null) {
                    $collaborateur->setLaboratoire($individu->getLabo());
                }
                if ($individu->getEtab()   != null) {
                    $collaborateur->setEtablissement($individu->getEtab());
                }
                if ($individu->getId()     != null) {
                    $collaborateur->setId($individu->getId());
                }
                $form = $this->createForm('App\Form\IndividuFormType', $collaborateur, ['csrf_protection' => false]);

                return $this->render('version/collaborateurs_ligne.html.twig', [ 'form' => $form->createView() ]);
            } else {
                return  new Response('reallynouserrrrrrrr');
            }
        }
        //return new Response( 'no form submitted' );
        return new Response(json_encode('no form submitted'));
    }


    /**
     * Liste tous les individus
     *
     * @Route("/gerer", name="individu_gerer")
     * @Route("/liste")
     * @Security("is_granted('ROLE_ADMIN')")
     * @Method({"GET","POST"})
     */
    public function gererAction(Request $request)
    {
        $ff = $this->ff;
        $em = $this->getDoctrine()->getManager();

        $form = Functions::getFormBuilder($ff, 'tri', GererUtilisateurType::class, [])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->getData()['all'] == true) {
                $users = $em->getRepository(Individu::class)->findAll();
            } else {
                $users = $em->getRepository(Individu::class)->getActiveUsers();
            }

            $pattern = '/' . $form->getData()['filtre'] . '/i';

            $individus = [];
            foreach ($users as $individu) {
                if (preg_match($pattern, $individu->getMail())) {
                    $individus[] = $individu;
                } elseif (preg_match($pattern, $individu->getNom())) {
                    $individus[] = $individu;
                } elseif (preg_match($pattern, $individu->getMail())) {
                    $individus[] = $individu;
                };
            }
        } else {
            $individus = $em->getRepository(Individu::class)->getActiveUsers();
        }

        // statistiques
        $total = $em->getRepository(Individu::class)->countAll();
        $actifs = 0;
        $idps = [];
        foreach ($individus as $individu) {
            $individu_ssos = $individu->getSso()->toArray();
            if (count($individu_ssos) > 0 && $individu->getDesactive() == false) {
                $actifs++;
            }

            $idps = array_merge(
                $idps,
                array_map(
                    function ($value) {
                        $str = $value->__toString();
                        preg_match('/^(.+)(@.+)$/', $str, $matches);
                        if (array_key_exists(2, $matches)) {
                            return $matches[2];
                        } else {
                            return '@';
                        }
                    },
                    $individu_ssos
                )
            );
        }
        $idps = array_count_values($idps);

        return $this->render(
            'individu/liste.html.twig',
            [
            'idps'  => $idps,
            'total' => $total,
            'actifs' => $actifs,
            'form'  => $form->createView(),
            'individus' => $individus,
            ]
        );
    }

    private static function sso_to_string($sso, $key)
    {
        return $sso->__toString();
    }
}
