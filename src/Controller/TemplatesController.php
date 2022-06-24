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

use App\Entity\Templates;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Template controller.
 *
 * @Security("is_granted('ROLE_ADMIN')")
 * @Route("templates")
 */
class TemplatesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Lists all template entities.
     *
     * @Route("/", name="templates_index",methods={"GET"})
     * Method("GET")
     */
    public function indexAction()
    {
        $em = $this->em;

        $templates = $em->getRepository(Templates::class)->findAll();

        return $this->render('templates/index.html.twig', array(
            'templates' => $templates,
        ));
    }

    /**
     * Creates a new template entity.
     *
     * @Route("/new", name="templates_new",methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $template = new Templates();
        $form = $this->createForm('App\Form\TemplatesType', $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($template);
            $em->flush($template);

            return $this->redirectToRoute('templates_show', array('id' => $template->getId()));
        }

        return $this->render('templates/new.html.twig', array(
            'template' => $template,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a template entity.
     *
     * @Route("/{id}", name="templates_show",methods={"GET"})
     * Method("GET")
     */
    public function showAction(Templates $template)
    {
        $deleteForm = $this->createDeleteForm($template);

        return $this->render('templates/show.html.twig', array(
            'template' => $template,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing template entity.
     *
     * @Route("/{id}/edit", name="templates_edit",methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function editAction(Request $request, Templates $template)
    {
        $deleteForm = $this->createDeleteForm($template);
        $editForm = $this->createForm('App\Form\TemplatesType', $template);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('templates_edit', array('id' => $template->getId()));
        }

        return $this->render('templates/edit.html.twig', array(
            'template' => $template,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a template entity.
     *
     * @Route("/{id}", name="templates_delete",methods={"DELETE"})
     * Method("DELETE")
     */
    public function deleteAction(Request $request, Templates $template)
    {
        $form = $this->createDeleteForm($template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->remove($template);
            $em->flush($template);
        }

        return $this->redirectToRoute('templates_index');
    }

    /**
     * Creates a form to delete a template entity.
     *
     * @param Templates $template The template entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Templates $template)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('templates_delete', array('id' => $template->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
