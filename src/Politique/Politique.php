<?php

namespace App\Politique;

use App\Entity\Version;

class Politique
{
    public const POLITIQUE     =           0;  // politique nulle
    public const CPU_POLITIQUE  =          1;  // politique par défaut
    public const GPU_POLITIQUE  =          2;

    public const DEFAULT_POLITIQUE =  self::CPU_POLITIQUE;  // la politique par défaut


    public const   LIBELLE_POLITIQUE =
        [
            self::POLITIQUE                 =>  'POLITIQUE',
            self::CPU_POLITIQUE             =>  'CPU_POLITIQUE',
            self::GPU_POLITIQUE             =>  'GPU_POLITIQUE',
        ];

    public static function getLibelle($politique)
    {
        if ($politique != null && array_key_exists($politique, self::LIBELLE_POLITIQUE)) {
            return self::LIBELLE_POLITIQUE[$politique];
        } else {
            return 'UNKNOWN';
        }
    }

    public function getName()
    {
        return "politique";
    }

    public function getData(Version $version)
    {
        return null;
    }
}
