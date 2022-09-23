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
 *  authors : Emmanuel Courcelle - C.N.R.S. - UMS 3667 - CALMIP
 *            Nicolas Renon - Université Paul Sabatier - CALMIP
 **/

/***
 * class ExceptionListener = Ce service intercepte les exceptions et les traite de la manière suivante:
 *                            - En mode DEBUG, affiche l'exception et sort
 *                            - En mode NON DEBUG, écrit dans le fichier de log ou dans le journal, puis redirige vers la page d'accueil
 *                            - TODO - refaire tout ça de manière symfoniquement correcte !
 *
 *
 **********************/
 
// src/EventListener/ExceptionListener.php
namespace App\EventListener;

use App\Entity\Individu;
use App\Utils\Functions;
use App\GramcServices\ServiceJournal;

//use App\Exception\UserException;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

use Symfony\Component\HttpKernel\Exception\HttpException; 
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\ORMException;
use Doctrine\DBAL\DBALException;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

use Psr\Log\LoggerInterface;

use Doctrine\ORM\EntityManagerInterface;

class ExceptionListener 
{
    private $router;
    private $logger;

    public function __construct($kernel_debug,RouterInterface $router,LoggerInterface $logger, ServiceJournal $sj, EntityManagerInterface $em)
    { 
        $this->kernel_debug = $kernel_debug;
        $this->router = $router;
        $this->logger = $logger;
        $this->sj     = $sj;
        $this->em     = $em;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        $server = $event->getRequest()->server;

        $exception = $event->getThrowable();
        //dd($exception);

        // En mode debug, on affiche l'exception
        // Commenter cette ligne pour récupérer le comportement de la prod !
         if( $this->kernel_debug == true ) return;
 
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

        // On essaie d'aller voir une url sans être authentifié
        elseif ( $exception instanceof HttpException && $exception->getPrevious() instanceof InsufficientAuthenticationException)
        {

            // On garde l'url de destination dans la session
            $event->getRequest()->getSession()->set('url', $event->getRequest()->getUri() );

            // Pas la peine d'encombrer les logs
            //$this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." accès anonyme à la page " . $event->getRequest()->getPathInfo());

            // On renvoie sur l'écran de login'
            if( $this->kernel_debug == true )
                $response =  new RedirectResponse( $this->router->generate('connexion_dbg') );
            else
                $response =  new RedirectResponse( $this->router->generate('connexion') );
            
            $event->setResponse($response);
        }

        //   AccessDeniedHttpException
        //   problème avec access_control dans security.yml (IP par exemple) ou un mauvais rôle
        elseif( $exception instanceof AccessDeniedHttpException /*or $exception instanceof AccessDeniedException*/)
        {
            //dd($exception);
            $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." accès à la page " . $event->getRequest()->getPathInfo() . " non autorisé");
            $response = new RedirectResponse($this->router->generate('accueil') );
            $event->setResponse($response);
        }

        // Erreur 404
        elseif( $exception instanceof NotFoundHttpException )
        {
            // Nous redirigeons vers la page 'accueil' - pas de log
            $response =  new RedirectResponse($this->router->generate('accueil') );
            $event->setResponse($response); 
        }

        // comportement général
        else
        {
            $this->logger->warning("Error to " .  $event->getRequest()->getRequestUri(),
                    [
                    'exception' => $exception,
                    'request' => $event->getRequest()
                    ] );

            $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." Exception " . get_class($exception) . ' : ' . $exception->getMessage() .
                                      "  À partir de URL : " .  $event->getRequest()->getPathInfo() );

            // Nous redirigeons vers la page d'accueil
            $response =  new RedirectResponse($this->router->generate('accueil') );
            $event->setResponse($response); 
        }
    }
}
