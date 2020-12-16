<?php

namespace App\Politique;

use App\Entity\Version;


class GpuPolitique  extends Politique
{

    public function getName()   {  return "gpu"; }
    
    public function getData(Version $version)   {  return new GpuData($version);  }
    
}
