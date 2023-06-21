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

use App\Entity\Clessh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Sso controller.
 *
 * @Route("clessh")
 */
class ClesshController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tok
    ) {}

    /**
     * Liste toutes les clés ssh associées à l'utilisateur connecté
     * 
     * @Route("/gerer",name="gerer_clessh", methods={"GET"} )
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function gererAction(): Response
    {
        $token = $this->tok->getToken();
        $em = $this->em;

        $moi = $token->getUser();
        $menu = [];
        $menu[] = ['ok' => true,'name' => 'gerer_clessh_all' ,'lien' => 'Montrer tout','commentaire'=> 'Montrer aussi les clés révoquées'];
        $menu[] = ['ok' => true,'name' => 'ajouter_clessh' ,'lien' => 'Ajouter une clé','commentaire'=> 'Ajouter une clé'];

        // On filtre en ne présentant pas les clés révoquées !
        $clessh_all = $moi->getClessh();
        $clessh = [];
        foreach ($clessh_all as $c)
        {
            if ($c->getRvk()) continue;
            $clessh[] = $c;
        }

        return $this->render(
            'clessh/liste.html.twig',
            [
            'menu' => $menu,
            'clessh' => $clessh
            ]
        );
    }

    /**
     * Liste toutes les clés ssh associées à l'utilisateur connecté, même si elles sont révoquées
     * 
     * @Route("/gerer_all",name="gerer_clessh_all", methods={"GET"} )
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function gererActionAll(): Response
    {
        $token = $this->tok->getToken();
        $em = $this->em;

        $moi = $token->getUser();
        $menu = [];
        $menu[] = ['ok' => true,'name' => 'gerer_clessh' ,'lien' => 'Masquer','commentaire'=> 'Masquer les clés révoquées'];
        $menu[] = ['ok' => true,'name' => 'ajouter_clessh' ,'lien' => 'Ajouter une clé','commentaire'=> 'Ajouter une clé'];

        // On filtre en ne présentant pas les clés révoquées !
        $clessh_all = $moi->getClessh();

        return $this->render(
            'clessh/liste.html.twig',
            [
            'menu' => $menu,
            'clessh' => $clessh_all
            ]
        );
    }

    /**
     * Supprime une clé
     *
     * @Route("/{id}/supprimer", name="supprimer_clessh", methods={"GET"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     * Method("DELETE")
     */
    public function supprimerAction(Request $request, Clessh $clessh): Response
    {

        // On n'a pas le droit de supprimer une clé ssh révoquée !
        if ($clessh->getRvk())
        {
            $msg = "Cette clé est révoquée, vous ne pouvez ni la supprimer, ni l'utiliser";
            $request->getSession()->getFlashbag()->add("flash erreur",$msg);
        }
        else
        {
            $em = $this->em;
            $em->remove($clessh);
    
            try {
                $em->flush();
            }
            catch ( \Exception $e)
            {
                $msg = "Votre clé est utilisée dans un de vos projets, on ne peut pas la supprimer";
                $request->getSession()->getFlashbag()->add("flash erreur",$msg);
            }
        }
        
        return $this->redirectToRoute('gerer_clessh');
    }

    /**
     * Ajoute une nouvelle cléssh
     *
     * @Route("/ajouter", name="ajouter_clessh", methods={"GET","POST"})
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function ajouterAction(Request $request): Response
    {
        $em = $this->em;
        $token = $this->tok->getToken();

        $moi = $token->getUser();
        $clessh = new Clessh();
        $clessh->setIndividu($moi);

        $clessh->setIndividu($moi);
        $form = $this->createForm('App\Form\ClesshType', $clessh);

        $form->handleRequest($request);
        if ($form->isSubmitted())
        {
            if ($form->isValid())
            {
                // On garde l'empreinte de la clé ssh dans la base de données pour être sûr de l'unicité de chaque clé ssh

                // TODO ssh-keygen est appelé ici pour la SECONDE FOIS car il a déjà été appelé
                //      par ClesshValidator: pas très malin
                //      Mais au moins on est sûr qu'il renverra un résultat correct !
                //      Du coup on ne fait pas trop de tests
                //

                $o = [];
                $pub = $clessh->getPub();
                exec("/bin/bash -c 'ssh-keygen -l -f <(echo $pub)'",$o,$c);
                $empreinte = explode(' ',$o[0]);
                $clessh->setEmp($empreinte[1]);

                $em = $this->em;
                $em->persist($clessh);
                try
                {
                    $em->flush();
                }
                catch ( \Exception $e)
                {
                    $msg = "La clé n'a pas été ajoutée: Vous ne pouvez pas avoir deux fois la même clé, ou le même nom de clé.
                    Ou pire, quelqu'un a la même clé que vous";
                    $request->getSession()->getFlashbag()->add("flash erreur",$msg);
                }
                return $this->redirectToRoute('gerer_clessh');
            }
            else
            {
                $msg = "La clé n'a pas été ajoutée: elle n'est pas valide. Seuls certains types de clé sont autorisés";
                $request->getSession()->getFlashbag()->add("flash erreur",$msg);
                return $this->redirectToRoute('gerer_clessh');
            }
        }

        return $this->render(
            'clessh/ajouter.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_clessh',
                        'lien' => 'Clés ssh',
                        'commentaire'=> 'Retour vers la liste des clé ssh'
                        ] ],
            'laboratoire' => $clessh,
            'form' => $form->createView(),
            ]
        );
    }
}
