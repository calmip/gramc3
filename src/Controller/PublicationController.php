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

use App\Entity\Publication;
use App\Entity\Projet;
//use App\App;
use App\Utils\Functions;

use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use App\Form\PublicationType;

use Symfony\Component\HttpFoundation\Response;

/**
 * Publication controller.
 *
 * @Route("publication")
 */
class PublicationController extends Controller
{
    /**
     * Lists all publication entities.
     *
     * @Route("/", name="publication_index")
     * @Method("GET")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $publications = $em->getRepository('App:Publication')->findAll();

        return $this->render('publication/index.html.twig', array(
            'publications' => $publications,
        ));
    }
    
    /**
     * @Route("/{id}/gerer",name="gerer_publications" )
     * @Security("is_granted('ROLE_DEMANDEUR')")
     */
    public function gererAction(Projet $projet, Request $request, LoggerInterface $lg)
    {
		$em = $this->getDoctrine()->getManager();
		
        $publication    = new Publication();
        $form = $this->createForm('App\Form\PublicationType', $publication);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() )
        {
            if( $publication->getIdPubli() != null )
            {
                $sj->noticeMessage( "PublicationController gererAction : La publication " . $publication->getIdPubli() . " est partagée par plusieurs projets");
                $old = $em->getRepository(Publication::class)->find( $publication->getIdPubli() );
                if( $old->getRefbib() != $publication->getRefbib() )
                {
                    $sj->warningMessage("Changement de REFBIB de la publication " . $publication->getIdPubli() );
                    $old->setRefbib( $publication->getRefbib() );
                }
                
                if( $old->getDoi() != $publication->getDoi() )
                {
                    $sj->warningMessage("Changement de DOI de la publication " . $publication->getIdPubli() );
                    $old->setDoi( $publication->getDoi() );
                }

                if( $old->getOpenUrl() != $publication->getOpenUrl() )
                {
                    $sj->warningMessage("Changement de OpenUrl de la publication " . $publication->getIdPubli() );
                    $old->setOpenUrl( $publication->getOpenUrl() );
                }

                if( $old->getAnnee() != $publication->getAnnee() )
                {
                    $sj->warningMessage("Changement d'année de la publication " . $publication->getIdPubli() );
                    $old->setAnnee( $publication->getAnnee() );
                }
                
                $publication = $old;
            }
                
            $projet->addPubli( $publication );
            $publication->addProjet( $projet );
            Functions::sauvegarder( $publication, $em, $lg );
            Functions::sauvegarder( $projet, $em, $lg );
        }

        $form = $this->createForm('App\Form\PublicationType', new Publication() ); // on efface le formulaire
            
        return $this->render( 'publication/liste.html.twig',
        [
            'publications' => $projet->getPubli(),
            'form'  => $form->createView(),
            'projet'    => $projet,
        ]
        );
    }

    /**
     * @Route("/{id}/consulter",name="consulter_publications" )
     * @Security("is_granted('ROLE_EXPERT')")
     */
    public function consulterAction(Projet $projet, Request $request)
    {
            
        return $this->render( 'publication/consulter.html.twig',
        [
            'publications' => $projet->getPubli(),
            'projet'    => $projet,
        ]
        );
    }

    /**
     * @Route("/annee",name="publication_annee" )
     * @Security("is_granted('ROLE_OBS') or is_granted('ROLE_PRESIDENT')")
     * @Method({"GET", "POST"})
     */
    public function AnneeAction(Request $request)
    {
		$ss    = $this->get('App\GramcServices\ServiceSessions');
		$data  = $ss->selectAnnee($request); // formulaire
        $annee = $data['annee'];
        $em    = $this->getDoctrine()->getManager();
        $publications = $em->getRepository(Publication::class)->findBy( ['annee' => $annee ] );

        return $this->render('publication/annee.html.twig',
			[
			'form'  => $data['form']->createView(), // formulaire
			'annee' => $annee,
			'publications' => $publications,
			]);
    }

    /**
     * @Route("/{annee}/annee_csv",name="publication_annee_csv" )
     * @Security("is_granted('ROLE_OBS') or is_granted('ROLE_PRESIDENT')")
     * @Method({"GET", "POST"})
     */
    public function AnneeCsvAction($annee)
    {
        $em    = $this->getDoctrine()->getManager();
        $publications = $em->getRepository(Publication::class)->findBy( ['annee' => $annee ] );
        
		$header  = [
                    'Référence',
                    'annee',
                    'doi',
                    'URL',
                    'Projets'
                    ];
                    
        $sortie = join("\t",$header) . "\n";
        foreach ( $publications as $publi)
        {
			$line   = [];
			$line[] = '"'.str_replace(["\n","\r\n","\t",'"'],[' ',' ',' ',' '],$publi->getRefbib()).'"';
			//$line[] = 'ref';
			$line[] = $publi->getAnnee();
			$line[] = $publi->getDoi();
			$line[] = $publi->getOpenUrl();
			$line[] = join(" ",$publi->getProjet()->toArray());
			$sortie.= join("\t",$line) . "\n";
		}
		return Functions::csv($sortie,'publis_'.$annee.'.csv');
    }

    /**
     * Creates a new publication entity.
     *
     * @Route("/new", name="publication_new")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $publication = new Publication();
        $form = $this->createForm('App\Form\PublicationType', $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $em = $this->getDoctrine()->getManager();
            $em->persist($publication);
            $em->flush($publication);

            return $this->redirectToRoute('publication_show', array('id' => $publication->getId()));
        }

        return $this->render('publication/new.html.twig', array(
            'publication' => $publication,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a publication entity.
     *
     * @Route("/{id}/show", name="publication_show")
     * @Method("GET")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function showAction(Publication $publication)
    {
        $deleteForm = $this->createDeleteForm($publication);

        return $this->render('publication/show.html.twig', array(
            'publication' => $publication,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing publication entity.
     *
     * @Route("/{id}/edit", name="publication_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function editAction(Request $request, Publication $publication)
    {
        $deleteForm = $this->createDeleteForm($publication);
        $editForm = $this->createForm('App\Form\PublicationType', $publication);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('publication_edit', array('id' => $publication->getId()));
        }

        return $this->render('publication/edit.html.twig', array(
            'publication' => $publication,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }


    /**
     * Displays a form to edit an existing publication entity.
     *
     * @Route("/{id}/{projet}/modify", name="modifier_publication")
     * @Security("is_granted('ROLE_DEMANDEUR')") 
     * @Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, Publication $publication, Projet $projet, LoggerInterface $lg)
    {
		$sj = $this->get('App\GramcServices\ServiceJournal');
		$em = $this->getdoctrine()->getManager();

        $editForm = $this->createForm('App\Form\PublicationType', $publication);
        $editForm->handleRequest($request);
        
        $deleteForm =  $this->createFormBuilder()
            ->setAction($this->generateUrl('supprimer_publication', ['id' => $publication->getId(), 'projet' =>  $projet->getIdProjet()] ))
            ->setMethod('DELETE')
            ->getForm()
        ;
        if ($editForm->isSubmitted() && $editForm->isValid())
            {
            Functions::sauvegarder( $publication, $em, $lg );
            if( count(  $publication->getProjet() ) > 1 )
                 $sj->warningMessage("Modification de la publication  " . $publication->getIdPubli() . " partagée par plusieurs projets" ); 
            return $this->redirectToRoute('gerer_publications', [ 'id' => $projet->getIdProjet() ] );
            }

        return $this->render('publication/modify.html.twig', array(
            'publication' => $publication,
            'projet' => $projet,
            'form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a publication entity.
     *
     * @Security("is_granted('ROLE_ADMIN')") 
     * @Route("/{id}", name="publication_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Publication $publication)
    {

        $form = $this->createDeleteForm($publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($publication);
            $em->flush($publication);
        }

        return $this->redirectToRoute('publication_index');
    }

    /**
     * Deletes a publication entity.
     * 
     * @Security("is_granted('ROLE_DEMANDEUR')") 
     * @Route("/{id}/{projet}/supprimer", name="supprimer_publication")
     * @Method({ "GET","DELETE"})
     */
    public function supprimerAction(Request $request, Publication $publication, Projet $projet, LoggerInterface $lg)
    {
		$ac = $this->get('security.authorization_checker');
		$token = $this->get('security.token_storage')->getToken();
		$sj = $this->get('App\GramcServices\ServiceJournal');
		$em = $this->getdoctrine()->getManager();

        // ACL
        if( ! $projet->isCollaborateur($token->getUser()) && ! $ac->isGranted('ROLE_ADMIN') )
        {
			$sj->throwException();
		}
        
        $projet->removePubli( $publication );
        $publication->removeProjet( $projet );
        Functions::sauvegarder( $projet, $em, $lg );
        Functions::sauvegarder( $publication, $em, $lg );

        if( $publication->getProjet() == null )
                {
                $em = $this->getDoctrine()->getManager();
                $em->remove($publication);
                $em->flush($publication);
                }
                
        return $this->redirectToRoute('gerer_publications', [ 'id' => $projet->getIdProjet() ] );
    }

    /**
     * Creates a form to delete a publication entity.
     *
     * @param Publication $publication The publication entity
     * @Security("is_granted('ROLE_ADMIN')")
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Publication $publication)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('publication_delete', ['id' => $publication->getId() ] ))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * Autocomplete publication
     *
     * @Route("/autocomplete", name="publication_autocomplete")
     * @Security("is_granted('ROLE_DEMANDEUR')") 
     * @Method({"POST","GET"})
     */
    public function autocompleteAction(Request $request)
    {
		$sj = $this->get('App\GramcServices\ServiceJournal');
		$em = $this->getDoctrine()->getManager();
		
        $sj->debugMessage('autocompleteAction ' .  print_r($_POST, true) );
        $form = $this
            ->get('form.factory')
            ->createNamedBuilder('autocomplete_form', FormType::class, [])
            ->add('refbib', TextType::class, [ 'required' => true, 'csrf_protection' => false] )
            ->getForm();
            
        $form->handleRequest($request);
        
        if ( $form->isSubmitted() ) // nous ne pouvons pas ajouter $form->isValid() et nous ne savons pas pourquoi
            {
             if ( array_key_exists('refbib',$form->getData() ) )
                $data   =   $em->getRepository(Publication::class)->liste_refbib_like( $form->getData()['refbib'] );
            else
                $data  =   [ ['refbib' => 'Problème avec AJAX dans PublicationController:autocompleteAction' ] ];
            
            $output = [];
            foreach( $data as $item )
                if( array_key_exists('refbib', $item ))
                    $output[]   =   $item['refbib'];

            $response = new Response(json_encode( $output ) );
            $response->headers->set('Content-Type', 'application/json');
            return $response;
            }

        // on complète le reste des informations

        $publication    = new Publication();
        $form = $this->createForm(PublicationType::class, $publication, ['csrf_protection' => false]);            
        $form->handleRequest($request);
        
        if (  $form->isSubmitted()  && $form->isValid() )
            {
            $publication = $em->getRepository(Publication::class)->findOneBy(['refbib' => $publication->getRefbib() ]);
            
            if( $publication != null   )
                {
                $form = $this->createForm(PublicationType::class, $publication, ['csrf_protection' => true]);   
                return $this->render('publication/form.html.twig', [ 'form' => $form->createView() ] );
                }
            else
                return  new Response('nopubli');
            }
        //return new Response( 'no form submitted' );

        $form = $this->createForm(PublicationType::class, $publication, ['csrf_protection' => true]);   
                
        return $this->render('publication/form.html.twig', [ 'form' => $form->createView() ] );
        
        return new Response( json_encode('no form submitted') );
    }
}
