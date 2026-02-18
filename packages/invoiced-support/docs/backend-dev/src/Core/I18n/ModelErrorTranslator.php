<?php

namespace App\Core\I18n;

use App\Core\Orm\Interfaces\TranslatorInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class ModelErrorTranslator implements ContainerAwareInterface, TranslatorInterface
{
    use ContainerAwareTrait;

    public function translate(string $phrase, array $params = [], ?string $locale = null, ?string $fallback = null): string
    {
        if (!$this->container) {
            return $phrase;
        }

        /** @var \Symfony\Contracts\Translation\TranslatorInterface $translator */
        $translator = $this->container->get('app.translator');

        // wrap parameters in `%` to make them unique
        $newParams = [];
        foreach ($params as $key => $value) {
            $newParams['%'.$key.'%'] = $value;
        }

        return $translator->trans($phrase, $newParams, 'errors', $locale);
    }
}
