<?php

namespace App\Controller;

use App\Utils\IDP;
use App\Utils\Functions;
use App\Entity\Scalar;
use App\Entity\Individu;
use App\Entity\Journal;

use App\GramcServices\ServiceJournal;
//use App\GramcServices\ServiceNotificationsGramce;


use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
//use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Login controller.
 * 
 */
 
class LoginController extends AbstractController 
{
    //const NOTIFICATION_NEW_EPPN  = 100;
    //const NOTIFICATION_CHGT_EPPN = 101;
    //const NOTIFICATION_NO_MAIL   = 102;
    //const NOTIFICATION_AUTH_PB   = 103;
    
    private $sj;
    private $ff;
    
    public function __construct(ServiceJournal $sj, FormFactoryInterface $ff)
    {
        $this->sj = $sj;
        $this->ff = $ff;
    }

    /** 
     * @Route("/login", name="connexionshiblogin",methods={"GET"})
     * Method({"GET"})
     */
    public function shibloginAction(Request $request)
    {
        $this->sj->InfoMessage("shiblogin d'un utilisateur");
        //dd($request);
        if( $request->getSession()->has('url') )
            return $this->redirect( $request->getSession()->get('url') );
        else
            return $this->redirectToRoute('accueil');
    }

    /** 
     * @Route("/deconnect", name="deconnexion",methods={"GET"})
     * Method({"GET"})
     */
    public function deconnexionAction(Request $request)
    {
        // controller can be blank: it will never be executed!
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
        //return $this->redirectToRoute('deconnected');
    }

    /** 
     * @Route("/deconnected", name="deconnected",methods={"GET"})
     * Method({"GET"})
     */

     // TODO - Personnaliser le processus de logout - Pour l'instant je ne sais pas faire
    public function deconnectedAction(Request $request)
    {
        return $this->render('login/deconnected.html.twig');
    }

    /** 
     * @Route("/erreur_login", name="erreur_login",methods={"GET"})
     * Method({"GET"})
     */
    public function erreur_loginAction(Request $request)
    {
        return $this->render('login/erreur_login.html.twig');
    }

    /**
     * @Route("/login_choice", name="connexion", methods={"GET","POST"})
     *
     * Method({"GET", "POST"})
     */
    public function loginChoiceAction(Request $request)
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
    public function connexion_dbgAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $ff = $this->ff;
        
        if ($this->getParameter('kernel.debug') === false) {
            $sj->errorMessage(__METHOD__ . ':' . __LINE__ .' tentative de se connecter connection_debug - Mode DEBUG FALSE');
            return $this->redirectToRoute('accueil');
        }

        // Etablir la liste des users pouvant se connecter de cette manière
        $repository = $this->getDoctrine()->getRepository(Individu::class);
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
}
