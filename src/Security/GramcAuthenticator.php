<?php

namespace App\Security;

use App\Entity\Individu;
use App\Entity\Sso;
use App\GramcServices\ServiceJournal;

use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
//use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Doctrine\ORM\EntityManagerInterface;

// Voir ici pour comprendre ce fichier:
// https://www.youtube.com/watch?v=wCvGbv6E0AI

class GramcAuthenticator extends AbstractAuthenticator
{
    private $kernel_debug = null;
    private $em = null;
    private $sj = null;
    private $urg = null;

    public function __construct($knl_debug, EntityManagerInterface $em, ServiceJournal $sj, UrlGeneratorInterface $urg)
    {
        $this->knl_debug = $knl_debug;
        $this->em = $em;
        $this->sj = $sj;
        $this->urg = $urg;
    }
    
    /**
     * Does the authenticator support the given Request?
     *
     * If this returns false, the authenticator will be skipped.
     *
     * Returning null means authenticate() can be called lazily when accessing the token storage.
     */
    public function supports(Request $request): ?bool
    {
        $rvl = false;
        if ($request->attributes->get('_route') === 'connexionshiblogin' && $request->isMethod('GET')) $rvl = true;
        if ($request->attributes->get('_route') === 'connexion_dbg' && $request->isMethod('POST')) $rvl = true;
        return $rvl;
    }

    /**
     * Create a passport for the current request.
     *
     * The passport contains the user, credentials and any additional information
     * that has to be checked by the Symfony Security system. For example, a login
     * form authenticator will probably return a passport containing the user, the
     * presented password and the CSRF token value.
     *
     * You may throw any AuthenticationException in this method in case of error (e.g.
     * a UserNotFoundException when the user cannot be found).
     *
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): Passport
    {
        // vous devez avoir utilisé le paramètre
        // ShibUseHeaders On
        // dans la configuration apache
        //dd($request);

        // Récupérer user/mail dans les headers
        // TODO - Pourquoi on ne peut pas récupérer REMOTE_USER ?
        $remote_user = $request->headers->get('eppn');
        $mail = $request->headers->get('mail');

        // POUR LES TESTS UNIQUEMENT - On simule l'absence de eppn ou de mail
        // $remote_user = null;
        // $mail = null;
        // $mail = null;

        // Pas d'eppn, on essaie persistent-id
        // TODO - L'authentifiant s'appelle eppn même si le header est persistent-id, ce n'est pas très propre
        if (empty($remote_user))
        {
            $remote_user = $request->headers->get('persistent-id');
        }

        // POUR LES TESTS UNIQUEMENT - On prend un header stupide
        //if (empty($remote_user))
        //{
        //    $remote_user = $request->headers->get('x-real-ip');
        //}

        // $this->sj->debugMessage(__FILE__ . ":" . __LINE__ . " eppn=$remote_user, mail=$mail ");

        // Auhentification Shibboleth
        if (! empty($remote_user))
        {
            // Mettre les authentifiants dans la session
            $request->getSession()->set('eppn',$remote_user);
            $request->getSession()->set('mail',$mail);

            $repository = $this->em->getRepository(Sso::class);
            $sso = $repository->findOneBy(['eppn' => $remote_user]);

            // Pas de sso --> nouveau compte ou nouvel eppn !
            if ($sso == null)
            {
                // Récupérer les autres headers utiles dans la session
                $this->shibbHeadersToSession($request);
                throw new UsernameNotFoundException();
            }
            $individu = $sso->getIndividu();
            if ($individu instanceof Individu)
            {
                return new SelfValidatingPassport(new UserBadge($individu->getIdIndividu()),
                [
                    new GramcBadge($this->sj, $individu)
                ]);
            }
            else
            {
                // Ecrit le eppn dans le journal et refuse l'authentification
                // On recherche les headers: eppn, subject_id, persistent_id et pairwise_id
                // TODO - Une fonction privée, pour éviter le copier-coller (cf. ligne 250))
                $eppn = $request->headers->get('eppn');
                $persistent_id = $request->headers->get('persistent-id');
                $targeted_id  = $request->headers->get('targeted-id');
                $subject_id = $request->headers->get('subject-id');
                $pairwise_id = $request->headers->get('pairwise-id');
                $this->sj->warningMessage("Un utilisateur a tenté de se connecter - remote_user = $remote_user, mail = $mail");
                $this->sj->warningMessage("--> eppn = $eppn");
                $this->sj->warningMessage("--> persistent-id = $persistent_id");
                $this->sj->warningMessage("--> targeted-id = $targeted_id");
                $this->sj->warningMessage("--> subject-id = $subject_id");
                $this->sj->warningMessage("--> pairwise-id = $pairwise_id");
                throw new UsernameNotFoundException("votre compte n'est pas encore opérationnel");
            }
        }

        // On essaie ensuite le formulaire d'authentification bidon
        // Seulement si on est en debug
        if ($this->knl_debug)
        {
            $form_data = $request->get("form");
            if ($form_data != null)
            {
                if ( isset($form_data['data']) && $form_data['data'] != null)
                {
                    $idIndividu = $form_data['data'];
                    $repository = $this->em->getRepository(Individu::class);
                    $individu = $repository->findOneBy( ['idIndividu' => $idIndividu]);
                    if ($individu instanceof Individu)
                    {
                        return new SelfValidatingPassport(new UserBadge($individu->getIdIndividu()),
                        [
                            new CsrfTokenBadge('form',$form_data['_token']),
                            new GramcBadge($this->sj, $individu)
                        ]);
                    }
                }
            }
        }

        // Si on arrive là c'est que ça n'a pas fonctionné
        throw new UsernameNotFoundException();
    }

    /**
     * Create an authenticated token for the given user.
     *
     * If you don't care about which token class is used or don't really
     * understand what a "token" is, you can skip this method by extending
     * the AbstractAuthenticator class from your authenticator.
     *
     * @see AbstractAuthenticator
     *
     * @param Passport $passport The passport returned from authenticate()
     */
    //public function createAuthenticatedToken(Passport $passport, string $firewallName): TokenInterface
    
    /**
     * Called when authentication executed and was successful!
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the last page they visited.
     *
     * If you return null, the current request will continue, and the user
     * will be authenticated. This makes sense, for example, with an API.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $individu = $token->getUser();
        $this->sj->infoMessage($token->getUser() . " vient de s'authentifier");
        // $request->getSession()->getFlashbag()->add("flash info","Vous êtes authentifié");

        return null;
    }

    /**
     * Called when authentication executed, but failed (e.g. wrong username password).
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the login page or a 403 response.
     *
     * If you return null, the request will continue, but the user will
     * not be authenticated. This is probably not what you want to do.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        //dd($exception);
        //dd($request);

        // S'il y a eppn ET mail dans la session on redirige vers nouveau_compte
        if ($request->getSession()->has('eppn') && $request->getSession()->has('mail'))
        //if (0)
        {
            return new RedirectResponse($this->urg->generate('nouveau_compte'));
        }

        // Sinon nous avons un problème d'authentification
        else {
            $log_msg = " Erreur d'authentification - ";
            if ($request->getSession()->has('eppn'))
            {
                $eppn = $request->getSession()->get('eppn');
                $log_msg .= "eppn = $eppn ";
            }
            else
            {
                $log_msg .= "PAS D'EPPN ";
            }
            if ($request->getSession()->has('mail'))
            {
                $mail = $request->getSession()->get('mail');
                $log_msg .= "mail = $mail ";
            }
            else
            {
                $log_msg .= "PAS DE MAIL ";
            }
            $log_msg .= "HTTP_REFERER = " . $request->server->get('HTTP_REFERER');
            $this->sj->warningMessage(__FILE__ . ":" . __LINE__ . $log_msg);
            $eppn = $request->headers->get('eppn');
            $persistent_id = $request->headers->get('persistent-id');
            $targeted_id  = $request->headers->get('targeted-id');
            $subject_id = $request->headers->get('subject-id');
            $pairwise_id = $request->headers->get('pairwise-id');
            $this->sj->warningMessage("--> eppn = $eppn");
            $this->sj->warningMessage("--> persistent-id = $persistent_id");
            $this->sj->warningMessage("--> targeted-id = $targeted_id");
            $this->sj->warningMessage("--> subject-id = $subject_id");
            $this->sj->warningMessage("--> pairwise-id = $pairwise_id");
            
            $message = "ERREUR d'AUTHENTIFICATION";
            $request->getSession()->getFlashbag()->add("flash erreur",$message . " - Merci de vous rapprocher de CALMIP");
            return new RedirectResponse($this->urg->generate('accueil'));
        }
    }

    ////////////////////////////////////////////////////////////////

    /*
     * Déposer dans la session les headers fournis par Shibboleth
     * NOTE - On ne s'occupe pas de eppn, cela est déjà fait par auth_connexionAction
     *        Côté Fédération, on doit envoyer les attributs correspondants
     *        Conf Shibboleth: il faut modifier le fichier attribute-map.xml (ie décommenter quelques lignes
     *        vers Other eduPerson attributes)
     *
     ***/
    private function shibbHeadersToSession(Request $request) {
        $headers = ['givenName', 'sn', 'displayName', 'cn', 'affiliation', 'primary-affiliation'];
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
}

