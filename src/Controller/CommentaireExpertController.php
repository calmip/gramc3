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
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/****
* Fichier généré automatiquement et modifié par E.Courcelle
*
* Les méthodes createDeleteForm, deleteAction, editAction, newAction, showAction ont été générées
* automatiquement et ne sont pas utilisées (pas de routage)
*
*************/

/**
 * Commentaireexpert controller.
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
     * Displays a form to edit an existing commentaireExpert entity.
     *
     */
    public function editAction(Request $request, CommentaireExpert $commentaireExpert)
    {
        $deleteForm = $this->createDeleteForm($commentaireExpert);
        $editForm = $this->createForm('App\Form\CommentaireExpertType', $commentaireExpert);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('commentaireexpert_edit', array('id' => $commentaireExpert->getId()));
        }

        return $this->render('commentaireexpert/edit.html.twig', array(
            'commentaireExpert' => $commentaireExpert,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
    * Modification d'un commentaire par l'utilisateur connecté
    *
    * @Route("/{id}/modif", name="commentaireexpert_modify", methods={"GET","POST"})
    * @Security("is_granted('ROLE_EXPERT')")
    * Method({"GET", "POST"})
    ************************/
    public function modifyAction(Request $request, CommentaireExpert $commentaireExpert)
    {
        $sj = $this->sj;
        $token = $this->tok;

        // Chaque expert ne peut accéder qu'à son commentaire à lui
        $moi = $token->getUser();
        if ($moi->getId() != $commentaireExpert->getExpert()->getId()) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ .' problème avec ACL');
        }

        $em = $this->getDoctrine()->getManager();
        $editForm = $this->createForm('App\Form\CommentaireExpertType', $commentaireExpert, ["only_comment" => true]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $commentaireExpert->setMajStamp(new \DateTime());
            $em->flush();
            return $this->redirectToRoute('commentaireexpert_modify', array('id' => $commentaireExpert->getId()));
        }

        $menu = [];
        return $this->render('commentaireexpert/modify.html.twig', array(
            'menu'              => $menu,
            'commentaireExpert' => $commentaireExpert,
            'edit_form'         => $editForm->createView(),
        ));
    }

    /**
     * Creates a new commentaireExpert entity.
     *
     */
    public function newAction(Request $request)
    {
        $commentaireExpert = new Commentaireexpert();
        $form = $this->createForm('App\Form\CommentaireExpertType', $commentaireExpert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($commentaireExpert);
            $em->flush();

            return $this->redirectToRoute('commentaireexpert_show', array('id' => $commentaireExpert->getId()));
        }

        return $this->render('commentaireexpert/new.html.twig', array(
            'commentaireExpert' => $commentaireExpert,
            'form' => $form->createView(),
        ));
    }

    /**
     * Lists all commentaireExpert entities.
     *
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $commentaireExperts = $em->getRepository('App:CommentaireExpert')->findAll();

        return $this->render('commentaireexpert/index.html.twig', array(
            'commentaireExperts' => $commentaireExperts,
        ));
    }

    /**
     * Finds and displays a commentaireExpert entity.
     *
     */
    public function showAction(CommentaireExpert $commentaireExpert)
    {
        $deleteForm = $this->createDeleteForm($commentaireExpert);

        return $this->render('commentaireexpert/show.html.twig', array(
            'commentaireExpert' => $commentaireExpert,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a commentaireExpert entity.
     *
     */
    public function deleteAction(Request $request, CommentaireExpert $commentaireExpert)
    {
        $form = $this->createDeleteForm($commentaireExpert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($commentaireExpert);
            $em->flush();
        }

        return $this->redirectToRoute('commentaireexpert_index');
    }

    /**
     * Creates a form to delete a commentaireExpert entity.
     *
     * @param CommentaireExpert $commentaireExpert The commentaireExpert entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(CommentaireExpert $commentaireExpert)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('commentaireexpert_delete', array('id' => $commentaireExpert->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
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
    public function creeOuModifAction(Request $request, $annee)
    {
        $token = $this->tok;
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
    public function listeAction(Request $request, $annee)
    {
        $em = $this->getDoctrine()->getManager();
        $commentairesExperts = $em->getRepository('App:CommentaireExpert')->findBy(['annee' => $annee ]);

        return $this->render('commentaireexpert/liste_annee.html.twig', ['annee' => $annee, 'commentairesExperts' => $commentairesExperts]);
    }
}
