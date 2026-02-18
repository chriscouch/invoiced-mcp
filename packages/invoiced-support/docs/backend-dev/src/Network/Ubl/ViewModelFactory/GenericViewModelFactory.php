<?php

namespace App\Network\Ubl\ViewModelFactory;

use App\Network\Interfaces\UblDocumentViewModelFactoryInterface;
use App\Network\Ubl\ViewModel\DocumentViewModel;
use SimpleXMLElement;

final class GenericViewModelFactory implements UblDocumentViewModelFactoryInterface
{
    public function make(SimpleXMLElement $xml): DocumentViewModel
    {
        return new DocumentViewModel();
    }
}
