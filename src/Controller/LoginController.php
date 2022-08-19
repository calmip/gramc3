<?php

namespace App\Controller;

//use App\Utils\IDP;
use App\Utils\Functions;
use App\Entity\Scalar;
use App\Entity\Individu;
use App\Entity\Journal;

use App\GramcServices\ServiceJournal;


use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Login controller.
 * 
 */
 
class LoginController extends AbstractController 
{
    public function __construct(private ServiceJournal $sj,
                                private FormFactoryInterface $ff,
                                private AuthorizationCheckerInterface $ac,
                                private TokenStorageInterface $ts,
                                private EntityManagerInterface $em)
    {}

    /** 
     * @Route("/login", name="connexionshiblogin",methods={"GET"})
     * Method({"GET"})
     */
    public function shibloginAction(Request $request): Response
    {
        $this->sj->InfoMessage("shiblogin d'un utilisateur");
        //dd($request);
        if( $request->getSession()->has('url') )
            return $this->redirect( $request->getSession()->get('url') );
        else
            return $this->redirectToRoute('accueil');
    }

    /**
     * @Route("/deconnexion",name="deconnexion", methods={"GET"})
     *
     * Si on est en sudo, revient en normal, sinon invalide la session
     * NOTE - NE PAS renseigner logout: dans security.yaml !
     * 
     **/
    public function deconnexionAction(Request $request): Response
    {
        $sj = $this->sj;
        $ac = $this->ac;
        $ts = $this->ts;
        $session = $request->getSession();

        // En sudo: on revient à l'utilisateur précédent
        if ($ac->isGranted('IS_IMPERSONATOR'))
        {
            $sudo_url = $session->get('sudo_url');
            $real_user = $ts->getToken()->getOriginalToken()->getUser();
            $sj->infoMessage(__METHOD__ . ":" . __LINE__ . " déconnexion d'un utilisateur en SUDO vers " . $real_user);
            return new RedirectResponse($sudo_url . '?_switch_user=_exit');
            
        }

        // Pas sudo: on remet token et session à zéro
        elseif ($ac->isGranted('IS_AUTHENTICATED_FULLY')) {
            $sj->infoMessage(__METHOD__ . ":" . __LINE__ .  " déconnexion de l'utilisateur " . $ts->getToken()->getUser());
            $ts->setToken(null);
            $session->invalidate();
            return $this->render('default/deconnexion.html.twig');
        }

        // On a cliqué sur Déconnecter alors qu'on n'est pas connecté
        else {
            return new RedirectResponse($this->generateUrl('accueil'));
        }
    }

    /** 
     * @Route("/erreur_login", name="erreur_login",methods={"GET"})
     * Method({"GET"})
     */
    public function erreur_loginAction(Request $request): Response
    {
        return $this->render('login/erreur_login.html.twig');
    }

    /**
     * @Route("/login_choice", name="connexion", methods={"GET","POST"})
     *
     * Method({"GET", "POST"})
     */
    public function loginChoiceAction(Request $request): Response
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
            ->add('connect', SubmitType::class, ['label' => 'Connexion *'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $url    =   $request->getSchemeAndHttpHost();
            $url    .= '/Shibboleth.sso/Login?target=';
            $url    .= $this->generateUrl('connexionshiblogin');

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
    * @Route("/connexion_dbg",name="connexion_dbg", methods={"GET","POST"})
    **/
    public function connexion_dbgAction(Request $request): Response
    {
        $em = $this->em;
        $ff = $this->ff;
        
        if ($this->getParameter('kernel.debug') === false) {
            $sj->errorMessage(__METHOD__ . ':' . __LINE__ .' tentative de se connecter connection_debug - Mode DEBUG FALSE');
            return $this->redirectToRoute('accueil');
        }

        // Etablir la liste des users pouvant se connecter de cette manière
        $repository = $this->em->getRepository(Individu::class);
        $experts    = $repository->findBy(['expert'   => true ]);
        $admins     = $repository->findBy(['admin'    => true ]);
        $obs        = $repository->findby(['obs'      => true ]);
        $sysadmins  = $repository->findby(['sysadmin' => true ]);
        $users      = array_unique(array_merge($admins, $experts, $obs, $sysadmins));

        // TODO - Il doit y avoir plus élégant
        $choices = [];
        foreach ($users as $u) {
            $choices[$u->getPrenom() . ' ' . $u->getnom()] = $u->getIdIndividu();
        }
        ksort($choices);
    
        //dd($choices);

        $form = Functions::createFormBuilder($ff)
            ->add(
                'data',
                ChoiceType::class,
                [
             'choices' => $choices
             ]
            )
            ->add('connect', SubmitType::class, ['label' => 'Connexion'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            // Rediriger là où on veut aller
            if( $request->getSession()->has('url') )
                return $this->redirect( $request->getSession()->get('url') );

            // Ou vers l'accueil
            else
                return $this->redirectToRoute('accueil');
            
        }
                         
        return $this->render('login/connexion_dbg.html.twig', array( 'form' => $form->createView())  );
    }

    /**
     * Sudo (l'admin change d'identité)
     *
     * @Route("/{id}/sudo", name="sudo", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function sudoAction(Request $request, Individu $individu): Response
    {
        $sj = $this->sj;
        $ac = $this->ac;
        if (! $ac->isGranted('IS_IMPERSONATOR')) {
            $session = $request->getSession();
            $sudo_url = $request->headers->get('referer');
            $session->set('sudo_url',$sudo_url);
            $sj->infoMessage("Controller : connexion de l'utilisateur " . $individu . ' en SUDO ');
            return new RedirectResponse($this->generateUrl('accueil', [ '_switch_user' => $individu->getId() ]));
        } else {
            $sj->warningMessage("Controller : connexion de l'utilisateur " . $individu . ' déjà en SUDO !');
            return $this->redirectToRoute('individu_gerer');
        }
    }
}
