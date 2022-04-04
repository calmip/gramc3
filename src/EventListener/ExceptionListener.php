<?php



/****
 * Ce service traite les exceptionsss
 ************/
 
// src/EventListener/ExceptionListener.php
namespace App\EventListener;

use App\Entity\Individu;
use App\Utils\Functions;
use App\GramcServices\ServiceJournal;

use App\Exception\UserException;

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
        // Commenter ces trois lignes pour voir ce qui se passe en prod !
        if( $this->kernel_debug == true ) {
            $response =  new Response( '<pre>' . $exception . '</pre>');
            return $response;
        }
 
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

        //   AccessDeniedHttpException
        //   problème avec access_control dans security.yml (IP par exemple) ou un mauvais rôle
        elseif( $exception instanceof AccessDeniedHttpException /*or $exception instanceof AccessDeniedException*/)
        {
            //dd($exception);
            $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." accès à la page " . $event->getRequest()->getPathInfo() . " non autorisé");
            $response =  new RedirectResponse($this->router->generate('index') );
            $event->setResponse($response);
        }
        // pas de rôle pour le moment
        // elseif( $exception instanceof InsufficientAuthenticationException )
        elseif( $exception instanceof InsufficientAuthenticationException )
        {
            $event->getRequest()->getSession()->set('url', $event->getRequest()->getUri() );
            
            $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." accès anonyme à la page " . $event->getRequest()->getPathInfo());
        
            if( $this->kernel_debug == true )
                $response =  new RedirectResponse( $this->router->generate('connexion_dbg') );
            else
                $response =  new RedirectResponse( $this->router->generate('connexion') );
            
            //$event->setResponse($response);    
        }

        elseif( $exception instanceof NotFoundHttpException )
        {
            // uniquement en production nous redirigeons vers la page 'accueil' - pas de log
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

            $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." Exception " . get_class($exception) . ' : ' . $exception->getMessage() .
                                      "  À partir de URL : " .  $event->getRequest()->getPathInfo() );

           // uniquement en production nous redirigeons vers la page 'index'
           if( $this->kernel_debug == false )
           {
                $response =  new RedirectResponse($this->router->generate('accueil') );
                $event->setResponse($response); 
           }
            
        }
    }
}
