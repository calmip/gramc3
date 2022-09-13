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
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;

use App\Utils\Functions;
use App\GramcServices\Etat;
use App\GramcServices\Signal;
use App\Form\IndividuFormType;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\form;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use App\Validator\Constraints\PagesNumber;
use Knp\Snappy\Pdf;

use Doctrine\ORM\EntityManagerInterface;

/******************************************
 *
 * VersionController = Les contrôleurs utilisés avec les versions de projets
 *                     Partie COMMUNE A TOUS LES MESOCENTRES
 *
 * Voir aussi les fichiers mesocentres/xxx/src/Controller/VersionModifController.php
 * pour des contrôleurs spécifiques à chaque mésocentre
 * (ce qui concerne la modification des versions)
 *
 **********************************************************************/

/**
 * Version controller.
 *
 * @Route("version")
 */
class VersionController extends AbstractController
{
    public function __construct(
        private ServiceNotifications $sn,
        private ServiceJournal $sj,
        private ServiceMenus $sm,
        private ServiceProjets $sp,
        private ServiceSessions $ss,
        private ServiceForms $sf,
        private GramcDate $sd,
        private ServiceVersions $sv,
        private ServiceExperts $se,
        private ProjetWorkflow $pw,
        private FormFactoryInterface $ff,
        private ValidatorInterface $vl,
        private TokenStorageInterface $tok,
        private AuthorizationCheckerInterface $ac,
        private Pdf $pdf,
        private EntityManagerInterface $em
    ) {}

    /**
     * Lists all version entities.
     * TODO - INUTILISé, donc SUPPRIMER
     * 
     * @Route("/", name="version_index",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function indexAction(): Response
    {
        $em = $this->em;

        $versions = $em->getRepository(Version::class)->findAll();

        return $this->render('version/index.html.twig', array(
            'versions' => $versions,
        ));
    }

    /**
     * Creates a new version entity.
     *
     * @Route("/new", name="version_new",methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function newAction(Request $request): Response
    {
        $version = new Version();
        $form = $this->createForm('App\Form\VersionType', $version);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
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
     * Affichage d'un écran de confirmation avant la suppression d'une version de projet
     *
     * @Route("/{id}/avant_supprimer/{rtn}",
     *        name="version_avant_supprimer",
     *        defaults= {"rtn" = "X" },
     *        methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     *
     */
    public function avantSupprimerAction(Version $version, $rtn): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;

        // ACL
        if ($sm->modifierVersion($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de supprimer la version " . $version->getIdVersion().
                " parce que : " . $sm->modifierVersion($version)['raison']);
        }

        return $this->render(
            'version/avant_supprimer.html.twig',
            [
                'version' => $version,
                'rtn' => $rtn,
            ]
        );
    }

    /**
     * Supprimer version (en base de données et dans le répertoire data)
     *
     * @Route("/{id}/supprimer/{rtn}", defaults= {"rtn" = "X" }, name="version_supprimer",methods={"GET"} )
     * @Security("is_granted('ROLE_DEMANDEUR')")
     *
     */
    public function supprimerAction(Version $version, $rtn): Response
    {
        $em = $this->em;
        $sm = $this->sm;
        $sv = $this->sv;
        $sj = $this->sj;

        // ACL
        if ($sm->modifierVersion($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de supprimer la version " . $version->getIdVersion().
                " parce que : " . $sm->modifierVersion($version)['raison']);
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

    /**
     * Supprimer Fichier attaché à une version
     *
     * @Route("/{id}/{filename}/supprimer_fichier", name="version_supprimer_fichier",methods={"GET"} )
     * @Security("is_granted('ROLE_DEMANDEUR')")
     *
     */
    public function supprimerFichierAction(Version $version, string $filename): Response
    {
        $em = $this->em;
        $sm = $this->sm;
        $sv = $this->sv;
        $sj = $this->sj;
        $ac = $this->ac;

        // ACL - Les mêmes que pour supprimer version !
        if ($sm->modifierVersion($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de supprimer des images de cette version " . $version->getIdVersion().
                " parce que : " . $sm->modifierVersion($version)['raison']);
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

        // Seulement en édition demande, ou alors si je suis admin !
        if ($etat == Etat::EDITION_DEMANDE || $ac->isGranted('ROLE_ADMIN'))
        {
            // suppression des fichiers liés à la version
            $sv->effacerFichier($version, $filename);
        }

        return new Response(json_encode("OK $filename"));
    }


    //////////////////////////////////////////////////////////////////////////

    /**
     * Convertit et affiche la version au format pdf
     *
     * @Route("/{id}/pdf", name="version_pdf",methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     */
    public function pdfAction(Version $version, Request $request): Response
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

        $img_expose = [
            $sv->imageProperties('img_expose_1', 'Figure 1', $version),
            $sv->imageProperties('img_expose_2', 'Figure 2', $version),
            $sv->imageProperties('img_expose_3', 'Figure 3', $version),
        ];

        $img_justif_renou = [
            $sv->imageProperties('img_justif_renou_1', 'Figure 1', $version),
            $sv->imageProperties('img_justif_renou_2', 'Figure 2', $version),
            $sv->imageProperties('img_justif_renou_3', 'Figure 3', $version),
        ];
        
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
            'pdf'                => true,
            'version'            => $version,
            'session'            => $session,
            'menu'               => null,
            'img_expose'         => $img_expose,
            'img_justif_renou'   => $img_justif_renou,
            'conso_cpu'          => $sp->getConsoRessource($projet, 'cpu', $version->getAnneeSession()),
            'conso_gpu'          => $sp->getConsoRessource($projet, 'gpu', $version->getAnneeSession()),
            'rapport_1'          => null,
            'rapport'            => null,
            'toomuch'            => $toomuch
            ]
        );

        // NOTE - Pour déboguer la version pdf, décommentez 
        //return $html4pdf;
        
        $pdf = $spdf->setOption('enable-local-file-access', true);
        $pdf = $spdf->getOutputFromHtml($html4pdf->getContent());
        return Functions::pdf($pdf);
    }

    /**
     * Téléchargement de la fiche Projet
     *
     * @Route("/{id}/fiche_pdf", name="version_fiche_pdf",methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("GET")
     */
    public function fichePdfAction(Version $version, Request $request): Response
    {
        $sm   = $this->sm;
        $sj   = $this->sj;
        $spdf = $this->pdf;

        $projet =  $version->getProjet();

        // ACL
        if ($sm->telechargerFiche($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de télécharger la fiche du projet " . $projet .
                " parce que : " . $sm->telechargerFiche($version)['raison']);
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

    /**
     * Téléversement de la fiche projet
     *
     * @Route("/{id}/televerser_fiche", name="version_televerser_fiche",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function televerserFicheAction(Request $request, Version $version): Response
    {
        $em = $this->em;
        $sm = $this->sm;
        $sj = $this->sj;

        // ACL
        if ($sm->televerserFiche($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible de téléverser la fiche de la version " . $version .
                " parce que : " . $sm -> televerserFiche($version)['raison']);
        }

        $rtn = $this->televerser($request, $version, "fiche.pdf");

        // Si on récupère un formulaire on l'affiche
        if (is_a($rtn, 'Symfony\Component\Form\Form'))
        {
            return $this->render(
                'version/televerser_fiche.html.twig',
                [
                    'version' => $version,
                    'form' => $rtn->createView(),
                    'resultat' => null
                ]);
        }

        // Sinon c'est une chaine de caractères en json.
        else
        {
            $resultat = json_decode($rtn, true);

            if ($resultat['OK'])
            {
                $this->modifyFiche($version);
                $request->getSession()->getFlashbag()->add("flash info","La fiche projet a été correctement téléversée");
                return $this->redirectToRoute('consulter_projet', ['id' => $version->getProjet()->getIdProjet() ]);
            }
            else
            {
                $request->getSession()->getFlashbag()->add("flash erreur",strip_tags($resultat['message']));
                return $this->redirectToRoute('version_televerser_fiche', ['id' => $version->getIdVersion() ]);
            }
            return new Response ($rtn);
        }
    }

    private function modifyFiche(Version $version) : void
    {
        $em = $this->em;
        
        // on marque le téléversement de la fiche projet
        $version->setPrjFicheVal(true);
        $em->persist($version);
        $em->flush();
    }

    /**
     * Changer le responsable d'une version.
     *
     * @Route("/{id}/responsable", name="changer_responsable",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function changerResponsableAction(Version $version, Request $request): Response
    {
        $sm = $this->sm;
        $sn = $this->sn;
        $sj = $this->sj;
        $sv = $this->sv;
        $ff = $this->ff;
        $token = $this->tok->getToken();

        // Si changement d'état de la session alors que je suis connecté !
        $request->getSession()->remove('SessionCourante'); // remove cache

        // ACL
        $moi = $token->getUser();

        if ($version == null) {
            $sj->throwException(__METHOD__ .":". __LINE__ .' version null');
        }

        if ($sm->changerResponsable($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ .
                    " impossible de changer de responsable parce que " . $sm->changerResponsable($version)['raison']);
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
                    'class' => Individu::class,
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
     * Modifier les collaborateurs d'une version.
     *
     * @Route("/{id}/collaborateurs", name="modifier_collaborateurs",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function modifierCollaborateursAction(Version $version, Request $request): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $sval= $this->vl;
        $sv = $this->sv;
        $em = $this->em;


        if ($sm->modifierCollaborateurs($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de modifier la liste des collaborateurs de la version " . $version .
                " parce que : " . $sm->modifierCollaborateurs($version)['raison']);
        }

        $text_fields = true;
        if ($this->getParameter('resp_peut_modif_collabs'))
        {
            $text_fields = false;
        }
        $collaborateur_form = $this->ff
                                   ->createNamedBuilder('form_projet', FormType::class, [
                                       'individus' => $sv->prepareCollaborateurs($version, $sj, $sval)
                                   ])
                                   ->add('individus', CollectionType::class, [
                                       'entry_type'   =>  IndividuFormType::class,
                                       'label'        =>  false,
                                       'allow_add'    =>  true,
                                       'allow_delete' =>  true,
                                       'prototype'    =>  true,
                                       'required'     =>  true,
                                       'by_reference' =>  false,
                                       'delete_empty' =>  true,
                                       'attr'         => ['class' => "profil-horiz"],
                                       'entry_options' =>['text_fields' => $text_fields]
                                   ])
                                   ->add('submit', SubmitType::class, [
                                        'label' => 'Sauvegarder',
                                        'attr' => ['title' => "Sauvegarder et revenir au projet"],
                                   ])
                                   ->add('annuler', SubmitType::class, [
                                        'label' => 'Annuler',
                                        'attr' => ['title' => "Annuler et revenir au projet"],
                                   ])
                                   ->getForm();

        $collaborateur_form->handleRequest($request);

        $projet =  $version->getProjet();
        if ($projet != null) {
            $idProjet   =   $projet->getIdProjet();
        } else {
            $sj->errorMessage(__METHOD__ .':' . __LINE__ . " : projet null pour version " . $version->getIdVersion());
            $idProjet   =   null;
        }

        if ($collaborateur_form->isSubmitted() && $collaborateur_form->isValid()) {

            // Annuler ou Sauvegarder ?
            if ($collaborateur_form->get('submit')->isClicked())
            {
                // Un formulaire par individu
                $individu_forms =  $collaborateur_form->getData()['individus'];
                $validated = $sv->validateIndividuForms($individu_forms);
                if (! $validated) {
                    $message = "Pour chaque personne vous <strong>devez renseigner</strong>: email, prénom, nom";
                    $request->getSession()->getFlashbag()->add("flash erreur",$message);
                    return $this->redirectToRoute('modifier_collaborateurs', ['id' => $version ]);
                }
                
                // On traite les formulaires d'individus un par un
                $sv->handleIndividuForms($individu_forms, $version);
            }

            // On retourne à la page du projet
            return $this->redirectToRoute('consulter_version', ['id' => $version->getProjet() ]);

            // return new Response( Functions::show( $resultat ) );
            // return new Response( print_r( $mail, true ) );
            //return new Response( print_r($request->request,true) );

            // TODO - SI ON VIRE ça ON N'A PLUS LES MAILS: POURQUOI ???????????????
            //return $this->redirectToRoute(
            //    'modifierCollaborateurs',
            //    [
            //    'id'    => $version->getIdVersion() ,
           // ]
           // );
        }

        //return new Response( dump( $collaborateur_form->createView() ) );
        return $this->render(
            'version/collaborateurs.html.twig',
            [
             'projet' => $idProjet,
             'collaborateur_form'   => $collaborateur_form->createView(),
             'version'   =>  $version,
             'session'   =>  $version->getSession(),
         ]
        );
    }

    /**
     * Mettre une pénalité sur une version (en GET par ajax)
     *
     * @Route("/{id}/version/{penal}/penalite", name="penal_version",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function penalAction(Version $idversion, $penal): Response
    {
        $data = [];
        $em = $this->em;
        $version = $em->getRepository(Version::class)->findOneBy([ 'idVersion' =>  $idversion]);
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
     * @Route("/{id}/envoyer", name="envoyer_en_expertise",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function envoyerAction(Version $version, Request $request, LoggerInterface $lg): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $ff = $this->ff;
        $se = $this->se;
        $projetWorkflow = $this->pw;

        $em = $this->em;

        if ($sm->envoyerEnExpertise($version)['ok'] == false) {
        $sj->throwException(__METHOD__ . ":" . __LINE__ .
            " impossible d'envoyer en expertise parce que " . $sm->envoyerEnExpertise($version)['raison']);
        }

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
            if ($form->get('annuler')->isClicked())
            {
                $request->getSession()->getFlashbag()->add("flash erreur","Votre projet n'a toujours pas été envoyé en expertise");
                return $this->redirectToRoute('consulter_projet', [ 'id' => $projet->getIdProjet() ]);
            }

            if ($CGU == false && $form->get('envoyer')->isClicked())
            {
                $request->getSession()->getFlashbag()->add("flash erreur","Vous ne pouvez pas envoyer votre projet en expertise si vous n'acceptez pas les CGU");
            }
            elseif ($CGU == true && $form->get('envoyer')->isClicked())
            {
                $version->setCGU(true);
                Functions::sauvegarder($version, $em, $lg);

                // Crée une nouvelle expertise avec proposition d'experts
                $se->newExpertiseIfPossible($version);

                // Avance du workflow
                $rtn = $projetWorkflow->execute(Signal::CLK_VAL_DEM, $projet);

                if ($rtn == true)
                {
                    $request->getSession()->getFlashbag()->add("flash info","Votre projet a été envoyé en expertise. Vous allez recevoir un courriel de confirmation.");
                }
                else
                {
                    $sj->errorMessage(__METHOD__ .  ":" . __LINE__ . " Le projet " . $projet->getIdProjet() . " n'a pas pu etre envoyé à l'expert correctement.");
                    $request->getSession()->getFlashbag()->add("flash erreur","Votre projet n'a pas pu être envoyé en expertise. Merci de vous rapprocher du support");
                }
                return $this->redirectToRoute('projet_accueil');
            }
            else
            {
                $request->getSession()->getFlashbag()->add("flash erreur","Votre projet n'a pas pu être envoyé en expertise. Merci de vous rapprocher du support");
                $sj->throwException(__METHOD__ .":". __LINE__ ." Problème avec le formulaire d'envoi à l'expert du projet " . $version->getIdVersion());
            }
        }

        return $this->render(
            'version/envoyer_en_expertise.html.twig',
            [ 'projet' => $projet,
              'form' => $form->createView(),
              'session' => $session
            ]
        );
    }

    /**
     * Téléversements génériques de rapport d'activité ou de fiche projet
     *
     * @Route("/televersement", name="televersement_generique",methods={"GET","POST"})
     * Method({"POST","GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function televersementGeneriqueAction(Request $request): Response
    {
        $em = $this->em;
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
                $em = $this->em;
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
     * Téléverser un fichier lié à une version (images, document attaché, rapport d'activité)
     *
     * DOIT ETRE APPELE EN AJAX, Sinon ça NE VA PAS MARCHER !
     *      Renvoie normalement une réponse en json
     *
     * @Route("/{id}/fichier/{filename}", name="televerser",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function televerserAction(Request $request, version $version, string $filename): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;

        // ACL - Mêmes ACL que modification de version !
        if ($sm->modifierVersion($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de modifier la version " . $version->getIdVersion().
        " parce que : " . $sm->modifierVersion($version)['raison']);
        }

        $rtn = $this->televerser($request, $version, $filename);
        if (is_a($rtn, 'Symfony\Component\Form\Form'))
        {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " Erreur interne - televerser a renvoyé un Form");
        }
        else
        {
            return new Response($rtn);
        }
    }

    /**********************************************************************
     * Fonction unique pour faire le téléversement, que ce soit en ajax ou pas
     *
     ****************************************************/
    private function televerser(Request $request, version $version, string $filename): Form|string
    {
        $sv = $this->sv;
        $sf = $this->sf;
        
        // SEULEMENT CERTAINS NOMS !!!!
        $valid_filenames = ['document.pdf',
                            'rapport.pdf',
                            'fiche.pdf',
                            'img_expose_1',
                            'img_expose_2',
                            'img_expose_3',
                            'img_justif_renou_1',
                            'img_justif_renou_2',
                            'img_justif_renou_3'];

        if (!in_array($filename, $valid_filenames))
        {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " Erreur interne - $filename pas un nom autorisé");
        }

        // Calcul du répertoire et du type de fichier: dépend du nom de fichier
        $dir = "";
        switch ($filename)
        {
            case "document.pdf":
            case "img_expose_1":
            case "img_expose_2":
            case "img_expose_3":
            case "img_justif_renou_1":
            case "img_justif_renou_2":
            case "img_justif_renou_3":
                $dir = $sv->imageDir($version);
                break;
            case "rapport.pdf":
                $dir = $sv->rapportDir($version);
                break;
            case "fiche.pdf":
                $dir = $sv->getSigneDir($version);
                break;
            default:
                $sj->throwException(__METHOD__ . ":" . __LINE__ . " Erreur interne - $filename - calcul de dir pas possible");
                break;
        }

        $type = substr($filename,-3);   // 'pdf' ou ... n'importe quoi !

        // Seulement deux types supportés = pdf ou jpg
        if ($type != 'pdf')
        {
            $type = 'jpg';
        }

        // Traitement différentié pour un rapport:
        // 1/ On CHANGE $filename
        // 2/ On appelle modifyRapport afin d'écrire un truc dans la base de données
        //    On doit le faire ici car on est en ajax et on appelle la fonction "générique" téléverser...
        //
        // TODO: Pas bien joli tout ça...
        if ($filename === 'rapport.pdf')
        {
            $d = basename($dir); // /a/b/c/d/2022 -> 2022
            $filename = $d . $version->getProjet()->getIdProjet() . ".pdf";
            $rtn = $sv->televerserFichier($request, $version, $dir, $filename, $type);
            $resultat = json_decode($rtn, true);
            if ($resultat['OK'])
            {
                $this->modifyRapport($version->getProjet(), $version->anneeRapport(), $filename);
            }
        }

        // Traitement différentié pour une fiche:
        // 1/ On CHANGE $filename
        // 2/ La fonction modifyFiche sera appelée par le controleur televerserFicheAction
        //
        // TODO: Pas bien joli tout ça...
        elseif ($filename === 'fiche.pdf')
        {
            $filename = $sv ->getSignePath($version);
            $rtn = $sv->televerserFichier($request, $version, $dir, $filename, $type);
        }
        else
        {
            $rtn = $sv->televerserFichier($request, $version, $dir, $filename, $type);
        }
        return $rtn;
    }

    ////////////////////////////////////////////////////////////////////

    private function modifyRapport(Projet $projet, string $annee, string $filename): void
    {
        $em = $this->em;
        $sv = $this->sv;

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

        $size = filesize($sv->rapportDir1($projet, $annee) . '/' .$filename );
        $rapportActivite->setTaille($size);
        $em->persist($rapportActivite);
        $em->flush();
    }

}
