<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;

use App\Form\IndividuType;
use App\Entity\Individu;
use App\Entity\Scalar;
use App\Entity\Sso;
use App\Entity\Journal;
use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Session;

use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceNotifications;
use App\GramcServices\ServiceProjets;

use App\Utils\Functions;
use App\Utils\Menu;
use App\Utils\Etat;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Form\FormFactoryInterface;

/////////////////////////////////////////////////////

/**
 * Mail controller.
 *
 * @Route("mail")
 * @Security("is_granted('ROLE_ADMIN')")
 */
class MailController extends AbstractController
{
    public function __construct(
        private ServiceNotifications $sn,
        private ServiceJournal $sj,
        private ServiceProjets $sp,
        private FormFactoryInterface $ff
    ) {}

    /**
     * @Route("/{id}/mail_to_responsables_fiche",name="mail_to_responsables_fiche", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_PRESIDENT')")
     * Method({"GET", "POST"})
    **/

    public function mailToResponsablesFicheAction(Request $request, Session $session)
    {
        $em = $this->getDoctrine()->getManager();
        $sn = $this->sn;
        $sj = $this->sj;
        $ff = $this->ff;

        $nb_msg = 0;
        $sujet   = \file_get_contents(__DIR__."/../../templates/notification/mail_to_responsables_fiche-sujet.html.twig");
        $body    = \file_get_contents(__DIR__."/../../templates/notification/mail_to_responsables_fiche-contenu.html.twig");
        $sent    =   false;
        $responsables   =   $this->getResponsablesFiche($session);

        $form   =  Functions::createFormBuilder($ff)
                    ->add('texte', TextareaType::class, [
                        'label' => " ",
                        'data' => $body,
                        'attr' => ['rows'=>10,'cols'=>150]])
                    ->add('submit', SubmitType::class, ['label' => "Envoyer le message"])
                    ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sent   = true;
            $body   = $form->getData()['texte'];

            foreach ($responsables as $item) {
                $individus[ $item['responsable']->getIdIndividu() ] = $item['responsable'];
                $selform = $this->getSelForm($item['responsable']);
                $selform->handleRequest($request);
                if ($selform->getData()['sel']==false) {
                    //$sj->debugMessage( __METHOD__ . $version->getIdVersion().' selection NON');
                    continue;
                }
                $sn->sendMessageFromString(
                    $sujet,
                    $body,
                    [ 'session' => $session, 'projets' => $item['projets'], 'responsable' => $item['responsable'] ],
                    [$item['responsable']]
                );
                $nb_msg++;
                // DEBUG = Envoi d'un seul message
                 // break;
            }
        }

        return $this->render(
            'mail/mail_to_responsables_fiche.html.twig',
            [
            'sent'         => $sent,
            'nb_msg'       => $nb_msg,
            'responsables' => $responsables,
            'session'      => $session,
            'form'         => $form->createView(),
            ]
        );
    }

    ////////////////////////////////////////////////////////////////////////
    /***********************************************************
     *
     * Renvoie la liste des responsables de projet (et des projets) qui n'ont pas (encore)
     * téléversé leur fiche projet signée pour la session $session
     *
     ************************************************************/
    private function getResponsablesFiche(Session $session)
    {
        $sj = $this->sj;
        $em = $this->getDoctrine()->getManager();
        $responsables = [];

        $all_versions = $em->getRepository(Version::class)->findBy(['session' => $session, 'prjFicheVal' => false]);

        foreach ($all_versions as $version) {
            $projet = $version->getProjet();
            if ($projet == null) {
                $sj->errorMessage(__METHOD__ . ':'. __LINE__ . " version " . $version . " n'a pas de projet !");
                continue;
            }

            if ($version->getEtatVersion() != Etat::ACTIF && $version->getEtatVersion() != Etat::EN_ATTENTE) {
                continue;
            }

            $responsable    =  $version->getResponsable();

            if ($responsable != null) {
                $responsables[$responsable->getIdIndividu()]['selform']                         = $this->getSelForm($responsable)->createView();
                $responsables[$responsable->getIdIndividu()]['responsable']                     = $responsable;
                $responsables[$responsable->getIdIndividu()]['projets'][$projet->getIdProjet()] = $projet;
            } else {
                $sj->errorMessage(__METHOD__ . ':'. __LINE__ . " version " . $version . " n'a pas de responsable !");
            }
        }

        return $responsables;
    }

    /**
     * @Route("/{id}/mail_to_responsables",name="mail_to_responsables", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_PRESIDENT')")
     * Method({"GET", "POST"})
    **/
    public function mailToResponsablesAction(Request $request, Session $session)
    {
        $em = $this->getDoctrine()->getManager();
        $sn = $this->sn;
        $sj = $this->sj;
        $ff = $this->ff;

        $nb_msg = 0;
        $nb_projets = 0;

        // On lit directement les templates pour laisser à l'admin la possibilité de les modifier !
        $sujet   = \file_get_contents(__DIR__."/../../templates/notification/mail_to_responsables-sujet.html.twig");
        $body    = \file_get_contents(__DIR__."/../../templates/notification/mail_to_responsables-contenu.html.twig");

        $sent    =   false;
        $responsables   =   $this->getResponsables($session);
        $form   =  Functions::createFormBuilder($ff)
                    ->add('texte', TextareaType::class, [
                        'label' => " ",
                        'data' => $body,
                        'attr' => ['rows'=>10,'cols'=>150]])
                    ->add('submit', SubmitType::class, ['label' => "Envoyer le message"])
                    ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sent   = true;
            $body   = $form->getData()['texte'];

            foreach ($responsables as $item) {
                $individus[ $item['responsable']->getIdIndividu() ] = $item['responsable'];
                $selform = $this->getSelForm($item['responsable']);
                $selform->handleRequest($request);
                if (empty($selform->getData()['sel'])) {
                    //$sj->debugMessage( __METHOD__ . $version->getIdVersion().' selection NON');
                    continue;
                }
                $sn->sendMessageFromString(
                    $sujet,
                    $body,
                    [ 'session' => $session,
                      'projets' => $item['projets'],
                      'responsable' => $item['responsable'] ],
                    [$item['responsable']]
                );
                $nb_msg++;
                $nb_projets += count($item['projets']);
                // DEBUG = Envoi d'un seul message
                 // break;
            }
        }

        return $this->render(
            'mail/mail_to_responsables.html.twig',
            [
                'sent'          => $sent,
                'nb_msg'        => $nb_msg,
                'responsables'  => $responsables,
                'nb_projets'    => $nb_projets,
                'session'       => $session,
                'form'          => $form->createView(),
            ]
        );
    }


    /***********************************************************
     *
     * Renvoie la liste des responsables de projet (et des projets) qui n'ont pas (encore)
     * renouvelé leur projet pour la session $session
     *
     ************************************************************/
    private function getResponsables(Session $session)
    {
        $sp = $this->sp;
        $sj = $this->sj;
        $em = $this->getDoctrine()->getManager();

        $type_session = $session->getLibelleTypeSession();
        if ($type_session =='B') {
            $annee = $session->getAnneeSession();
        } else {
            $annee = $session->getAnneeSession() - 1;
        }

        $responsables = [];
        $projets = [];
        $all_projets = $em->getRepository(Projet::class)->findAll();

        foreach ($all_projets as $projet) {
            if ($projet->isProjetTest()) {
                continue;
            }
            if ($projet->getEtatProjet() == Etat::TERMINE ||  $projet->getEtatProjet() == Etat::ANNULE) {
                continue;
            }
            $derniereVersion    =  $projet->derniereVersion();
            if ($derniereVersion != null
            && $derniereVersion->getSession() != null
            && $derniereVersion->getSession()->getAnneeSession() == $annee
            &&  ($derniereVersion->getEtatVersion() == Etat::ACTIF || $derniereVersion->getEtatVersion() == Etat::TERMINE)
            ) {
                if ($type_session == 'A') {
                    $responsable    =  $derniereVersion->getResponsable();
                    if ($responsable != null) {
                        $ind = $responsable->getIdIndividu();
                        $responsables[$ind]['selform']                         = $this->getSelForm($responsable)->createView();
                        $responsables[$ind]['responsable']                     = $responsable;
                        $responsables[$ind]['projets'][$projet->getIdProjet()] = $projet;
                        if (!isset($responsables[$ind]['max_attr'])) {
                            $responsables[$ind]['max_attr'] = 0;
                        }
                        $attr = $projet->getVersionActive()->getAttrHeures();
                        if ($attr>$responsables[$ind]['max_attr']) {
                            $responsables[$ind]['max_attr']=$attr;
                        }
                    }
                }

                # Session de type B = On ne s'intéresse qu'aux projets qui ont une forte consommation
                else
                {
                    if ($derniereVersion->getSession()->getLibelleTypeSession() == 'B') {
                        continue;
                    }
                    $conso = $sp->getConsoCalculVersion($derniereVersion);

                    if ($derniereVersion->getAttrHeures() > 0) {
                        $rapport = $conso / $derniereVersion->getAttrHeures() * 100;
                    } else {
                        $rapport = 0;
                    }

                    if ($rapport > $this->getParameter('conso_seuil_1')) {
                        $responsable = $derniereVersion->getResponsable();
                        if ($responsable != null) {
                            $ind = $responsable->getIdIndividu();
                            $responsables[$ind]['selform'] = $this->getSelForm($responsable)->createView();
                            $responsables[$ind]['responsable'] = $responsable;
                            $responsables[$ind]['projets'][$projet->getIdProjet()] = $projet;
                            if (!isset($responsables[$ind]['max_attr'])) {
                                $responsables[$ind]['max_attr'] = 0;
                            }
                            $attr = $projet->getVersionActive()->getAttrHeuresTotal();
                            if ($attr>$responsables[$ind]['max_attr']) {
                                $responsables[$ind]['max_attr'] = $attr;
                            }
                        }
                    }
                }
            }
        }

        // On trie $responsables en commençant par les responsables qui on le plus d'heures attribuées !
        usort($responsables, "self::compAttr");
        return $responsables;
    }

    // Pour le tri des responsables en commençant par celui qui a la plus grosse (attribution)
    private static function compAttr($a, $b)
    {
        if ($a['max_attr']==$b['max_attr']) {
            return 0;
        }
        return ($a['max_attr'] > $b['max_attr']) ? -1 : 1;
    }

    /***
     * Renvoie un formulaire avec une case à cocher, rien d'autre
     *
     *   params  $individu
     *   return  une form
     *
     */
    private function getSelForm(Individu $individu)
    {
        $nom = 'selection_'.$individu->getId();
        return $this->get('form.factory')  -> createNamedBuilder($nom, FormType::class, null, ['csrf_protection' => false ])
                                            -> add('sel', CheckboxType::class, [ 'required' =>  false, 'label' => " " ])
                                            ->getForm();
    }
}
