<?php

namespace App\Core\Templating;

use App\Core\Statsd\StatsdFacade;
use App\Core\Templating\Exception\MustacheException;
use Mustache_Engine;
use Mustache_Exception;

class MustacheRenderer
{
    private static self $instance;

    /**
     * Gets the singleton instance of this renderer.
     */
    public static function get(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Renders a template using Mustache.
     *
     * @param string $template Mustache template
     *
     * @throws MustacheException when the template cannot be rendered
     */
    public function render(string $template, array $parameters): string
    {
        try {
            $m = new Mustache_Engine();

            return $m->render($template, $parameters);
        } catch (Mustache_Exception $e) {
            StatsdFacade::get()->increment('pdf.mustache_error');

            throw new MustacheException('Could not render document due to a parsing error: '.$e->getMessage(), 0, $e);
        }
    }
}
