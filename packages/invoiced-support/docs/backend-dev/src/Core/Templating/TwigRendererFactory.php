<?php

namespace App\Core\Templating;

class TwigRendererFactory
{
    public function __construct(private readonly TwigAutomationsExtension $extension)
    {
    }

    /**
     * @throws Exception\RenderException
     */
    public function render(string $template, array $parameters, TwigContext $context): string
    {
        return TwigRenderer::get([$this->extension])->render($template, $parameters, $context);
    }
}
