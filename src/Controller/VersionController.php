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
use App\Entity\Session;
use App\Entity\Individu;
use App\Entity\CollaborateurVersion;
use App\Entity\RapportActivite;
use App\Entity\Expertise;

use App\GramcServices\Workflow\Projet\ProjetWorkflow;
use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceNotifications;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServiceForms;
use App\GramcServices\ServiceVersions;
use App\GramcServices\ServiceExperts\ServiceExperts;
use App\GramcServices\GramcDate;

use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
//use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;

use App\Utils\Functions;
use App\Utils\Etat;
use App\Utils\Signal;
use App\Utils\IndividuForm;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Form\IndividuFormType;

use App\Validator\Constraints\PagesNumber;

use Knp\Snappy\Pdf;

/**
 * Version controller.
 *
 * @Route("version")
 */
class VersionController extends AbstractController
{
    private $sn;
    private $sj;
    private $sm;
    private $sp;
    private $ss;
    private $sf;
    private $sd;
    private $sv;
    private $se;
    private $pw;
    private $ff;
    private $vl;
    private $tok;
    private $sss;
    private $pdf;


    public function __construct(
        ServiceNotifications $sn,
        ServiceJournal $sj,
        ServiceMenus $sm,
        ServiceProjets $sp,
        ServiceSessions $ss,
        ServiceForms $sf,
        GramcDate $sd,
        ServiceVersions $sv,
        ServiceExperts $se,
        ProjetWorkflow $pw,
        FormFactoryInterface $ff,
        ValidatorInterface $vl,
        TokenStorageInterface $tok,
        SessionInterface $sss,
        Pdf $pdf
    ) {
        $this->sn  = $sn;
        $this->sj  = $sj;
        $this->sm  = $sm;
        $this->sp  = $sp;
        $this->ss  = $ss;
        $this->sf  = $sf;
        $this->sd  = $sd;
        $this->sv  = $sv;
        $this->se  = $se;
        $this->pw  = $pw;
        $this->ff  = $ff;
        $this->vl  = $vl;
        $this->tok = $tok;
        $this->sss = $sss;
        $this->pdf = $pdf;
    }

    /**
     * Lists all version entities.
     *
     * @Route("/", name="version_index",methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $versions = $em->getRepository('App:Version')->findAll();

        return $this->render('version/index.html.twig', array(
            'versions' => $versions,
        ));
    }

    /**
     * Creates a new version entity.
     *
     * @Route("/new", name="version_new",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $version = new Version();
        $form = $this->createForm('App\Form\VersionType', $version);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($version);
            $em->flush($version);

            return $this->redirectToRoute('version_show', array('id' => $version->getId()));
        }

        return $this->render('version/new.html.twig', array(
            'version' => $version,
            'form' => $form->createView(),
        ));
    }

    /**
     * Supprimer version
     *
     * @Route("/{id}/avant_supprimer/{rtn}",
     *        name="version_avant_supprimer",
     *        defaults= {"rtn" = "X" },
     *        methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     *
     */
    public function avantSupprimerAction(Version $version, $rtn)
    {
        $sm = $this->sm;
        $sj = $this->sj;

        // ACL
        if ($sm->modifier_version($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de supprimer la version " . $version->getIdVersion().
                " parce que : " . $sm->modifier_version($version)['raison']);
        }

        return $this->render(
            'version/avant_supprimer.html.twig',
            [
                'version' => $version,
                'rtn'   => $rtn,
                ]
        );
    }

    /**
     * Supprimer version (en base de données et dans le répertoire data)
     *
     * @Route("/{id}/supprimer/{rtn}", defaults= {"rtn" = "X" }, name="version_supprimer",methods={"GET"} )
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     *
     */
    public function supprimerAction(Version $version, $rtn)
    {
        $em = $this->getDoctrine()->getManager();
        $sm = $this->sm;
        $sv = $this->sv;
        $sj = $this->sj;

        // ACL
        if ($sm->modifier_version($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de supprimer la version " . $version->getIdVersion().
                " parce que : " . $sm->modifier_version($version)['raison']);
        }

        $etat = $version->getEtatVersion();
        $idProjet = null;
        $idVersion = null;
        if ($version->getProjet() == null) {
            $idProjet = null;
            $idVersion = $version->getIdVersion();
        } else {
            $idProjet   =  $version->getProjet()->getIdProjet();
        }


        if ($etat == Etat::EDITION_DEMANDE || $etat == Etat::EDITION_TEST) {
            // Suppression des collaborateurs
            foreach ($version->getCollaborateurVersion() as $collaborateurVersion) {
                $em->remove($collaborateurVersion);
            }

            // Suppression des expertises éventuelles
            $expertises = $version->getExpertise();
            foreach ($expertises as $expertise) {
                $em->remove($expertise);
            }

            $em->flush();
        }

        if ($idProjet == null) {
            $sj->warningMessage(__METHOD__ . ':' . __LINE__ . " version " . $idVersion . " sans projet supprimée");
        } else {
            $projet = $em->getRepository(Projet::class)->findOneBy(['idProjet' => $idProjet]);

            // On met le champ version derniere a NULL
            $projet->setVersionDerniere(null);
            $em -> persist($projet);
            $em->flush();

            // On supprime la version
            // Du coup la versionDerniere est mise à jour par l'EventListener
            $em->remove($version);
            $em->flush();

            // Si pas d'autre version, on supprime le projet
            if ($projet != null && $projet->getVersion() != null && count($projet->getVersion()) == 0) {
                $em->remove($projet);
                $em->flush();
            }
        }

        // suppression des fichiers liés à la version
        $sv->effacerDonnees($version);
        
        //return $this->redirectToRoute( 'projet_accueil' );
        // Il faudrait plutôt revenir là d'où on vient !
        if ($rtn == "X") {
            return $this->redirectToRoute('projet_accueil');
        } else {
            return $this->redirectToRoute($rtn);
        }
    }

    //////////////////////////////////////////////////////////////////////////

    /**
     * Finds and displays a version entity.
     *
     * @Route("/{id}/show", name="version_show",methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function showAction(Version $version)
    {
        $deleteForm = $this->createDeleteForm($version);

        return $this->render('version/show.html.twig', array(
            'version' => $version,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Affiche au format pdf
     *
     * @Route("/{id}/pdf", name="version_pdf",methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     */
    public function pdfAction(Version $version, Request $request)
    {
        $sv = $this->sv;
        $sp = $this->sp;
        $sj = $this->sj;
        $spdf = $this->pdf;

        $projet = $version->getProjet();
        if (! $sp->projetACL($projet)) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        $session = $version->getSession();

        $img_expose_1 = $sv->imageProperties('img_expose_1', $version);
        $img_expose_2 = $sv->imageProperties('img_expose_2', $version);
        $img_expose_3 = $sv->imageProperties('img_expose_3', $version);

        $img_justif_renou_1 = $sv->imageProperties('img_justif_renou_1', $version);
        $img_justif_renou_2 = $sv->imageProperties('img_justif_renou_2', $version);
        $img_justif_renou_3 = $sv->imageProperties('img_justif_renou_3', $version);

        //$toomuch = $sv->is_demande_toomuch($version->getAttrHeures(),$version->getDemHeures());
        $toomuch = false;
        if ($session->getLibelleTypeSession()=='B' && ! $sv->isNouvelle($version)) {
            $version_prec = $version->versionPrecedente();
            if ($version_prec->getAnneeSession() == $version->getAnneeSession()) {
                $toomuch  = $sv -> is_demande_toomuch($version_prec->getAttrHeures(), $version->getDemHeures());
            }
        }

        $formation = $sv->buildFormations($version);

        $html4pdf =  $this->render(
            'version/pdf.html.twig',
            [
            'warn_type'          => false,
            'formation'          => $formation,
            'projet'             => $projet,
            'version_form'       => null,
            'version'            => $version,
            'session'            => $session,
            'menu'               => null,
            'img_expose_1'       => $img_expose_1,
            'img_expose_2'       => $img_expose_2,
            'img_expose_3'       => $img_expose_3,
            'img_justif_renou_1' => $img_justif_renou_1,
            'img_justif_renou_2' => $img_justif_renou_2,
            'img_justif_renou_3' => $img_justif_renou_3,
            'conso_cpu'          => $sp->getConsoRessource($projet, 'cpu', $version->getAnneeSession()),
            'conso_gpu'          => $sp->getConsoRessource($projet, 'gpu', $version->getAnneeSession()),
            'rapport_1'          => null,
            'rapport'            => null,
            'toomuch'            => $toomuch
        ]
        );

        $pdf = $spdf->setOption('enable-local-file-access', true);
        $pdf = $spdf->getOutputFromHtml($html4pdf->getContent());
        return Functions::pdf($pdf);
    }

    /**
     * Finds and displays a version entity.
     *
     * @Route("/{id}/fiche_pdf", name="version_fiche_pdf",methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     */
    public function fichePdfAction(Version $version, Request $request)
    {
        $sm   = $this->sm;
        $sj   = $this->sj;
        $spdf = $this->pdf;

        $projet =  $version->getProjet();

        // ACL
        if ($sm->telechargement_fiche($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de télécharger la fiche du projet " . $projet .
                " parce que : " . $sm->telechargement_fiche($version)['raison']);
        }

        $session = $version->getSession();

        $html4pdf =  $this->render(
            'version/fiche_pdf.html.twig',
            [
                'projet' => $projet,
                'version'   =>  $version,
                'session'   =>  $session,
                ]
        );
        // return $html4pdf;
        //$html4pdf->prepare($request);
        //$pdf = App::getPDF($html4pdf);
        //$pdf = App::getPDF($html4pdf->getContent());
        $pdf = $spdf->getOutputFromHtml($html4pdf->getContent());

        return Functions::pdf($pdf);
    }

    ///////////////////////////////////////////////////////////////

    /**
     * Téléverser le rapport d'activité de l'année précedente
     *
     * @Route("/{id}/televersement_fiche", name="version_televersement_fiche",methods={"GET","POST"})
     * Method({"POST","GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function televersementFicheAction(Request $request, Version $version)
    {
        $em = $this->getDoctrine()->getManager();
        $sm = $this->sm;
        $sj = $this->sj;

        // ACL
        if ($sm->televersement_fiche($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de téléverser la fiche de la version " . $version .
                " parce que : " . $sm -> telechargement_fiche($version)['raison']);
        }

        $format_fichier = new \Symfony\Component\Validator\Constraints\File(
            [
                'mimeTypes'=> [ 'application/pdf' ],
                'mimeTypesMessage'=>' Le fichier doit être un fichier pdf. ',
                'maxSize' => "2024k",
                'uploadIniSizeErrorMessage' => ' Le fichier doit avoir moins de {{ limit }} {{ suffix }}. ',
                'maxSizeMessage' => ' Le fichier est trop grand ({{ size }} {{ suffix }}), il doit avoir moins de {{ limit }} {{ suffix }}. ',
            ]
        );

        $form = $this->ff
                    ->createNamedBuilder('upload', FormType::class, [], ['csrf_protection' => false ])
                    ->add(
                        'file',
                        FileType::class,
                        [
                        'required'          =>  true,
                        'label'             => "",
                        'constraints'       => [$format_fichier , new PagesNumber() ]
                        ]
                    )
                   ->getForm();

        $erreurs  = [];
        $resultat = [];
        $file = null;

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $data = $form->getData();

            if (isset($data['file']) && $data['file'] != null) {
                $tempFilename = $data['file'];
                if (! empty($tempFilename) && $tempFilename != "") {
                    $validator  = $this->vl;
                    $violations = $validator->validate($tempFilename, [ $format_fichier, new PagesNumber() ]);
                    foreach ($violations as $violation) {
                        $erreurs[]  =   $violation->getMessage();
                    }
                }
            } else {
                $tempFilename = null;
            }


            if (is_file($tempFilename) && ! is_dir($tempFilename)) {
                $file = new File($tempFilename);
            } elseif (is_dir($tempFilename)) {
                $sj->errorMessage(__METHOD__ .":" . __LINE__ . " Le nom  " . $tempFilename . " correspond à un répertoire");
                $erreurs[]  =  " Le nom  " . $tempFilename . " correspond à un répertoire";
            } else {
                $sj->errorMessage(__METHOD__ .":" . __LINE__ . " Le fichier " . $tempFilename . " n'existe pas");
                $erreurs[]  =  " Le fichier " . $tempFilename . " n'existe pas";
            }

            if ($form->isValid() && $erreurs == []) {
                $session = $version->getSession();
                $projet = $version->getProjet();
                if ($projet != null && $session != null) {
                    $filename = $this->getParameter('signature_directory') .'/'.$session->getIdSession() .
                                    "/" . $session->getIdSession() . $projet->getIdProjet() . ".pdf";
                    $file->move(
                        $this->getParameter('signature_directory') .'/'.$session->getIdSession(),
                        $session->getIdSession() . $projet->getIdProjet() . ".pdf"
                    );

                    // on marque le téléversement de la fiche projet
                    $version->setPrjFicheVal(true);
                    $em->flush();
                    $resultat[] =   " La fiche du projet " . $projet . " pour la session " . $session . " a été téléversée ";
                } else {
                    $resultat[] =   " La fiche du projet n'a pas été téléversée";
                    if ($projet == null) {
                        $sj->errorMessage(__METHOD__ . ':'. __LINE__ . " version " . $version . " n'a pas de projet");
                    }
                    if ($session == null) {
                        $sj->errorMessage(__METHOD__ . ':' . __LINE__ . " version " . $version . " n'a pas de session");
                    }
                }
            }
        }

        return $this->render(
            'version/televersement_fiche.html.twig',
            [
            'version'       =>  $version,
            'form'          =>  $form->createView(),
            'erreurs'       =>  $erreurs,
            'resultat'      =>  $resultat,
            ]
        );
    }



    /**
     * Displays a form to edit an existing version entity.
     *
     * @Route("/{id}/edit", name="version_edit",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function editAction(Request $request, Version $version)
    {
        $deleteForm = $this->createDeleteForm($version);
        $editForm = $this->createForm('App\Form\VersionType', $version);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('version_edit', array('id' => $version->getId()));
        }

        return $this->render('version/edit.html.twig', array(
            'version' => $version,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a version entity.
     *
     * @Route("/{id}", name="version_delete",methods={"DELETE"})
     * Method("DELETE")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    /*    public function deleteAction(Request $request, Version $version)
        {
            $form = $this->createDeleteForm($version);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->remove($version);
                $em->flush($version);
            }

            return $this->redirectToRoute('version_index');
        }*/

    /**
     * Creates a form to delete a version entity.
     *
     * @param Version $version The version entity
     * @Security("is_granted('ROLE_ADMIN')")
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Version $version)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('version_delete', array('id' => $version->getIdVersion())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }


    /**
     * Changer le responsable d'une version.
     *
     * @Route("/{id}/responsable", name="changer_responsable",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function changerResponsableAction(Version $version, Request $request)
    {
        $sm = $this->sm;
        $sn = $this->sn;
        $sj = $this->sj;
        $sv = $this->sv;
        $ff = $this->ff;
        $sss= $this->sss;
        $token = $this->tok->getToken();

        // Si changement d'état de la session alors que je suis connecté !
        $sss->remove('SessionCourante'); // remove cache

        // ACL
        $moi = $token->getUser();

        if ($version == null) {
            $sj->throwException(__METHOD__ .":". __LINE__ .' version null');
        }

        if ($sm->changer_responsable($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ .
                    " impossible de changer de responsable parce que " . $sm->changer_responsable($version)['raison']);
        }

        // préparation de la liste des responsables potentiels
        $moi = $token->getUser();
        $collaborateurs = $version->getCollaborateurs(false, true, $moi); // pas moi, seulement les éligibles

        $change_form = Functions::createFormBuilder($ff)
            ->add(
                'responsable',
                EntityType::class,
                [
                    'multiple' => false,
                    'class' => 'App:Individu',
                    'required'  =>  true,
                    'label'     => '',
                    'choices' =>  $collaborateurs,
                ]
            )
            ->add('submit', SubmitType::class, ['label' => 'Nouveau responsable'])
            ->getForm();
        $change_form->handleRequest($request);

        $projet =  $version->getProjet();

        if ($projet != null) {
            $idProjet   =   $projet->getIdProjet();
        } else {
            $sj->errorMessage(__METHOD__ .":". __LINE__ . " projet null pour version " . $version->getIdVersion());
            $idProjet   =   null;
        }

        if ($change_form->isSubmitted() && $change_form->isValid()) {
            $ancien_responsable  = $version->getResponsable();
            $nouveau_responsable = $change_form->getData()['responsable'];
            if ($nouveau_responsable == null) {
                return $this->redirectToRoute('consulter_version', ['id' => $idProjet, 'version' => $version->getId()]);
            }

            if ($ancien_responsable != $nouveau_responsable) {
                $sv->changerResponsable($version, $nouveau_responsable);

                $params = [
                            'ancien' => $ancien_responsable,
                            'nouveau'=> $nouveau_responsable,
                            'version'=> $version
                           ];

                // envoyer une notification à l'ancien et au nouveau responsable
                $sn->sendMessage(
                    'notification/changement_resp_pour_ancien-sujet.html.twig',
                    'notification/changement_resp_pour_ancien-contenu.html.twig',
                    $params,
                    [$ancien_responsable]
                );

                $sn->sendMessage(
                    'notification/changement_resp_pour_nouveau-sujet.html.twig',
                    'notification/changement_resp_pour_nouveau-contenu.html.twig',
                    $params,
                    [$nouveau_responsable]
                );

                $sn->sendMessage(
                    'notification/changement_resp_pour_admin-sujet.html.twig',
                    'notification/changement_resp_pour_admin-contenu.html.twig',
                    $params,
                    $sn->mailUsers(['A'], null)
                );
            }
            return $this->redirectToRoute(
                'consulter_version',
                [
                    'version' => $version->getIdVersion(),
                    'id'    =>  $idProjet,
                ]
            );
        }

        return $this->render(
            'version/responsable.html.twig',
            [
                'projet' => $idProjet,
                'change_form'   => $change_form->createView(),
                'version'   =>  $version,
                'session'   =>  $version->getSession(),
            ]
        );
    }


    /**
     * Mettre une pénalité sur une version (en GET par ajax)
     *
     * @Route("/{id}/version/{penal}/penalite", name="penal_version",methods={"GET"})
     * Method({"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function penalAction(Version $idversion, $penal)
    {
        $data = [];
        $em = $this->getDoctrine()->getManager();
        $version = $em->getRepository('App:Version')->findOneBy([ 'idVersion' =>  $idversion]);
        if ($version != null) {
            if ($penal >= 0) {
                $data['recuperable'] = 0;
                $data['penalite' ] = $penal;
                $version ->setPenalHeures($penal);
            } else {
                $data['penalite'] = 0;
                $data['recuperable' ] = -$penal;
                $version ->setPenalHeures(0);
            }
            $em->persist($version);
            $em->flush($version);
        }
        return new Response(json_encode($data));
    }

    /**
     * envoyer à l'expert
     *
     * @Route("/{id}/avant_envoyer", name="avant_envoyer_expert",methods={"GET","POST"})
     * Method({"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function avantEnvoyerAction(Version $version, Request $request, LoggerInterface $lg)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $ff = $this->ff;
        $em = $this->getdoctrine()->getManager();

        if ($sm->envoyer_expert($version)['ok'] == false) {
        $sj->throwException(__METHOD__ . ":" . __LINE__ .
            " impossible d'envoyer en expertise parce que " . $sm->envoyer_expert($version)['raison']);
        }
        //$this->MenuACL($sm->envoyer_expert($version), "Impossible d'envoyer la version " . $version->getIdVersion() . " à l'expert", __METHOD__, __LINE__);

        $projet  = $version->getProjet();
        $session = $version->getSession();

        $form = Functions::createFormBuilder($ff)
                ->add(
                    'CGU',
                    CheckBoxType::class,
                    [
                        'required'  =>  false,
                        'label'     => '',
                        ]
                )
            ->add('envoyer', SubmitType::class, ['label' => "Envoyer à l'expert"])
            ->add('annuler', SubmitType::class, ['label' => "Annuler"])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $CGU = $form->getData()['CGU'];
            if ($form->get('annuler')->isClicked()) {
                return $this->redirectToRoute('consulter_projet', [ 'id' => $projet->getIdProjet() ]);
            }

            if ($CGU == false && $form->get('envoyer')->isClicked()) {
                //$sj->errorMessage(__METHOD__  .":". __LINE__ . " CGU pas acceptées ");
                //return $this->redirectToRoute('consulter_projet',[ 'id' => $projet->getIdProjet() ] );
                return $this->render(
                    'version/avant_envoyer_expert.html.twig',
                    [ 'projet' => $projet, 'form' => $form->createView(), 'session' => $session, 'cgu' => 'KO' ]
                );
            } elseif ($CGU == true && $form->get('envoyer')->isClicked()) {
                $version->setCGU(true);
                Functions::sauvegarder($version, $em, $lg);
                return $this->redirectToRoute('envoyer_expert', [ 'id' => $version->getIdVersion() ]);
            } else {
                $sj->throwException(__METHOD__ .":". __LINE__ ." Problème avec le formulaire d'envoi à l'expert du projet " . $version->getIdVersion());
            }
        }

        return $this->render(
            'version/avant_envoyer_expert.html.twig',
            [ 'projet' => $projet, 'form' => $form->createView(), 'session' => $session, 'cgu' => 'OK' ]
        );
    }

    /**
     * envoyer à l'expert
     *
     * @Route("/{id}/envoyer", name="envoyer_expert",methods={"GET"})
     * Method("GET")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function envoyerAction(Version $version, Request $request, LoggerInterface $lg)
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $se = $this->se;
        $em = $this->getdoctrine()->getManager();

        ////$this->MenuACL($sm->envoyer_expert($version), " Impossible d'envoyer la version " . $version->getIdVersion() . " à l'expert", __METHOD__, __LINE__);

        $projet = $version -> getProjet();

        if ($sm->envoyer_expert($version)['incomplet'] == true) {
            $sj->throwException(__METHOD__ .":". __LINE__ ." Version " . $version->getIdVersion() . " incomplet envoyé à l'expert !");
        }

        if ($version->getCGU() == false) {
            $sj->throwException(__METHOD__ .":". __LINE__ ." Pas d'acceptation des CGU " . $projet->getIdProjet());
        }

        // Crée une nouvelle expertise avec proposition d'experts
        $se->newExpertiseIfPossible($version);

        $projetWorkflow = $this->pw;
        $rtn = $projetWorkflow->execute(Signal::CLK_VAL_DEM, $projet);

        //$sj->debugMessage(__METHOD__ .  ":" . __LINE__ . " Le projet " . $projet . " est dans l'état " . Etat::getLibelle( $projet->getObjectState() )
        //    . "(" . $projet->getObjectState() . ")" );

        if ($rtn == true) {
            return $this->render('version/envoyer_expert.html.twig', [ 'projet' => $projet, 'session' => $version->getSession() ]);
        } else {
            $sj->errorMessage(__METHOD__ .  ":" . __LINE__ . " Le projet " . $projet->getIdProjet() . " n'a pas pu etre envoyé à l'expert correctement");
            return new Response("Le projet " . $projet->getIdProjet() . " n'a pas pu etre envoyé à l'expert correctement");
        }
    }

    /**
     * Téléversements génériques de rapport d'activité ou de fiche projet
     *
     * @Route("/televersement", name="televersement_generique",methods={"GET","POST"})
     * Method({"POST","GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function televersementGeneriqueAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $sd = $this->sd;
        $ss = $this->ss;
        $sp = $this->sp;
        $sj = $this->sj;

        $format_fichier = new \Symfony\Component\Validator\Constraints\File(
            [
            'mimeTypes'=> [ 'application/pdf' ],
            'mimeTypesMessage'=>' Le fichier doit être un fichier pdf. ',
            'maxSize' => "2024k",
            'uploadIniSizeErrorMessage' => ' Le fichier doit avoir moins de {{ limit }} {{ suffix }}. ',
            'maxSizeMessage' => ' Le fichier est trop grand ({{ size }} {{ suffix }}), il doit avoir moins de {{ limit }} {{ suffix }}. ',
        ]
        );

        $def_annee = $sd->format('Y');
        $def_sess  = $ss->getSessionCourante()->getIdSession();

        $form = $this
           ->ff
           ->createNamedBuilder('upload', FormType::class, [], ['csrf_protection' => false ])
           ->add('projet', TextType::class, [ 'label'=> "", 'required' => false, 'attr' => ['placeholder' => 'P12345']])
           ->add('session', TextType::class, [ 'label'=> "", 'required' => false, 'attr' => ['placeholder' => $def_sess]])
           ->add('annee', TextType::class, [ 'label'=> "", 'required' => false, 'attr' => ['placeholder' => $def_annee]])
           ->add(
               'type',
               ChoiceType::class,
               [
                'required' => true,
                'choices'  => [
                                "Rapport d'activité" => "r",
                                "Fiche projet"       => "f",
                              ],
                'label'    => "",
            ]
           )
           ->add(
               'file',
               FileType::class,
               [
                'required'    =>  true,
                'label'       => "",
                'constraints' => [$format_fichier , new PagesNumber() ]
            ]
           )
           ->getForm();

        $erreurs  = [];
        $resultat = [];

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $data   =   $form->getData();

            if (isset($data['projet']) && $data['projet'] != null) {
                $projet = $em->getRepository(Projet::class)->find($data['projet']);
                if ($projet == null) {
                    $erreurs[]  =   "Le projet " . $data['projet'] . " n'existe pas";
                }
            } else {
                $projet = null;
            }

            if (isset($data['session']) && $data['session'] != null) {
                $session = $em->getRepository(Session::class)->find($data['session']);
                if ($session == null) {
                    $erreurs[] = "La session " . $data['session'] . " n'existe pas";
                }
            } else {
                $session = $ss->getSessionCourante();
            }

            if (isset($data['annee']) && $data['annee'] != null) {
                $annee = $data['annee'];
            } else {
                $annee = $session->getAnneeSession() + 2000;
            }

            if (isset($data['file']) && $data['file'] != null) {
                $tempFilename = $data['file'];
                if (! empty($tempFilename) && $tempFilename != "") {
                    $validator = $this->vl;
                    $violations = $validator->validate($tempFilename, [ $format_fichier, new PagesNumber() ]);
                    foreach ($violations as $violation) {
                        $erreurs[]  =   $violation->getMessage();
                    }
                }
            } else {
                $tempFilename = null;
            }

            $type = $data['type'];

            if ($annee == null && $type != "f") {
                $erreurs[] =  "L'année doit être donnée pour un rapport d'activité";
            }
            if ($projet == null) {
                $erreurs[] =  "Le projet doit être donné";
            }
            if ($session == null && $type == "f") {
                $erreurs[] =  "La session doit être donnée pour une fiche projet";
            }

            $sp->createDirectories($annee, $session);

            $file = null;
            if (is_file($tempFilename) && ! is_dir($tempFilename)) {
                $file = new File($tempFilename);
            } elseif (is_dir($tempFilename)) {
                $sj->errorMessage(__METHOD__ .":" . __LINE__ . " Le nom  " . $tempFilename . " correspond à un répertoire");
                $erreurs[]  =  " Le nom  " . $tempFilename . " correspond à un répertoire";
            } else {
                $sj->errorMessage(__METHOD__ .":" . __LINE__ . " Le fichier " . $tempFilename . " n'existe pas");
                $erreurs[]  =  " Le fichier " . $tempFilename . " n'existe pas";
            }

            if ($form->isValid() && $erreurs == []) {
                if ($type == "f") {
                    $filename = $this->getParameter('signature_directory') .'/'.$session->getIdSession() .
                                    "/" . $session->getIdSession() . $projet->getIdProjet() . ".pdf";
                    $file->move(
                        $this->getParameter('signature_directory') .'/'.$session->getIdSession(),
                        $session->getIdSession() . $projet->getIdProjet() . ".pdf"
                    );

                    // on marque le téléversement de la fiche projet
                    $version = $em->getRepository(Version::class)->findOneBy(['projet' => $projet, 'session' => $session ]);
                    if ($version != null) {
                        $version->setPrjFicheVal(true);
                        $em->flush();
                    } else {
                        $sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Il n'y a pas de version du projet " . $projet . " pour la session " . $session);
                    }

                    $resultat[] =   " Fichier " . $filename . " téléversé";
                } elseif ($type = "r") {
                    $filename = $this->getParameter('rapport_directory') .'/'.$annee .
                                    "/" . $annee . $projet->getIdProjet() . ".pdf";
                    $file->move(
                        $this->getParameter('rapport_directory') .'/'.$annee,
                        $annee . $projet->getIdProjet() . ".pdf"
                    );
                    $resultat[] =   " Fichier " . $filename . " téléversé";
                    $this->modifyRapport($projet, $annee, $filename);
                }
            }
        }

        $form1 = $this->ff
            ->createBuilder()
            ->add('version', TextType::class, [
                    'label' => "Numéro de version",'required' => true, 'attr' => ['placeholder' => $def_sess.'P12345']])
            ->add('attrHeures', IntegerType::class, [
                    'label' => 'Attribution', 'required' => true, 'attr' => ['placeholder' => '100000']])
            ->add('attrHeuresEte', IntegerType::class, [
                    'label' => 'Attribution', 'required' => false, 'attr' => ['placeholder' => '10000']])
            ->getForm();

        $erreurs1 = [];
        $form1->handleRequest($request);
        if ($form1->isSubmitted()) {
            $data    = $form1->getData();
            if (isset($data['version']) && $data['version'] != null) {
                $version = $em->getRepository(Version::class)->find($data['version']);
                if ($version == null) {
                    $erreurs1[]  =   "La version " . $data['version'] . " n'existe pas";
                }
            } else {
                $version     = null;
                $erreurs1[]  = "Pas de version spécifiée";
            }
            if ($version != null) {
                $etat = $version -> getEtatVersion();
                if ($etat == Etat::TERMINE || $etat == Etat::ANNULE ) {
                    $libelle = Etat::LIBELLE_ETAT[$etat];
                    $erreurs1[] = "La version ".$version->getIdVersion()." est en état $libelle, pas possible de changer son attribution !";
                }
            }

            $attrHeures = $data['attrHeures'];
            if ($attrHeures<0) {
                $erreurs1[] = "$attrHeures ne peut être une attribution";
            }
            if (isset($data['attrHeuresEte']) && $data['attrHeuresEte'] != null) {
                $attrHeuresEte = $data["attrHeuresEte"];
                if ($attrHeuresEte<0) {
                    $erreurs1[] = "$attrHeuresEte ne peut être une attribution, même pour un été torride";
                }
            } else {
                $attrHeuresEte = -1;
            }

            if (count($erreurs1) == 0) {
                $version->setAttrHeures($attrHeures);
                if ($attrHeuresEte>=0) {
                    $version->setAttrHeuresEte($attrHeuresEte);
                }
                $em = $this->getDoctrine()->getManager();
                $em->persist($version);
                $em->flush();
            }
        }

        return $this->render(
            'version/televersement_generique.html.twig',
            [
            'form'     => $form->createView(),
            'erreurs'  => $erreurs,
            'form1'    => $form1->createView(),
            'erreurs1' => $erreurs1,
            'def_annee' => $def_annee,
            'def_sess'  => $def_sess,
            'resultat' => $resultat,
        ]
        );
    }

    ///////////////////////////////////////////////////////////////

    /**
     * Téléverser le rapport d'actitivé
     *
     * @Route("/{id}/rapport_annee/{annee}", defaults={"annee"=0}, name="televerser_rapport_annee",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function televerserRapportAction(Version $version, Request $request, $annee)
    {
        $em = $this->getDoctrine()->getManager();
        $sm = $this->sm;
        $sf = $this->sf;
        $sj = $this->sj;

        // ACL
        if ($sm->televerser_rapport_annee($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ .
            " impossible de téléverser le rapport parce que " . $sm->televerser_rapport_annee($version)['raison']);
        }
        //$sj->debugMessage('VersionController:televerserRapportActionAnnee');

        if ($annee == 0) {
            $annee  =   $version->getAnneeSession();
        }

        // Calcul du nom de fichier
        $dir = $this->getParameter('rapport_directory') . '/' . $annee;
        if (! file_exists($dir)) {
            mkdir($dir);
        } elseif (! is_dir($dir)) {
            unlink($dir);
            mkdir($dir);
        }
        $filename = $annee . $version->getProjet()->getIdProjet() . ".pdf";
        $path     = $dir . '/' . $filename;
        
        $rtn = $sf->televerserFichier($request, $dir, $filename);

        // Fichier téléversé avec succès -> On écrit dans la base de données
        //                                  On confirme que le rapport est bien là
        if ($rtn == 'OK') {
            $rapportActivite = $em->getRepository(RapportActivite::class)->findOneBy(
                [
        'projet' => $version->getProjet(),
        'annee' => $annee,
        ]
            );
            if ($rapportActivite == null) {
                $rapportActivite    = new RapportActivite($version->getProjet(), $annee);
            }

            $rapportActivite->setTaille(filesize($path));

            // TODO - ces deux champs ne servent à RIEN Il faut les supprimer
            $rapportActivite->setNomFichier("");
            $rapportActivite->setFiledata("");

            $em->persist($rapportActivite);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new Response('OK');
            } else {
                return $this->render(
                    'version/confirmation_rapport.html.twig',
                    [
            'projet'    =>  $version->getProjet()->getIdProjet(),
            'version'   =>  $version->getIdVersion(),
            ]
                );
            }
        }

        // L'objet form est retourné = il faut juste l'afficher
        elseif (is_object($rtn)) {
            return $this->render(
                'version/televerser_rapport.html.twig',
                [
        'projet'    =>  $version->getProjet()->getIdProjet(),
        'version'   =>  $version->getIdVersion(),
        'annee'     =>  $version->getAnneeSession(),
        'form'      =>  $rtn->createView(),
        ]
            );
        }

        // Un autre string = Message d'erreur
        else {
            if ($request->isXmlHttpRequest()) {
                return new Response($rtn);
            } else {
                return $this->render(
                    'version/erreur_rapport.html.twig',
                    [
            'projet'    =>  $version->getProjet()->getIdProjet(),
            'version'   =>  $version->getIdVersion(),
            'annee'     =>  $version->getAnneeSession(),
            'erreur'    =>  $rtn,
            ]
                );
            }
        }
    }

    /**
     * Téléverser un fichier attaché à une version
     *
     * @Route("/{id}/fichier", name="televerser_fichier_attache",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function televerserFichierAction(version $version, Request $request)
    {
        $sv = $this->sv;
        $sm = $this->sm;
        $sf = $this->sf;
        $sj = $this->sj;

        // ACL - Mêmes ACL que modification de version !
        if ($sm->modifier_version($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de modifier la version " . $version->getIdVersion().
        " parce que : " . $sm->modifier_version($version)['raison']);
        }

        $dir      = $sv->imageDir($version);
        $filename = 'document.pdf';

        if (! file_exists($dir)) {
            mkdir($dir);
        } elseif (! is_dir($dir)) {
            unlink($dir);
            mkdir($dir);
        }
        $rtn = $sf->televerserFichier($request, $dir, $filename);
        return new Response($rtn);
    }

    ////////////////////////////////////////////////////////////////////

    private function modifyRapport(Projet $projet, $annee, $filename)
    {
        $em = $this->getDoctrine()->getManager();

        // création de la table RapportActivite
        $rapportActivite = $em->getRepository(RapportActivite::class)->findOneBy(
            [
            'projet' => $projet,
            'annee' => $annee,
        ]
        );
        if ($rapportActivite == null) {
            $rapportActivite = new RapportActivite($projet, $annee);
        }


        $rapportActivite->setTaille(filesize($filename));
        $rapportActivite->setNomFichier($filename);
        $rapportActivite->setFiledata("");

        $em->persist($rapportActivite);
        $em->flush();
    }
}
