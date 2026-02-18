<?php

namespace App\Network\Interfaces;

use App\Network\Ubl\ViewModel\DocumentViewModel;
use SimpleXMLElement;

interface UblDocumentViewModelFactoryInterface
{
    public function make(SimpleXMLElement $xml): DocumentViewModel;
}
