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
use App\GramcServices\ServicePhpSessions;
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

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;


use Symfony\Component\Mailer\Mailer;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Twig\Environment;

/////////////////////////////////////////////////////

class GramcSessionController extends AbstractController
{
    public function __construct(
        private ServiceNotifications $sn,
        private ServiceJournal $sj,
        private ServiceMenus $sm,
        private ServicePhpSessions $sps,
        private ServiceProjets $sp,
        private ServiceSessions $ss,
        private PropositionExpertsType1 $pe1,
        private PropositionExpertsType2 $pe2,
        private GramcDate $sd,
        private ServiceVersions $sv,
        private ProjetWorkflow $pw,
        private FormFactoryInterface $ff,
        private ValidatorInterface $vl,
        private TokenStorageInterface $ts,
        private AuthorizationCheckerInterface $ac,
        private Environment $tw
    ) {}

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
    * @Route("/nouveau_compte", name="nouveau_compte", methods={"GET","POST"})
    */
    public function nouveau_compteAction(Request $request, LoggerInterface $lg)
    {
        $sj = $this->sj;
        $ff = $this->ff;

        // vérifier si eppn est disponible dans $session
        // Sinon, c'est peut-être qu'on est allé à l'URL nouveau_compte sans être authentifié
        if (! $request->getSession()->has('eppn')) {
            $sj->warningMessage(__FILE__ . ":" . __LINE__ . " Pas d'eppn dans session");
            $lg->warning("URL nouveau_compte: Pas d'EPPN", [ 'request' => $request ]);
            return $this->redirectToRoute('accueil');
        }

        // normalement on a un eppn correct dans la sesison
        $eppn = "";
        if ($request->getSession()->has('eppn')) {
            $eppn = $request->getSession()->get('eppn');
        }

        // vérifier si email est disponible dans la session
        $email = "";
        if ($request->getSession()->has('mail')) {
            $email = $request->getSession()->get('mail');
        }

        // On ne vérifie que la présence de l'eppn, pas sa conformité
        // Ce qu'on appelle eppn peut être un autre header (persistent-id par exemple)'
        if ($eppn === "") {
            $sj->warningMessage(__FILE__ . ":" . __LINE__ . " eppn défectueux pour le nouveau compte (eppn=$eppn, mail=$email)");
            return $this->redirectToRoute('accueil');
        };

        // $email = "";
        // Mauvaise adresse - Pas d'ouverture de compte
        if ($email != "" && !$this->isEmail($email))
        {
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
        if ($individu === null)
        {
            $flg_ind = false;
            $individu = new Individu();
            $individu->setMail($session->get('mail'));
            if ($session->has('sn'))
            {
                $individu->setNom($session->get('sn'));
            }
        }
        else
        {
            if ($individu->getDesactive())
            {
                $sj->errorMessage(__METHOD__ .':' . __LINE__ . " $individu est désactivé - eppn $eppn refusé !");
                return $this->redirectToRoute('accueil');
            }
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

            // On supprime ces données afin de refaire complètement le processus de connexion
            $session = $request->getSession();
            $session->remove('eppn');
            $session->remove('mail');
            return $this->redirectToRoute('connexionshiblogin');
        }
        return $this->render('default/nouveau_profil.html.twig', array( 'mail' => $request->getSession()->get('mail'), 'form' => $form->createView()));
    }

    /**
     * @Route("/connexions", name="connexions", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function connexionsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $sps = $this->sps;
        $sj = $this->sj;

        $connexions = $sps->getConnexions();
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
}
