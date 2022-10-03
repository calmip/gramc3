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

namespace App\Utils;

//use App\App;
use App\Entity\Journal;
use App\Entity\Individu;
use App\Entity\Templates;
use App\Entity\Session;
use App\Entity\Version;
use App\Entity\Projet;
use App\Entity\Thematique;
use App\Entity\CollaborateurVersion;

use App\Entity\Expertise;
use App\Entity\Sso;
use App\Entity\CompteActivation;

use App\Controller\SessionController;

use App\Utils\GramcDate;
use App\Utils\ExactGramcDate;

use App\GramcServices\ServiceJournal;

use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;


use Doctrine\ORM\ORMException;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Monolog\Logger;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\Proxy;
use Doctrine\Common\Util\Debug;
use Doctrine\ORM\EntityManagerInterface;

use App\Form\ChoiceList\ExpertChoiceLoader;

class Functions
{
    public const TOUS      =   0;  // tous les projets
        public const ANCIENS   =   1;  // les projets renouvellés
        public const NOUVEAUX  =   2;  // les nouveaux projets

    /*
     * calcul des années pour des formulaires
     *
     */
    public static function years($begin, $end, $difference = 5)
    {
        // le nombre d'années est +- 5 par défaut, nous devons le changer
        $first_year = $begin->format('Y');      // la première année
        $last_year = $end->format('Y');         // la dernière année

        if ($first_year <= $last_year) {
            $years = range($first_year - $difference, $last_year + $difference);
        } else {
            $years = range($last_year - $difference, $first_year + $difference);
        }

        return $years;
    }

    public static function choicesYear($begin = null, $end = null, $difference = 5)
    {
        $choices = [];
        foreach (array_values(static::years($begin, $end, $difference)) as $choice) {
            $choices[$choice]  =   $choice;
        }
        return $choices;
    }

    /*
     * sauvegarder un objet avec un traitement des exceptions
     * 
     *    - Si le flush se passe bien, renvoie true
     *    - Sinon:
     *       - écrit des choses dans le Logger (il y a des chances que le journal ne marche pas non plus)
     *       - Si on est dans une requête ajax: renvoie false, sinon: relance l'exception
     *
     */
    public static function sauvegarder(object $object, EntityManager $em, Logger $logger=null): bool
    {
        try {
            if ($em->isOpen())
            {
                $em->persist($object);
                $em->flush($object);
                return true;
            }
            else
            {
                if ($logger != null) $logger()->error(__METHOD__ . ":" . __LINE__ . ' Entity manager closed');
                return static::exception_treatment(new ORMException());
            }
        }
        catch (ORMException $e) {
            if ($logger != null) $logger()->error(__METHOD__ . ":" . __LINE__ . ' ORMException');
            return static::exception_treatment($e);
        }
        catch (\InvalidArgumentException $e) {
            if ($logger != null) $logger()->error(__METHOD__ . ":" . __LINE__ . ' InvalidArgumentException');
            return static::exception_treatment($e);
        }
        catch (DBALException $e) {
            if ($logger != null) $logger()->error(__METHOD__ . ":" . __LINE__ . ' DBALException');
            return static::exception_treatment($e);
        }
    }

    /*
     * Appelé par sauvegarder - retourne false ou propage l'exception suivant la requête
     *
     */
    private static function exception_treatment($e): bool
    {
        if (Request::createFromGlobals()->isXmlHttpRequest()) {
            return false;
        } else {
            throw $e;
        }
    }

    /*
     * Remplacement de flush(): Appelle flush en traitant l'exception.
     * Dépose si besoin l'exception dans le flashbag de la session
     * 
     * On ne traite que les ORMException - Par exemple violation de contrainte d'intégrité
     * Si une autre eception est générée (DBALException par exemple) elle sera propagée
     *
     * $em = EntityManager, on doit avoir fait l'appel persist avant d'appeler cette fonction
     * Retourne true si OK, false si une exception a été générée
     *
     * TODO - Je n'ai pas réussi à intercepter autre chose que \Exception. du coup je suis sceptique sur
     *        Function::sauvegarder, pas sûr que ça marche...
     */
    public static function flush(EntityManagerInterface $em, Request $request=null): bool
    {
        try
        {
            $em->flush();
        }
        catch ( \Exception $e) {
            if ($request != null && $request->getSession() != null) {
                $request->getSession()->getFlashbag()->add("flash erreur",$e->getMessage());
                return false;
            }
        }
        return true;
    }
    
    //////////////////////////////////////////////////

    /***************
     * Téléchargement d'un fichier csv
     *****************/
    public static function csv($content, $filename = 'filename')
    {
        $response = new Response();
        $response->setContent($content);
        $response->headers->set('Content-Type', 'text/csv');  // télécharger
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('Cache-Control', 'post-check=0,pre-check=0');
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        return $response;
    }

    /***************
     * Téléchargement d'un fichier pdf
     *****************/
    public static function pdf($filename, $dwnfn=null)
    {
        if ($dwnfn==null) {
            $dwnfn = $filename;
        }
        $response = new Response();
        if (preg_match('/^\%PDF/', $filename)) {
            //$filename = preg_replace('~[\r\n]+~', '', $filename);
            $response->setContent($filename);
            $response->headers->set('Content-Disposition', 'inline; filename="document_gramc.pdf"');
        } elseif ($filename != null && file_exists($filename) && ! is_dir($filename)) {
            $response->setContent(file_get_contents($filename));
            $response->headers->set('Content-Disposition', 'inline; filename="' . basename($dwnfn) .'"');
        } else {
            $response->setContent('');
        }

        $response->headers->set(
            'Content-Type',
            'application/pdf'
        );

        return $response;
    }

    public static function string_conversion($string)
    {
        return str_replace(["\n", "\t", "\r"], '  ', trim($string));
    }

    ////////////////////////////////////////////////////

    ////////////////////////////////////////////////////


    // Renvoie une représentation en "string" de la variable passée en input
    // Utilisé pour déboguer
    public static function show($input)
    {
        if ($input instanceof \DateTime) {
            return $input->format("d F Y H:i:s");
        } elseif (is_object($input)) {
            $reflect    = new \ReflectionClass($input);
            if (method_exists($input, '__toString')) {
                return '{'.$reflect->getShortName() .':'.$input->__toString().'}';
            } elseif (method_exists($input, 'toArray')) {
                return '{'.$reflect->getShortName() .':' . static::show($input->toArray()) .'}';
            } else {
                ob_start();
                Debug::dump($input, 1);
                //return '{'.$reflect->getShortName().'}';
                return ob_get_clean();
            }
        } elseif (is_string($input)) {
            return "'" . $input . "'";
        } elseif ($input === []) {
            return '[]';
        } elseif (is_array($input)) {
            $output = '[ ';
            foreach ($input as $key => $value) {
                $output .= static::show($key) . '=>' . static::show($value) . ' ';
            }
            return $output .= ']';
        } elseif ($input === null) {
            return 'null';
        } elseif (is_bool($input)) {
            if ($input == true) {
                return 'true';
            } else {
                return 'false';
            }
        } else {
            return $input;
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////
    // TODO - A METTRE DANS ServicesForms
    public static function dataError(ValidatorInterface $sval, $data, $groups = ['Default']): array
    {
        if (is_string($groups)) {
            $groups = [$groups];
        }
        $violations = $sval->validate($data, null, $groups);

        $erreurs = [];
        foreach ($violations as $violation) {
            $erreurs[] = $violation->getMessage();
        }
        return $erreurs;
    }
    ///////////////////////////////////////////////////////////////////////////////////////////////////

    // $old et $new sont soit true, soit arrays

    public static function merge_return($old, $new)
    {
        if (is_array($old) && is_array($new)) {
            return array_merge($old, $new);
        } elseif (is_bool($new) && is_array($old)) {
            return $old;
        } elseif (is_bool($old)  && is_array($new)) {
            return $new;
        } elseif (is_bool($old)  && is_bool($new)) {
            return $new && $old;
        } else {
            throw new \exception();
        }
        //else
        //    static::errorMessage(__METHOD__ . " arguments error" . static::show( $new ) . " " . static::show( $old ) );
        //return false;
    }

    /**********
     * $ff = form factory
     *
     * Usage: $ff   = $this->get('form.factory')
     *        $form = Function::getFormBuilder($ff);
     *
     * TODO - Ces fonctions n'ont aucun intérêt - A virer !
     *
     **********************************/
    public static function getFormBuilder($ff, $nom = 'form', $class = FormType::class, $options = [])
    {
        return  $ff->createNamedBuilder($nom, $class, null, $options);
    }

    public static function createFormBuilder($ff, $data = null, $options = [])
    {
        return $ff->createBuilder(FormType::class, $data, $options);
    }

    /*************************************************************
     * SimpleEncrypt: Chiffre un message en utilisant
     *                la clé de la variable d'environnement
     *                CLE_DE_CHIFFREMENT
     *
     * param string $message
     * return string (le message chiffré et base64 encoded)
     *
     * Utilise la bibliothèque sodium
     * adapté de:
     * https://paragonie.com/blog/2017/06/libsodium-quick-reference-quick-comparison-similar-functions-and-which-one-use#crypto-secretbox
     **************************************************************/

    public static function simpleEncrypt($message)
    {
        $key = $_SERVER['CLE_DE_CHIFFREMENT'];

        $nonce     = random_bytes(24); // NONCE = Number to be used ONCE, for each message
        $encrypted = sodium_crypto_secretbox(
            $message,
            $nonce,
            $key
        );
        return base64_encode($nonce . $encrypted);
    }

    /**************************************************************
     * SimpleDecrypt: l'inverse de SimpleEncrypt
     **************************************************************/

    public static function simpleDecrypt($message)
    {
        $key = $_SERVER['CLE_DE_CHIFFREMENT'];

        $message = base64_decode($message);
        $nonce = mb_substr($message, 0, 24, '8bit');
        $ciphertext = mb_substr($message, 24, null, '8bit');
        try {
            $plaintext = sodium_crypto_secretbox_open(
                $ciphertext,
                $nonce,
                $key
            );
        } catch (\Exception $e) {
            return "";
        }
        if (!is_string($plaintext)) {
            return "";
        }
        return $plaintext;
    }
}
