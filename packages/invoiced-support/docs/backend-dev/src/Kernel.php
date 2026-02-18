<?php

namespace App;

use App\Core\Facade;
use App\Core\I18n\ModelErrorTranslator;
use Defuse\Crypto\Key;
use App\Core\Orm\Driver\DbalDriver;
use App\Core\Orm\Errors;
use App\Core\Orm\Model;
use App\Core\Orm\Type;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();

        // The facade is used to migrate off of Infuse framework. It's a bad coding practice
        // and should eventually be phased out.
        Facade::init($this->container);

        $this->configureOrm();
    }

    private function configureOrm(): void
    {
        $driver = new DbalDriver($this->container->get('app.database'));
        Model::setDriver($driver);

        $translator = new ModelErrorTranslator();
        $translator->setContainer($this->container);
        Errors::setTranslator($translator);

        // set the encryption key
        Type::setEncryptionKey($this->container->get(Key::class));
    }
}
