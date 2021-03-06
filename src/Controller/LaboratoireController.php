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

use App\Entity\Laboratoire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Laboratoire controller.
 *
 * @Route("laboratoire")
 */
class LaboratoireController extends AbstractController
{
    public function __construct(private AuthorizationCheckerInterface $ac) {}

    /**
     * Lists all laboratoire entities.
     *
     * @Route("/", name="laboratoire_index", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $laboratoires = $em->getRepository('App:Laboratoire')->findAll();

        return $this->render('laboratoire/index.html.twig', ['laboratoires' => $laboratoires, ]);
    }

    /**
     * @Route("/gerer",name="gerer_laboratoires", methods={"GET"} )
     * @Security("is_granted('ROLE_OBS')")
     */
    public function gererAction()
    {
        $ac = $this->ac;
        $em = $this->getDoctrine()->getManager();

        // Si on n'est pas admin on n'a pas accès au menu
        $menu = $ac->isGranted('ROLE_ADMIN') ? [ ['ok' => true,'name' => 'ajouter_laboratoire' ,'lien' => 'Ajouter un laboratoire','commentaire'=> 'Ajouter un laboratoire'] ] : [];

        return $this->render(
            'laboratoire/liste.html.twig',
            [
            'menu' => $menu,
            'laboratoires' => $em->getRepository('App:Laboratoire')->findBy([], ['numeroLabo' => 'ASC'])
            ]
        );
    }

    /**
     * Creates a new laboratoire entity.
     *
     * @Route("/new", name="laboratoire_new", methods={"GET","POST"})
     * @Route("/ajouter", name="ajouter_laboratoire", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $laboratoire = new Laboratoire();
        $form = $this->createForm('App\Form\LaboratoireType', $laboratoire, ['ajouter' => true ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($laboratoire);
            $em->flush($laboratoire);

            return $this->redirectToRoute('gerer_laboratoires');
        }

        return $this->render(
            'laboratoire/ajouter.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_laboratoires',
                        'lien' => 'Retour vers la liste des laboratoires',
                        'commentaire'=> 'Retour vers la liste des laboratoires'
                        ] ],
            'laboratoire' => $laboratoire,
            'form' => $form->createView(),
            ]
        );
    }

    /**
     * Finds and displays a laboratoire entity.
     *
     * @Route("/{id}/show", name="laboratoire_show", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function showAction(Laboratoire $laboratoire)
    {
        $deleteForm = $this->createDeleteForm($laboratoire);

        return $this->render('laboratoire/show.html.twig', array(
            'laboratoire' => $laboratoire,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing laboratoire entity.
     *
     * @Route("/{id}/edit", name="laboratoire_edit", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function editAction(Request $request, Laboratoire $laboratoire)
    {
        $deleteForm = $this->createDeleteForm($laboratoire);
        $editForm = $this->createForm('App\Form\LaboratoireType', $laboratoire);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('laboratoire_edit', array('id' => $laboratoire->getId()));
        }

        return $this->render('laboratoire/edit.html.twig', array(
            'laboratoire' => $laboratoire,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing laboratoire entity.
     *
     * @Route("/{id}/modify", name="modifier_laboratoire", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, Laboratoire $laboratoire)
    {
        $deleteForm = $this->createDeleteForm($laboratoire);
        $editForm = $this->createForm('App\Form\LaboratoireType', $laboratoire, ['modifier' => true ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('gerer_laboratoires');
        }

        return $this->render(
            'laboratoire/modif.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_laboratoires',
                        'lien' => 'Retour vers la liste des laboratoires',
                        'commentaire'=> 'Retour vers la liste des laboratoires'
                        ] ],
            'laboratoire' => $laboratoire,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
            ]
        );
    }

    /**
     * Deletes a laboratoire entity.
     *
     * @Route("/{id}/supprimer", name="supprimer_laboratoire", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function supprimerAction(Request $request, Laboratoire $laboratoire)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($laboratoire);
        $em->flush($laboratoire);
        return $this->redirectToRoute('gerer_laboratoires');
    }

    /**
     * Deletes a laboratoire entity.
     *
     * @Route("/{id}/delete", name="laboratoire_delete", methods={"DELETE"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("DELETE")
     */
    public function deleteAction(Request $request, Laboratoire $laboratoire)
    {
        $form = $this->createDeleteForm($laboratoire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($laboratoire);
            $em->flush($laboratoire);
        }

        return $this->redirectToRoute('laboratoire_index');
    }

    /**
     * Creates a form to delete a laboratoire entity.
     *
     * @param Laboratoire $laboratoire The laboratoire entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Laboratoire $laboratoire)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('laboratoire_delete', array('id' => $laboratoire->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
