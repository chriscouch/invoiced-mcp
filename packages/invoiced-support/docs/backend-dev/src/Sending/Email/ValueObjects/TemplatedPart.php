<?php

namespace App\Sending\Email\ValueObjects;

use App\Core\Templating\TwigFacade;

class TemplatedPart
{
    private string $content;

    public function __construct(private string $template, private array $variables)
    {
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getContent(): string
    {
        if (!isset($this->content)) {
            $this->content = TwigFacade::get()->render($this->template, $this->variables);
        }

        return $this->content;
    }

    public function __invoke(): string
    {
        return $this->getContent();
    }
}
