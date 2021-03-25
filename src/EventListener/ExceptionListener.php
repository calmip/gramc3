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


/****
 * Ce service traite les exceptions
 ************/
 
// src/App/EventListener/ExceptionListener.php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use Symfony\Component\HttpKernel\Exception\HttpException; 
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Doctrine\ORM\ORMException;
use Doctrine\DBAL\DBALException;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

//use Symfony\Bridge\Monolog\Logger;
use Psr\Log\LoggerInterface;

use App\Exception\UserException;
//use App\App;

//use App\Entity\Journal;
use App\Entity\Individu;
use App\Utils\Functions;
use App\GramcServices\ServiceJournal;
use Doctrine\ORM\EntityManagerInterface;


class ExceptionListener 
{
    private $router;
    private $logger;
    private $session;

	// TODO - c'est quoi router ?
    public function __construct($kernel_debug,$router,LoggerInterface $logger, ServiceJournal $sj, SessionInterface $session,EntityManagerInterface $em)
    { 
		$this->kernel_debug = $kernel_debug;
        $this->router = $router;
        $this->logger = $logger;
        $this->sj     = $sj;
        $this->session= $session;
        $this->em     = $em;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {

        $server = $event->getRequest()->server;
        $exception = $event->getException();
 
        
		// S'il y a une erreur sur la page d'accueil, décommenter ci-dessous
		// pour avoir des détails sur l'erreur
		// Mais en général, laisser commenté sinon grosses emmerdes en perspective !!!
        //$response =  new Response( '<pre>' . $exception . '</pre>');
        //$event->setResponse($response);
        //return;
        
 
        // nous captons des erreurs de la page d'accueil
        if( $event->getRequest()->getPathInfo() == '/' )
		{
			// ne pas écrire dans le journal quand il y a une exception de Doctrine
			if( ! $exception instanceof ORMException && ! $exception instanceof \InvalidArgumentException && ! $exception instanceof DBALException )
				$this->sj->errorMessage(__METHOD__ . ":" . __LINE__ . " erreur dans la page / depuis " . $event->getRequest()->headers->get('referer'));
			else
				$this->logger->error(__METHOD__ . ":" . __LINE__ . "erreur dans la page / depuis " . $event->getRequest()->headers->get('referer'));
			$response =  new Response
				("<h1>Bienvenue sur gramc</h1> Erreur dans la page d'accueil");
			$event->setResponse($response);
			return;
		}
        
        // on ne fait rien quand il y a une exception de Doctrine
        if( $exception instanceof ORMException || $exception instanceof \InvalidArgumentException || $exception instanceof DBALException)
		{
            if( method_exists( $this->em, 'isOpen' ) && $this->em->isOpen() )
                $this->logger->error(__METHOD__ . ":" . __LINE__ .  " Exception " . get_class($exception) . ' : ' .  $exception->getMessage() . "  À partir de URL : " .  $event->getRequest()->getPathInfo());
            else
                $this->logger->error(__METHOD__ . ":" . __LINE__ .  " Exception " . get_class($exception) . ' : ' .  $exception->getMessage() .                                                "  À partir de URL : " .  $event->getRequest()->getPathInfo() . ' Entity manager closed');

		}   
        
        //
        // utilisé si on utilise l'authentification remote_user de symfony
        //
        
        elseif( $exception instanceof AuthenticationCredentialsNotFoundException)
		{ // problème avec REMOTE_USER
			if( $username = getenv('REMOTE_USER') ||  $server->has('REMOTE_USER') || $server->has('REDIRECT_REMOTE_USER'))
			{ // problème avec REMOTE_USER
				if( $server->has('REMOTE_USER') )
					$username =  $server->get('REMOTE_USER');
				
	            if( $server->has('REDIRECT_REMOTE_USER') )
					$username =  $server->get('REDIRECT_REMOTE_USER');
				
				$session = $event->getRequest()->getSession();
				$session->set('eppn',$username);

				if( $server->has('REDIRECT_mail') )
					$session->set('mail', $server->get('REDIRECT_mail') );
				
				$this->logger->info(__METHOD__ . ":" . __LINE__ . " Unknown REMOTE_USER " .  $username . " to : " . $event->getRequest()->getRequestUri(),
						[
						'exception' => $exception,
						'request' => $event->getRequest()
						] );
						
				if( $server->has('REDIRECT_mail') )
				{
					$user = $this->em->getRepository('App:Individu')->findOneBy([ 'mail' => $server->get('REDIRECT_mail')] );
					if( $user instanceof Individu )
					{
						// un problème avec UserChecker ??
						//if( $user->getEppn() == $username )
					   if( ! in_array( $username, $user->getEppn() ) ) 
							$this->sj->noticeMessage(__METHOD__ . ":" . __LINE__ . " UserChecker : Problème de connexion pour EPPN ".$username.' user ' .
								$user->getPrenom() . ' ' . $user->getNom() . ' mail : ' . $server->get('REDIRECT_mail') . ', new_eppn');
						else
							$this->sj->noticeMessage(__METHOD__ . ":" . __LINE__ ." REMOTE_USER " . $username . " inconnu pour la page " . $event->getRequest()->getRequestUri()
								. ", new_eppn pour user " . $user->getPrenom() . ' ' . $user->getNom() . ' mail : ' .
								$server->get('REDIRECT_mail') . ', new_eppn');
					}
					else
						$this->sj->infoMessage(__METHOD__ . ":" . __LINE__ . " REMOTE_USER " . $username . " inconnu pour la page " . $event->getRequest()->getRequestUri()
							. ", new_eppn pour user avec le mail transmis par l'authorité : " .
							$server->get('REDIRECT_mail') . ", new_eppn");
				}
				else
					$this->sj->infoMessage(__METHOD__ . ":" . __LINE__ ." REMOTE_USER " . $username . " inconnu pour la page " . $event->getRequest()->getRequestUri()
							. ', new_eppn' );

			    if( $username == "" )	
		    	{
		    		$this->sj->errorMessage(__METHOD__. ':' . __LINE__ . " EPPN vide ");
					$response =  new Response("Erreur d'authentification avec IDP, EPPN vide");
	    		}
		    else
				 $response =  new RedirectResponse($this->router->generate('nouveau_compte') );
				//$response =  new Response($username);
				$event->setResponse($response);
			}
			else
			{ // il ne devrait jamais y arriver
				$this->sj->journalMessage(__METHOD__ . ":" . __LINE__ ." UnknownAuthenticationCredentialsNotFoundException  pour la page " . $event->getRequest()->getRequestUri(), Journal::ERROR);
				$this->logger->warning(__METHOD__ . ":" . __LINE__ ." UnknownAuthenticationCredentialsNotFoundException !" ,
						[
						'exception' => $exception,
						'request' => $event->getRequest()
						] );
						
				$response =  new RedirectResponse($this->router->generate('accueil') );
				$event->setResponse($response);
			}
		} //  problème avec REMOTE_USER 

        //
        // UserChecker exceptions
        //
        
        elseif( $exception instanceof UserException )
        {
                // cela arrive quand on n'est pas utilisateur Gramc ou à partir d'une mauvaise adressse IP
                // retour à la page d'index
                $response = new RedirectResponse( $this->router->generate('erreur_login') );
                $event->setResponse($response);   
        }
        //   AccessDeniedHttpException
        //   problème avec access_control dans security.yml (IP par exemple) ou un mauvais rôle
        elseif( $exception instanceof AccessDeniedHttpException /*or $exception instanceof AccessDeniedException*/)
		{
			$this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." accès à la page " . $event->getRequest()->getPathInfo() . " non autorisé");
			$response =  new RedirectResponse($this->router->generate('accueil') );
			$event->setResponse($response);
		}
        // pas de rôle pour le moment
        // Pourquoi on est passé de InsufficientAuthenticationException à HttpException entre Symf 3.2 et 3.4 ?
        //elseif( $exception instanceof InsufficientAuthenticationException )
        elseif( $exception instanceof HttpException )
		{
			$event->getRequest()->getSession()->set('url', $event->getRequest()->getUri() );
			
			$this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." accès anonyme à la page " . $event->getRequest()->getPathInfo());
		
			if( $this->kernel_debug == true )
				$response =  new RedirectResponse( $this->router->generate('connexion_dbg') );
			else
				$response =  new RedirectResponse( $this->router->generate('connexion') );
			
			$event->setResponse($response);    
		}

        elseif( $exception instanceof NotFoundHttpException )
		{
			// uniquement en production nous redirigeons vers la page spéciale 'accueil' - pas de log
			if( $this->kernel_debug == false )
			{
				$response =  new RedirectResponse($this->router->generate('accueil') );
				$event->setResponse($response); 
			}
		}
        else
		{
			// comportement général
			$this->logger->warning("Error to " .  $event->getRequest()->getRequestUri(),
					[
					'exception' => $exception,
					'request' => $event->getRequest()
					] );

			$this->sj->errorMessage(__METHOD__ . ":" . __LINE__ ." Exception " . get_class($exception) . ' : ' . $exception->getMessage() .
									  "  À partir de URL : " .  $event->getRequest()->getPathInfo() );

			// uniquement en production nous redirigeons vers la page spéciale 'accueil'
			if( $this->kernel_debug == false )
			{
				$response =  new RedirectResponse($this->router->generate('accueil') );
				$event->setResponse($response); 
			}
		}
    }
}
