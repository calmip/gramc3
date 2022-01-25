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
 *  authors : Thierry Jouve      - C.N.R.S. - UMS 3667 - CALMIP
 *            Emmanuel Courcelle - C.N.R.S. - UMS 3667 - CALMIP
 *            Nicolas Renon - Université Paul Sabatier - CALMIP
 **/

namespace App\Controller;

use App\Entity\CommentaireExpert;
use App\Utils\Functions;
use App\GramcServices\ServiceJournal;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


/**
 * Commentaireexpert controller. Les experts peuvent entrer un commentaire
 * concernant la session d'attribution
 *
 * @Route("commentaireexpert")
 */
class CommentaireExpertController extends AbstractController
{
    public function __construct(
        private ServiceJournal $sj,
        private TokenStorageInterface $tok
    ) {}

   /**
    * 
    * Modification d'un commentaire par l'utilisateur connecté
    *
    * @Route("/{id}/modif", name="commentaireexpert_modify", methods={"GET","POST"})
    * @Security("is_granted('ROLE_EXPERT')")
    * Method({"GET", "POST"})
    ************************/
    public function modifyAction(Request $request, CommentaireExpert $commentaireExpert): Response
    {
        $em = $this->getDoctrine()->getManager();
        $sj = $this->sj;
        $token = $this->tok->getToken();

        // Chaque expert ne peut accéder qu'à son commentaire à lui
        $moi = $token->getUser();
        if ($moi->getId() != $commentaireExpert->getExpert()->getId()) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        $editForm = $this->createForm('App\Form\CommentaireExpertType', $commentaireExpert, ["only_comment" => true]);
        $editForm->handleRequest($request);

        $err = false;
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $commentaireExpert->setMajStamp(new \DateTime());
            $em->flush();
        }

        $menu = [];
        return $this->render('commentaireexpert/modify.html.twig', array(
            'menu'              => $menu,
            'commentaireExpert' => $commentaireExpert,
            'edit_form'         => $editForm->createView(),
        ));
    }

    /**
    * Modification ou Création d'un commentaire par l'utilisateur connecté
    *
    * Vérifie que le commentaire de l'année passée en paramètre et de la personne connectée
    * existe, et sinon le crée. Ensuite redirige vers le contrôleur de modification
    *
    * @Route("/{annee}/cree-ou-modif", name="cree_ou_modif", methods={"GET","POST"})
    * @Security("is_granted('ROLE_EXPERT')")
    * Method({"GET", "POST"})
    **********/
    public function creeOuModifAction(Request $request, $annee): Response
    {
        $token = $this->tok->getToken();
        $em = $this->getDoctrine()->getManager();

        $moi = $token->getUser();
        $commentaireExpert = $em->getRepository('App:CommentaireExpert')->findOneBy(['expert' => $moi, 'annee' => $annee ]);
        if ($commentaireExpert==null) {
            $commentaireExpert = new Commentaireexpert();
            $commentaireExpert->setAnnee($annee);
            $commentaireExpert->setExpert($moi);
            $commentaireExpert->setMajStamp(new \DateTime());
            $em->persist($commentaireExpert);
            $em->flush();
        }

        return $this->redirectToRoute('commentaireexpert_modify', array('id' => $commentaireExpert->getId()));
    }

    /**
    * Liste tous les commentaires des experts pour une année
    *
    * @Route("/{annee}/liste", name="commentaireexpert_liste", methods={"GET","POST"})
    * @Security("is_granted('ROLE_OBS')")
    * Method({"GET", "POST"})
    **********/
    public function listeAction(Request $request, $annee): Response
    {
        $em = $this->getDoctrine()->getManager();
        $commentairesExperts = $em->getRepository('App:CommentaireExpert')->findBy(['annee' => $annee ]);

        return $this->render('commentaireexpert/liste_annee.html.twig', ['annee' => $annee, 'commentairesExperts' => $commentairesExperts]);
    }
}
