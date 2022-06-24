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

use App\Entity\CollaborateurVersion;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Collaborateurversion controller.
 *
 * @Security("is_granted('ROLE_ADMIN')")
 * @Route("collaborateurversion")
 */
class CollaborateurVersionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Lists all collaborateurVersion entities.
     *
     * @Route("/", name="collaborateurversion_index", methods={"GET"})
     * Method("GET")
     */
    public function indexAction(): Response
    {
        $em = $this->em;

        $collaborateurVersions = $em->getRepository(CollaborateurVersion::class)->findAll();

        return $this->render('collaborateurversion/index.html.twig', array(
            'collaborateurVersions' => $collaborateurVersions,
        ));
    }

    /**
     * Creates a new collaborateurVersion entity.
     *
     * @Route("/new", name="collaborateurversion_new", methods={"GET", "POST"})
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request): Response
    {
        $collaborateurVersion = new Collaborateurversion();
        $form = $this->createForm('App\Form\CollaborateurVersionType', $collaborateurVersion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($collaborateurVersion);
            $em->flush($collaborateurVersion);

            return $this->redirectToRoute('collaborateurversion_show', array('id' => $collaborateurVersion->getId()));
        }

        return $this->render('collaborateurversion/new.html.twig', array(
            'collaborateurVersion' => $collaborateurVersion,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a collaborateurVersion entity.
     *
     * @Route("/{id}", name="collaborateurversion_show", methods={"GET"})
     * Method("GET")
     */
    public function showAction(CollaborateurVersion $collaborateurVersion): Response
    {
        $deleteForm = $this->createDeleteForm($collaborateurVersion);

        return $this->render('collaborateurversion/show.html.twig', array(
            'collaborateurVersion' => $collaborateurVersion,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing collaborateurVersion entity.
     *
     * @Route("/{id}/edit", name="collaborateurversion_edit", methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function editAction(Request $request, CollaborateurVersion $collaborateurVersion): Response
    {
        $deleteForm = $this->createDeleteForm($collaborateurVersion);
        $editForm = $this->createForm('App\Form\CollaborateurVersionType', $collaborateurVersion);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('collaborateurversion_edit', array('id' => $collaborateurVersion->getId()));
        }

        return $this->render('collaborateurversion/edit.html.twig', array(
            'collaborateurVersion' => $collaborateurVersion,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a collaborateurVersion entity.
     *
     * @Route("/{id}", name="collaborateurversion_delete", methods={"DELETE"})
     * Method("DELETE")
     */
    public function deleteAction(Request $request, CollaborateurVersion $collaborateurVersion): Response
    {
        $form = $this->createDeleteForm($collaborateurVersion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->remove($collaborateurVersion);
            $em->flush($collaborateurVersion);
        }

        return $this->redirectToRoute('collaborateurversion_index');
    }

    /**
     * Creates a form to delete a collaborateurVersion entity.
     *
     * @param CollaborateurVersion $collaborateurVersion The collaborateurVersion entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(CollaborateurVersion $collaborateurVersion): Response
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('collaborateurversion_delete', array('id' => $collaborateurVersion->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
