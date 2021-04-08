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

use App\Entity\RapportActivite;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;


use Symfony\Component\HttpFoundation\Request;

/**
 * Rapportactivite controller.
 *
 * @Security("is_granted('ROLE_ADMIN')")
 * @Route("rapportactivite")
 */
class RapportActiviteController extends AbstractController
{
    /**
     * Lists all rapportActivite entities.
     *
     * @Route("/", name="rapportactivite_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $rapportActivites = $em->getRepository('App:RapportActivite')->findAll();

        return $this->render('rapportactivite/index.html.twig', array(
            'rapportActivites' => $rapportActivites,
        ));
    }

    /**
     * Creates a new rapportActivite entity.
     *
     * @Route("/new", name="rapportactivite_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $rapportActivite = new Rapportactivite();
        $form = $this->createForm('App\Form\RapportActiviteType', $rapportActivite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($rapportActivite);
            $em->flush($rapportActivite);

            return $this->redirectToRoute('rapportactivite_show', array('id' => $rapportActivite->getId()));
        }

        return $this->render('rapportactivite/new.html.twig', array(
            'rapportActivite' => $rapportActivite,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a rapportActivite entity.
     *
     * @Route("/{id}", name="rapportactivite_show")
     * @Method("GET")
     */
    public function showAction(RapportActivite $rapportActivite)
    {
        $deleteForm = $this->createDeleteForm($rapportActivite);

        return $this->render('rapportactivite/show.html.twig', array(
            'rapportActivite' => $rapportActivite,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing rapportActivite entity.
     *
     * @Route("/{id}/edit", name="rapportactivite_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, RapportActivite $rapportActivite)
    {
        $deleteForm = $this->createDeleteForm($rapportActivite);
        $editForm = $this->createForm('App\Form\RapportActiviteType', $rapportActivite);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('rapportactivite_edit', array('id' => $rapportActivite->getId()));
        }

        return $this->render('rapportactivite/edit.html.twig', array(
            'rapportActivite' => $rapportActivite,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a rapportActivite entity.
     *
     * @Route("/{id}", name="rapportactivite_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, RapportActivite $rapportActivite)
    {
        $form = $this->createDeleteForm($rapportActivite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($rapportActivite);
            $em->flush($rapportActivite);
        }

        return $this->redirectToRoute('rapportactivite_index');
    }

    /**
     * Creates a form to delete a rapportActivite entity.
     *
     * @param RapportActivite $rapportActivite The rapportActivite entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(RapportActivite $rapportActivite)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('rapportactivite_delete', array('id' => $rapportActivite->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
