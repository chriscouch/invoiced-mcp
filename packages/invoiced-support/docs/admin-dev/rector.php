<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Nette\Set\NetteSetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES);
    $containerConfigurator->import(SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES);
    $containerConfigurator->import(NetteSetList::ANNOTATIONS_TO_ATTRIBUTES);
    $containerConfigurator->import(SensiolabsSetList::FRAMEWORK_EXTRA_61);
};
