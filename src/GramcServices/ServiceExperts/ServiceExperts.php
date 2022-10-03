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

namespace App\GramcServices\ServiceExperts;

use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Individu;
use App\Entity\CollaborateurVersion;
use App\Entity\Expertise;
use App\Interfaces\Demande;
use App\Form\ChoiceList\ExpertChoiceLoader;

use App\Entity\Thematique;
use App\Entity\Rattachement;
use App\GramcServices\Etat;
use App\Utils\Functions;

use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceNotifications;
use App\GramcServices\PropositionExperts\PropositionExpertsType1;
use App\GramcServices\PropositionExperts\PropositionExpertsType2;

use Psr\Log\LoggerInterface;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormFactoryInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;

/****************************************
 * ExpertsService: cette classe encapsule les algorithmes utilisés par les pages d'affectation
 * des experts (versions, projets tests, rallonges)
 **********************************************************/

class ServiceExperts
{

    protected $notifications = null;
    private $form_buttons = null;
    private $thematiques = null;
    private $rattachements = null;
    private $demandes = null;

    public function __construct(
        protected $max_expertises_nb,
        protected FormFactoryInterface $formFactory,
        protected ServiceNotifications $sn,
        protected ServiceJournal $sj,
        protected PropositionExpertsType1 $pe1,
        protected PropositionExpertsType2 $pe2,
        protected LoggerInterface $lg,
        protected EntityManagerInterface $em
    ) {}

    // Garde en mémoire les demandes
    public function setDemandes($demandes)
    {
        $this->demandes = $demandes;
    }

    // Renvoie le formulaire des boutons principaux
    public function getFormButtons()
    {
        if ($this->form_buttons==null) {
            $this->form_buttons =
                $this->formFactory->createNamedBuilder('BOUTONS', FormType::class, null, ['csrf_protection' => false ])
                     ->add("sub1", SubmitType::class, ['label' => 'Affecter et notifier les experts', 'attr' => ['title' => 'Les experts affectés recevront une notification par courriel'] ])
                     ->add("sub2", SubmitType::class, ['label' => 'Affecter les experts', 'attr' => ['title' => 'Les experts seront affectés mais ne recevront aucune notification'] ])
                     ->add("sub3", SubmitType::class, ['label' => 'Ajouter une expertise', 'attr' => ['title' => 'Ajouter un expert si possible'] ])
                     ->add("sub4", SubmitType::class, ['label' => 'Supp expertise sans expert', 'attr' => ['title' => 'ATTENTION - Risque de perte de données'] ])
                     ->getForm();
        }
        return $this->form_buttons;
    }

    /*********************************************
     * noThematique
     *
     * Supprime les associations individu - thématiques
     * Utilisé lorsqu'un individu n'est plus expert
     * Faire un flush après utilisation de cette fonction !
     * TODO - Intégrer cette fonctionnalité à un controleur !
     *
     **************************************/
    public function noThematique(Individu $individu)
    {
        // Relations ManyToMany
        foreach ($individu->getThematique() as $thematique) {
            $individu->removeThematique($thematique);
        }
        $this->em->persist($individu);

        $all_thematiques = $this->em->getRepository(Thematique::class)->findAll();

        foreach ($all_thematiques as $thematique) {
            $thematique->removeExpert($individu);
            $this->em->persist($thematique);
        }
    }

    /*********************************************
     * getTableauThematiques = Calcule et renvoie le tableau des thématiques,
     * avec pour chacune la liste des experts associés et
     * le nombre de projets affectés à la thématique
     *
     * NOTE - Si un expert a disparu on appelle noThematique puis on fait un flush
     *        cela modifie la BD
     * return: Le tableau des thématiques
     *
     ***************************************************/
    public function getTableauThematiques()
    {
        $em       = $this->em;
        $demandes = $this->demandes;
        if ($this->thematiques==null) {
            // Construction du tableau des thématiques
            $thematiques = [];
            foreach ($em->getRepository(Thematique::class)->findAll() as $thematique) {
                foreach ($thematique->getExpert() as $expert) {
                    if ($expert->getExpert() == false) {
                        $this->sj->warningMessage(__METHOD__ . ':' . __LINE__ . " $expert" . " est supprimé de la thématique " . $thematique);
                        $this->noThematique($expert);
                        $em->flush();
                    }
                }
                $thematiques[ $thematique->getIdThematique() ] =
                    ['thematique' => $thematique, 'experts' => $thematique->getExpert(), 'projets' => 0 ];
            }

            // Remplissage avec le nb de demandes par thématiques
            foreach ($demandes as $demande) {
                $etatDemande    =   $demande->getEtat();
                if ($etatDemande == Etat::EDITION_DEMANDE || $etatDemande == Etat::ANNULE) {
                    continue;
                }

                if ($demande->getPrjThematique() != null) {
                    $thematiques[ $demande->getPrjThematique()->getIdThematique() ]['projets']++;
                }
            }
            $this->thematiques = $thematiques;
        }
        return $this->thematiques;
    }

    /*********************************************
     * getTableauRattachements = Calcule et renvoie le tableau des rattachements,
     * avec pour chacun la liste des experts associés et
     * le nombre de projets affectés au rattachement
     *
     * return: Le tableau des rattachements
     *
     ***************************************************/
    public function getTableauRattachements()
    {
        $em       = $this->em;
        $demandes = $this->demandes;
        if ($this->rattachements==null) {
            // Construction du tableau des thématiques
            $rattachements = [];
            foreach ($em->getRepository(Rattachement::class)->findAll() as $rattachement) {
                foreach ($rattachement->getExpert() as $expert) {
                    if ($expert->getExpert() == false) {
                        $this->sj->warningMessage(__METHOD__ . ':' . __LINE__ . " $expert" . " est supprimé de la thématique pour ce projet" . $rattachement);
                        //Functions::noRattachement($expert);
                        $expert->removeRattachement($rattachement);
                    }
                }
                $rattachements[ $rattachement->getIdRattachement() ] =
                    ['rattachement' => $rattachement, 'experts' => $rattachement->getExpert(), 'projets' => 0 ];
            }

            // Remplissage avec le nb de demandes par thématiques
            foreach ($demandes as $demande) {
                $etatDemande    =   $demande->getEtat();
                if ($etatDemande == Etat::EDITION_DEMANDE || $etatDemande == Etat::ANNULE) {
                    continue;
                }

                if ($demande->getPrjRattachement() != null) {
                    $rattachements[ $demande->getPrjRattachement()->getIdRattachement() ]['projets']++;
                }
            }
            $this->rattachements = $rattachements;
        }
        return $this->rattachements;
    }

    /*********************************************
     * traitementFormulaires
     * Traite les formulaires d'affectation des experts pour les demandes sélectionnées
     * Retourne un bool: Si true, notifications envoyées, si false pas de notifications envoyées
     *
     ********/
    public function traitementFormulaires(Request $request): bool
    {
        $this->clearNotifications();
        $demandes = $this->demandes;

        // Traitements différentiés suivant le bouton sur lequel on a cliqué
        $form_buttons = $this->getFormButtons();
        foreach ($demandes as $demande) {
            $etatDemande    =   $demande->getEtat();
            if ($etatDemande == Etat::EDITION_DEMANDE || $etatDemande == Etat::ANNULE) {
                continue;
            }

            // La demande est-elle sélectionnée ? - Si non on ignore
            $selform = $this->getSelForm($demande);
            $selform->handleRequest($request);
            if ($selform->getData() == null || $selform->getData()['sel']==false) {
                continue;
            }

            // traitement du formulaire d'affectation
            $forms   = $this->getExpertForms($demande);

            $experts_affectes = [];
            foreach ($forms as $f) {
                $f->handleRequest($request);
                $experts_affectes[] = $f->getData()['expert'];
            }

            if ($form_buttons->get('sub2')->isClicked()) {
                $this->affecterExpertsToDemande($experts_affectes, $demande);
            } elseif ($form_buttons->get('sub1')->isClicked()) {
                $this->affecterExpertsToDemande($experts_affectes, $demande);
                $this->addNotification($demande);
            } elseif ($form_buttons->get('sub3')->isClicked()) {
                $this->addExpertiseToDemande($demande);
            } elseif ($form_buttons->get('sub4')->isClicked()) {
                $this->affecterExpertsToDemande($experts_affectes, $demande);
                $this->remExpertiseFromDemande($demande);
            } else {
                continue;
            }
        }

        if ($form_buttons->get('sub1')->isClicked()) {
            $this->notifierExperts();
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Si pas déjà fait, crée une expertise et fait une proposition automatique d'experts pour un projet
     * param = $version
     *
     *********/
    public function newExpertiseIfPossible(Version $version): void
    {
        $pe1 = $this->pe1;
        $pe2 = $this->pe2;
        $lg  = $this->lg;
        $sj = $this->sj;
        $em  = $this->em;

        // S'il y a déjà une expertise on ne fait rien
        // Sinon on la crée et on appelle le programme d'affectation automatique des experts
        if (count($version->getExpertise()) > 0) {
            $sj->noticeMessage(__METHOD__ . ":" . __LINE__ . " Expertise de la version " . $version . " existe déjà");
        } else {
            $expertise = new Expertise();
            $expertise->setVersion($version);

            // Attention, l'algorithme de proposition des experts dépend du type de projet
            $projet = $version->getProjet();
            if ($projet -> getTypeProjet() == Projet::PROJET_TEST || $projet->getTypeProjet() == Projet::PROJET_FIL) {
                $prop_expert = $pe2;
            } else {
                $prop_expert = $pe1;
            }
            $expert      = $prop_expert->getProposition($version);
            if ($expert != null) {
                $expertise->setExpert($expert);
            }
            Functions::sauvegarder($expertise, $em, $lg);
        }
    }

    /**
    * Ajoute une expertise à la demande
    * Si on atteint le paramètre max_expertises_nb, ne fait rien
    * TODO - Si on atteint le paramètre max_expertises_nb, envoyer un message d'erreur !
    *
    * param = $demande
    * Return= rien
    *
    ****/
    private function addExpertiseToDemande($demande)
    {
        $expertises = $demande->getExpertise()->toArray();
        if (count($expertises)< $this->max_expertises_nb) {
            $expertise  =   new Expertise();
            $expertise->setVersion($demande);

            // Attention, l'algorithme de proposition des experts dépend du type de projet
            // TODO Actuellement on ne propose pas d'expertise à ce moment
            //      Il faudra améliorer l'algorithme de proposition
            //$expert = $demande->getProjet()->proposeExpert();
            //if ($expert != null)
            //{
            //	$expertise->setExpert( $expert );
            //}
            Functions::sauvegarder($expertise, $this->em);
        }
    }

    /**
    * Retire les expertises sans experts de la demande, sauf la première
    * car il doit rester au moins une expertise
    *
    * TODO - Plutôt que de ne rien faire, envoyer un message d'erreur !
    *
    * param = $demande
    * Return= rien
    *
    ****/
    private function remExpertiseFromDemande(Demande $demande)
    {
        $expertises = $demande->getExpertise()->toArray();
        $em         = $this->em;

        // On travaille en deux temps pour ne pas supprimer un tableau tout en itérant
        // 1/ Identifier les id d'expertises à supprimer
        // 2/ Les supprimer
        $first = true;
        $to_rem= [];
        foreach ($expertises as $e) {
            if ($first) {
                $first = false;
                continue;
            }
            if ($e->getExpert()==null) {
                $to_rem[]=$e->getid();
            }
        }
        if (count($to_rem)>0) {
            foreach ($to_rem as $e_id) {
                $em->remove($em->getRepository(Expertise::class)->find($e_id));
            }
            $em->flush();
        }
    }

    /**
     * Sauvegarde les experts associés à une demande
     *
     ***/
    protected function affecterExpertsToDemande($experts, Demande $demande)
    {
        $em         = $this->em;
        $expertises = $demande->getExpertise()->toArray();
        usort($expertises, ['self','cmpExpertises']);

        if (count($experts)>1) {
            // On vérifie qu'il n'y a pas deux experts identiques
            // TODO Dans ce cas il faudrait envoyer un message d'erreur !
            // TODO - Trouver un truc plus élégant que ça !
            $id_experts=[];
            $cnt_null = 0;
            foreach ($experts as $e) {
                $id_experts[] = $e==null ? $cnt_null++ : $e->getIdIndividu();
            }
            //$this->sj->debugMessage( __METHOD__ . ' experts uniques -> '.count(array_unique($id_experts)) .'  experts -> '.count($id_experts));
            if (count(array_unique($id_experts)) != count($id_experts)) {
                return;
            }
        }

        foreach ($expertises as $e) {
            $e->setExpert(array_shift($experts));
            $em->persist($e);
        }
        // Je n'utilise pas Functions::sauvegarder car je sauvegarde plusieurs objets à la fois !
        $em->flush();
    }

    /*************************************************************************
     * getExpertsForms
     * Génère les formulaires d'affectation des experts pour chaque demande
     *
     * return:  Un tableau de formulaire, indexé par l'id de la demande
     *
     ****************************************************************************/
    public function getExpertsForms()
    {
        $demandes = $this->demandes;
        $forms    = [];
        foreach ($demandes as $demande) {
            $etatDemande    =   $demande->getEtat();

            // Pas de formulaire sauf pour ces états
            if ($etatDemande != Etat::EDITION_EXPERTISE && $etatDemande != Etat::EXPERTISE_TEST) {
                continue;
            }

            $exp = $demande->getExperts();

            // Formulaire pour la sélection (case à cocher)
            $sform = $this->getSelForm($demande)->createView();
            $forms['selection_'.$demande->getId()] = $sform;

            // Génération des formulaires de choix de l'expert
            $eforms  = $this->getExpertForms($demande);
            foreach ($eforms as &$f) {
                $f=$f->createView();
            }
            $forms[$demande->getId()] = $eforms;
        }
        if (count($forms) > 0) {
            $forms['BOUTONS'] = $this->getFormButtons()->createView();
        }

        return $forms;
    }

    /*************************************************************************
     * getStats
     * Génère différentes statistiques sur les attributions
     *
     * return:  les stats
     *
     ****************************************************************************/
    public function getStats()
    {
        $nbProjets      = 0;
        $nouveau        = 0;
        $renouvellement = 0;
        $sansexperts    = 0;
        $nbDemHeures    = 0;
        $nbAttHeures    = 0;

        $demandes       = $this->demandes;
        $experts_assoc  = [];
        foreach ($demandes as $demande) {
            $etatDemande = $demande->getEtat();

            // Pas de choix d'expert pour ces états de demandes
            if ($etatDemande == Etat::EDITION_DEMANDE || $etatDemande == Etat::ANNULE) {
                continue;
            }

            $exp = $demande->getExperts();
            if (count($exp)==0) {
                $sansexperts++;
            } else {
                foreach ($exp as $e) {
                    if ($e==null) {
                        continue;
                    }
                    if (! isset($experts_assoc[$e->getIdIndividu()])) {
                        $experts_assoc[$e->getIdIndividu()] = ['expert' => $e, 'projets' => 0 ];
                    }
                    $experts_assoc[$e->getIdIndividu()]['projets']++;
                }
            }

            $nbProjets++;

            $nbDemHeures += $demande->getDemHeures();
            $nbAttHeures += $demande->getAttrHeures();
        }
        $stats = ["nbProjets"      => $nbProjets,
                  "nouveau"        => $nouveau,
                  "renouvellement" => $renouvellement,
                  "sansexperts"    => $sansexperts,
                  "nbDemHeures"    => $nbDemHeures,
                  "nbAttHeures"    => $nbAttHeures];
        return $stats;
    }

    /*************************************************************************
     * getAttHeures
     * Renvoie un tableau avec le nombre d'heures attribuées, pour affichage
     *
     * return:  Un tableau indexé par l'id de la demande
     *
     ****************************************************************************/
    public function getAttHeures()
    {
        $attHeures = [];
        $demandes  = $this->demandes;
        foreach ($demandes as $demande) {
            $etatDemande    =   $demande->getEtat();
            if ($etatDemande == Etat::EDITION_DEMANDE || $etatDemande == Etat::ANNULE) {
                continue;
            }
        }
        return $attHeures;
    }

    ///////////////////////
    private static function cmpExperts($a, $b)
    {
        return ($a["expert"]->getNom()<=$b["expert"]->getNom()) ? -1 : 1;
    }

    private static function cmpExpertises($a, $b)
    {
        return $a->getId() > $b ->getId();
    }

    /***********
     * getTableauExperts Renvoie un tableau permettant d'afficher le nombre de projets affectés à chaque expert
     *
     * return Un tableau de tableaux.
     *        Pour chaque entrée: [ 'expert' => $e, 'projets' => $nb ]
     *
     ***********************************************/
    public function getTableauExperts()
    {
        $demandes = $this->demandes;
        $experts_assoc = [];
        foreach ($demandes as $demande) {
            // Pas de choix d'expert pour ces états de demandes
            $etat_demande = $demande->getEtat();
            if ($etat_demande == Etat::EDITION_DEMANDE || $etat_demande == Etat::ANNULE) {
                continue;
            }

            $exp = $demande->getExperts();
            foreach ($exp as $e) {
                if ($e==null) {
                    continue;
                }
                if (! isset($experts_assoc[$e->getIdIndividu()])) {
                    $experts_assoc[$e->getIdIndividu()] = ['expert' => $e, 'projets' => 0 ];
                }
                $experts_assoc[$e->getIdIndividu()]['projets']++;
            }
        }

        // Mise en forme du tableau experts, pour avoir l'ordre alphabétique !
        $experts = [];
        foreach ($experts_assoc as $k => $e) {
            if ($e['projets'] > 0) {
                $experts[] = $e;
            }
        }
        usort($experts, "self::cmpExperts");

        return $experts;
    }

    /***
     * Renvoie un formulaire avec une case à cocher, rien d'autre
     *
     *   params  $demande (pour calculer le nom du formulaire)
     *   return  une form
     *
     */
    private function getSelForm(Demande $demande)
    {
        $nom = 'selection_'.$demande->getId();
        $formBuilder = $this->formFactory->createNamedBuilder($nom, FormType::class, null, ['csrf_protection' => false]);
        $formBuilder->add('sel', CheckboxType::class, [ 'required' =>  false, 'attr' => ['class' => "expsel"]  ]);
        return $formBuilder->getForm();
    }

    /***
     * Renvoie un tableau de formulaires de choix d'experts
     *
     *   params  $demande (pour calculer le nom des formulaires)
     *   return  un tableau de forms
     *
     */

    protected function getExpertForms(Demande $demande)
    {
        $em    = $this->em;
        $forms = [];
        $expertises = $demande->getExpertise()->toArray();
        usort($expertises, ['self','cmpExpertises']);

        // Liste d'exclusion = Les collaborateurs + les experts choisis par ailleurs
        $exclus = $em->getRepository(CollaborateurVersion::class)->getCollaborateurs($demande->getProjet());
        $experts= [];
        foreach ($expertises as $expertise) {
            $expert = $expertise->getExpert();
            if ($expert != null) {
                $exclus[$expert->getId()] = $expert;
            }
        }

        $first = true;
        foreach ($expertises as $expertise) {
            // L'expert actuel (peut-être null)
            $expert = $expertise->getExpert();

            // La liste d'exclusion pour cette expertise
            $exclus_exp = $exclus;

            // On vire l'expert actuel de la liste d'exclusion
            if ($expert != null) {
                unset($exclus_exp[$expert->getId()]);
            }

            // Nom du formulaire
            $nom = 'expert'.$demande->getProjet()->getIdProjet().'-'.$expertise->getId();

            //if ($demande->getIdVersion()=="20A200044")	$this->sj->debugMessage("koukou $nom ".$expert->getId());
            //$this->sj->debugMessage(__METHOD__ . "Experts exclus pour $demande ".Functions::show( $exclus));

            // Projets de type Projet::PROJET_FIL -> La première expertise est obligatoirement faite par un président !
            if ($first && $demande->getProjet()->getTypeProjet() == Projet::PROJET_FIL) {
                $choice = new ExpertChoiceLoader($em, $exclus_exp, true);
            } else {
                $choice = new ExpertChoiceLoader($em, $exclus_exp);
            }

            $forms[] = $this->formFactory->createNamedBuilder($nom, FormType::class, null, ['csrf_protection' => false ])
                            ->add(
                                'expert',
                                ChoiceType::class,
                                [
                                'multiple'  =>  false,
                                'required'  =>  false,
                                //'choices'       => $choices, // cela ne marche pas à cause d'un bogue de symfony
                                'choice_loader' => $choice, // nécessaire pour contourner le bogue de symfony
                                'data'          => $expert,
                                //'choice_value' => function (Individu $entity = null) { return $entity->getIdIndividu(); },
                                'choice_label'  => function ($individu) {
                                    return $individu->__toString();
                                },
                                ]
                            )
                            ->getForm();
            // Ne pas proposer plusieurs fois le même expert !
            //$choice = null;
            //if ($expert != null) $exclus[$expert->getId()] = $expert;
            $first = false;
        }
        return $forms;
    }

    /******
     * Efface le tableau notifications
     *****/
    protected function clearNotifications()
    {
        $this->notifications = [];
    }

    /******
     * Ajoute des données dans le tableau notifications
     *
     * notifications = tableau associatif
     *                 clé = $expert
     *                 val = Liste de $demandes
     *
     * params $demande La demande (=version) correspondante
     *****/
    protected function addNotification($demande)
    {
        //$notifications = $this    -> notifications;
        $expertises    = $demande -> getExpertise();
        foreach ($expertises as $e) {
            $exp_mail = $e -> getExpert() -> getMail();
            if (!array_key_exists($exp_mail, $this->notifications)) {
                $this->notifications[$exp_mail] = [];
            }
            $this->notifications[$exp_mail][] = $demande;
        }
    }

    /******
    * Appelée quand on clique sur Notifier les experts
    * Envoie une notification aux experts du tableau notifications
    *
    *****/
    protected function notifierExperts()
    {
        $notifications = $this->notifications;

        $this->sj->debugMessage(__METHOD__ . count($notifications) . " notifications à envoyer");

        foreach ($notifications as $e => $liste_d) {
            $dest   = [ $e ];
            $params = [ 'object' => $liste_d ];
            //$this->sj->debugMessage( __METHOD__ . "Envoi d'un message à " . join(',',$dest) . " - " . Functions::show($liste_d) );

            $this->sn->sendMessage(
                'notification/affectation_expert_version-sujet.html.twig',
                'notification/affectation_expert_version-contenu.html.twig',
                $params,
                $dest
            );
        }
    }
}
