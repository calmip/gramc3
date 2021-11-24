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

use Symfony\Component\Routing\Annotation\Route;
//use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;

use App\Form\IndividuType;
use App\Entity\Individu;
use App\Entity\Scalar;
use App\Entity\Sso;
use App\Entity\CompteActivation;
use App\Entity\Journal;

use App\Utils\Functions;
use App\Utils\IDP;

use App\GramcServices\Workflow\Projet\ProjetWorkflow;
use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceNotifications;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServiceVersions;
use App\GramcServices\PropositionExperts\PropositionExpertsType1;
use App\GramcServices\PropositionExperts\PropositionExpertsType2;
use App\GramcServices\GramcDate;
use App\Security\User\UserChecker;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;


use Symfony\Component\Mailer\Mailer;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Twig\Environment;

//function redirection_externe($url)
//{
//    $controller = new Controller();
//    return $controller->redirect($url);
//}


/////////////////////////////////////////////////////

class GramcSessionController extends AbstractController
{
    private $sn;
    private $sj;
    private $sm;
    private $sp;
    private $ss;
    private $pe1;
    private $pe2;
    private $sd;
    private $sv;
    private $pw;
    private $ff;
    private $vl;
    private $ts;
    private $tok;
    private $sss;
    private $uc;
    private $ac;
    private $tw;


    public function __construct(
        ServiceNotifications $sn,
        ServiceJournal $sj,
        ServiceMenus $sm,
        ServiceProjets $sp,
        ServiceSessions $ss,
        PropositionExpertsType1 $pe1,
        PropositionExpertsType2 $pe2,
        GramcDate $sd,
        ServiceVersions $sv,
        ProjetWorkflow $pw,
        FormFactoryInterface $ff,
        ValidatorInterface $vl,
        TokenStorageInterface $ts,
        SessionInterface $sss,
        UserChecker $uc,
        AuthorizationCheckerInterface $ac,
        Environment $tw
    ) {
        $this->sn  = $sn;
        $this->sj  = $sj;
        $this->sm  = $sm;
        $this->sp  = $sp;
        $this->ss  = $ss;
        $this->pe1 = $pe1;
        $this->pe2 = $pe2;
        $this->sd  = $sd;
        $this->sv  = $sv;
        $this->pw  = $pw;
        $this->ff  = $ff;
        $this->vl  = $vl;
        $this->ts  = $ts;
        $this->sss = $sss;
        $this->uc  = $uc;
        $this->ac  = $ac;
        $this->tw  = $tw;
    }

    /**
     * @Route("/admin/accueil",name="admin_accueil", methods={"GET"})
     * @Security("is_granted('ROLE_OBS') or is_granted('ROLE_PRESIDENT')")
    **/

    public function adminAccueilAction()
    {
        $sm      = $this->sm;
        $menu1[] = $sm->individu_gerer();

        $menu2[] = $sm->gerer_sessions();
        $menu2[] = $sm->bilan_session();
        $menu2[] = $sm->mailToResponsables();
        $menu2[] = $sm->mailToResponsablesFiche();

        $menu3[] = $sm->projet_session();
        $menu3[] = $sm->projet_annee();
        $menu3[] = $sm->projet_tous();
        if ($this->getParameter('nodata')==false) {
            $menu3[] = $sm->projet_donnees();
        }
        $menu3[] = $sm->televersement_generique();

        if ($this->getParameter('norattachement')==false) {
            $menu4[] = $sm->rattachements();
        }
        $menu4[] = $sm->thematiques();
        $menu4[] = $sm->metathematiques();
        $menu4[] = $sm->laboratoires();
        $menu4[] = $sm->formations();

        $menu5[] = $sm->bilan_annuel();
        $menu5[] = $sm->statistiques();
        $menu5[] = $sm->publications();

        $menu6[] = $sm->connexions();
        $menu6[] = $sm->journal();
        if ($this->getParameter('kernel.debug')) {
            $menu6[] = $sm->avancer();
        }
        $menu6[] = $sm->info();
        $menu6[] = $sm->nettoyer();

        return $this->render('default/accueil_admin.html.twig', ['menu1' => $menu1,
                                                                'menu2' => $menu2,
                                                                'menu3' => $menu3,
                                                                'menu4' => $menu4,
                                                                'menu5' => $menu5,
                                                                'menu6' => $menu6 ]);
    }

    /**
     * @Route("/mentions_legales", name="mentions_legales", methods={"GET"} )
     */
    public function mentionsAction()
    {
        return $this->render('default/mentions.html.twig');
    }

    /**
    * @Route("/aide", name="aide", methods={"GET"} )
    */
    public function aideAction()
    {
        return $this->render('default/aide.html.twig');
    }

    /**
    * @Route("/", name="accueil", methods={"GET"} )
    *
    */
    public function accueilAction()
    {
        $sm     = $this->sm;
        $ss     = $this->ss;
        $session= $ss->getSessionCourante();

        // Lors de l'installation, aucune session n'existe: redirection
        // vers l'écran de création de session, le seul qui fonctionne !
        if ($session == null) {
            if ($this->ac->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('gerer_sessions');
            }
            return $this->redirectToRoute('projet_accueil');
        }

        // Si true, cet utilisateur n'est ni expert ni admin ni président !
        $seulement_demandeur=true;

        $menu   = [];
        $m = $sm->demandeur();
        if ($m['ok'] == false) {
            // Même pas demandeur !
            $seulement_demandeur = false;
        }
        $menu[] = $m;

        $m = $sm->expert();
        if ($m['ok']) {
            $seulement_demandeur = false;
        }
        $menu[] = $m;

        $m = $sm->administrateur();
        if ($m['ok']) {
            $seulement_demandeur = false;
        }
        $menu[] = $m;

        $m = $sm->president();
        if ($m['ok']) {
            $seulement_demandeur = false;
        }
        $menu[] = $m;
        $menu[] = $sm->aide();

        if ($seulement_demandeur) {
            return $this->redirectToRoute('projet_accueil');
        } else {
            // juin 2021 -> Suppression des projets tests
            return $this->render(
                'default/accueil.html.twig',
                ['menu' => $menu,
         //'projet_test' => $sm->nouveau_projet_test()['ok'],
         'projet_test' => false,
         'session' => $session ]
            );
        }
    }

    /**
     * @Route("/president", name="president_accueil", methods={"GET"} )
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function presidentAccueilAction()
    {
        $sm     = $this->sm;
        $menu[] = $sm->affectation();
        
        if ($this->getParameter('noedition_expertise')==false) {
            $menu[] = $sm->commSess();
        }
        $menu[] = $sm->affectation_rallonges();
        /* $menu[] = $sm->affectation_test(); */
        return $this->render('default/president.html.twig', ['menu' => $menu]);
    }

    /**
     * @Route("/deconnexion",name="deconnexion", methods={"GET"})
     **/
    public function deconnexionAction(Request $request)
    {
        $sj    = $this->sj;
        $ac    = $this->ac;
        $token = $this->ts->getToken();
        $sss   = $this->sss;

        if ($ac->isGranted('ROLE_PREVIOUS_ADMIN')) {
            $sudo_url = $sss->get('sudo_url');
            //$sj->debugMessage(__METHOD__ . " sudo_url = " . $sudo_url );
            $userChecker = $this->uc;
            $real_user   = $sss->get('real_user');
            $userChecker->checkPostAuth($real_user);
            $sj->infoMessage(__METHOD__ . ":" . __LINE__ . " déconnexion d'un utilisateur en SUDO vers " . $real_user);
            return new RedirectResponse($sudo_url . '?_switch_user=_exit');
        //return $this->redirectToRoute('accueil',[ '_switch_user' => '_exit' ]);
        } elseif ($ac->isGranted('IS_AUTHENTICATED_FULLY')) {
            $sj->infoMessage(__METHOD__ . ":" . __LINE__ .  " déconnexion de l'utilisateur " . $token->getUser());
            $request->getSession()->invalidate();
            session_destroy();
        }
        return $this->redirectToRoute('deconnected');
    }


    /**
    * @Route("/deconnected", name="deconnected", methods={"GET"})
    **/
    public function deconnexion_showAction(Request $request)
    {
        return $this->render('default/deconnexion.html.twig');
    }

    /**
    * @Route("/profil",name="profil", methods={"GET","POST"})
    * @Security("is_granted('ROLE_DEMANDEUR')")

    **/
    public function profilAction(Request $request)
    {
        $sj = $this->sj;

        $individu = $this->ts->getToken()->getUser();

        if ($individu == 'anon.' || ! ($individu instanceof Individu)) {
            return $this->redirectToRoute('accueil');
        }
        $old_individu = clone $individu;
        $form = $this->createForm(IndividuType::class, $individu, ['mail' => false ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($old_individu->isPermanent() != $individu->isPermanent() && $individu->isPermanent() == false) {
                $sj->warningMessage(__METHOD__ . ':' . __LINE__ . " " . $individu . " cesse d'être permanent !!");
            }

            if ($old_individu->isFromLaboRegional() != $individu->isFromLaboRegional() && $individu->isFromLaboRegional() == false) {
                $sj->warningMessage(__METHOD__ . ':' . __LINE__ . " " . $individu . " cesse d'être d'un labo regional !!");
            }

            $new_statut = $individu->getStatut();
            $old_statut = $old_individu->getStatut();
            if ($new_statut != $old_statut) {
                $sj->noticeMessage(__METHOD__ . ':' . __LINE__ . " " . $individu . " a changé son statut de " . $old_statut
                . " vers " . $new_statut);
            }

            $new_laboratoire = $individu->getLabo();
            $old_laboratoire = $old_individu->getLabo();
            if ($new_laboratoire != $old_laboratoire) {
                $sj->noticeMessage(__METHOD__ . ':' . __LINE__ . " " . $individu . " a changé son laboratoire de " . $old_laboratoire
                . " vers " . $new_laboratoire);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($individu);
            $em->flush();
            return $this->redirectToRoute('accueil');
        } else {
            return $this->render('default/profil.html.twig', ['form' => $form->createView() ]);
        }
    }

    /**
     *
     * Connexion en debug (c-a-d pas d'authentification
     *
     * @Route("/connexion_dbg",name="connexion_dbg", methods={"GET","POST"})
     **/
    public function connectiondbgAction(Request $request)
    {
        $sj         = $this->sj;
        $token      = $this->ts->getToken();
        $em         = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(Individu::class);

        // Bizarre...
        // echo "coucou " . (int) $this->has('kernel.debug');
        // echo "coucou " . (int) $this->getParameter('kernel.debug');
        if ($this->getParameter('kernel.debug') === false) {
            //if ( ! $this->container->hasParameter('kernel.debug') || $this->getParameter('kernel.debug') == false )
            $sj->warningMessage(__METHOD__ . ':' . __LINE__ .' tentative de se connecter avec debug en production');
            return $this->redirectToRoute('accueil');
        }

        $u = new Individu();
        //$mail = $user->getMail();

        $experts    = $repository->findBy(['expert'   => true ]);
        $admins     = $repository->findBy(['admin'    => true ]);
        $obs        = $repository->findby(['obs'      => true ]);
        $sysadmins  = $repository->findby(['sysadmin' => true ]);
        $responsables   = static::elements($repository->getCollaborateurs(true));

        $moi            = $token->getUser();
        $collaborateurs = static::elements($repository->getCollaborateurs(false, false, $moi));
        $users          = array_unique(array_merge($admins, $experts, $obs, $sysadmins, $responsables, $collaborateurs));
        sort($users);

        $form = $this->createFormBuilder($u)
            ->add(
                'mail',
                EntityType::class,
                [
                    'multiple' => false,
                    'placeholder' => 'Choisissez',
                    'class' => 'App:Individu',
                    'choices' => $users,
                    //'choice_label' => function($user){ return $user->getPrenom() . ' ' . $user->getNom(); }
                ]
            )
            ->add('save', SubmitType::class, ['label' => 'Connexion'])
            ->add('reset', ResetType::class, ['label' => 'Effacer'])
            ->getForm();

        $form->handleRequest($request);

        //if ($form->get('save')->isClicked() )
        //    {
        //    $m = $user->getMail();
        //    }

        if ($form->isSubmitted()) {
            // TODO - Particulièrement laid et incompréhensible !
            //        php-stan (level -2) renvoie une erreur et pourtant ça marche
            $user  = $repository->findOneByMail($u->getMail()->getMail());
            $roles = $user->getRoles();
            $token = new UsernamePasswordToken($user, null, 'main', $roles);

            //$userChecker = new UserChecker();
            $userChecker = $this->uc;
            $userChecker->checkPreAuth($user);

            $session = $request->getSession();
            $this->ts->setToken($token);
            $session->set('_security_main', serialize($token));

            $userChecker->checkPostAuth($user);
            $sj->infoMessage(__METHOD__ . ":" . __LINE__ . " connexion DBG de l'utilisateur " . $user);

            if ($request->getSession()->has('url')) {
                return $this->redirect($request->getSession()->get('url'));
            } else {
                return $this->redirectToRoute('accueil');
            }
        }

        return $this->render('default/connexion_dbg.html.twig', [ 'form' => $form->createView() ]);
    }

    /**
    * @Route("/login/activation",name="activation", methods={"GET"})
    * @Route("/login/activation/{key}", methods={"GET"})
    **/

    public function activationAction(Request $request, $key)
    {
        $em = $this->getDoctrine()->getManager();
        $sn = $this->sn;
        $sj = $this->sj;

        $server = $request->server;
        if ($server->has('REMOTE_USER') || $server->has('REDIRECT_REMOTE_USER')) {
            $eppn = "";
            if ($server->has('REMOTE_USER')) {
                $eppn =  $server->get('REMOTE_USER');
            }
            if ($server->has('REDIRECT_REMOTE_USER')) {
                $eppn =  $server->get('REDIRECT_REMOTE_USER');
            }

            $em = $this->getDoctrine()->getManager();

            $compteactivation = $this->getDoctrine()
                ->getRepository(CompteActivation::class)
                ->findOneBy(['key' => $key ]);

            if (!  $compteactivation) {
                return new Response('<pre> Activation error for this key </pre>');
            }

            $sso = new Sso();
            $sso->setEppn($eppn);
            $individu = $compteactivation->getIndividu();
            $sso->setIndividu($individu);

            $em->remove($compteactivation);

            if ($em->getRepository(Sso::class)->findOneBy([ 'eppn' => $eppn ]) == null) {
                $em->persist($sso);
            } else {
                $sj->noticeMessage(__FILE__ . ":" . __LINE__ . "  " . $eppn . " existe déjà");
            }

            $em->flush();

            // Envoyer un mail de bienvenue à ce nouvel utilisateur
            $dest   = [ $individu->getMail() ];
            $etab   = preg_replace('/.*@/', '', $eppn);
            $sn->sendMessage(
                "notification/compte_ouvert-sujet.html.twig",
                "notification/compte_ouvert-contenu.html.twig",
                [ 'individu' => $individu, 'etab' => $etab ],
                $dest
            );

            return $this->redirectToRoute('connexion');
        } else {
            return new Response('<pre> Activation error - no eppn </pre>');
        }
    }


    /**
     * @Route("/login_choice", name="connexion", methods={"GET","POST"})
     *
     * Method({"GET", "POST"})
     */

    public function loginAction(Request $request)
    {
        $sj = $this->sj;
        $ff = $this->ff;

        $form = Functions::createFormBuilder($ff)
                ->add(
                    'data',
                    ChoiceType::class,
                    [
                 'choices' => $this->getParameter('IDPprod')
                 ]
                )
            ->add('connect', SubmitType::class, ['label' => 'Connexion'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $url    =   $request->getSchemeAndHttpHost();
            $url    .= '/Shibboleth.sso/Login?target=';
            //$url    .=   $this->generateUrl('connexionshiblogin');
            //$url    .= '/gramce-milos/login';
            $url    .= $this->generateUrl('connexionshiblogin');

            //$url = $this->generateUrl('connexionshib', [] , UrlGeneratorInterface::ABSOLUTE_URL);
            //$url = $url .  $this->generateUrl('accueil', [] , UrlGeneratorInterface::ABSOLUTE_URL);


            if ($form->getData()['data'] != 'WAYF') {
                $url = $url . '&providerId=' . $form->getData()['data'];
            }

            $sj->debugMessage(__FILE__. ":" . __LINE__ . " URL shiblogin = " . $url);

            return $this->redirect($url);
        }

        return $this->render(
            'default/login.html.twig',
            [ 'form' => $form->createView(), ]
        );
    }


    /**
     * @Route("/login", name="shiblogin", methods={"GET"})
     * Method({"GET"})
     */
    /*public function shibloginAction(Request $request)
    {
        //return new Response($request->server->get('REDIRECT_mail'));
        //return new Response(print_r($request->server,true) );

        $sj->infoMessage("shiblogin d'un utilisateur");

        if( $request->getSession()->has('url') )
            return $this->redirect( $request->getSession()->get('url') );
        else
            return $this->redirectToRoute('index');
    }*/




    /**
     * @Route("/login/connexion", name="connexionshiblogin", methods={"GET"})
     * @Route("/connexion", methods={"GET"})
     */
    public function auth_connexionAction(Request $request)
    {
        $sj = $this->sj;
        $ac = $this->ac;

        $sj->infoMessage("shiblogin d'un utilisateur");
        $em = $this->getDoctrine()->getManager();


        // PAS BO
        //$myfile = fopen("/tmp/testfile.txt", "w");
        //fwrite($myfile, print_r($request->headers->keys(),true));
        //fclose($myfile);

        //$sj->debugMessage("Shib infos = ".$request->headers->get('affiliation').' '.$request->headers->get('eppn').' ');
        $server = $request->server;
        if (($username = getenv('REMOTE_USER')) || $server->has('REMOTE_USER') || $server->has('REDIRECT_REMOTE_USER')) {
            if ($server->has('REMOTE_USER')) {
                $sj->debugMessage('REMOTE_USER='.$server->get('REMOTE_USER'));
                $username =  $server->get('REMOTE_USER');
            }
            if ($server->has('REDIRECT_REMOTE_USER')) {
                $sj->debugMessage('REDIRECT_REMOTE_USER='.$server->get('REDIRECT_REMOTE_USER'));
                $username =  $server->get('REDIRECT_REMOTE_USER');
            }
            $repository1 = $em->getRepository(Sso::class);
            $repository2 = $em->getRepository(Individu::class);

            if ($sso = $repository1->findOneByEppn($username)) {
                $individu = $sso->getIndividu();
            } elseif ($individu = $repository2->find($username)) { // seulement en mode testing
            } else { // nouvel utilisateur
                $session = $request->getSession();
                $session->set('eppn', $username);

                // Récupérer les headers dans la session
                $this->shibbHeadersToSession($request);

                //return new Response('nouvel utilisateur');
                return $this->redirectToRoute('nouveau_compte');
            }

            // authentification manuelle sans remote_user de symfony
            //$userChecker = new UserChecker();
            $userChecker = $this->uc;
            $userChecker->checkPreAuth($individu);

            $token = new UsernamePasswordToken($individu, null, 'main', $individu->getRoles());
            $session = $request->getSession();

            $this->ts->setToken($token);
            $session->set('_security_main', serialize($token));

            $userChecker->checkPostAuth($individu);
        } //  if( $server->has('REMOTE_USER') )
        else { // no REMOTE_USER
            return $this->redirectToRoute('deconnexion');
        } //  if( $server->has('REMOTE_USER') )

        $sj->infoMessage("Controller : connexion d'un utilisateur");

        if ($request->getSession()->has('url')) {
            return $this->redirect($request->getSession()->get('url'));
        } else {
            return $this->redirectToRoute('accueil');
        }
    }

    /**
    * @Route("/nouveau_compte", name="nouveau_compte", methods={"GET"})
    */
    public function nouveau_compteAction(Request $request, LoggerInterface $lg)
    {
        $sj = $this->sj;
        $ff = $this->ff;

        // vérifier si eppn est disponible dans $session
        if (! $request->getSession()->has('eppn')) { // une tentative de piratage
            $sj->warningMessage(__FILE__ . ":" . __LINE__ . " No eppn pour le nouveau_compte");
            $lg->warning("No eppn at nouveau_compte", [ 'request' => $request ]);
            // return new Response(' no eppn ' );
            return $this->redirectToRoute('accueil');
        }


        $eppn = $request->getSession()->get('eppn');

        // tests !
        // $eppn = "";

        // vérifier si email est disponible dans $session, sinon on redirige sur accueil avec un message dans le journal
        if ($request->getSession()->has('mail')) {
            $email = $request->getSession()->get('mail');

        // vérifier si email est disponible dans les headers
        } elseif ($request->headers->has('mail')) {
            $email = $request->headers->get('mail');
            $request->getSession()->set('mail',$email);

        // Pas d'adresse = Pas d'ouverture de compte
        } else {
            $sj->warningMessage(__FILE__ . ":" . __LINE__ . " Pas d'adresse mail pour le nouveau compte (eppn = $eppn");
            return $this->redirectToRoute('accueil');
        }

        // $eppn = 'toto';
        // Mauvais eppn - Pas d'ouverture de compte
        // On ne vérifie que la présence de l'eppn, pas sa conformité
        if ($eppn === "") {
            $sj->warningMessage(__FILE__ . ":" . __LINE__ . " eppn défectueux pour le nouveau compte (eppn=$eppn, mail=$email)");
            return $this->redirectToRoute('accueil');
        };

        // $email = "";
        // Mauvaise adresse - Pas d'ouverture de compte
        if (!$this->isEmail($email)) {
            $sj->warningMessage(__FILE__ . ":" . __LINE__ . " Adresse mail défectueuse pour le nouveau compte (eppn=$eppn, mail=$email)");
            return $this->redirectToRoute('accueil');
        };

        $form = Functions::createFormBuilder($ff)
        ->add('save', SubmitType::class, ['label' => 'Continuer'])
        ->getForm();

        $form->handleRequest($request);

        // On a cliqué sur "Continuer": on continue vers la page de profil !
        if ($form->get('save')->isClicked() && $form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('nouveau_profil');
        };
        
        return $this->render('default/nouveau_compte.html.twig', [ 'mail' => $email , 'eppn' => $eppn, 'form' => $form->createView()]);    
    }

    private function isEmail(string $email) {
        $regex = '/^[a-z0-9_%.+-]+@[a-z0-9.-]+\.[a-z]{2,}$/';
        if (preg_match($regex, strtolower($email))==1) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * @Route("/nouveau_profil",name="nouveau_profil", methods={"GET","POST"})
     *
     */
    public function nouveau_profilAction(Request $request, LoggerInterface $lg)
    {
        $sn = $this->sn;
        $sj = $this->sj;
        $em = $this->getDoctrine()->getManager();

        $session = $request->getSession();

        // vérifier si eppn est disponible dans $session
        if (! $session->has('eppn')) {
            $sj->warningMessage(__FILE__ . ":" . __LINE__ .  "Pas d'eppn pour le nouveau profil");
            return $this->redirectToRoute('accueil');
        } else {
            $eppn = $session->get('eppn');
        }

        // vérifier si email est disponible dans $session
        if (! $session->has('mail')) {
            $sj->warningMessage(__FILE__ . ":" . __LINE__ . " Pas d'email pour le nouveau profil");
            return $this->redirectToRoute('accueil');
        } else {
            $mail = $session->get('mail');
        }

        // Est-ce qu'il y a déjà un compte avec cette adresse ?
        $individu = $em->getRepository(Individu::class)->findOneBy(['mail' => $mail]);
        if ($individu === null) {
            $flg_ind = false;
            $individu = new Individu();
            $individu->setMail($session->get('mail'));
            if ($session->has('sn')) {
                $individu->setNom($session->get('sn'));
            }
        } else {
            $flg_ind = true;
        }

        // TESTS !
        // $session->set('givenName','ursule');
        // $session->set('sn','Dupont');
        
        // Préremplissage du formulaire si la fédération nous a envoyé l'info !
        if ($session->has('givenName')) {
            $individu->setPrenom($session->get('givenName'));
        }
        
        if ($session->has('sn')) {
            $individu->setNom($session->get('sn'));
        }

        $form = $this->createForm(IndividuType::class, $individu, [ 'mail' => false ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($individu);

            $sso = new Sso();
            $sso->setEppn($eppn);
            $sso->setIndividu($individu);
            $em->persist($sso);

            $em->flush();

            if ($flg_ind) {
                $sj->infoMessage(__METHOD__ .':' . __LINE__ . " Nouvel eppn pour $mail = $eppn");
            } else {
                $sj->infoMessage(__METHOD__ .':' . __LINE__ . " Nouvel utilisateur créé: $eppn -> $mail");
            };

            // Envoyer un mail de bienvenue à ce nouvel utilisateur
            $dest   = [ $mail ];
            $etab   = preg_replace('/.*@/', '', $eppn);
            $sn->sendMessage(
                "notification/compte_ouvert-sujet.html.twig",
                "notification/compte_ouvert-contenu.html.twig",
                [ 'individu' => $individu, 'etab' => $etab, 'eppn' => $eppn ],
                $dest
            );

            // si c'est un compte cru, envoyer un mail aux admins
            if (strpos($eppn, 'sac.cru.fr') !== false) {
                //$sj->debugMessage(__FILE__ .':' . __LINE__ . ' Demande de COMPTE CRU - '.$eppn);
                $dest = $sn->mailUsers(['A']);
                $sn->sendMessage(
                    "notification/compte_ouvert_pour_admin-sujet.html.twig",
                    "notification/compte_ouvert_pour_admin-contenu.html.twig",
                    [ 'individu' => $individu, 'eppn' => $eppn, 'mail' => $mail ],
                    $dest
                );
            }

            // On est automatiquement connecté en sortant de cet écran
            return $this->redirectToRoute('connexionshiblogin');
            //return $this->redirectToRoute('accueil');
        }

        return $this->render('default/nouveau_profil.html.twig', array( 'mail' => $request->getSession()->get('mail'), 'form' => $form->createView()));

    }


    /**
    * @Route("/erreur_login", name="erreur_login", methods={"GET"})
    * Method({"GET"})
    */
    public function erreurLoginAction(Request $request)
    {
        return $this->render('default/erreur_login.html.twig');
    }

    /**
     * @Route("/exception_index", name="exception_index", methods={"GET"})
     * @Route("/index", name="index", methods={"GET"})
     * @Route("/accueil_demandeur", name="accueil_demandeur", methods={"GET"})
     * Method({"GET"})
     */
    public function exceptionIndexAction(Request $request)
    {
        // sans haut et bas
        return $this->render('default/exception_index.html.twig');
    }

    private static function elements($array)
    {
        $date = new \DateTime();
        mt_srand($date->setTime(0, 0, 0)->getTimestamp());
        $output=[];

        for ($i = 1; $i < 6; $i++) {
            if (count($array) < 1) {
                return $output;
            }
            $index  =   mt_rand(0, count($array) - 1);
            $output[]   =  $array[ $index ];
            array_splice($array, $index, 1);
        }
        return $output;
    }

    /**
     * @Route("/connexions", name="connexions", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function connexionsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $sj = $this->sj;

        $connexions = Functions::getConnexions($em, $sj);
        return $this->render('default/connexions.html.twig', [ 'connexions' => $connexions ]);
    }

    /**
     * @Route("/phpinfo", name="phpinfo", methods={"GET"})
     * Method({"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     *********************************************/
    public function infoAction(Request $request)
    {
        ob_start();
        phpinfo();
        $info = ob_get_clean();
        return $this->render('default/phpinfo.html.twig', [ 'info' => $info ]);
    }

    ///////////////////////////////////////////////////////////////////////////////////////



    /**
     * @Route("/md5", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     **/

    public function md5Action()
    {
        $salt = random_int(1, 10000000000) . microtime();
        $key = md5($salt);
        return new Response('<pre>' . $salt . ' '. $key . '</pre>');
    }

    /**
     * @Route("/uri", methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     **/

    public function uri(Request $request)
    {
        $IDPprod    =   $this->getParameter('IDPprod');
        return new Response(Functions::show($IDPprod));
        $output = $request->getUri();
        $output = $request->getPathInfo() ;
        return new Response('<pre>' . $output . '</pre>');
    }

    /*
     * Déposer dans la session les headers fournis par Shibboleth
     * NOTE - On ne s'occupe pas de eppn, cela est déjà fait par auth_connexionAction
     *        Côté Fédération, on doit envoyer les attributs correspondants
     *        Conf Shibboleth: il faut modifier le fichier attribute-map.xml (ie décommenter quelques lignes
     *        vers Other eduPerson attributes)
     *
     ***/
    private function shibbHeadersToSession(Request $request) {
        $headers = ['mail', 'givenName', 'sn', 'displayName', 'cn', 'affiliation', 'primary-affiliation'];
        $headers_values = [];

        // On recherche dans les headers
        foreach($headers as $h) {
            if ($request->headers->has($h)) {
                $headers_values[$h] = $request->headers->get($h);
            }
        }

        // On recherche dans les variables du serveur
        $server = $request->server;
        foreach($headers as $h) {
            if (!isset($headers_values[$h])) {

                // mail -> REDIRECT_mail
                $k1 = 'REDIRECT_'.$h;
                $k2 = 'HTTP_'.strtoupper($h);
                if ($server->has($k1)) {
                    $headers_values[$h] = $server->get($k1);
                }

                // mail -> HTTP_MAIL
                elseif ($server->has($k2)) {
                    $headers_values[$h] = $server->get($k2);
                }
                
            }
        }
        
        $session = $request->getSession();
        foreach($headers_values as $h => $v) {
            $session->set($h, $v);
        }
    
        return $headers_values;
    }



    /**
     * @Route("/test_workflow")
     * @Security("is_granted('ROLE_ADMIN')")
     * TODO - A réécrire
     **/

    //public function workflow(Request $request)
    //{
        //$session_workflow = new \App\Workflow\SessionWorkflow();
        //$session = new \App\Entity\Session();
        //$session->setEtatSession(\App\Utils\ETAT::ACTIF);

        //$projet_workflow = new \App\Workflow\ProjetWorkflow();
        //echo $projet_workflow;
        //echo '*******************************************************************' ."\n";


        //$version_workflow = new \App\Workflow\VersionWorkflow();
        //echo $version_workflow;
        //echo '*******************************************************************' ."\n";

        //$projet_workflow = new \App\Workflow\ProjetWorkflow();
        //echo $projet_workflow;
        //echo '*******************************************************************' ."\n";

        //$session_workflow = new \App\Workflow\SessionWorkflow();
        //echo $session_workflow;
        //echo '*******************************************************************' ."\n";



        //if( $session_workflow->canExecute(\App\Utils\Signal::CLK_SESS_DEB, $session ) ) echo ' true '; else echo ' false ';
        //if( $session_workflow->canExecute(\App\Utils\Signal::CLK_SESS_FIN, $session ) ) echo ' true '; else echo ' false ';
        //return new Response();
    //}
}
