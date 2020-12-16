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

use App\Entity\Statut;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;


use Symfony\Component\HttpFoundation\Request;

/**
 * Statut controller.
 *
 * @Security("is_granted('ROLE_ADMIN')") 
 * @Route("statut")
 */
class StatutController extends Controller
{
    /**
     * Lists all statut entities.
     *
     * @Route("/", name="statut_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $statuts = $em->getRepository('App:Statut')->findAll();

        return $this->render('statut/index.html.twig', array(
            'statuts' => $statuts,
        ));
    }

    /**
     * Creates a new statut entity.
     *
     * @Route("/new", name="statut_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $statut = new Statut();
        $form = $this->createForm('App\Form\StatutType', $statut);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($statut);
            $em->flush($statut);

            return $this->redirectToRoute('statut_show', array('id' => $statut->getId()));
        }

        return $this->render('statut/new.html.twig', array(
            'statut' => $statut,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a statut entity.
     *
     * @Route("/{id}", name="statut_show")
     * @Method("GET")
     */
    public function showAction(Statut $statut)
    {
        $deleteForm = $this->createDeleteForm($statut);

        return $this->render('statut/show.html.twig', array(
            'statut' => $statut,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing statut entity.
     *
     * @Route("/{id}/edit", name="statut_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Statut $statut)
    {
        $deleteForm = $this->createDeleteForm($statut);
        $editForm = $this->createForm('App\Form\StatutType', $statut);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('statut_edit', array('id' => $statut->getId()));
        }

        return $this->render('statut/edit.html.twig', array(
            'statut' => $statut,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a statut entity.
     *
     * @Route("/{id}", name="statut_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Statut $statut)
    {
        $form = $this->createDeleteForm($statut);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($statut);
            $em->flush($statut);
        }

        return $this->redirectToRoute('statut_index');
    }

    /**
     * Creates a form to delete a statut entity.
     *
     * @param Statut $statut The statut entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Statut $statut)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('statut_delete', array('id' => $statut->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
