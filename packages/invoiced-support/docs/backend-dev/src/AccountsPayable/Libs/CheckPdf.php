<?php

namespace App\AccountsPayable\Libs;

use App\AccountsPayable\Enums\CheckStock;
use App\Core\Pdf\Exception\PdfException;
use App\Core\Pdf\HtmlToPdf;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Themes\Interfaces\PdfBuilderInterface;
use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class CheckPdf implements StatsdAwareInterface, PdfBuilderInterface
{
    use StatsdAwareTrait;
    private array $parameters;
    private string $template;
    private string $filename = 'Print Check.pdf';

    public function __construct(private readonly Environment $twig, private readonly HtmlToPdf $htmlToPdf)
    {
    }

    public function getTemplate(int $perPage = 1): string
    {
        return 1 === $perPage ? '/pdf/checks/bill_check_main.twig' : '/pdf/checks/bill_check_multi.twig';
    }

    public function setParameters(array $checks, CheckStock $checkStock, string $filename): void
    {
        $parameters['title'] = 'Bills-Checks-'.date('YmdHis').'.pdf';
        $parameters['highlightColor'] = '000';
        $parameters['css'] = '';
        $parameters['testMode'] = false;
        $parameters['checks'] = $checks;
        $this->template = CheckStock::CheckOnTop == $checkStock ? '/pdf/checks/bill_check_main.twig' : '/pdf/checks/bill_check_multi.twig';
        $this->parameters = $parameters;
        $this->filename = $filename;
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
        return $this->filename;
    }

    public function toHtml(string $locale): string
    {
        throw new PdfException('Not supported');
    }
}
