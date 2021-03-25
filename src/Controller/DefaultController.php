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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//use App\App;
use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Individu;

use App\Utils\Functions;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use JpGraph\JpGraph;

class DefaultController extends Controller
{
   /**
     * @Route("/test", name="test")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function testAction(Request $request)
    {
		$em = $this->getDoctrine()->getManager();
    $projet = $em->getRepository(Projet::class)->findOneBy(['idProjet' => 'P1440']);
    //return new Response( count( $projet->getVersion() ) );
    //return new Response( gettype($projet->calculDerniereVersion()  ));
    //return new Response( $projet->derniereVersion()->getSession() );
    //return new Response( $projet->calculDerniereVersion()->getSession() );
    //return new Response( $projet->getVersionDerniere()->getSession() );





    $query = $em->createQuery('SELECT partial u.{idIndividu,nom} AS individu, partial s.{eppn} AS sso, count(s) AS score FROM App\Entity\Individu u JOIN u.sso s GROUP BY u');
    $result = $query->getResult();
    //$version = $em->getRepository(Version::class)->findDerniereVersion( $projet  );

    return new Response( get_class(  $result[0]['individu'] ));
    return new Response( gettype(  $result[0]['individu'] ));
    return new Response(implode(" ",array_keys( $result[0] ) ));
    return new Response($result[0]['score'] );

    if( gettype($result) == 'array' )
        return new Response( gettype( end($result) ) );
    else
        return new Response( gettype($result)  );

    return new Response( implode(" ", array_keys($result) ) );
    }

    /**
     * @Route("/twig", name="twig")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function twigAction(Request $request)
    {
		$sn = $this->get('App\GramcServices\ServiceNotifications');
		$em = $this->getDoctrine()->getManager();
		
	    $users    = [ 'a@x', 'b@x' ];
	    $users    = $em->getRepository(Individu::class)->findBy( ['president' => true ] );
	    $versions = $em->getRepository(Version::class)->findAll();
	    $users    = $sn->mailUsers( [ 'E','R' ], $versions[301] );
	    $output   = $sn->sendMessage( 'projet/dialog_back.html.twig' ,'projet/dialog_back.html.twig' , [ 'projet' => [ 'idProjet' => 'ID' ] ], $users);
	
	    //return new Response ( $users[0] );
	
	    //return new Response ( Functions::getSessionCourante()->getPresident() );
	
	    return new Response( $output['to'] );
	    return new Response( $output['contenu'] );
	    return new Response( $output['subject'] );
    }

  /**
     * @Route("/test_projet", name="test_projet")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    //public function test_projetAction(Request $request)
    //{
    //   $projets = $em->getRepository(Projet::class)->get_projets_resp_ou_collab('31', false, true);
    //   return new Response( $projets[0]->getIdProjet() );
    //}

    /**
     * 
     * ça fait quoi ça ? (manu)
     * 
     * @Route("/count", name="count")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function countAction(Request $request)
    {
		
		$sp = $this->get('App\GramcServices\ServiceProjets');
		$sj = $this->get('App\GramcServices\ServiceJournal');
		$em = $this->getDoctrine()->getManager();
		
        $projets = $em->getRepository(Projet::class)->heuresProjetsAnnee($annee, Functions::TOUS);
       //return new Response( Functions::show( $projets ));


       $projets = $em->getRepository(Projet::class)->heuresProjetsAnnee($annee);
       //return new Response( Functions::show( $projets ));

       $projets = $em->getRepository(Projet::class)->findProjetsAnnee($annee, Functions::TOUS);

       $conso = 0;
       foreach( $projets as $projet )
		{
            $consoProjet    =  $sp->getConsoCalcul($projet, $annee);
            if(  $consoProjet < 0 )
                $sj->errorMessage(__METHOD__ . ':' . __FILE__ . " consommation du projet " . $projet . " pour l'année " . $annee . " negative !");
            else
                $conso  +=  $consoProjet;
		}

       return new Response( "conso = " . $conso . "  ". Functions::show( $projets ));

       $projets = $em->getRepository(Projet::class)->countProjetsAnnee($annee);
       return new Response( Functions::show( $projets ));
    }

    /**
     * @Route("/test_params/{id1}/{id2}", name="test_params")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function test_paramsAction(Request $request)
    {
       return new Response( 'ok' );
    }

	/**
     * @Route("/test_session", name="test_session")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function test_sessionAction(Request $request)
    {
		$ss = $this->get('App\GramcServices\ServiceSessions');
		return new Response( var_dump( $ss->getSessionCourante() ) );
    }

   /**
     * @Route("/test_form", name="test_session")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function test_formAction(Request $request)
    {
        $form = $this
           ->get('form.factory')
           ->createNamedBuilder('image_form', FormType::class, [])
           ->add('image', TextType::class, [ 'required'       =>  false,] )
           ->add('number', TextType::class, ['required'       =>  false,] )
           ->getForm();

        $form->handleRequest($request);

        //if ($form->isSubmitted() )
        print_r($_POST, true);

        return $this->render('version/test_form.html.twig',
            [
            'form'       =>   $form->createView(),
            'print'     => print_r($_POST, true)
            ]);
    }
}
