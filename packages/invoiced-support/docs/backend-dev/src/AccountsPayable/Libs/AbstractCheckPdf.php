<?php

namespace App\AccountsPayable\Libs;

use App\Core\Pdf\Exception\PdfException;
use App\Core\Pdf\HtmlToPdf;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Themes\Interfaces\PdfBuilderInterface;
use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

abstract class AbstractCheckPdf implements StatsdAwareInterface, PdfBuilderInterface
{
    use StatsdAwareTrait;
    private array $parameters;
    private string $template;

    public function __construct(private readonly Environment $twig, private readonly HtmlToPdf $htmlToPdf)
    {
    }

    public function setParameters(array $checks, int $perPage = 1): void
    {
        $parameters['title'] = 'Bills-Checks-'.date('YmdHis').'.pdf';
        $parameters['highlightColor'] = '000';
        $parameters['css'] = '';
        $parameters['testMode'] = false;
        $parameters['checks'] = $checks;
        $this->template = $this->getTemplate($perPage);
        $this->parameters = $parameters;
    }

    public function build(string $locale): string
    {
        $templatesDir = dirname(__DIR__, 3).'/templates';
        $content = (string) file_get_contents($templatesDir.$this->template);
        (new ArrayLoader())->setTemplate($this->template, $content);
        try {
            $html = $this->twig->render($this->template, $this->parameters);

            return $this->htmlToPdf->makeFromHtml($html, [
                'orientation' => 'Portrait',
                'page-size' => 'Letter',
                'no-outline',
                'margin-top' => 0,
                'margin-right' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'disable-smart-shrinking',
            ]);
        } catch (Throwable $e) {
            $this->statsd->increment('pdf.twig_error');
            throw new PdfException('Could not render document due to an error: '.$e->getMessage(), 0, $e);
        }
    }

    public function getFilename(string $locale): string
    {
        return 'Bills-Checks-'.date('YmdHis').'.pdf';
    }

    abstract public function getTemplate(int $perPage = 1): string;

    public function toHtml(string $locale): string
    {
        throw new PdfException('Not supported');
    }
}
