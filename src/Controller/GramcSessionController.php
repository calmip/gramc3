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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;

use App\Form\IndividuType;
use App\Entity\Individu;
use App\Entity\Scalar;
use App\Entity\Sso;
use App\Entity\Compteactivation;
use App\Entity\Journal;

//use App\App;
use App\Security\User\UserChecker;
use App\Utils\Functions;
use App\Utils\IDP;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

function redirection_externe($url)
{
    $controller = new Controller();
    return $controller->redirect($url);
}


/////////////////////////////////////////////////////

class GramcSessionController extends Controller
{
    /**
     * @Route("/admin/accueil",name="admin_accueil")
     * @Security("is_granted('ROLE_OBS') or is_granted('ROLE_PRESIDENT')")
    **/

    public function adminAccueilAction()
    {
		$sm      = $this->get('app.gramc.ServiceMenus');
        $menu1[] = $sm->individu_gerer();

        $menu2[] = $sm->gerer_sessions();
        $menu2[] = $sm->bilan_session();
        $menu2[] = $sm->mailToResponsables();
        $menu2[] = $sm->mailToResponsablesFiche();

        $menu3[] = $sm->projet_session();
        $menu3[] = $sm->projet_annee();
        $menu3[] = $sm->projet_tous();
        $menu3[] = $sm->projet_donnees();
        $menu3[] = $sm->televersement_generique();

        $menu4[] = $sm->rattachements();
        $menu4[] = $sm->thematiques();
        $menu4[] = $sm->metathematiques();
        $menu4[] = $sm->laboratoires();

        $menu5[] = $sm->bilan_annuel();
        $menu5[] = $sm->statistiques();
        $menu5[] = $sm->publications();

        $menu6[] = $sm->connexions();
        $menu6[] = $sm->journal();
        if ( $this->getParameter('kernel.debug'))
        {
			$menu6[] = $sm->avancer();
		}
		$menu6[] = $sm->info();
        $menu6[] = $sm->nettoyer();

        return $this->render('default/accueil_admin.html.twig',['menu1' => $menu1,
                                                                'menu2' => $menu2,
                                                                'menu3' => $menu3,
                                                                'menu4' => $menu4,
                                                                'menu5' => $menu5,
                                                                'menu6' => $menu6 ]);
    }

    /**
     * @Route("/mentions_legales", name="mentions_legales" )
     */
    public function mentionsAction()
    {
        return $this->render('default/mentions.html.twig');
    }

     /**
     * @Route("/aide", name="aide" )
     */
    public function aideAction()
    {
        return $this->render('default/aide.html.twig');
    }

     /**
     * @Route("/", name="accueil" )
     *
     */
    public function accueilAction()
	{
		$sm     = $this->get('app.gramc.ServiceMenus');
		$ss     = $this->get('app.gramc.ServiceSessions');
		$session= $ss->getSessionCourante();
		
		// Si true, cet utilisateur n'est ni expert ni admin ni président !
		$seulement_demandeur=true;
		
		$menu   = [];
		$m = $sm->demandeur();
		if ($m['ok'] == false)
		{
			// Même pas demandeur !
			$seulement_demandeur = false;
		}
		$menu[] = $m;
		
		$m = $sm->expert();
		if ($m['ok'])
		{
			$seulement_demandeur = false;
		}
		$menu[] = $m;
		
		$m = $sm->administrateur();
		if ($m['ok'])
		{
			$seulement_demandeur = false;
		}
		$menu[] = $m;
		
		$m = $sm->president();
		if ($m['ok'])
		{
			$seulement_demandeur = false;
		}
		$menu[] = $m;
		$menu[] = $sm->aide();

		if ($seulement_demandeur)
		{
			return $this->redirectToRoute('projet_accueil');
		}
		else
		{
	        return $this->render('default/accueil.html.twig', 
								['menu' => $menu, 
								 'projet_test' => $sm->nouveau_projet_test()['ok'],
								 'session' => $session ]);
		}
	}

    /**
     * @Route("/president", name="president_accueil" )
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function presidentAccueilAction()
	{
 		$sm     = $this->get('app.gramc.ServiceMenus');
        $menu[] = $sm->affectation();
        $menu[] = $sm->commSess();
	    $menu[] = $sm->affectation_rallonges();
        $menu[] = $sm->affectation_test();
        return $this->render('default/president.html.twig', ['menu' => $menu]);
	}

    /**
     * @Route("/deconnexion",name="deconnexion")
     **/
    public function deconnexionAction(Request $request)
    {
		$sj    = $this->get('app.gramc.ServiceJournal');
		$ac    = $this->get('security.authorization_checker');
		$token = $this->get('security.token_storage')->getToken();
		$ss    = $this->get('session');
        if( $ac->isGranted('ROLE_PREVIOUS_ADMIN') )
		{
            $sudo_url = $this->get('session')->get('sudo_url');
            //$userChecker = new UserChecker();
            $userChecker = $this->get('app.user_checker');
            $real_user   = $ss->get('real_user' );
            $userChecker->checkPostAuth( $real_user );

            $sj->infoMessage(__METHOD__ . ":" . __LINE__ . " déconnexion d'un utilisateur en SUDO vers " . $real_user );

            //$sj->debugMessage(__METHOD__ . " sudo_url = " . $ss->get('sudo_url') );
            return new RedirectResponse(  $sudo_url . '?_switch_user=_exit' );
            //return $this->redirectToRoute('gramc_gerer_utilisateurs',[ '_switch_user' => '_exit' ]);
		}
        elseif ($ac->isGranted('IS_AUTHENTICATED_FULLY'))
		{
            $sj->infoMessage(__METHOD__ . ":" . __LINE__ .  " déconnexion de l'utilisateur " . $token->getUser() );
            $request->getSession()->invalidate();
            session_destroy();
		}
        return $this->redirectToRoute('deconnected');
    }


    /**
    * @Route("/deconnected", name="deconnected")
    **/
    public function deconnexion_showAction(Request $request)
    {
	    return $this->render('default/deconnexion.html.twig');
    }

    /**
    * @Route("/profil",name="profil")
    * @Security("is_granted('ROLE_DEMANDEUR')") 

    **/
    public function profilAction(Request $request)
    {
		$sj = $this->get('app.gramc.ServiceJournal');

        $individu = $this->get('security.token_storage')->getToken()->getUser();

        if( $individu == 'anon.' || ! ($individu instanceof Individu)  )
        {
            return $this->redirectToRoute('accueil');
        }
        $old_individu = clone $individu;
        $form = $this->createForm(IndividuType::class, $individu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            if( $old_individu->isPermanent() != $individu->isPermanent() && $individu->isPermanent() == false )
                 $sj->warningMessage(__METHOD__ . ':' . __LINE__ . " " . $individu . " cesse d'être permanent !!");

             if( $old_individu->isFromLaboRegional() != $individu->isFromLaboRegional() && $individu->isFromLaboRegional() == false )
                 $sj->warningMessage(__METHOD__ . ':' . __LINE__ . " " . $individu . " cesse d'être d'un labo regional !!");

            $new_statut = $individu->getStatut();
            $old_statut = $old_individu->getStatut();
            if( $new_statut != $old_statut )
                $sj->noticeMessage(__METHOD__ . ':' . __LINE__ . " " . $individu . " a changé son statut de " . $old_statut
                . " vers " . $new_statut );

            $new_laboratoire = $individu->getLabo();
            $old_laboratoire = $old_individu->getLabo();
            if( $new_laboratoire != $old_laboratoire )
                $sj->noticeMessage(__METHOD__ . ':' . __LINE__ . " " . $individu . " a changé son laboratoire de " . $old_laboratoire
                . " vers " . $new_laboratoire );

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
     * @Route("/connexion_dbg",name="connexion_dbg")
     **/
    public function connectiondbgAction(Request $request)
    {
		$sj         = $this->get('app.gramc.ServiceJournal');
		$token      = $this->get('security.token_storage')->getToken();
		$em         = $this->getDoctrine()->getManager();
		$repository = $em->getRepository(Individu::class);
		
		if ( ! $this->container->hasParameter('kernel.debug') || $this->getParameter('kernel.debug') == false )
		{
			$sj->warningMessage(__METHOD__ . ':' . __LINE__ .' tentative de se connecter avec debug en production');
            return $this->redirectToRoute('accueil');
		}

        $user = new Individu();
        $mail = $user->getMail();

        $experts    = $repository->findBy( ['expert'   => true ] );
        $admins     = $repository->findBy( ['admin'    => true ] );
        $obs        = $repository->findby( ['obs'      => true ] );
        $sysadmins  = $repository->findby( ['sysadmin' => true ] );
        $responsables   = static::elements( $repository->getCollaborateurs(true) );
        
        $moi            = $token->getUser();
        $collaborateurs = static::elements( $repository->getCollaborateurs(false, false, $moi) );
        $users          = array_unique( array_merge( $admins, $experts, $obs, $sysadmins, $responsables , $collaborateurs) );
        sort($users);

        $form = $this->createFormBuilder($user )
	        ->add('mail', EntityType::class,
	            [
		            'multiple' => false,
		            'placeholder' => 'Choisissez',
		            'class' => 'App:Individu',
		            'choices' => $users,
		            //'choice_label' => function($user){ return $user->getPrenom() . ' ' . $user->getNom(); }
	            ])
			->add('save', SubmitType::class, ['label' => 'Connexion'])
			->add('reset',ResetType::class,  ['label' => 'Effacer'])
			->getForm();

        $form->handleRequest($request);

        //if ($form->get('save')->isClicked() )
        //    {
        //    $m = $user->getMail();
        //    }

        if ($form->isSubmitted() )
		{
            $user  = $repository->findOneByMail($user->getMail()->getMail() );
            $roles = $user->getRoles();
            $token = new UsernamePasswordToken($user, null, 'main', $roles );

            //$userChecker = new UserChecker();
            $userChecker = $this->get('app.user_checker');
            $userChecker->checkPreAuth($user);

            $session = $request->getSession();
            $this->get('security.token_storage')->setToken($token);
            $session->set('_security_main', serialize($token));

            $userChecker->checkPostAuth($user);
            $sj->infoMessage(__METHOD__ . ":" . __LINE__ . " connexion DBG de l'utilisateur " . $user);

            if( $request->getSession()->has('url') )
                return $this->redirect( $request->getSession()->get('url') );
            else
                return $this->redirectToRoute('accueil');
		}

        return $this->render('default/connexion_dbg.html.twig', [ 'form' => $form->createView() ]  );
    }

    /**
    * @Route("/login/activation",name="activation")
    * @Route("/login/activation/{key}")
    **/

    public function activationAction(Request $request,$key)
    {
		$em = $this->getDoctrine()->getManager();
		$sn = $this->get('app.gramc.ServiceNotifications');
		$sj = $this->get('app.gramc.ServiceJournal');

		$server = $request->server;
		if(  $server->has('REMOTE_USER') || $server->has('REDIRECT_REMOTE_USER') )
	    {
		    $eppn = "";
			if( $server->has('REMOTE_USER') ) $eppn =  $server->get('REMOTE_USER');
			if( $server->has('REDIRECT_REMOTE_USER') ) $eppn =  $server->get('REDIRECT_REMOTE_USER');

			$em = $this->getDoctrine()->getManager();

			$compteactivation = $this->getDoctrine()
				->getRepository('App:Compteactivation')
				->findOneBy( ['key' => $key ] );

			if( !  $compteactivation )
				   return new Response('<pre> Activation error for this key </pre>');

			$sso = new Sso();
			$sso->setEppn( $eppn );
			$individu = $compteactivation->getIndividu();
			$sso->setIndividu( $individu );

			$em->remove($compteactivation);

			if( $em->getRepository(Sso::class)->findOneBy( [ 'eppn' => $eppn ] ) == null )
				$em->persist($sso);
			else
				$sj->noticeMessage( __FILE__ . ":" . __LINE__ . "  " . $eppn . " existe déjà");

			$em->flush();

			// Envoyer un mail de bienvenue à ce nouvel utilisateur
			$dest   = [ $individu->getMail() ];
			$etab   = preg_replace('/.*@/','',$eppn);
			$sn->sendMessage( "notification/compte_ouvert-sujet.html.twig",
							  "notification/compte_ouvert-contenu.html.twig",
							  [ 'individu' => $individu, 'etab' => $etab ],
							  $dest );

			return $this->redirectToRoute('connexion');
		}
		else
		{
			return new Response('<pre> Activation error - no eppn </pre>');
		}
    }


    /**
     * @Route("/login_choice", name="connexion")
     *
     * @Method({"GET", "POST"})
     */

    public function loginAction(Request $request)
    {
		$sj = $this->get('app.gramc.ServiceJournal');
		$ff = $this->get('form.factory');

		$form = Functions::createFormBuilder($ff)
	            ->add('data', ChoiceType::class,
                [
                 'choices' => $this->getParameter('IDPprod'),
                 'choices_as_values' => true
                 ]
                 )
            ->add('connect', SubmitType::class, ['label' => 'Connexion'] )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $url    =   $request->getSchemeAndHttpHost();
            $url    .= '/Shibboleth.sso/Login?target=';
            //$url    .=   $this->generateUrl('connexionshiblogin');
            //$url    .= '/gramce-milos/login';
            $url    .= $this->generateUrl('connexionshiblogin');
            //$url = $this->generateUrl('connexionshib', [] , UrlGeneratorInterface::ABSOLUTE_URL);
            //$url = $url .  $this->generateUrl('accueil', [] , UrlGeneratorInterface::ABSOLUTE_URL);


            if (  $form->getData()['data'] != 'WAYF' )
                $url = $url . '&providerId=' . $form->getData()['data'];

            $sj->debugMessage(__FILE__. ":" . __LINE__ . " URL shiblogin = " . $url);

            return $this->redirect($url);
        }

        return $this->render('default/login.html.twig',   [ 'form' => $form->createView(), ]
        );
    }


    /**
     * @Route("/login", name="shiblogin")
     * @Method({"GET"})
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
     * @Route("/public/auth/connexion" )
     * @Route("/role/public/login.php")
     * @Route("/login/connexion", name="connexionshiblogin")
     * @Route("/connexion")
     */
    public function auth_connexionAction(Request $request)
	{
		$sj = $this->get('app.gramc.ServiceJournal');
		$ac = $this->get('security.authorization_checker');

        $sj->infoMessage("shiblogin d'un utilisateur");
		$em = $this->getDoctrine()->getManager();
		
        /*
        if( $request->getSession()->has('url') )
            return $this->redirect( $request->getSession()->get('url') );
        else
            return $this->redirectToRoute('index');
        */
        $individu = $this->get('security.token_storage')->getToken()->getUser(); // OK si l'authentification remote_user de symfony

        //
        // utilisé si on n'utilise pas l'authentification remote_user de symfony
        //

        if( $individu == 'anon.' || ! ($individu instanceof Individu)
		|| ! $ac->isGranted('IS_AUTHENTICATED_FULLY')
		)
		{
            $server = $request->server;
            if( ( $username = getenv('REMOTE_USER') ) || $server->has('REMOTE_USER') || $server->has('REDIRECT_REMOTE_USER') )
			{
				if( $server->has('REMOTE_USER') ) $username =  $server->get('REMOTE_USER');
				if( $server->has('REDIRECT_REMOTE_USER') ) $username =  $server->get('REDIRECT_REMOTE_USER');

				$repository1 = $em->getRepository(Sso::class);
				$repository2 = $em->getRepository(Individu::class);

				if( $sso = $repository1->findOneByEppn($username) )
						{
						$individu = $sso->getIndividu();
						}
				elseif ( $individu = $repository2->find($username) )
						{ // seulement en mode testing
						}
				else
					{ // nouvel utilisateur
					$session = $request->getSession();
					$session->set('eppn', $username);

					//return new Response('nouvel utilisateur');
					return $this->redirectToRoute('nouveau_compte');
					}

				// authentification manuelle sans remote_user de symfony
				//$userChecker = new UserChecker();
	            $userChecker = $this->get('app.user_checker');
				$userChecker->checkPreAuth($individu);

				$token = new UsernamePasswordToken($individu, null, 'main', $individu->getRoles() );
				$session = $request->getSession();
				$this->get('security.context')->setToken($token);
				$session->set('_security_main', serialize($token));

				$userChecker->checkPostAuth($user);
				//return new Response("<pre> manual login ".print_r($_SESSION,true)."</pre>");

			} //  if( $server->has('REMOTE_USER') )
			else
			{ // no REMOTE_USER
				//return new Response("<pre> no login ".print_r($_SESSION,true)."</pre>");
				return $this->redirectToRoute('deconnexion');
			} //  if( $server->has('REMOTE_USER') )

		  } // if  ( $individu == 'anon.' || ! ($individu instanceof Individu)  )

        $sj->infoMessage("Controller : connexion d'un utilisateur");

        if( $request->getSession()->has('url') )
        {
			return $this->redirect( $request->getSession()->get('url') );
		}
		else
		{
			return $this->redirectToRoute('accueil');
		}
	}


     /**
     * @Route("/nouveau_compte",name="nouveau_compte")
     */
    public function nouveau_compteAction(Request $request, LoggerInterface $lg)
    {
		$sj = $this->get('app.gramc.ServiceJournal');
		$ff = $this->get('form.factory');

        // vérifier si eppn est disponible dans $session
        if( ! $request->getSession()->has('eppn') )
                    { // une tentative de piratage
                    $sj->warningMessage(__FILE__ . ":" . __LINE__ . " No eppn pour le nouveau_compte");
                     $lg->warning("No eppn at nouveau_compte", [ 'request' => $this->getRequest() ] );
                    // return new Response(' no eppn ' );
                    return $this->redirectToRoute('accueil');
                    }

        $form = Functions::createFormBuilder($ff)
        ->add('mail', TextType::class , [ 'label' => 'Votre mail :', 'data' => "nom@labo.fr" ])
        ->add('save', SubmitType::class,    ['label' => 'Connexion'])
        ->add('reset',ResetType::class,     ['label' => 'Effacer'])
        ->getForm();

        $form->handleRequest($request);

        if ($form->get('save')->isClicked() && $form->isSubmitted() && $form->isValid() )
            {
            $em = $this->getDoctrine()->getManager();
            $repository = $this->getDoctrine()->getRepository('App:Individu');

            $email = $form->getData()['mail'];
            $request->getSession()->set('email',$email );

            if( $individu = $repository->findOneBy( ['mail' =>  $email ] ) )
                { // user existe déjà
                $this->mail_activation( $individu );
                return $this->render('default/email_activation.html.twig');
                //return new Response('<pre> Activation done </pre>');
                //$this->get('logger')->info("New eppn added : " . $request->getSession()->get('eppn'),
                //            array('request' => $request) );
                //return new Response(' user added ' );
                //return $this->redirectToRoute('accueil');
                }
            else
                {
		        // activation du compte à faire
                return $this->redirectToRoute('nouveau_profil');
                }
            return $this->render('default/nouveau_profil.html.twig', [ 'mail' => $email , 'form' => $form2->createView() ]  );
            }

        return $this->render('default/nouveau_compte.html.twig', array( 'form' => $form->createView())  );

    }

    /**
     * @Route("/nouveau_profil",name="nouveau_profil")
     */
    public function nouveau_profilAction(Request $request, LoggerInterface $lg)
    {
		$sn = $this->get('app.gramc.ServiceNotifications');
		$sj = $this->get('app.gramc.ServiceJournal');
		$em = $this->getDoctrine()->getManager();
		
	    // vérifier si eppn est disponible dans $session
	    if( ! $request->getSession()->has('eppn')  )
                    { // une tentative de piratage
                    $sj->warningMessage(__FILE__ . ":" . __LINE__ .  "Pas d'eppn pour le nouveau profil");
                    return $this->redirectToRoute('accueil');
                    }

		// vérifier si email est disponible dans $session
		if( ! $request->getSession()->has('email')  )
	                    { // une tentative de piratage
                    $sj->warningMessage(__FILE__ . ":" . __LINE__ . " Pas d'email pour le nouveau profil");
                     $lg->warning("No email at nouveau_profil",
                            array('request' => $event->getRequest()) );
                    return $this->redirectToRoute('accueil');
                    }

	    $individu = new Individu();
	    //var_dump(  $request->getSession() );
	    $individu->setMail( $request->getSession()->get('email') );
	    //echo $individu->getMail();
	
	    $form = $this->createForm(IndividuType::class, $individu, [ 'permanent' => true ]);
	
	    $form->handleRequest($request);
	
	    if ($form->isSubmitted() && $form->isValid())
	    {
	        //$old_individu = $em->getRepository(Individu::class)->findOneBy( ['mail' => $request->getSession()->get('email') ] );
	        $old_individu = $em->getRepository(Individu::class)->findOneBy( ['mail' => $individu->getMail() ] );
	        if( $old_individu != null )
	        {
	            $sj->noticeMessage(__FILE__ .':' . __LINE__ . " Utilisateur " . $individu->getMail() . " existe déjà");
	            $this->mail_activation(  $old_individu );
	            return $this->render('default/email_activation.html.twig');
	            //$sj->debugMessage(__FILE__ .':' . __LINE__ . ' old_individu = ' . Functions::show($old_individu) );
	            return new Response('<pre> Impossible de créer cet utilisateur </pre>');
	        }
	        else
	        {
	            /* Envoi d'un mail d'activation à l'utilisateur */
	            $em = $this->getDoctrine()->getManager();
	            $em->persist($individu);
	            $em->flush();
	            $this->mail_activation(  $individu );
	
	            /* Envoi d'une notification aux admins dans le cas où il s'agit d'un compte CRU */
	            $eppn = $request->getSession()->get('eppn');
	            if (strpos($eppn ,'sac.cru.fr') !== false) {
	                $dest   = $sn->mailUsers( ['A'] );
	                $sn->sendMessage( "notification/compte_ouvert_pour_admin-sujet.html.twig",
	                                  "notification/compte_ouvert_pour_admin-contenu.html.twig",
	                                  [ 'individu' => $individu, 'eppn' => $eppn ],
	                                  $dest );
	            }
	
	            $sj->infoMessage(__METHOD__ .':' . __LINE__ . " Nouvel utilisateur " . $individu->getMail() . " créé");
	            return $this->render('default/email_activation.html.twig');
	            //return new Response('<pre> Activation effectuée </pre>');
	            }
	        }
        return $this->render('default/nouveau_profil.html.twig', array( 'email' => $request->getSession()->get('email'), 'form' => $form->createView())  );
    }

//////

    private function mail_activation($individu)
    {
		$sj = $this->get('app.gramc.ServiceJournal');

		$key = md5( random_int(1,10000000000) . microtime() );
		$compteactivation = new Compteactivation();
		$compteactivation->setIndividu($individu);
		$compteactivation->setKey( $key );
		$em = $this->getDoctrine()->getManager();
		$em->persist($compteactivation);
		$em->flush();

		// envoi de mail

		$session = new Session();

		$template = $this->get('twig')->createTemplate('click on <a href="{{ url(\'activation\') }}/{{key}}">Activation</a> {{ url(\'activation\') }}/{{key}}');
		$body = $template->render(array('key' => $key));

		$message = \Swift_Message::newInstance()
			->setSubject('Activation GRAMC')
			->setFrom( $this->container->getParameter('mailfrom') )
			->setTo( $session->get('email')  )
			->setBody($body,'text/html');
		$this->get('mailer')->send($message);
		$sj->infoMessage(__METHOD__ .':' . __LINE__ . ' Activation GRAMC  pour ' .  $session->get('email').  ' envoyé  : ' . $body  );
     }

     /**
     * @Route("/erreur_login", name="erreur_login")
     * @Method({"GET"})
     */
    public function erreurLoginAction(Request $request)
    {
        return $this->render('default/erreur_login.html.twig');
    }

    /**
     * @Route("/exception_index", name="exception_index")
     * @Route("/index", name="index")
     * @Route("/accueil_demandeur", name="accueil_demandeur")
     * @Method({"GET"})
     */
    public function exceptionIndexAction(Request $request)
    {
        // sans haut et bas
        return $this->render('default/exception_index.html.twig');
    }

    private static function elements($array)
    {
    $date = new \DateTime();
    mt_srand( $date->setTime(0,0,0)->getTimestamp() );
    $output=[];

    for( $i = 1; $i < 6; $i++ )
        {
        if( count( $array ) < 1 ) return $output;
        $index  =   mt_rand(0, count( $array ) - 1 );
        $output[]   =  $array[ $index ];
        array_splice( $array, $index, 1 );
        }
    return $output;
    }

    /**
     * @Route("/connexions", name="connexions")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function connexionsAction(Request $request)
    {
		$em = $this->getDoctrine()->getManager();
		$sj = $this->get('app.gramc.ServiceJournal');

		$connexions = Functions::getConnexions($em, $sj);
	    return $this->render('default/connexions.html.twig', [ 'connexions' => $connexions ] );
    }

	/**
	 * @Route("/phpinfo", name="phpinfo")
	 * @Method({"GET"})
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
     * @Route("/md5")
     * @Security("is_granted('ROLE_ADMIN')")
     **/

    public function md5Action()
    {
        $salt = random_int(1,10000000000) . microtime();
        $key = md5( $salt );
        return new Response('<pre>' . $salt . ' '. $key . '</pre>');
    }

    /**
     * @Route("/uri")
     * @Security("is_granted('ROLE_DEMANDEUR')")
     **/

    public function uri(Request $request)
    {
        $IDPprod    =   $this->getParameter('IDPprod');
        return new Response( Functions::show($IDPprod) );
        $output = $request->getUri();
        $output = $request->getPathInfo() ;
        return new Response('<pre>' . $output . '</pre>');
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
