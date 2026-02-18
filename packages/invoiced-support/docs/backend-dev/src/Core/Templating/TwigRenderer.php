<?php

namespace App\Core\Templating;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Statsd\StatsdFacade;
use App\Core\Templating\Exception\RenderException;
use Throwable;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Loader\ArrayLoader;

class TwigRenderer implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private static self $instance;
    private Environment $twig;
    private ArrayLoader $twigLoader;
    private static string $cacheCheckSum = '';

    /**
     * Gets the singleton instance of this renderer.
     */
    public static function get(array $extensions = []): self
    {
        // in case our extensions change, we need to rebuild the twig environment
        $checkSumCandidate = md5(implode(',', array_map(fn ($extension) => $extension::class, $extensions)));

        if (!isset(self::$instance) || self::$cacheCheckSum !== $checkSumCandidate) {
            // When this is refactored to use the service container it
            // should come from '%kernel.cache_dir%/twig'. The below
            // code will produce the same value.
            $cacheDir = dirname(__DIR__, 3).'/var/cache/'.getenv('APP_ENV').'/twig';
            self::$instance = new self($cacheDir, $extensions);
            self::$instance->setStatsd(StatsdFacade::get());
            self::$cacheCheckSum = $checkSumCandidate;
        }

        return self::$instance;
    }

    /**
     * @param AbstractExtension[] $extensions An array of Twig extensions to add to the environment
     */
    public function __construct(private string $cacheDir, private readonly array $extensions = [])
    {
    }

    /**
     * Renders a template using Twig.
     *
     * @throws RenderException when the template cannot be rendered
     */
    public function render(string $template, array $parameters, TwigContext $context): string
    {
        $twig = $this->getTwig();

        // Add private variables needed by our custom filters and functions
        // from the given context.
        $parameters = array_replace($parameters, $context->getParameters());

        try {
            // The template name does not matter because the array loader
            // will build a cache key based on the name AND template contents
            // to ensure that cache keys are always unique.
            $this->twigLoader->setTemplate('custom_twig', $template);

            return @$twig->render('custom_twig', $parameters);
        } catch (Throwable $e) {
            $this->statsd->increment('pdf.twig_error');

            throw new RenderException('Could not render document due to a parsing error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Gets a Twig instance.
     */
    private function getTwig(): Environment
    {
        if (isset($this->twig)) {
            return $this->twig;
        }

        $this->twigLoader = new ArrayLoader();
        $this->twig = new Environment($this->twigLoader, [
            'cache' => $this->cacheDir,
        ]);

        // add our custom filters and functions
        $this->twig->addExtension(new TwigUserTemplateExtension());
        foreach ($this->extensions as $extension) {
            $this->twig->addExtension($extension);
        }
        TwigSandboxing::install($this->twig);

        return $this->twig;
    }
}
