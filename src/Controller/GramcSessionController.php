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

use App\Form\IndividuType;
use App\Entity\Version;
use App\Entity\Projet;
use App\Entity\CollaborateurVersion;
use App\Entity\Individu;
use App\Entity\Invitation;
use App\Entity\Scalar;
use App\Entity\Sso;
use App\Entity\CompteActivation;
use App\Entity\Journal;

use App\Utils\Functions;

use App\GramcServices\Etat;
use App\GramcServices\Workflow\Projet\ProjetWorkflow;
use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceIndividus;
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

use Psr\Log\LoggerInterface;

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpKernel\Kernel;

use Twig\Environment;

/////////////////////////////////////////////////////

// Pour connexionsAction
function c_cmp($a, $b): bool
{
    return $a["temps"] > $b["temps"];    
}

class GramcSessionController extends AbstractController
{
    public function __construct(
        private GramcDate $grdt,
        private ServiceIndividus $sid,
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
        private Environment $tw,
        private EntityManagerInterface $em
    ) {}

    /**
     * @Route("/admin/accueil",name="admin_accueil", methods={"GET"})
     * @Security("is_granted('ROLE_OBS') or is_granted('ROLE_PRESIDENT')")
    **/

    public function adminAccueilAction(): Response
    {
        $sm      = $this->sm;
        $menu1[] = $sm->gererIndividu();
        $menu1[] = $sm->gererInvitations();

        $menu2[] = $sm->gererSessions();
        $menu2[] = $sm->bilanSession();

        $menu2[] = $sm->mailToResponsablesRallonge();
        $menu2[] = $sm->mailToResponsables();
        $menu2[] = $sm->mailToResponsablesFiche();

        $menu3[] = $sm->projetsSession();
        $menu3[] = $sm->projetsAnnee();
        $menu3[] = $sm->projetsTous();
        if ($this->getParameter('nodata')==false) {
            $menu3[] = $sm->projet_donnees();
        }
        $menu3[] = $sm->televersementGenerique();

        $menu4[] = $sm->gererFormations();
        $menu4[] = $sm->gererLaboratoires();
        if ($this->getParameter('norattachement')==false) $menu4[] = $sm->gererRattachements();
        $menu4[] = $sm->gererThematiques();
        $menu4[] = $sm->gererMetathematiques();

        $menu5[] = $sm->bilanAnnuel();
        $menu5[] = $sm->statistiques();
        $menu5[] = $sm->publicationsAnnee();

        $menu6[] = $sm->lireJournal();
        $menu6[] = $sm->afficherConnexions();
        if ($this->getParameter('kernel.debug')) $menu6[] = $sm->tempsAvancer();
        $menu6[] = $sm->testerMail();
        $menu6[] = $sm->phpInfo();
        $menu6[] = $sm->nettoyerRgpd();

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
    public function mentionsAction(): Response
    {
        return $this->render('default/mentions.html.twig');
    }

    /**
    * @Route("/aide", name="aide", methods={"GET"} )
    */
    public function aideAction(): Response
    {
        return $this->render('default/aide.html.twig');
    }

    /**
    * @Route("/", name="accueil", methods={"GET"} )
    *
    */
    public function accueilAction(): Response
    {
        $sm = $this->sm;
        $ss = $this->ss;
        $sid = $this->sid;
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

        $token = $this->ts->getToken();
        if ($token != null)
        {
            $individu = $this->ts->getToken()->getUser();
            if (! $sid->validerProfil($individu))
            {
                return $this->redirectToRoute('profil');
            };
        }
        
        if ($seulement_demandeur) {
            return $this->redirectToRoute('projet_accueil');
        } else {
            // juin 2021 -> Suppression des projets tests
            return $this->render(
                'default/accueil.html.twig',
                ['menu' => $menu,
         'projet_test' => false,
         'session' => $session ]
            );
        }
    }

    /**
     * @Route("/president", name="president_accueil", methods={"GET"} )
     * @Security("is_granted('ROLE_PRESIDENT')")
     */
    public function presidentAccueilAction(): Response
    {
        $sm     = $this->sm;
        $menu[] = $sm->affecterExperts();
        
        if ($this->getParameter('noedition_expertise')==false) {
            $menu[] = $sm->commSess();
        }
        $menu[] = $sm->affecterExpertsRallonges();
        /* $menu[] = $sm->affectation_test(); */
        return $this->render('default/president.html.twig', ['menu' => $menu]);
    }

    /**
    * @Route("/profil",name="profil", methods={"GET","POST"})
    * @Security("is_granted('ROLE_DEMANDEUR')")

    **/
    public function profilAction(Request $request): Response
    {
        $sj = $this->sj;
        $em = $this->em;
        
        $individu = $this->ts->getToken()->getUser();

        if ($individu == 'anon.' || ! ($individu instanceof Individu)) {
            return $this->redirectToRoute('accueil');
        }
        $old_individu = clone $individu;
        $form = $this->createForm(IndividuType::class, $individu, ['mail' => false ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
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

            $em->persist($individu);
            $em->flush();

            // On recopie le nouveau profil dans les projets actifs ou en cours d'édition
            $projetRepository = $em->getRepository(Projet::class);
            $cvRepository = $em->getRepository(CollaborateurVersion::class);
            $list_projets = $projetRepository-> getProjetsCollab($individu->getId(), true, true);
            foreach ($list_projets as $projet)
            {
                foreach ($projet->getVersion() as $v)
                {
                    if ($v->getEtatVersion() != Etat::TERMINE && $v->getEtatVersion() != Etat::ANNULE)
                    {
                        
                        foreach ($v->getCollaborateurVersion() as $cv)
                        {
                            $c = $cv->getCollaborateur();
                            if ( $c->isEqualTo($individu))
                            {
                                $cv->setStatut($individu->getStatut());
                                $cv->setLabo($individu->getLabo());
                                $cv->setEtab($individu->getEtab());
                                $em->persist($cv);
                                $em->flush();

                                // Si le responsable a changé de labo il faut poitionner le champ de la version
                                if ($cv->getResponsable())
                                {
                                    $v->setPrjLLabo(Functions::string_conversion($c->getLabo()));
                                    $em->persist($v);
                                    $em->flush();
                                }
                            }
                        }
                    }
                }
            }

            return $this->redirectToRoute('accueil');
        }
        else
        {
            return $this->render('default/profil.html.twig', ['form' => $form->createView() ]);
        }
    }

    /**
    * @Route("/nouveau_compte", name="nouveau_compte", methods={"GET","POST"})
    */
    public function nouveau_compteAction(Request $request, LoggerInterface $lg): Response
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
    public function nouveau_profilAction(Request $request, LoggerInterface $lg): Response
    {
        $sn = $this->sn;
        $sj = $this->sj;
        $em = $this->em;

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
    public function connexionsAction(Request $request): Response
    {
        $em = $this->em;
        $sps = $this->sps;
        $sj = $this->sj;

        $connexions = $sps->getConnexions();
        //dd($connexions);
        
        // On garde seulement la connexion la plus récente pour chaque utilisateur
        $c_uniq = [];
        foreach ($connexions as $c)
        {
            if ($c["user"] == null && $c["rest_user"] == null) continue;
            if ($c["rest_user"] == null)
            {
                $u = $c["user"] . "";
            }
            else
            {
                $u = $c["rest_user"] . "";
            }
            $m = intval($c['minutes']) + 60 * intval($c['heures']);
            $c["temps"] = $m;
            
            if (array_key_exists($u, $c_uniq))
            {
                $cu = $c_uniq[$u];
                if ($cu["temps"] > $c["temps"])
                {
                    $c_uniq[$u] = $c;
                }
            }
            else
            {
                $c_uniq[$u] = $c;
            }
        }

        // On reforme le tableau de connexions, simplifié
        $connexions_uniq = array_values($c_uniq);
        //dd($connexions_uniq);
        usort($connexions_uniq, '\App\Controller\c_cmp');
        
        return $this->render('default/connexions.html.twig', [ 'connexions' => $connexions_uniq ]);
    }

    /**
     * @Route("/phpinfo", name="phpinfo", methods={"GET"})
     * Method({"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     *********************************************/
    public function infoAction(Request $request): Response
    {
        $sf_version = Kernel::VERSION;
        ob_start();
        phpinfo();
        $info = ob_get_clean();
        return $this->render('default/phpinfo.html.twig', [ 'sfversion' => $sf_version, 'info' => $info ]);
    }

    ///////////////////////////////////////////////////////////////////////////////////////

    /**
     * @Route("/{clef}/repinvit", name="repinvit", methods={"GET","POST"})
     */
    public function repinvitAction(Request $request, Invitation $invitation=null): Response
    {
        $em = $this->em;
        $sj = $this->sj;
        $ts = $this->ts;

        // Invitation non valide = on déconnecte et on redirige vers la page d'accueil
        if ( ! $this->validInvit($request, $invitation))
        {
            $ts->setToken(null);
            //$request->getSession()->invalidate();
            return $this->redirectToRoute('accueil');
        }
        
        // Invitation valide - On vérifie les users
        $em = $this->em;
        $invited = $invitation->getInvited();
        $connected = $this->ts->getToken()->getUser();
        
        if ($invited->getId() === $connected->getId())
        {
            // L'invitation est à usage unique, on la supprime
            $em->remove($invitation);
            $em->flush();

            // On oblige l'utilisateur à vérifier son profil
            return $this->redirectToRoute('profil');
        }
        else
        {
            // On supprimera l'invitation dans choisirMail seulement lorsque l'utilisateur
            // aura choisi son mail !
            return $this->choisirMail($request, $connected, $invitation);
        }        
    }

    /*******************************************************************************
     * Vérifie que l'invitation passée en paramètres est valide
     * c'est-à-dire qu'elle existe et qu'elle n'a pas expiré
     *
     * Si non valide: Met un message d'erreur dans le flasjbag et renvoie false
     * Si valide: renvoie true (met ne la supprime pas encore)
     *
     *****************************************************************************/
    private function validInvit(Request $request, Invitation $invitation=null) : bool
    {
        $sj = $this->sj;
        // Invitation supprimée !
        if ( $invitation == null)
        {
            $message = "Cette invitation n'existe pas, ou a été supprimée";
            $request->getSession()->getFlashbag()->add("flash erreur",$message . " - Merci de vous rapprocher de CALMIP");
            $sj->warningMessage(__METHOD__ . ':' . __LINE__ . $message);
            return false;
        }

        // Invitation OK - On vérifie la date
        $now = $this->grdt;
        $invit_duree = $this->getParameter('invit_duree');
        $expiration = $invitation->getCreationStamp()->add(new \DateInterval($invit_duree));

        // On supprime les invitations expirées
        if ($now > $expiration)
        {
            // L'invitation est à usage unique, on la supprime
            $em->remove($invitation);
            $em->flush();
        
            $message = "Cette invitation a expiré ";
            $request->getSession()->getFlashbag()->add("flash erreur",$message . " - Merci de vous rapprocher de CALMIP");
            $sj->warningMessage(__METHOD__ . ':' . __LINE__ . $message . " de " . $invitation->getInviting() . " pour " .$invitation->getInvited());
            return false;
        }

        return true;

    }
    /*************************
     * Fonction appelée par repinvitAction lorsque l'adresse de l'invité ne colle pas avec l'adresse du profil
     **********************************************/
    private function choisirMail(Request $request, Individu $connected, Invitation $invitation): Response
    {
        $em = $this->em;
        $ff = $this->ff;
        $sid = $this->sid;
        $sj = $this->sj;

        $mail_connected = $connected->getMail();
        $mail_invited = $invitation->getInvited()->getMail();
        $form = Functions::createFormBuilder($ff)
                    ->add('mail',
                    ChoiceType::class,
                    [
                        'required' => true,
                        'label'    => '',
                        'expanded' => true,
                        'multiple' => false,
                        'choices' => [ $mail_connected => $mail_connected, $mail_invited => $mail_invited ],
                        'placeholder' => false,
                        'label' => ' '
                    ]
                )
                ->add('OK', SubmitType::class, ['label' => "OK"])
                ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            // L'invitation est à usage unique, on la supprime
            $em->remove($invitation);
            $em->flush();
        
            $mail = $form->getData()['mail'];

            // On fusionne invité -> connecté puis on supprime le connecté
            $sid->fusionnerIndividus($invitation->getInvited(), $connected);
            $em->remove($invitation->getInvited());
            $em->flush();

            // On veut garder le mail de l'invité, il faut donc changer le mail du connecté
            if ($mail === $mail_invited)
            {
                $connected->setMail($mail_invited);
                $em->persist($connected);
                $em->flush();
            }

            // oups qu'est-ce que c'est que cette adresse ?
            // ne devrait jamais arriver
            else if ($mail != $mail_connected)
            {
                $message = "mail connected = " . $connected->getMail() . " - mail invited = " . $invitation->getInvited()->getMail() . " - réponse au formulaire = " . $mail;
                $sj->warningMessage(__METHOD__ . ':' . __LINE__ . $message);
                $request->getSession()->getFlashbag()->add("flash erreur","Problème de mail, rapprochez-vous de " . $this->getParameter('mesoc'));
            }
            return $this->redirectToRoute('profil');
        }

        return $this->render('individu/repinvit.html.twig',
                            ['invitation' => $invitation,
                             'connected' => $connected,
                             'form' => $form->createView()
                            ]);
    }

    /**
     * @Route("/md5", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     **/

    public function md5Action(): Response
    {
        $salt = random_int(1, 10000000000) . microtime();
        $key = md5($salt);
        return new Response('<pre>' . $salt . ' '. $key . '</pre>');
    }

    /**
     * @Route("/uri", methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     **/

    public function uri(Request $request): Response
    {
        $IDPprod    =   $this->getParameter('IDPprod');
        return new Response(Functions::show($IDPprod));
        $output = $request->getUri();
        $output = $request->getPathInfo() ;
        return new Response('<pre>' . $output . '</pre>');
    }

   /**
    * @Route("/admin_red", name="admin_red", methods={"GET"})
    * @Security("is_granted('ROLE_ADMIN')")
    **/
    public function adminRedAction(Request $request): Response
    {
        $request->getSession()->set('admin_red',true);
        return new Response(json_encode('OK'));
    }

   /**
    * @Route("/admin_exp", name="admin_exp", methods={"GET"})
    * @Security("is_granted('ROLE_ADMIN')")
    **/
    public function adminExpAction(Request $request): Response
    {
        $request->getSession()->set('admin_red',false);
        return new Response(json_encode('OK'));
    }
}
