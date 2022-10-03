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
 * class Menu = Permet d'afficher des liens html vers différents controleurs.
 *              Chaque fonction renvoit un tableau qui sera repris pour affichage par le twig
 *              Voir la macro menu dans macros.html.twig
 *
 * Clés du tableau menu:
 *      name             -> Nom du controleur symfony
 *      lien             -> Texte du lien html
 *      commentaire      -> Ce que fait le controleur en une phrase
 *      ok               -> Si true le lien est actif sinon le lien est inactif
 *      reason           -> Si le lien est inactif, explication du pourquoi. Pas utilisé si le lien est inactif
 *      todo (optionnel) -> Si le lien est actif, permet de visualiser le menu sous forme de todo liste - cf. consulter.html.twig, vers la ligne 20
 *
 *****************************************************************************************************************************************************/

namespace App\GramcServices;

use App\Entity\Session;
use App\Entity\Projet;
use App\Entity\Rallonge;
use App\Entity\Version;
use App\Entity\Individu;
use App\Entity\RapportActivite;
use App\GramcServices\Etat;
use App\GramcServices\Signal;
use App\Utils\Functions;
use App\GramcServices\Workflow\Session\SessionWorkflow;

// TODO - Pas bien beau à modifier !
use App\Controller\VersionModifController;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;

class ServiceMenus
{
    private $token = null;
    
    public function __construct(
        private $max_rall,
        private $nodata,
        private ServiceVersions $sv,
        private ServiceProjets $sp,
        private ServiceJournal $sj,
        private SessionWorkflow $sw,
        private GramcDate $grdt,
        private ValidatorInterface $sval,
        private ServiceSessions $ss,
        private TokenStorageInterface $tok,
        private AuthorizationCheckerInterface $ac,
        private EntityManagerInterface $em
    ) {
        $this->token = $this->tok->getToken();
    }

    /*****************************************************
     * Gestion de la priorité des boutons:
     *     - Si ok on fixe la priorité suivant le paramètre $priorite
     *     - Si pas ok ou pas spécifié on la met à BASSE
     *
     ******/

    public const BPRIO = 2; // Basse priorité il faudra cliquer sur un voir plus pour afficher
    public const HPRIO = 1; // Haute priorité on verra toujours le bouton
    
    private function __prio(array &$menu, int $priorite): void
    {
        if (isset($menu['ok']) && $menu['ok']==true)
        {
            $menu['priorite'] = $priorite;
        }
        else
        {
            $menu['priorite'] = self::BPRIO;
        }
    }

    /*******************
     * Page d'accueil principale
     ***************************************************/
    public function demandeur(int $priorite=self::HPRIO): array
    {
        $menu['name']      = 'projet_accueil';
        $menu['lien']      = 'Demandeur';
        if ($this->ac->isGranted('ROLE_DEMANDEUR')) {
            $menu['ok']        = true;
            $menu['commentaire'] = 'Espace demandeurs';
        } else {
            $menu['ok']          = false;
            $menu['commentaire'] = "Vous ne pouvez pas rejoindre l'espace demandeur ";
            $menu['raison']      = "vous n'êtes pas connecté";
        }
        
        $this->__prio($menu, $priorite);
        return $menu;
    }

    public function expert(int $priorite=self::HPRIO):array
    {
        $menu['name']      = 'expertise_liste';
        $menu['lien']      = 'Expert';
        if ($this->ac->isGranted('ROLE_EXPERT')) {
            $menu['ok']          = true;
            $menu['commentaire'] = 'Espace expertise';
        } else {
            $menu['ok']          = false;
            $menu['commentaire'] = "Vous ne pouvez pas rejoindre l'espace expertise";
            $menu['raison']      = "vous n'êtes pas connecté, ou vous n'avez pas les droits expert";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    public function administrateur(int $priorite=self::HPRIO):array
    {
        $menu['name']      = 'admin_accueil';
        $menu['lien']      = 'Administrateur';
        if ($this->ac->isGranted('ROLE_OBS')) {
            $menu['ok']          = true;
            $menu['commentaire'] = 'Espace administrateur';
        } else {
            $menu['ok']          = false;
            $menu['commentaire'] = "Vous ne pouvez pas rejoindre l'espace administrateur";
            $menu['raison']      = "vous n'êtes pas connecté, ou vous n'avez pas les droits administrateur";
        }
        
        $this->__prio($menu, $priorite);
        return $menu;
    }

    public function president(int $priorite=self::HPRIO):array
    {
        $menu['name']      = 'president_accueil';
        $menu['lien']      = 'Président';
        if ($this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']          = true;
            $menu['commentaire'] = "Espace Président du Comité d'Attribution";
        } else {
            $menu['ok']          = false;
            $menu['commentaire'] = "Vous ne pouvez pas rejoindre l'espace président";
            $menu['raison']      = "vous n'êtes pas connecté, ou vous n'avez pas les droits du président";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    public function aide(int $priorite=self::HPRIO):array
    {
        $menu['name']      = 'aide';
        $menu['lien']      = '?';
        $menu['ok']          = true;
        $menu['commentaire'] = "Aide et documentation";

        $this->__prio($menu, $priorite);
        return $menu;
    }

    /*******************
     * Gestion de la session
     ***************************************************/

    // Nouvelle session
    public function ajouterSession(int $priorite=self::HPRIO):array
    {
        $session      = $this->ss->getSessionCourante();
        $etat_session = $session->getEtatSession();
        $workflow     = $this->sw;
        $menu['name'] = 'ajouter_session';
        $menu['lien'] = "Nouvelle session";
        if ($etat_session === Etat::ACTIF) {
            $menu['ok']          = true;
            $menu['commentaire'] = 'Nouvelle session';
        } else {
            $menu['commentaire'] = "Pas possible de créer une nouvelle session";
            $menu['ok']          = false;
            $menu['raison']      = "La session courante n'est pas encore activée";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    // Modifier la session
    public function modifierSession(int $priorite=self::HPRIO):array
    {
        $session        = $this->ss->getSessionCourante();
        $workflow       = $this->sw;
        $menu['name']   = 'modifier_session';
        $menu['params'] = [ 'id' => $session->getIdSession() ];
        $menu['lien']   = "Modifier la session";
        if ($workflow->canExecute(Signal::DAT_DEB_DEM, $session)) {
            $menu['ok']          = true;
            $menu['commentaire'] = 'Modifier les paramètres de la session';
        } else {
            $menu['commentaire'] = 'Pas possible de modifier la session.';
            $menu['ok']          = false;
            $menu['raison']      = 'La session a déjà démarré !';
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    // Début de la saisie
    public function demarrerSaisie(int $priorite=self::HPRIO):array
    {
        $session        = $this->ss->getSessionCourante();
        $workflow       = $this->sw;
        $menu['name']   = 'session_avant_changer_etat';
        $menu['lien']   = "Demandes";
        $menu['params'] = [ 'ctrl' => 'demarrer_saisie'];

        if ($workflow->canExecute(Signal::DAT_DEB_DEM, $session)) {
            $menu['ok']          = true;
            $menu['commentaire'] = 'Début de la saisie des demandeurs';
        } else {
            $menu['commentaire'] = 'Pas possible de débuter la saisie des projets';
            $menu['ok']          = false;
            $menu['raison']      = 'La période est déjà passée !';
        }
        
        $this->__prio($menu, $priorite);
        return $menu;
    }

    // Fin de la saisie
    public function terminerSaisie(int $priorite=self::HPRIO):array
    {
        $session        = $this->ss->getSessionCourante();
        $workflow       = $this->sw;
        $menu['name']   = 'session_avant_changer_etat';
        $menu['lien']   = "Expertises";
        $menu['params'] = [ 'ctrl' => 'terminer_saisie'];

        if ($workflow->canExecute(Signal::DAT_FIN_DEM, $session)) {
            $menu['ok']          = true;
            $menu['commentaire'] = "Début d'expertise des projets";
        } else {
            $menu['commentaire'] = 'Pas possible de passer les projets en expertise';
            $menu['ok']          = false;
            $menu['raison']      = 'La session n\'est pas en période de saisie des projets';
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    // Envoyer les expertises
    public function envoyerExpertises(int $priorite=self::HPRIO):array
    {
        $session        = $this->ss->getSessionCourante();
        $workflow       = $this->sw;
        $menu['name']   = 'session_avant_changer_etat';
        $menu['lien']   = 'Envoi des expertises';
        $menu['params'] = [ 'ctrl' => 'envoyer_expertises'];

        if ($workflow->canExecute(Signal::CLK_ATTR_PRS, $session)  &&  $session->getcommGlobal() != null) {
            $menu['ok']          = true;
            $menu['commentaire'] = "Envoyer les expertises";
        } else {
            $menu['ok']          = false;
            $menu['commentaire'] = "Impossible d'envoyer les expertises";
            
            if ($session->getCommGlobal() == null) {
                $menu['raison']  = "Il n'y a pas de commentaire de session (menu Président)";
            } else {
                $menu['raison']  = "La session n'est pas en \"expertise\"";
            }
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    // Commentaire de session - accessible à partir de l'écran Président
    public function commSess(int $priorite=self::HPRIO):array
    {
        $session      = $this->ss->getSessionCourante();
        $workflow     = $this->sw;

        $menu['name'] = 'session_commentaires';
        $menu['lien'] = "Commentaire de session ($session)";

        if (!$this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']          = false;
            $menu['commentaire'] = "Vous ne pouvez pas ajouter le commentaire de session";
            $menu['raison']      = "Vous n'êtes pas président";
        }
        else
        {
            if (! $workflow->canExecute(Signal::CLK_ATTR_PRS, $session)) {
                $menu['ok']          = false;
                $menu['commentaire'] = "Vous ne pouvez pas ajouter le commentaire de session";
                $menu['raison']      = "La session n'est pas en phase d'expertise";
            } else {
                $menu['ok']          = true;
                $menu['commentaire'] = "Commentaire de session et fin de la phase d'expertise";
            }
        }
        $this->__prio($menu, $priorite);
        return $menu;
    }

    // Activer la session
    public function activerSession(int $priorite=self::HPRIO):array
    {
        $session        = $this->ss->getSessionCourante();
        $workflow       = $this->sw;

        $menu['name']   = 'session_avant_changer_etat';
        $menu['lien']   = "Activation";
        $menu['params'] = [ 'ctrl' => 'activer_session'];

        // NOTE - Le workflow accepte d'activer la session plusieurs fois, du coup il faut tester l'état ici
        if (! $workflow->canExecute(Signal::CLK_SESS_DEB, $session) || $session->getEtatSession() == Etat::ACTIF) {
            $menu['ok']          = false;
            $menu['commentaire'] = "Vous ne pouvez pas activer la session pour l'instant";
            $menu['raison']      = "La session n'est pas en attente";
        } else {
            $menu['ok']          = true;
            $menu['commentaire'] = "Activer la session $session";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    /***********************************************************************
     * 
     * Transformation d'un projet FIL ( ou projet test) en projet de session
     *     - Seulement lors des sessions d'attribution
     *     - La transformation inverse n'est pas possible
     *
     ***********************************************************************/
    public function transformerProjet(Projet $projet, int $priorite=self::BPRIO):array
    {
        $token = $this->token;
        $user = $token->getUser();
        
        $menu = [];
        $menu['commentaire'] = "Impossible pour l'instant";
        $menu['name'] = 'avant_transformer';
        $menu['params'] = [ 'projet' => $projet ];
        $menu['lien'] = 'Transformer';
        $menu['ok'] = false;

        $session = $this->ss->getSessionCourante();
        if ($session == null) {
            $menu['raison'] = "Il n'y a pas de session courante";
        }
        else
        {
            $etat_session   =   $session->getEtatSession();
    
            if ($user == null)
            {
                $menu['raison'] = "Connexion anonyme ?";
            }
            elseif (! $user->peutCreerProjets())
            {
                $menu['raison'] = "Commencez par changer de responsable, le responsable doit être membre permanent d'un laboratoire enregistré";
            }
            elseif ($etat_session == Etat::EDITION_DEMANDE)
            {
                $menu['raison'] = '';
                $menu['commentaire'] = "Confirmez le test, créez un VRAI projet !";
                $priorite = $priorite=self::HPRIO;
                $menu['ok'] = true;
            }
            else
            {
                $menu['raison'] = 'Nous ne sommes pas en période de demande';
            }
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    /*******************
     * Gestion des projets et des versions
     ***************************************************/

    public function nouveauProjet($type, int $priorite=self::HPRIO):array
    {
        switch ($type) {
        case Projet::PROJET_FIL:
        return $this->nouveauProjetFil($priorite);
        break;
        case Projet::PROJET_SESS:
        return $this->nouveauProjetSess($priorite);
        break;
        case Projet::PROJET_TEST:
        return $this->nouveauProjetTest($priorite);
        break;
    }
    }

    /*
     * Création d'un projet de type PROJET_SESS:
     *     - Peut être créé seulement lors des sessions d'attribution
     *     - Renouvelable à chaque session
     *     - Créé seulement par un permanent, qui devient responsable du projet
     *
     */
    private function nouveauProjetSess(int $priorite=self::HPRIO):array
    {
        $menu   =   [];
        $menu['commentaire']    =   "Vous ne pouvez pas créer de nouveau projet actuellement";
        $menu['name']   =   'avant_nouveau_projet';
        $menu['params'] =   [ 'type' =>  Projet::PROJET_SESS ];
        $menu['lien']   =   'Nouveau projet';
        $menu['ok'] = false;

        $session =  $this->ss->getSessionCourante();
        if ($session == null) {
            $menu['raison'] = "Il n'y a pas de session courante";
        }
        else
        {
            $etat_session   =   $session->getEtatSession();
            if (! $this->peutCreerProjets())
            {
                $menu['raison'] = "Seuls les personnels permanents des laboratoires enregistrés peuvent créer un projet";
            }
            elseif ($etat_session == Etat::EDITION_DEMANDE)
            {
                $menu['raison'] = '';
                $menu['commentaire'] = "Créez un nouveau projet, vous en serez le responsable";
                $menu['ok'] = true;
            }
            else
            {
                $menu['raison'] = 'Nous ne sommes pas en période de demande, pas possible de créer un nouveau projet';
            }
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    /*
     * Création d'un projet de type PROJET_TEST:
     *     - Peut être créé seulement EN-DEHORS des sessions d'attribution
     *     - Non renouvelable
     *     - Créé par n'importe qui, qui devient responsable du projet
     *
     */
    public function nouveauProjetTest(int $priorite=self::HPRIO):array
    {
        $menu   =   [];
        $menu['commentaire']    =   "Vous ne pouvez pas créer de nouveau projet test actuellement";
        $menu['name']   =   'nouveau_projet';
        $menu['params'] =   [ 'type' =>  Projet::PROJET_TEST ];
        $menu['lien']   =   'Nouveau projet test';
        $menu['ok'] = false;

        $session =  $this->ss->getSessionCourante();
        if ($session == null)
        {
            $menu['raison'] = "Il n'y a pas de session courante";
        }
        else
        {
            //$etat_session   =   $session->getEtatSession();
            $user = $this->token->getUser();
            if (! $user instanceof Individu)
            {
                $menu['raison'] = "Vous n'êtes pas connecté";
    
            }
            elseif ($this->em->getRepository(Projet::class)->countProjetsTestResponsable($user) > 0)
            {
                $menu['raison'] = "Vous êtes déjà responsable d'un projet test";
            }
            else
            {
                $menu['commentaire'] = "Créer un projet test: 5000h max, uniquement pour faire des essais et avoir une idée du nombre d'heures dont vous avez besoin.";
                $menu['ok'] = true;
            }
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    /*
     * Création d'un projet de type PROJET_FIL:
     *     - Peut être créé n'importe quand ("au fil de l'eau")
     *     - Renouvelable seulement à chaque session
     *     - Créé seulement par un permanent, qui devient responsable du projet
     *
     */
    private function nouveauProjetFil(int $priorite=self::HPRIO):array
    {
        $menu   =   [];

        $menu['commentaire']    =   "Vous ne pouvez pas créer de nouveau projet actuellement";
        $menu['name']   =   'avant_nouveau_projet';
        $menu['params'] =   [ 'type' =>  Projet::PROJET_FIL ];
        $menu['lien']   =   'Nouveau projet';
        $menu['ok']     = false;

        $session =  $this->ss->getSessionCourante();
        if ($session == null)
        {
            $menu['raison'] = "Il n'y a pas de session courante";
        }
        else
        {
            $etat_session   =   $session->getEtatSession();
            if (! $this->peutCreerProjets()) {
                $menu['raison'] = "Seuls les personnels permanents des laboratoires enregistrés peuvent créer un projet";
            } else {
                $menu['raison'] = '';
                $menu['commentaire'] = "Créez un nouveau projet, vous en serez le responsable";
                $menu['ok'] = true;
            }
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    private function peutCreerProjets($user = null): bool
    {
        if ($user == null) {
            $user = $this->token->getUser();
        }

        if ($user != null && $user->peutCreerProjets()) {
            return true;
        } else {
            return false;
        }
    }

    //////////////////////////////////////

    // Menu principal Admin

    //////////////////////////////////////

    public function gererIndividu(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'individu_gerer';
        $menu['commentaire']    =   "Gérer les utilisateurs de gramc";
        $menu['lien']           =   "Utilisateurs";
        $menu['icone']          =   "liste_utilisateur";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous n'êtes pas un administrateur";
        }
        $this->__prio($menu, $priorite);
        return $menu;
    }

    public function gererInvitations(int $priorite=self::HPRIO):array
    {
        $menu['name'] = 'invitations';
        $menu['commentaire'] = "Récapituler les invitations en cours";
        $menu['lien'] = "Invitations";
        $menu['icone'] = "mail";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous n'êtes pas un administrateur";
        }
        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function gererSessions(int $priorite=self::HPRIO): array
    {
        $menu['name']   =   'gerer_sessions';
        $menu['commentaire']    =   "Gérer les sessions d'attribution";
        $menu['lien']           =   "Sessions";
        $menu['icone']          =   "sessions";


        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur ou président pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function bilanSession(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'bilan_session';
        $menu['commentaire']    =   "Générer et télécharger le bilan de session";
        $menu['lien']           =   "Bilan de session";
        $menu['icone']           =   "bilan";


        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur ou président pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function bilanAnnuel(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'bilan_annuel';
        $menu['commentaire']    =   "Générer et télécharger le bilan annuel";
        $menu['lien']           =   "Bilan annuel";
        $menu['icone']           =   "annee";


        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur ou président pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function projetsSession(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'projet_session';
        $menu['commentaire']    =   "Gérer les projets par session";
        $menu['lien']           =   "Projets ( par session )";
        $menu['icone']           =   "projet_session";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur ou président pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function projetsAnnee(int $priorite=self::HPRIO):array
    {
        $menu['name']        =   'projet_annee';
        $menu['commentaire'] =   "Gérer les projets par année";
        $menu['lien']        =   "Projets ( par année )";
        $menu['icone']       =   "annee";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur ou président pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function projet_donnees(int $priorite=self::HPRIO):array
    {
        $menu['name']        =   'projet_donnees';
        $menu['commentaire'] =   "Projets ayant des demandes en stockage ou partage de données";
        $menu['lien']        =   "Gestion et valo des données";
        $menu['icone']        =   "donnees";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur ou président pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }
    //////////////////////////////////////

    public function projetsTous(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'projet_tous';
        $menu['commentaire']    =   "Liste complète des projets";
        $menu['lien']           =   "Tous les projets";
        $menu['icone']          =   "tous";


        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur ou président pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function lireJournal(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'journal_list';
        $menu['commentaire']    =   "Lire le journal des actions";
        $menu['lien']           =   "Lire le journal";
        $menu['icone']          =   "lire_journal";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function phpInfo(int $priorite=self::HPRIO):array
    {
        $menu['name']        = 'phpinfo';
        $menu['commentaire'] = "Infos sur php et sur aussi sur gramc";
        $menu['lien']        = "infos techniques";
        $menu['icone']       = "technique";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être un administrateur pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }
    //////////////////////////////////////

    public function gererLaboratoires(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'gerer_laboratoires';
        $menu['commentaire']    =   "Gérer la liste des laboratoires enregistrés";
        $menu['lien']           =   "Laboratoires";
        $menu['icone']          =   "laboratoire";

        if ($this->ac->isGranted('ROLE_OBS')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être au moins un observateur pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    public function gererFormations(int $priorite=self::HPRIO):array
    {
        $menu['name']       =   'gerer_formations';
        $menu['commentaire']=   "Gérer la liste des formations";
        $menu['lien']       =   "Formations";
        $menu['icone']      =   "formation";

        if ($this->ac->isGranted('ROLE_OBS')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être au moins un observateur pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function gererThematiques(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'gerer_thematiques';
        $menu['commentaire']    =   "Gérer la liste des thématiques";
        $menu['lien']           =   "Thématiques";
        $menu['icone']          =   "thematique";

        if ($this->ac->isGranted('ROLE_OBS')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être au moins un observateur pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function gererRattachements(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'gerer_rattachements';
        $menu['commentaire']    =   "Gérer la liste des rattachements";
        $menu['lien']           =   "Rattachements";
        $menu['icone']          =   "rattachement";

        if ($this->ac->isGranted('ROLE_OBS')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être au moins un observateur pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    public function gererMetathematiques(int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'gerer_metaThematiques';
        $menu['commentaire']    =   "Gérer la liste des méta-thématiques";
        $menu['lien']           =   "Méta-Thématiques";
        $menu['icone']           =   "thematique";

        if ($this->ac->isGranted('ROLE_OBS')) {
            $menu['ok'] = true;
        } else {
            $menu['ok'] = false;
            $menu['raison'] = "Vous devez être au moins un observateur pour accéder à cette page";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////

    // Menu principal Projet

    //////////////////////////////////////

    public function changerResponsable(Version $version, int $priorite=self::HPRIO):array
    {
        $menu['name']   =   'changer_responsable';
        $menu['param']  =   $version->getIdVersion();
        $menu['lien']   =   "Nouveau responsable";
        $user = $this->token->getUser();

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['commentaire'] = "Changer le responsable du projet en tant qu'administrateur";
            $menu['raison']      = "L'admininstrateur peut TOUJOURS modifier le responsable d'une version quelque soit son état !";
            $menu['ok']          = true;
        }
        else
        {
            $menu['ok']          = false;
            $menu['commentaire'] =   "Vous ne pouvez pas changer le responsable de ce projet";
    
            $session        =   $this->ss->getSessionCourante();
            $etatVersion    =   $version->getEtatVersion();
    
            if ($version->getEtatVersion() != Etat::EDITION_DEMANDE) {
                $menu['raison']         = "Commencez par demander le renouvellement du projet !";
            } elseif ($session->getEtatSession() != Etat::EDITION_DEMANDE && ! $version->isProjetTest()) {
                $menu['raison']         = "Nous ne sommes pas en période de demandes de ressources";
            } elseif ($etatVersion == Etat::EDITION_EXPERTISE || $etatVersion == Etat::EXPERTISE_TEST) {
                $menu['raison']         = "Le projet a déjà été envoyé en expertise";
            } elseif ($etatVersion != Etat::EDITION_DEMANDE && $etatVersion != Etat::EDITION_TEST) {
                $menu['raison']         = "Cette version de projet n'est pas en mode édition";
            } elseif (! $version->isResponsable($user)) {
                $menu['raison']         = "Seul le responsable du projet peut passer la main. S'il n'est pas joignable, merci de nous envoyer un mail";
            } else {
                $menu['ok']             = true;
                $menu['commentaire']    = "Quitter la responsabilité de ce projet";
            }
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////

    public function modifierVersion(Version $version, int $priorite=self::HPRIO):array
    {
        $menu['name'] = 'modifier_version';
        $menu['param'] = $version->getIdVersion();
        $menu['lien'] = "Modifier";
        $menu['icone'] = "modifier";
        $menu['commentaire'] = "Vous ne pouvez pas modifier ce projet";
        $menu['ok'] = false;

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['commentaire']    =   "Modifier le projet en tant qu'administrateur";
            $menu['raison']         =   "L'administrateur peut TOUJOURS modifier le projet quelque soit son état !";
            $menu['ok']             = true;
        }
        else
        {
            $etatVersion = $version->getEtatVersion();
            $isProjetTest = $version->isProjetTest();
            $isProjetSess = $version->getProjet()->getTypeProjet() === Projet::PROJET_SESS;
    
            if ($version->getSession() == null) {
                $menu['raison'] = "Pas de session attachée à ce projet !";
                $this->sj->errorMessage(__METHOD__ . ' la version ' . Functions::show($version) . " n'a pas de session attachée !");
            } elseif ($etatVersion ==  Etat::EDITION_EXPERTISE) {
                $menu['raison'] = "Le projet a déjà été envoyé en expertise !";
            } elseif ($isProjetTest == true && $etatVersion ==  Etat::ANNULE) {
                $menu['raison'] = "Le projet test a été annulé !";
            } elseif ($isProjetTest == true && $etatVersion !=  Etat::EDITION_TEST) {
                $menu['raison'] = "Le projet test a déjà été envoyé en expertise !";
            } elseif ($isProjetSess && $version->getSession()->getEtatSession() != Etat::EDITION_DEMANDE) {
                $menu['raison'] = "Nous ne sommes pas en période de demandes de ressources";
            } elseif ($version->isCollaborateur($this->token->getUser()) == false) {
                $menu['raison']         = "Seul un collaborateur du projet peut modifier ou supprimer le projet";
            } elseif ($etatVersion !=  Etat::EDITION_DEMANDE && $etatVersion !=  Etat::EDITION_TEST) {
                $menu['raison'] = "Le projet n'est pas en mode d'édition";
            } else {
                $menu['ok']          = true;
                $menu['commentaire'] = "Modifier votre demande de ressources";
                $menu['todo']        = "<strong>Vérifier</strong> le projet et le <strong>compléter</strong> si nécessaire";
            }
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ///////////////////////////////////////////////////////////

    public function modifierCollaborateurs(Version $version, int $priorite=self::HPRIO):array
    {
        $user = $this->token->getUser();

        $menu['name']  = 'modifier_collaborateurs';
        $menu['param'] = $version->getIdVersion();
        $menu['lien']  = "Collaborateurs";
        $menu['priorite'] =   $priorite;

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['commentaire'] = "Modifier les collaborateurs en tant qu'administrateur";
            $menu['ok']          = true;
        } elseif (! $version->isResponsable($user)) {
            $menu['ok']          = false;
            $menu['commentaire'] = 'Bouton inactif';
            $menu['raison']      = "Seul le responsable du projet peut ajouter ou supprimer des collaborateurs";
        } elseif ($version->getEtat() === Etat::TERMINE || $version->getEtat() === Etat::ANNULE) {
            $menu['ok'] = false;
            $menu['commentaire'] = 'Bouton inactif';
            $menu['raison']      = "Cette version est terminée !";
        } else {
            $menu['ok']          = true;
            $menu['commentaire'] = "Modifier la liste des collaborateurs du projet";
        }
        
        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////

    public function televerserRapportAnnee(Version $version, int $priorite=self::HPRIO):array
    {
        $menu['name']           =   'televerser_rapport_annee';
        $menu['ok']             =   false;
        $menu['priorite']  = $priorite;

        if ($version != null) {
            $etat           = $version->getEtatVersion();
            $menu['param']  = $version->getIdVersion();
            $menu['lien']   = "Rapport d'activité de l'année " . $version->getAnneeSession();

            if ($this->ac->isGranted('ROLE_ADMIN') && ($etat == Etat::ACTIF || $etat == Etat::TERMINE)) {
                $menu['commentaire'] = "Téléverser un rapport d'activité pour un projet en tant qu'administrateur";
                $menu['raison']      = "L'administrateur peut TOUJOURS téléverser un rapport d'activité pour un projet !";
                $menu['ok']          = true;
            }
            else
            {
                $menu['ok']          = false;
                $menu['commentaire'] = "Vous ne pouvez pas téléverser un rapport d'activité pour ce projet";
    
                if ($version->getProjet() != null) {
                    $rapportActivite = $this->em->getRepository(RapportActivite::class)->findOneBy(
                        [
                        'projet' => $version->getProjet(),
                        'annee' => $version->getAnneeSession(),
                        ]
                    );
                } else {
                    $rapportActivite = null;
                    $this->sj->errorMessage(__METHOD__ . ":" . __LINE__ . " version " . $version . " n'est pas associée à aucun projet !");
                }
    
                //if( $etat != Etat::ACTIF && $etat != Etat::TERMINE)
                //		$menu['raison'] = "Vous devez soumettre le rapport annuel quand vous avez fini vos calculs de l'année en question";
                if (! $version->isCollaborateur($this->token->getUser())) {
                    $menu['raison'] = "Seul un collaborateur du projet peut téléverser un rapport d'activité pour un projet";
                }
                //elseif( $rapportActivite != null)
                //     $menu['raison'] = "Vous avez déjà téléversé un rapport d'activité pour ce projet pour l'année en question";
                else
                {
                    $menu['ok']          = true;
                    $menu['commentaire'] = "Téléverser votre rapport d'activité pour l'année " . $version->getAnneeSession() . "si vous avez déjà terminé vos calculs";
                    $menu['todo']        = "Téléverser votre rapport d'activité pour " . $version->getAnneeSession();
                }
            }
        } else {
            $menu['param']          =   0;
            $menu['lien']           =   "Rapport d'activité";
            $menu['commentaire']    =   "Vous ne pouvez pas téléverser un rapport d'activité pour ce projet";
            $menu['raison']         =   "Mauvaise version du projet !";
            $this->sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Version null !");
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////
    public function telechargerModeleRapportDactivite(Version $version, int $priorite=self::HPRIO):array
    {
        $menu['name']           =   'telecharger_modele';
        $menu['lien']           =   "Modèle de rapport d'activité";
        $menu['ok']             =   false;
        $menu['commentaire'] = "Vous ne pouvez pas télécharger un modèle de rapport d'activité pour ce projet";
        $menu['telecharger']  = "telecharger";
        $menu['priorite']  = $priorite;

        if ($version != null)
        {
            if ($this->ac->isGranted('ROLE_ADMIN')) {
                $menu['commentaire'] = "Télécharger un modèle de rapport d'activité en tant qu'administrateur";
                $menu['raison']      = "L'admininstrateur peut TOUJOURS télécharger un modèle de rapport d'activité !";
                $menu['ok']          = true;
            }
            else
            {
                $etat    = $version->getEtatVersion();
    
                if (! $version->isCollaborateur($this->token->getUser())) {
                    $menu['raison'] = "Seul un collaborateur du projet peut télécharger un modèle de rapport d'activité pour ce projet";
                }
                //elseif( $etat != Etat::ACTIF && $etat != Etat::TERMINE)
                //    $menu['raison'] = "Vous devez soumettre le rapport annuel quand vous avez fini vos calculs de l'année en question";
                else {
                    $menu['ok']          = true;
                    $menu['commentaire'] = "Télécharger un modèle de rapport d'activité";
                }
            }
        }
        else
        {
            $menu['raison']         = "Mauvaise version du projet !";
            $this->sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Version null !");
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////

    public function gererPublications(Projet $projet, int $priorite=self::HPRIO):array
    {
        $menu['name']  = 'gerer_publications';
        $menu['param'] = $projet->getIdProjet();
        $menu['lien']  = "Publications";
        $menu['priorite']  = $priorite;

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['commentaire'] = "Modifier les publications en tant qu'administrateur";
            $menu['raison']      = "L'admininstrateur peut TOUJOURS modifier les publications du projet  !";
            $menu['ok']          = true;
        }
        else
        {
            $version = $projet->derniereVersion();
            $etat    = $version->getEtatVersion();
    
            $menu['ok']             = false;
            $menu['commentaire']    =   "Vous ne pouvez pas modifier les publications";
    
            if (! $projet->isCollaborateur($this->token->getUser())) {
                $menu['raison']     =  "Seul un collaborateur du projet peut gérer les publicatins associées à un projet";
            } elseif ($this->sv->isNouvelle($version) && ! ($etat == Etat::ACTIF || $etat == Etat::TERMINE)) {
                $menu['raison']     =  "Vous ne pouvez ajouter que des publications que vous avez publiées grâce au calcul sur notre mésocentre";
            } else {
                $menu['ok']             = true;
                $menu['commentaire']    = "Gérer les publicatins associées au projet " . $projet->getIdProjet();
                $menu['todo']           = '<strong>Signaler les dernières publications</strong> dans lesquelles le mésocentre a été remercié pour ce projet';
            }
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    /////////////////////////////////////////////////////////////////////
    public function renouvelerVersion(Version $version, int $priorite=self::BPRIO):array
    {
        $menu['name']        = 'renouveler_version';
        $menu['param']       = $version->getIdVersion();
        $menu['lien']        = "Renouveler";
        $menu['icone']       = "renouveler";
        $menu['commentaire'] = "Vous ne pouvez pas demander de renouvellement";
        $menu['ok']          = false;

        $session = $this->em->getRepository(Session::class)->findOneBy([ 'etatSession' => Etat::EDITION_DEMANDE ]);

        if ($session == null) {
            $menu['raison']     =   "Nous ne sommes pas en période de demandes de ressources";
        }
        else
        {
            $idVersion = $session->getIdSession() . $version->getProjet()->getIdProjet();
    
            if ($this->em->getRepository(Version::class)->findOneBy([ 'idVersion' =>  $idVersion]) != null) {
                $menu['raison'] = "Version initiale ou renouvellement déjà demandé";
            } elseif ($version->getProjet()->getEtatProjet() == Etat::TERMINE) {
                $menu['raison'] = "Votre projet est ou sera prochainement terminé";
            } elseif ($version->isCollaborateur($this->token->getUser())) {
                $menu['commentaire'] = "Demander de nouvelles ressources sur ce projet pour la session " . $session->getIdSession();
                $priorite = self::HPRIO;
                $menu['ok'] =   true;
            } elseif ($this->ac->isGranted('ROLE_ADMIN')) {
                $menu['commentaire'] = "Demander de nouvelles ressources sur ce projet pour la session "
                    .   $session->getIdSession() . " en tant qu'administrateur";
                $priorite = self::HPRIO;
                $menu['ok'] = true;
            } else {
                $menu['raison'] = "Vous n'avez pas le droit de renouveler ce projet, vous n'êtes pas un collaborateur";
            }
        }
        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////

    public function envoyerEnExpertise(Version $version, int $priorite=self::HPRIO):array
    {
        if ($version == null) {
            return [];
        }

        $projet = $version -> getProjet();
        $user   = $this->token->getUser();

        $menu['name']           =   'envoyer_en_expertise';
        $menu['param']          =   $version->getIdVersion();
        $menu['lien']           =   "Envoyer en expertise";
        $menu['icone']           =   "envoyer";
        $menu['commentaire']    =   "Vous ne pouvez pas envoyer ce projet en expertise";
        $menu['ok']             =   false;
        $menu['raison']         =   "";
        $menu['incomplet']      =   false;

        $etatVersion  = $version->getEtatVersion();

        // true si le projet est un projet test
        $type_projet  = $version->getProjet()->getTypeProjet();
        //$isProjetTest = ($type_projet == Projet::PROJET_FIL || $type_projet == Projet::PROJET_TEST);
        $isProjetTest = $type_projet == Projet::PROJET_TEST;
        $isProjetSess = $type_projet == Projet::PROJET_SESS;

        if ($version->getSession() != null) {
            $etatSession = $version->getSession()->getEtatSession();
        } else {
            $etatSession = null;
        }

        if ($version->getSession() == null) {
            $menu['raison'] = "Pas de session attachée à ce projet !";
            $this->sj->errorMessage(__METHOD__ . ' la version ' . Functions::show($version) . " n'a pas de session attachée !");
        } elseif ($version->isResponsable($user) == false) {
            $menu['raison'] = "Seul le responsable du projet peut envoyer ce projet en expertise";
        }

        // manu - $priorite=self::HPRIO$priorite=self::HPRIO juin 20$priorite=self::HPRIO9 - Tout le monde peut créer un projet test !
        // manu - Je ne comprends pas ce truc !
        elseif ($isProjetTest == false && $version->isResponsable($user) == true &&  ! $this->peutCreerProjets()) {
            $menu['raison'] = "Le responsable du projet n'a pas le droit de créer des projets";
            $this->sj->warningMessage(__METHOD__ . ':' . __LINE__ ." Le responsable " . $this->token->getUser()
                . " du projet " . $projet . " ne peut pas créer des projets");
        } elseif ($etatVersion ==  Etat::EDITION_EXPERTISE) {
        $menu['raison'] = "Le projet a déjà été envoyé en expertise !";
    } elseif ($isProjetTest == true) {
        $menu['raison'] = "ATTENTION - PAS DE PROJETS TESTS ACTUELLEMENT - Adressez-vous au support";
        } elseif ($isProjetTest == true && $etatVersion ==  Etat::ANNULE) {
            $menu['raison'] = "Le projet test a été annulé !";
        } elseif ($isProjetTest == true && $etatVersion !=  Etat::EDITION_TEST) {
            $menu['raison'] = "Le projet test a déjà été envoyé en expertise !";
        } elseif ($etatVersion !=  Etat::EDITION_DEMANDE && $etatVersion !=  Etat::EDITION_TEST) {
            $menu['raison'] = "Le responsable du projet n'a pas demandé de renouvellement";
        } elseif ($isProjetSess && $etatSession != Etat::EDITION_DEMANDE) {
            $menu['raison'] = "Nous ne sommes pas en période de demandes de ressources";
        } else {
            $menu['ok']          = true;
            $menu['commentaire'] = "Envoyer votre demande pour expertise. ATTENTION, vous ne pourrez plus la modifier par la suite";
            $menu['todo']        = "Envoyer le projet en <strong>expertise</strong>";
            $menu['name']        = 'avant_modifier_version';
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function affecterExperts(int $priorite=self::HPRIO):array
    {
        $session = $this->ss->getSessionCourante();

        $menu['name']        =   'affectation';
        $menu['lien']        =   "Affecter les experts ($session)";

        $menu['commentaire'] =   "Vous ne pouvez pas affecter les experts de la session " . $session;
        $menu['ok']          =   false;
        if (!$this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['raison']     =   "Vous n'avez pas le rôprésident";
        } else {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Espace d'affectation des experts";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    public function affecterExpertsRallonges(int $priorite=self::HPRIO):array
    {
        //$session = $this->ss->getSessionCourante();
        $menu['name']           =   'rallonge_affectation';
        $menu['lien']           =   "Rallonge de ressources";
        $menu['commentaire']    =   "Affecter les experts pour les rallonges";
        $menu['ok']             =   false;
        $menu['raison']         =   "Vous n'êtes pas président";

        if ($this->ac->isGranted('ROLE_ADMIN') ||  $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Affecter les experts pour les rallonges";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function expertiserRallonge(Rallonge $rallonge, int $priorite=self::HPRIO):array
    {
        $menu['name']        =   'expertiser_rallonge';
        $menu['param']       =   $rallonge->getIdRallonge();
        $menu['lien']        =   "Expertiser la rallonge";
        $menu['commentaire'] =   "Vous ne pouvez pas expertiser cette demande";
        $menu['ok']          =   false;
        $menu['raison']      =   "raison inconnue";
        $user                = $this->token->getUser();

        $version = $rallonge->getVersion();
        if ($version != null) {
            $projet = $version->getProjet();
        } else {
            $projet = null;
        }

        $etatRallonge   =  $rallonge->getEtatRallonge();

        if ($version == null) {
            $menu['raison']         =   "Cette rallonge n'est associée à aucun projet !";
        }
        //elseif( $version->getEtatVersion()  == Etat::NOUVELLE_VERSION_DEMANDEE )
        //    $menu['raison']         =   "Un renouvellement du projet " . $projet . " est déjà accepté !";
        elseif ($version->getProjet() == null) {
            $menu['raison']     =   "Cette version du projet n'est associée à aucun projet";
        } elseif ($version->getProjet()->getEtatProjet() == Etat::TERMINE) {
            $menu['raison']     =   "Votre projet est ou sera prochainement terminé";
        } elseif ($version->getEtatVersion() == Etat::ANNULE) {
            $menu['raison']     =   "Votre projet est annulé";
        } elseif ($version->getEtatVersion() == Etat::TERMINE) {
            $menu['raison']     =   "Votre projet de cette session est déjà terminé";
        } elseif ($etatRallonge == Etat::EDITION_DEMANDE) {
            $menu['raison']     =   "Cette demande n'a pas encore été envoyée en expertise";
        } elseif ($etatRallonge== Etat::ANNULE) {
            $menu['raison']     =   "Cette demande a été annulée";
        } elseif ($etatRallonge != Etat::EDITION_EXPERTISE) {
            $menu['raison']     =   "Cette demande a déjà été envoyée au président";
        } elseif ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez expertiser cette demande et l'envoyer au président en tant qu'administrateur !";
        } elseif ($rallonge->isExpertDe($user)) {
            $menu['commentaire']         =   "Vous pouvez expertiser cette demande et l'envoyer au président" ;
            $menu['ok']             =   true;
        } else {
            $menu['raison']         = "Vous n'avez pas le droit d'expertiser cette demande, vous n'êtes pas l'expert designé";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function testerMail(int $priorite=self::HPRIO):array
    {
        $menu['name']        = 'mail_tester';
        $menu['lien']        = "Tester le système de mail";
        $menu['commentaire'] = "Vous ne pouvez pas tester le mail";
        $menu['ok']          = false;
        $menu['raison']      = "Vous n'êtes pas un administrateur";
        $menu['icone']       = "mail";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Test du mail";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function tempsAvancer(int $priorite=self::HPRIO):array
    {
        //$session = $this->ss->getSessionCourante();
        $menu['name']           =   'param_avancer';
        $menu['lien']           =   "Avancer dans le temps";
        $menu['commentaire']    =   "Vous ne pouvez pas avancer dans le temps";
        $menu['ok']             =   false;
        $menu['raison']         =   "Vous n'êtes pas un administrateur";
        $menu['icone']         =   "avancer_temps";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez avancer dans le temps (pour déboguage)";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function mailToResponsables(int $priorite=self::HPRIO):array
    {
        $session = $this->ss->getSessionCourante();
        if ($session != null) {
            $etatSession     = $session->getEtatSession();
            $idSession       = $session->getIdSession();
        } else {
            $this->sj->errorMessage(__METHOD__ . ':' . __LINE__ . " La session courante est nulle !");
            $etatSession     = null;
            $idSession       = 'X';
        }

        $menu['name']        = 'mail_to_responsables';
        $menu['param']       = $idSession;
        $menu['lien']        = "Mail - projets non renouvelés";
        $menu['commentaire'] = "Vous ne pouvez pas envoyer un mail aux responsables des projets qui ne l'ont pas renouvelé";
        $menu['ok']          = false;
        $menu['raison']      = "Vous n'êtes pas un administrateur ou président";
        $menu['icone']      = "mail";

        if ($etatSession    != Etat::EDITION_DEMANDE) {
            $menu['raison']  = "La session n'est pas en mode d'édition";
        } elseif ($this->ac->isGranted('ROLE_ADMIN') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']          = true;
            $menu['commentaire'] = "Envoyer un rappel aux responsables des projets qui n'ont pas renouvelé !";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function mailToResponsablesRallonge(int $priorite=self::HPRIO):array
    {
        $session = $this->ss->getSessionCourante();
        if ($session != null) {
            $etatSession     = $session->getEtatSession();
            $idSession       = $session->getIdSession();
        } else {
            $this->sj->errorMessage(__METHOD__ . ':' . __LINE__ . " La session courante est nulle !");
            $etatSession     = null;
            $idSession       = 'X';
        }

        $menu['name']        = 'mail_to_responsables_rallonge';
        $menu['param']       = $idSession;
        $menu['lien']        = "Mail - Proposition de rallonge";
        $menu['commentaire'] = "Vous ne pouvez pas envoyer un mail aux responsables de projets";
        $menu['ok']          = false;
        $menu['raison']      = "Vous n'êtes pas un administrateur ou président";
        $menu['icone']      = "mail";

        if ($this->ac->isGranted('ROLE_ADMIN') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']          = true;
            $menu['commentaire'] = "Envoyer un rappel aux responsables des projets qui n'ont pas renouvelé !";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function mailToResponsablesFiche(int $priorite=self::HPRIO):array
    {
        $session = $this->ss->getSessionCourante();
        if ($session != null) {
            $etatSession     = $session->getEtatSession();
            $idSession       = $session->getIdSession();
        } else {
            $this->sj->errorMessage(__METHOD__ . ':' . __LINE__ . " La session courante est nulle !");
            $etatSession     = null;
            $idSession       = 'X';
        }

        $menu['name']        = 'mail_to_responsables_fiche';
        $menu['param']       = $idSession;
        $menu['lien']        = "Mail - projets sans fiche";
        $menu['commentaire'] = "Vous ne pouvez pas envoyer un mail aux responsables des projets qui n'ont pas téléversé leur fiche projet";
        $menu['ok']          = false;
        $menu['raison']      = "Vous n'êtes pas un administrateur ou président";
        $menu['icone']      = "mail";

        if ($etatSession    !=  Etat::ACTIF && $etatSession    !=  Etat::EN_ATTENTE) {
            $menu['raison']         =   "La session n'est pas en mode actif ou en attente";
        } elseif ($this->ac->isGranted('ROLE_ADMIN') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']          = true;
            $menu['commentaire'] = "Envoyer un rappel aux responsables des projets qui n'ont pas téléversé leur fiche projet !";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function nettoyerRgpd(int $priorite=self::HPRIO):array
    {
        $menu['name']            = 'rgpd';
        $menu['lien']            = "Nettoyage pour conformité au RGPD";
        $menu['commentaire']     = "Vous ne pouvez pas supprimer les projets ou les utilisateurs anciens";
        $menu['ok']              = false;
        $menu['raison']          = "Vous n'êtes pas un administrateur";
        $menu['icone']          = "nettoyage";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']          = true;
            $menu['commentaire'] = "Suppresion des anciens projets et des utilisateurs orphelins";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    /////////////////////////////////////////////////////////////////////////////////

    public function afficherConnexions(int $priorite=self::HPRIO):array
    {
        $menu['name']           =   'connexions';
        $menu['lien']           =   "Personnes connectées";
        $menu['commentaire']    =   "Vous ne pouvez pas voir les personnes connectées";
        $menu['ok']             =   false;
        $menu['raison']         =   "Vous n'êtes pas un administrateur";
        $menu['icone']          =   "personnes_connectees";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez pouvez voir les personnes connectées";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function nouvelleRallonge(Projet $projet, int $priorite=self::HPRIO):array
    {
        $sp = $this->sp;

        $menu['name']        = 'nouvelle_rallonge';
        $menu['param']       = $projet->getIdProjet();
        $menu['lien']        = "Rallonge";
        $menu['commentaire'] = "Vous ne pouvez pas créer une nouvelle rallonge";
        $menu['ok']          = false;
        $menu['raison']      = "Vous n'êtes pas un administrateur";


        $version = $this->sp->versionActive($projet);
        $max_rall= $this->max_rall;

        if ($version == null) {
            $menu['raison']         =   "Le projet " . $projet . " n'est pas actif !";
        } elseif ($this->em->getRepository(Rallonge::class)->findRallongesOuvertes($sp->versionActive($projet)) != null) {
            $menu['raison']         =   "Une autre rallonge du projet " . $projet . " est déjà en cours de traitement !";
        }
        elseif (count($version->getRallonge()) >= $max_rall) {
            $menu['raison']         =   "Pas plus de $max_rall rallonges par session !";
        }
        //elseif( $version->getEtatVersion()  == Etat::NOUVELLE_VERSION_DEMANDEE )
        //    $menu['raison']         =   "Un renouvellement du projet " . $projet . " est déjà accepté !";
        elseif ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez créer une nouvelle rallonge !";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function modifierRallonge(Rallonge $rallonge, int $priorite=self::HPRIO):array
    {
        $menu['name']           = 'modifier_rallonge';
        $menu['param']          = $rallonge->getIdRallonge();
        $menu['lien']           = "Modifier";
        $menu['icone']          = "modifier";
        $menu['commentaire']    = "Vous ne pouvez pas modifier la demande";
        $menu['ok']             = false;
        $menu['raison']         = "raison inconnue";


        $version = $rallonge->getVersion();
        if ($version != null) {
            $projet = $version->getProjet();
        } else {
            $projet = null;
        }

        if ($version == null) {
            $menu['raison']         =   "Cette rallonge n'est associée à aucun projet !";
        }
        //elseif( $version->getEtatVersion()  == Etat::NOUVELLE_VERSION_DEMANDEE )
        //    $menu['raison']         =   "Un renouvellement du projet " . $projet . " est déjà accepté !";
        elseif ($version->getProjet() == null) {
            $menu['raison']     =   "Cette version du projet n'est associée à aucun projet";
        } elseif ($version->getProjet()->getEtatProjet() == Etat::TERMINE) {
            $menu['raison']     =   "Votre projet est ou sera prochainement terminé";
        } elseif ($version->getEtatVersion() == Etat::ANNULE) {
            $menu['raison']     =   "Votre projet est annulé";
        } elseif ($version->getEtatVersion() == Etat::TERMINE) {
            $menu['raison']     =   "Votre projet de cette session est déjà terminé";
        } elseif ($rallonge->getEtatRallonge() == Etat::ANNULE) {
            $menu['raison']     =   "Cette rallonge a été annulée";
        } elseif ($rallonge->getEtatRallonge() != Etat::EDITION_DEMANDE) {
            $menu['raison']     =   "Cette rallonge a déjà été envoyée en expertise";
        } elseif ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez modifier la demande en tant qu'administrateur !";
        } elseif ($version->isCollaborateur($this->token->getUser())) {
            $menu['commentaire']         =   "Vous pouvez modifier votre demande " ;
            $menu['ok']             =   true;
        } else {
            $menu['raison']         = "Vous n'avez pas le droit de modifier cette demande, vous n'êtes pas un collaborateur";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }


    ////////////////////////////////////////////////////////////////////////////

    public function envoyerEnExpertiseRallonge(Rallonge $rallonge, int $priorite=self::HPRIO):array
    {
        $menu['name']        = 'avant_rallonge_envoyer';
        $menu['param']       = $rallonge->getIdRallonge();
        $menu['icone']       = 'envoyer';
        $menu['lien']        = "Envoyer en expertise";
        $menu['commentaire'] = "Vous ne pouvez pas envoyer cette demande en expertise";
        $menu['ok']          = false;
        $menu['raison']      = "raison inconnue";
        $user                = $this->token->getUser();


        $version = $rallonge->getVersion();
        if ($version != null) {
            $projet = $version->getProjet();
        } else {
            $projet = null;
        }

        if ($version == null) {
            $menu['raison']         =   "Cette rallonge n'est associée à aucun projet !";
        }
        //elseif( $version->getEtatVersion()  == Etat::NOUVELLE_VERSION_DEMANDEE )
        //    $menu['raison']         =   "Un renouvellement du projet " . $projet . " est déjà accepté !";
        elseif ($version->getProjet() == null) {
            $menu['raison']     =   "Cette version du projet n'est associée à aucun projet";
        } elseif ($version->getProjet()->getEtatProjet() == Etat::TERMINE) {
            $menu['raison']     =   "Votre projet est ou sera prochainement terminé";
        } elseif ($version->getEtatVersion() == Etat::ANNULE) {
            $menu['raison']     =   "Votre projet est annulé";
        } elseif ($version->getEtatVersion() == Etat::TERMINE) {
            $menu['raison']     =   "Votre projet de cette session est déjà terminé";
        } elseif ($rallonge->getEtatRallonge() == Etat::ANNULE) {
            $menu['raison']     =   "Cette rallonge a été annulée";
        } elseif ($rallonge->getEtatRallonge() != Etat::EDITION_DEMANDE) {
            $menu['raison']     =   "Cette rallonge a déjà été envoyée en expertise";
        } elseif ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez envoyer cette demande en expertise en tant qu'administrateur !";
        } elseif ($version->isResponsable($user)) {
            $menu['commentaire']         =   "Vous pouvez envoyer votre demande en expertise" ;
            $menu['ok']             =   true;
        } else {
            $menu['raison']         = "Vous n'avez pas le droit d'envoyer cette demande en expertise, vous n'êtes pas le responsable du projet";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    ////////////////////////////////////////////////////////////////////////////

    public function televersementGenerique(int $priorite=self::HPRIO):array
    {
        $menu['name']           =   'televersement_generique';
        $menu['lien']           =   "Téléversements génériques";
        $menu['commentaire']    =   "Téléverser des fiches projet ou des rapports d'activité";
        $menu['ok']             =   false;
        $menu['raison']         =   "Vous n'êtes pas un administrateur";
        $menu['icone']         =   "televersement_generique";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Téléverser des fiches projet ou des rapports d'activité";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////////


    public function telechargerFiche(Version $version, int $priorite=self::HPRIO):array
    {
        $menu['name']           =   'version_fiche_pdf';
        $menu['param']          =   $version->getIdVersion();
        $menu['lien']           =   "Fiche projet";
        $menu['commentaire']    =   "Vous ne pouvez pas télécharger la fiche de ce projet";
        $menu['ok']             =   false;
        $menu['raison']         =   "Vous n'êtes pas un collaborateur du projet";
        $menu['icone']          =   "telecharger";
        $menu['priorite']  = $priorite;

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Télécharger la fiche projet en tant qu'administrateur";
        } elseif (! $version->isCollaborateur($this->token->getUser())) {
            $menu['raison']         =   "Vous n'êtes pas un collaborateur du projet";
        } elseif (($this->sv->isSigne($version) == true)) {
            $menu['raison']         =   "La fiche projet signée a déjà été téléversée";
        } else {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Télécharger la fiche projet";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////////
    public function televerserFiche(Version $version, int $priorite=self::HPRIO):array
    {
        $menu['name']           =   'version_televerser_fiche';
        //$menu['name']           =   'televerser';
        //$menu['param']          =   $version->getIdVersion();
        $menu['params'] = [ 'id' => $version->getIdVersion(), 'filename' => 'fiche.pdf' ];
        $menu['lien']           =   "Fiche projet";
        $menu['commentaire']    =   "Vous ne pouvez pas téléverser la fiche de ce projet";
        $menu['ok']             =   false;
        $menu['raison']         =   "Vous n'êtes pas un collaborateur du projet";
        $menu['priorite']  = $priorite;
        $menu['icone']  = "televerser";

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Téléverser la fiche projet en tant qu'administrateur";
        } elseif (! $version->isCollaborateur($this->token->getUser())) {
            $menu['raison']         =   "Vous n'êtes pas un collaborateur du projet";
        } elseif ($this->sv->isSigne($version) == true) {
            $menu['raison']         =   "La fiche projet signée a déjà été téléversée";
        } else {
            $menu['ok']          = true;
            $menu['commentaire'] = "Téléverser la fiche projet";
            $menu['todo']        = "Télécharger la fiche projet, la faire signer et la téléverser à nouveau";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function statistiquesEtablissement(int $priorite=self::HPRIO): array
    {
        $menu['name']           =   'statistiques_etablissement';
        $menu['lien']           =   "Etablissements";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez accéder aux statistiques par établissement !";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux statistiques par établissement !";
            $menu['raison']         =   "Vous devez être président ou administrateur pour y accéder";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function statistiquesLaboratoire(int $priorite=self::HPRIO): array
    {
        $menu['name']           =   'statistiques_laboratoire';
        $menu['lien']           =   "Laboratoires";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez accéder aux statistiques par laboratoire !";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux statistiques par laboratoire !";
            $menu['raison']         =   "Vous devez être président ou administrateur pour y accéder";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function statistiquesThematique(int $priorite=self::HPRIO): array
    {
        $menu['name']           =   'statistiques_thematique';
        $menu['lien']           =   "Thématiques";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez accéder aux statistiques par thématique !";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux statistiques par thématique !";
            $menu['raison']         =   "Vous devez être président ou administrateur pour y accéder";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function statistiquesMetathematique(int $priorite=self::HPRIO): array
    {
        $menu['name']           =   'statistiques_metathematique';
        $menu['lien']           =   "Métathématiques";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez accéder aux statistiques par metathématique !";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux statistiques par metathématique !";
            $menu['raison']         =   "Vous devez être président ou administrateur pour y accéder";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }


    //////////////////////////////////////////////////////////////////////////

    public function statistiquesRattachement(int $priorite=self::HPRIO): array
    {
        $menu['name']           =   'statistiques_rattachement';
        $menu['lien']           =   "Rattachements";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez accéder aux statistiques par rattachement";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux statistiques par rattachement";
            $menu['raison']         =   "Vous devez être président ou administrateur pour y accéder";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function statistiques(int $priorite=self::HPRIO):array
    {
        $menu['name']           =   'statistiques';
        $menu['lien']           =   "Statistiques";
        $menu['icone']          =   "statistiques";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez accéder aux statistiques  !";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux statistiques  !";
            $menu['raison']         =   "Vous devez être président ou observateur";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function statistiquesCollaborateur(int $priorite=self::HPRIO): array
    {
        $menu['name']           =   'statistiques_collaborateur';
        $menu['lien']           =   "Collaborateurs";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez accéder aux statistiques concernant les collaborateurs !";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux  statistiques concenrant les collaborateurs!";
            $menu['raison']         =   "Vous devez être président ou administrateur pour y accéder";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function statistiquesRepartition(int $priorite=self::HPRIO): array
    {
        $menu['name']           =   'statistiques_repartition';
        $menu['lien']           =   "Projets";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Vous pouvez accéder aux statistiques concernant la répartition des projets !";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux  statistiques concenrant la répartition des projets !";
            $menu['raison']         =   "Vous devez être président ou administrateur pour y accéder";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    //////////////////////////////////////////////////////////////////////////

    public function publicationsAnnee(int $priorite=self::HPRIO):array
    {
        $menu['name']           =   'publication_annee';
        $menu['lien']           =   "Publications";
        $menu['icone']          =   "publications";

        if ($this->ac->isGranted('ROLE_OBS') || $this->ac->isGranted('ROLE_PRESIDENT')) {
            $menu['ok']             =   true;
            $menu['commentaire']    =   "Liste des publications par année";
        } else {
            $menu['ok']             =   false;
            $menu['commentaire']    =   "Vous ne pouvez pas accéder aux publications  !";
            $menu['raison']         =   "Vous devez être président ou observateur";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }

    /*
     * Demandes concernant stockage et partage des données
     */
    public function donnees(Version $version, int $priorite=self::HPRIO):array
    {
        $menu['name']  = 'donnees';
        $menu['param'] = $version->getIdVersion();
        $menu['lien']  = "Vos données";
        $menu['priorite']  = $priorite;
        $user          = $this->token->getUser();

        if ($this->ac->isGranted('ROLE_ADMIN')) {
            $menu['commentaire'] = "Gestion et valorisation des données en tant qu'admin";
            $menu['ok']          = true;
        } elseif (! $version->isResponsable($user)) {
            $menu['ok']          = false;
            $menu['commentaire'] = "Bouton inactif";
            $menu['raison']      = "Vous n'êtes pas responsable du projet";
        } elseif ($version->getEtat() === Etat::TERMINE || $version->getEtat() === Etat::ANNULE) {
            $menu['ok'] = false;
            $menu['commentaire'] = 'Bouton inactif';
            $menu['raison']      = "Cette version est terminée !";
        } else {
            $menu['ok']          = true;
            $menu['commentaire'] = "Gestion et valorisation des données";
        }

        $this->__prio($menu, $priorite);
        return $menu;
    }
}
