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
use App\Entity\Thematique;
use App\Entity\CollaborateurVersion;
use App\Entity\RapportActivite;
use App\Entity\Rattachement;
use App\Entity\Formation;

use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServiceVersions;
use App\GramcServices\ServiceForms;
use App\GramcServices\Workflow\Projet\ProjetWorkflow;

use App\Utils\Functions;
use App\GramcServices\Etat;
use App\GramcServices\Signal;
use App\Form\IndividuForm\IndividuForm;
use App\Form\IndividuFormType;
use App\Repository\FormationRepository;
use App\Validator\Constraints\PagesNumber;

use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Doctrine\ORM\EntityManagerInterface;

use Twig\Environment;

/**
 * Version controller.
 *
 * Les méthodes liées aux versions mais SPECIFIQUES à un mésocentre particulier
 *
 *
 * @Route("version")
 */
class VersionSpecController extends AbstractController
{
    public function __construct(
        private ServiceJournal $sj,
        private ServiceMenus $sm,
        private ServiceSessions $ss,
        private ServiceVersions $sv,
        private ServiceForms $sf,
        private ProjetWorkflow $pw,
        private FormFactoryInterface $ff,
        private ValidatorInterface $vl,
        private LoggerInterface $lg,
        private Environment $tw,
        private EntityManagerInterface $em
    ) {}

    /**
     * Appelé par le bouton Envoyer à l'expert: si la demande est incomplète
     * on envoie un écran pour la compléter. Sinon on passe à envoyer à l'expert
     * TODO - Un héritage ou un interface ici car tous les VersionSpecController ont le même code !
     *
     * @Route("/{id}/avant_modifier", name="avant_modifier_version",methods={"GET","POST"})
     * Method({"GET", "POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function avantModifierVersionAction(Request $request, Version $version): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $vl = $this->vl;
        $em = $this->em;

        // ACL
        if ($sm->modifierVersion($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de modifier la version " . $version->getIdVersion().
                " parce que : " . $sm->modifierVersion($version)['raison']);
        }
        if ($this->versionValidate($version) != []) {
            return $this->render(
                'version/avant_modifier.html.twig',
                [
                'version'   => $version
                ]);
        }
        else {
            return $this->redirectToRoute('envoyer_en_expertise', [ 'id' => $version->getIdVersion() ]);
        }
    }

    /**
     * Modification d'une version existante
     *
     *      1/ D'abord une partie générique (images, collaborateurs)
     *      2/ Ensuite on appelle modifierTypeX, car le formulaire dépend du type de projet
     *
     * @Route("/{id}/modifier", name="modifier_version",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * 
     */
    public function modifierAction(Request $request, Version $version): Response
    {
        $sm = $this->sm;
        $sv = $this->sv;
        $sj = $this->sj;
        $twig = $this->tw;
        $html = [];
        
        // ACL
        if ($sm->modifierVersion($version)['ok'] == false) {
            $sj->throwException(__METHOD__ . ":" . __LINE__ . " impossible de modifier la version " . $version->getIdVersion().
        " parce que : " . $sm->modifierVersion($version)['raison']);
        }

        // ON A CLIQUE SUR ANNULER
        // version est sauvegardée autrement et je ne sais pas pourquoi
        $form = $this->createFormBuilder(new Version())->add('annuler', SubmitType::class)->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->get('annuler')->isClicked()) {
            $sj->debugMessage(__METHOD__ .':'. __LINE__ . ' annuler clicked');
            return $this->redirectToRoute('consulter_projet', ['id' => $version->getProjet()->getIdProjet() ]);
        }

        // FORMULAIRE DES COLLABORATEURS
        $collaborateur_form = $sv->getCollaborateurForm($version);
        $collaborateur_form->handleRequest($request);
        $data   =   $collaborateur_form->getData();

        $individu_forms = $collaborateur_form->getData()['individus'];
        $validated = $sv->validateIndividuForms($individu_forms);
        if (! $validated) {
            $message = "Pour chaque personne vous <strong>devez renseigner</strong>: email, prénom, nom";
            $request->getSession()->getFlashbag()->add("flash erreur",$message);
        //    return $this->redirectToRoute('modifier_collaborateurs', ['id' => $version ]);
        }
        else
        {
            if ($data != null && array_key_exists('individus', $data)) {
                $sj->debugMessage('modifierAction traitement des collaborateurs');
                $sv->handleIndividuForms($data['individus'], $version);
    
                // ACTUCE : le mail est disabled en HTML et en cas de POST il est annulé
                // nous devons donc refaire le formulaire pour récupérer ces mails
                $collaborateur_form = $sv->getCollaborateurForm($version);
            }
        }

        // DES FORMULAIRES QUI DEPENDENT DU TYPE DE PROJET
        $type = $version->getProjet()->getTypeProjet();
        switch ($type) {
            case Projet::PROJET_SESS:
            return $this->modifierType1($request, $version, $collaborateur_form);
    
            case Projet::PROJET_TEST:
            return $this->modifierType3($request, $version, $collaborateur_form);
    
            case Projet::PROJET_FIL:
            return $this->modifierType3($request, $version, $collaborateur_form);
    
            default:
               $sj->throwException(__METHOD__ . ":" . __LINE__ . " mauvais type de projet " . Functions::show($type));
        }
    }

    /*
     * Appelée par modifierAction pour les projets de type 1 (PROJET_SESS)
     *
     * params = $request, $version
     *          $image_forms (formulaire de téléversement d'images)
     *          $collaborateurs_form (formulaire des collaborateurs)
     *
     */
    private function modifierType1(Request $request,
                                   Version $version,
                                   FormInterface $collaborateur_form
                                   ): Response
    {
        $sj = $this->sj;
        $sv = $this->sv;
        $ss = $this->ss;
        $sval = $this->vl;
        $em = $this->em;

        // formulaire principal
        $form_builder = $this->createFormBuilder($version);
        $this->modifierType1PartieI($version, $form_builder);
        $this->modifierType1PartieII($version, $form_builder);
        $this->modifierType1PartieIII($version, $form_builder);
        if ($this->getParameter('nodata')==false) {
            $this->modifierType1PartieIV($version, $form_builder);
        }
        $nb_form = 0;
        $this->modifierType1PartieV($version, $form_builder, $nb_form);

        $form_builder
            ->add('fermer', SubmitType::class)
            ->add( 'enregistrer',   SubmitType::Class )
            ->add('annuler', SubmitType::class);

        $form = $form_builder->getForm();
        $form->handleRequest($request);

        // traitement du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('annuler')->isClicked()) {
                // on ne devrait jamais y arriver !
                $sj->errorMessage(__METHOD__ . ' seconde annuler clicked !');
                return $this->redirectToRoute('projet_accueil');
            }

            // Changement de type ou plafonnement de la demande en heures !
            $this->validDemHeures($version);

            // on sauvegarde le projet
            $return = Functions::sauvegarder($version, $em, $this->lg);

            if ($request->isXmlHttpRequest()) {
                $sj->debugMessage(__METHOD__ . ' isXmlHttpRequest clicked');
                if ($return == true) {
                    return new Response(json_encode('OK - Votre projet est correctement enregistré'));
                } else {
                    return new Response(json_encode("ERREUR - Votre projet n'a PAS été enregistré !"));
                }
            }
            return $this->redirectToRoute('consulter_projet', ['id' => $version->getProjet()->getIdProjet() ]);
        }

        $img_expose = [$sv->imageProperties('img_expose_1', 'Figure 1', $version),
                       $sv->imageProperties('img_expose_2', 'Figure 2', $version),
                       $sv->imageProperties('img_expose_3', 'Figure 3', $version)];

        $img_justif_renou = [$sv->imageProperties('img_justif_renou_1', 'Figure 1', $version),
                             $sv->imageProperties('img_justif_renou_2', 'Figure 2', $version),
                             $sv->imageProperties('img_justif_renou_3', 'Figure 3', $version)];

        $session = $ss -> getSessionCourante();

        return $this->render(
            'version/modifier_projet_sess.html.twig',
            [
                'session' => $session,
                'form'      => $form->createView(),
                'version'   => $version,
                'img_expose' => $img_expose,
                'img_justif_renou' => $img_justif_renou,
                'collaborateur_form' => $collaborateur_form->createView(),
                'todo'          => static::versionValidate($version, $sj, $em, $sval, $this->getParameter('nodata')),
                'nb_form'       => $nb_form
            ]
        );
    }

    /*
     * Appelée par modifierType1
     *         Si on est en type 3, on plafonne le nombre d'heures
     *         NB - On ne fait pas de flush, c'est l'appelant qui s'en chargera.
     *
     * params = $version
     * return = true si OK, false changement de type de projet ou heures plafonnées !
     *
     */
    private function validDemHeures($version): bool
    {
        $em = $this->em;
        //$ss = $this->ss;
        $projet = $version->getProjet();
        $type = $projet->getTypeProjet();
        $seuil = intval($this->getParameter('prj_seuil_sess'));
        $demande = $version->getDemHeures();
        $rvl = true;

        if ($type == Projet::PROJET_FIL) {
            if ($demande > $seuil) {
                $version -> setDemHeures($seuil);
                $rvl = false;
            }
        }
        return $rvl;
    }

    /* Les champs de la partie I */
    private function modifierType1PartieI($version, &$form): void
    {
        $em = $this->em;
        $form
        ->add('prjTitre', TextType::class, [ 'required'       =>  false ])
        ->add(
            'prjThematique',
            EntityType::class,
            [
            'required'    => false,
            'multiple'    => false,
            'class'       => Thematique::class,
            'label'       => '',
            'placeholder' => '-- Indiquez la thématique',
            ]
        )
        ->add('prjSousThematique', TextType::class, [ 'required'       =>  false ]);

        if ($this->getParameter('norattachement')==false) {
            $form
            ->add(
                'prjRattachement',
                EntityType::class,
                [
                'required'    => false,
                'multiple'    => false,
                'expanded'    => true,
                'class'       => Rattachement::class,
                'empty_data'  => null,
                'label'       => '',
                'placeholder' => 'AUCUN',
                ]
            );
        };
        $form
            ->add('demHeures', IntegerType::class, [ 'required'       => false,'attr' => ['min' => $this->getParameter('prj_heures_min')]])
            ->add('prjFinancement', TextType::class, [ 'required'     => false ])
            ->add('prjGenciCentre', TextType::class, [ 'required' => false ])
            ->add('prjGenciMachines', TextType::class, [ 'required' => false ])
            ->add('prjGenciHeures', TextType::class, [ 'required' => false ])
            ->add('prjGenciDari', TextType::class, [ 'required'   => false ]);

        /* Pour un renouvellement, ajouter la justification du renouvellement */
        if (count($version->getProjet()->getVersion()) > 1) {
            $form = $form->add('prjJustifRenouv', TextAreaType::class, [ 'required' => false ]);
        }
    }

    /* Les champs de la partie II */
    private function modifierType1PartieII($version, &$form): void
    {
        $form
        ->add('prjResume', TextAreaType::class, [ 'required'       =>  false ])
        ->add('prjExpose', TextAreaType::class, [ 'required'       =>  false ])
        ->add('prjAlgorithme', TextAreaType::class, [ 'required'       =>  false ]);
    }

    /* Les champs de la partie III */
    private function modifierType1PartieIII($version, &$form): void
    {
        $form
        ->add('prjConception', CheckboxType::class, [ 'required'       =>  false ])
        ->add('prjDeveloppement', CheckboxType::class, [ 'required'       =>  false ])
        ->add('prjParallelisation', CheckboxType::class, [ 'required'       =>  false ])
        ->add('prjUtilisation', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeNom', TextType::class, [ 'required'       =>  false ])
        ->add('codeFor', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeC', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeCpp', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeAutre', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeLangage', TextType::class, [ 'required'       =>  false ])
        ->add('codeLicence', TextAreaType::class, [ 'required'       =>  false ])
        ->add('codeUtilSurMach', TextAreaType::class, [ 'required'       =>  false ])
        ->add(
            'codeHeuresPJob',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "< 6000 heures" => "< 6000 heures",
                    "< 18000 heures" => "< 18000 heures",
                    "< 72000 heures" => "< 72000 heures",
                    "> 72000 heures" => "> 72000 heures",
                    "Je ne sais pas" => "je ne sais pas",
                    ],
            ]
        )
        ->add(
            'gpu',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "Oui" => "Oui",
                    "Non" => "Non",
                    "Je ne sais pas" => "je ne sais pas",
                    ],
            ]
        )
        ->add(
            'codeRamPCoeur',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "< 5Go" => "< 5Go",
                    "> 5Go" => "> 5Go",
                    "Je ne sais pas" => "je ne sais pas",
                    ],
            ]
        )
        ->add(
            'codeRamPart',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "< 192Go" => "< 192Go",
                    "> 192Go" => "> 192Go",
                    "< 500Go" => "< 500Go",
                    "< 1To" => "< 1To",
                    "> 2To" => "> 2To",
                    "Je ne sais pas" => "je ne sais pas",
                    ],
            ]
        )
        ->add(
            'codeEffParal',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "< 36" => "< 36",
                    "36-360" => "36-360",
                    "> 360" => "> 360",
                    "< 1008" => "< 1008",
                    "> 1008" => "> 1008",
                    "Je ne sais pas" => "je ne sais pas",
                    ],
            ]
        )
        ->add(
            'codeVolDonnTmp',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "< 10Go" => "< 10Go",
                    "< 100Go" => "< 100Go",
                    "< 1To" => "< 1To",
                    "< 10To" => "< 10To",
                    "> 10To" => "> 10To",
                    "Je ne sais pas" => "je ne sais pas",
                    ],
            ]
        )
        ->add('demLogiciels', TextAreaType::class, [ 'required'       =>  false ])
        ->add('demBib', TextAreaType::class, [ 'required'       =>  false ])
        ->add(
            'demPostTrait',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "Oui" => "Oui",
                    "Non" => "Non",
                    "Je ne sais pas" => "je ne sais pas",
                    ],
            ]
        );
    }

    /* Les champs de la partie IV */
    private function modifierType1PartieIV($version, &$form): void
    {
        $form
        ->add(
            'sondVolDonnPerm',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "< 1To" => "< 1To",
                    "1 To" => "1 To",
                    "2 To" => "2 To",
                    "3 To" => "3 To",
                    "4 To" => "4 To",
                    "5 To" => "5 To",
                    "10 To" => "10 To",
                    "25 To" => "25 To",
                    "50 To" => "50 To",
                    "75 To" => "75 To",
                    "100 To" => "100 To",
                    "500 To" => "500 To",
                    "je ne sais pas" => "je ne sais pas",
                    ],
            ]
        )
        ->add('sondJustifDonnPerm', TextAreaType::class, [ 'required'       =>  false ])
        ->add(
            'dataMetadataFormat',
            ChoiceType::class,
            [
            'label' => 'Format de métadonnées',
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "IVOA" => "IVOA",
                    "OGC" => "OGC",
                    "Dublin Core" => "DC",
                    "Autre" => "Autre",
                    "Je ne sais pas" => "je ne sais pas",
                    "je ne suis pas intéressé.e" => "pas intéressé.e",
                    ],
            ]
        )
                 ->add(
                     'dataNombreDatasets',
                     ChoiceType::class,
                     [
            'label' => 'Estimation du nombre de datasets à partager',
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "< 10 datasets" => "< 10 datasets",
                    "< 100 datasets" => "< 100 datasets",
                    "< 1000 datasets" => "< 1000 datasets",
                    "> 1000 datasets" => "> 1000 datasets",
                    "Je ne sais pas" => "je ne sais pas",
                    "je ne suis pas intéressé.e" => "pas intéressé.e",
                    ],
            ]
                 )
                ->add(
                    'dataTailleDatasets',
                    ChoiceType::class,
                    [
            'label' => 'Taille moyenne approximative pour un dataset',
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                    "< 100 Mo" => "<100 Mo",
                    "< 500 Mo" => "< 500 Mo",
                    "> 1 Go" => ">1 Go",
                    "Je ne sais pas" => "je ne sais pas",
                                    "je ne suis pas intéressé.e" => "pas intéressé.e"
                    ],
            ]
            );
    }

    /* Les champs de la partie V */
    private function modifierType1PartieV($version, &$form, &$nb_form)
    {
        $em = $this->em;
        $formations = $em->getRepository(\App\Entity\Formation::class)->getFormationsPourVersion();

        $nb_form = 0;
        foreach ($formations as  $f) {
            $champ = 'demForm'.$f->getNumeroForm();
            $label = $f->getNomForm();
            $form->add($champ, IntegerType::class, [ 'required' => false, 'label' => $label ]);
            $nb_form++;
        }
        $form->add('demFormAutresAutres', TextAreaType::class, [ 'required' => false, 'label' => 'Vous pouvez préciser ici vos souhaits']);
    }

    /*
     * Appelée par modifierAction pour les projets de type 3 (PROJET_FIL => PROJET_TEST)
     *
     * params = $request, $version
     *          $image_forms (formulaire de téléversement d'images)
     *          $collaborateurs_form (formulaire des collaborateurs)
     *
     */
    private function modifierType3( Request $request,
                                    Version $version,
                                    FormInterface $collaborateur_form
                                    ): Response
    {
        $sj = $this->sj;
        $sval = $this->vl;
        $em = $this->em;

        $heures_projet_test = $this->getParameter('heures_projet_test');
        
        $form_builder = $this->createFormBuilder($version)
        ->add('prjTitre', TextType::class, [ 'required'       =>  false ])
        ->add(
            'prjThematique',
            EntityType::class,
            [
                'required'       =>  false,
                'multiple' => false,
                'class' => Thematique::class,
                'label'     => '',
                'placeholder' => '-- Indiquez la thématique',
                ]
        );
        if ($this->getParameter('norattachement')==false) {
            $form_builder->add(
                'prjRattachement',
                EntityType::class,
                [
                    'required'    => false,
                    'multiple'    => false,
                    'expanded'    => true,
                    'class'       => Rattachement::class,
                    'empty_data'  => null,
                    'label'       => '',
                    'placeholder' => 'AUCUN',
                ]
            );
        }
        $form_builder->add(
            'demHeures',
            IntegerType::class,
            [
                'required'       =>  false,
                'data' => $heures_projet_test,
                'disabled' => 'disabled'
            ]
        )
        //$form_builder
        //->add('demHeures', IntegerType::class, ['required' => false ])
        ->add('prjResume', TextAreaType::class, [ 'required'       =>  false ])
        ->add('codeNom', TextType::class, [ 'required'       =>  false ])
        ->add('codeFor', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeC', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeCpp', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeAutre', CheckboxType::class, [ 'required'       =>  false ])
        ->add('codeLangage', TextType::class, [ 'required'       =>  false ])
        ->add('codeLicence', TextAreaType::class, [ 'required'       =>  false ])
        ->add('codeUtilSurMach', TextAreaType::class, [ 'required'       =>  false ])
        ->add('demLogiciels', TextAreaType::class, [ 'required'       =>  false ])
        ->add('demBib', TextAreaType::class, [ 'required'       =>  false ])
        ->add(
            'gpu',
            ChoiceType::class,
            [
            'required'       =>  false,
            'placeholder'   =>  "-- Choisissez une option",
            'choices'  =>   [
                            "Oui" => "Oui",
                            "Non" => "Non",
                            "Je ne sais pas" => "je ne sais pas",
                            ],
            ]
        )
        ->add('fermer', SubmitType::class)
        ->add('annuler', SubmitType::class)
        ->add('enregistrer', SubmitType::class);

        $form = $form_builder->getForm();
        $form->handleRequest($request);
        $valid = true;

        if ($form->isSubmitted() && $form->isValid())
        {
            $version->setDemHeures($heures_projet_test);

            // TODO - Le mécanisme warn_type n'est PLUS UTILISE
            // Changement de type ou plafonnement de la demande en heures !
            $valid = $this->validDemHeures($version);

            $return = Functions::sauvegarder($version, $em, $this->lg);

            // On sauvegarde $warn_type dans la SESSION
            $request->getSession()->set('warn_type',$valid ? 0:1);
            return $this->redirectToRoute('consulter_projet', ['id' => $version->getProjet()->getIdProjet()]);
        }

        // Les heures de projets tests sont fixes, donc pas dans le formulaire
        $version->setDemHeures($heures_projet_test);
        
        return $this->render(
            'version/modifier_projet_test.html.twig',
            [
                'form'      => $form->createView(),
                'version'   => $version,
                'collaborateur_form' => $collaborateur_form->createView(),
                'todo'      => static::versionValidate($version, $sj, $em, $sval),
            ]
        );
    }


    /**
     * Demande de partage stockage ou partage des données
     *
     * @Route("/{id}/donnees", name="donnees",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method({"GET", "POST"})
     */
    public function donneesAction(Request $request, Version $version): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;

        if ($sm->donnees($version)['ok'] == false) {
            $sj->throwException("VersionController:donneesAction Bouton donnees inactif " . $version->getIdVersion());
        }

        /* Si le bouton modifier est actif, on doit impérativement passer par là ! */
        $modifierVersion_menu = $sm->modifierVersion($version);
        if ($modifierVersion_menu['ok'] == true) {
            return $this->redirectToRoute($modifier_version_menu['name'], ['id' => $version, '_fragment' => 'tab4']);
        }

        $form = $this->createFormBuilder($version);
        $this->modifierType1PartieIV($version, $form);
        $form
            ->add('valider', SubmitType::class)
            ->add('annuler', SubmitType::class);
        $form = $form->getForm();
        $projet =  $version->getProjet();
        if ($projet != null) {
            $idProjet   =   $projet->getIdProjet();
        } else {
            $sj->errorMessage(__METHOD__ .':' . __LINE__ . " : projet null pour version " . $version->getIdVersion());
            $idProjet   =   null;
        }

        // Pour traiter le retour d'une validation du formulaire
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('valider')->isClicked()) {
                //$sj->debugMessage("Entree dans le traitement du formulaire données");
                //$this->handleCallistoForms( $form, $version );
                $em = $this->em;
                $em->persist($version);
                $em->flush();
            }
            return $this->redirectToRoute('consulter_projet', ['id' => $projet->getIdProjet() ]);
        }
        /*
        if ($callisto_form->get('valider')->isClicked()) {
            static::handleCallistoForms( $callisto_form, $version );
        }
        */
        // Affichage du formulaire
        return $this->render(
            'version/donnees.html.twig',
            [
//            'usecase' => $usecase,
//            'session'   =>  $version->getSession(),
              'projet' => $projet,
//            'version'    => $version,
            'form'       => $form->createView(),
        ]
        );
    }

    ////////// Recupère et traite le retour du formulaire
    ////////// lié à l'écran données
    private function handleDonneesForms($form, Version $version): void
    {
        $version->setDataMetaDataFormat($form->get('dataMetadataFormat')->getData());
        $version->setDataNombreDatasets($form->get('dataNombreDatasets')->getData());
        $version->setDataTailleDatasets($form->get('dataTailleDatasets')->getData());
        $em = $this->em;
        $em->persist($version);
        $em->flush();
    }

    /**
     * Displays a form to edit an existing version entity.
     *
     * @Route("/{id}/renouveler", name="renouveler_version",methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method({"GET", "POST"})
     */
    public function renouvellementAction(Request $request, Version $version): Response
    {
        $sm = $this->sm;
        $sv = $this->sv;
        $sj = $this->sj;
        $projet_workflow = $this->pw;
        $em = $this->em;

        // ACL
        if ($sm->renouvelerVersion($version)['ok'] == false) {
            $sj->throwException("VersionController:renouvellementAction impossible de renouveler la version " . $version->getIdVersion());
        }

        $session = $em->getRepository(Session::class)->findOneBy([ 'etatSession' => Etat::EDITION_DEMANDE ]);
        $this->get('session')->remove('SessionCourante');
        if ($session != null) {
            $idVersion = $session->getIdSession() . $version->getProjet()->getIdProjet();
            if ($em->getRepository(Version::class)->findOneBy([ 'idVersion' =>  $idVersion]) != null) {
                $sj->errorMessage("VersionController:renouvellementAction version " . $idVersion . " existe déjà !");
                return $this->redirect($this->generateUrl('modifier_version', [ 'id' => $version->getIdVersion() ]));
            } else {
                $old_dir = $sv->imageDir($version);
                // nouvelle version
                $projet = $version->getProjet();
                //$em->detach( $version );
                $new_version = clone $version;
                //$em->detach( $new_version );

                $new_version->setSession($session);

                // Mise à zéro de certains champs
                $new_version->setDemHeures(0);
                $new_version->setPrjJustifRenouv(null);
                $new_version->setAttrHeures(0);
                $new_version->setAttrHeuresEte(0);
                $new_version->setAttrAccept(false);
                $new_version->setPenalHeures(0);
                $new_version->setPrjGenciCentre('');
                $new_version->setPrjGenciDari('');
                $new_version->setPrjGenciHeures('');
                $new_version->setPrjGenciMachines('');
                $new_version->setPrjFicheVal(false);
                $new_version->setPrjFicheLen(0);
                $new_version->setRapConf(0);
                $new_version->setCgu(0);

                $new_version->setIdVersion($session->getIdSession() . $version->getProjet()->getIdProjet());
                $new_version->setProjet($version->getProjet());
                $new_version->setEtatVersion(Etat::CREE_ATTENTE);
                $sv->setLaboResponsable($new_version, $version->getResponsable());

                // nouvelles collaborateurVersions
                Functions::sauvegarder($new_version, $em, $this->lg);

                $collaborateurVersions = $version->getCollaborateurVersion();
                foreach ($collaborateurVersions as $collaborateurVersion) {
                    
                    // ne pas reprendre un collaborateur sans login et marqué comme supprimé
                    // Attention un collaborateurVersion avec login = false mais loginname renseigné signifie ue le compte
                    // n'a pas encore été détruit: dans ce cas on le reprends !'
                    if ($collaborateurVersion->getDeleted() &&
                        $collaborateurVersion->getClogin() === false &&
                        $collaborateurVersion->getLoginname() === null ) continue;

                    $newCollaborateurVersion    = clone  $collaborateurVersion;
                    //$em->detach( $newCollaborateurVersion );
                    $newCollaborateurVersion->setVersion($new_version);
                    $em->persist($newCollaborateurVersion);
                }

                //On ne fait rien car ce sera fait dans l'EventListener !
                // $projet->setVersionDerniere( $new_version );
                $projet_workflow->execute(Signal::CLK_DEMANDE, $projet);

                // Remettre à false Nepasterminer qui n'a pas trop de sens ici
                $projet->setNepasterminer(false);
                $em->persist($projet);
                $em->flush();

                // images: On reprend les images "img_expose" de la version précédente
                //         On ne REPREND PAS les images "img_justif_renou" !!!
                $new_dir = $sv->imageDir($new_version);
                for ($id=1;$id<4;$id++) {
                    $f='img_expose_'.$id;
                    $old_f = $old_dir . '/' . $f;
                    $new_f = $new_dir . '/' . $f;
                    if (is_file($old_f)) {
                        $rvl = copy($old_f, $new_f);
                        if ($rvl==false) {
                            $sj->errorMessage("VersionController:erreur dans la fonction copy $old_f => $new_f");
                        }
                    }
                }
                return $this->redirect($this->generateUrl('modifier_version', [ 'id' => $new_version->getIdVersion() ]));
            }
        } else {
            $sj->errorMessage("VersionController:renouvellementAction il n'y a pas de session en état EDITION_DEMANDE !");
            return $this->redirect($this->generateUrl('modifier_version', [ 'id' => $version->getIdVersion() ]));
        }
    }

    /**
     * Validation du formulaire de version
     *
     *    param = Version
     *            $em l'entity manager
     *    return= Un array contenant la "todo liste", ie la liste de choses à faire pour que le formulaire soit validé
     *            Un array vide [] signifie: "Formulaire validé"
     *
     **/
    private function versionValidate(Version $version): array
    {
        $sv = $this->sv;
        $em   = $this->em;
        $nodata = $this->getParameter('nodata');

        $todo   =   [];
        if ($version->getPrjTitre() == null) {
            $todo[] = 'prj_titre';
        }
        if ($version->getDemHeures() == null) {
            $todo[] = 'dem_heures';
        }
        if ($version->getPrjThematique() == null) {
            $todo[] = 'prj_id_thematique';
        }
        if ($version->getPrjResume() == null) {
            $todo[] = 'prj_resume';
        }
        if ($version->getCodeNom() == null) {
            $todo[] = 'code_nom';
        }
        if ($version->getCodeLicence() == null) {
            $todo[] = 'code_licence';
        }
        if ($version->getGpu() == null) {
            $todo[] = 'gpu';
        }

        // TODO - Automatiser cela avec le formulaire !
        if ($version->getProjet()->getTypeProjet()==Projet::PROJET_SESS) {
            if ($version->getPrjExpose() == null) {
                $todo[] = 'prj_expose';
            }
            if ($version->getCodeHeuresPJob() == null) {
                $todo[] = 'code_heures_p_job';
            }
            if ($version->getCodeRamPCoeur() == null) {
                $todo[] = 'code_ram_p_coeur';
            }
            if ($version->getCodeRamPart() == null) {
                $todo[] = 'code_ram_part';
            }

            if ($version->getCodeEffParal() == null) {
                $todo[] = 'code_eff_paral';
            }
            if ($version->getCodeVolDonnTmp() == null) {
                $todo[] = 'code_vol_donn_tmp';
            }
            if ($version->getDemPostTrait() == null) {
                $todo[] = 'dem_post_trait';
            }

            // s'il s'agit d'un renouvellement
            if (count($version->getProjet()->getVersion()) > 1 && $version->getPrjJustifRenouv() == null) {
                $todo[] = 'prj_justif_renouv';
            }

            // Centres nationaux
            if ($version->getPrjGenciCentre()     == null
                || $version->getPrjGenciMachines() == null
                || $version->getPrjGenciHeures()   == null
                || $version->getPrjGenciDari()     == null) {
                $todo[] = 'genci';
            };

            // Partage de données
            if ($nodata == false) {		// Stockage de données
                if ($version->getSondVolDonnPerm() == null) {
                    $todo[] = 'sond_vol_donn_perm';
                } elseif ($version->getSondJustifDonnPerm() == null
                &&  $version->getSondVolDonnPerm() != '< 1To'
                &&  $version->getSondVolDonnPerm() != '1 To'
                &&  $version->getSondVolDonnPerm() !=  'je ne sais pas') {
                    $todo[] = 'sond_justif_donn_perm';
                }
    
                if ($version->getDataMetaDataFormat() == null) {
                    $todo[] = 'Format de métadonnées';
                }
                if ($version->getDataNombreDatasets() == null) {
                    $todo[] = 'Nombre de jeux de données';
                }
                if ($version->getDataTailleDatasets() == null) {
                    $todo[] = 'Taille de chaque jeu de données';
                }
            }
           
            if ($version->typeSession()  == 'A' ) {
                $version_precedente = $version->versionPrecedente();
                if ($version_precedente != null) {
                    $rapportActivite = $em->getRepository(RapportActivite::class)->findOneBy(
                        [
                        'projet' => $version_precedente->getProjet(),
                        'annee' => $version_precedente->getAnneeSession(),
                    ]
                    );
                    if ($rapportActivite == null) {
                        $todo[] = 'rapport_activite';
                    }
                }
            }
        }

        // Validation des formulaires des collaborateurs
        if (! $sv->validateIndividuForms($sv->prepareCollaborateurs($version), true)) {
            $todo[] = 'collabs';
        }

        return $todo;
    }

}
