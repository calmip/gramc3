<?php

namespace App\Politique;

use App\Entity\Version;

//use App\AppBundle;


class Data
{
    private $version;

    public function __construct(Version $version)
    {
        $this->version  =   $version;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setVersion(Version $version)
    {
        $this->version = $version;
        return $this;
    }

    public function getName()
    {
        return "data";
    }
}
