<?php

namespace App\Sending\Email\EmailFactory;

use App\Companies\Models\Company;
use App\Core\Templating\Exception\RenderException;
use App\Core\Templating\TwigContext;
use App\Core\Templating\TwigRendererFactory;
use App\Sending\Email\ValueObjects\Email;
use App\Sending\Email\ValueObjects\NamedAddress;

class GenericEmailFactory extends AbstractEmailFactory
{
    public function __construct(string $inboundEmailDomain, private readonly TwigRendererFactory $rendererFactory)
    {
        parent::__construct($inboundEmailDomain);
    }

    public function make(Company $company, TwigContext $context, array $variables, array $to, array $cc, array $bcc, string $subject, string $message): string|Email
    {
        $bcc = implode(',', array_map(fn ($item) => $item['email'], $bcc));

        $to = $this->generateTo($to, $company);
        if (0 === count($to)) {
            return 'Invalid email address';
        }
        $cc = $this->generateCc($to, $cc);
        $bcc = $this->generateBccs($company, array_merge($to, $cc), $bcc);

        try {
            $subject = trim($this->rendererFactory->render(self::addRawToTwigVariables($subject), $variables, $context));
            $body = trim($this->rendererFactory->render(self::addRawToTwigVariables($message), $variables, $context));
        } catch (RenderException $e) {
            return 'Could not render email template due to a parsing error: '.$e->getMessage();
        }

        return (new Email())
            ->company($company)
            ->from(new NamedAddress((string) $company->email, $company->getDisplayName()))
            ->to($to)
            ->cc($cc)
            ->bcc($bcc)
            ->subject($subject)
            ->plainText($body);
    }

    public function normalizeEmails(array $emails, array $variables, TwigContext $context): array
    {
        return array_reduce($emails, function ($carry, $email) use ($variables, $context) {
            $email = trim($this->rendererFactory->render($email, $variables, $context));
            if ($this->validateEmailAddress($email)) {
                $carry[] = [
                    'email' => $email,
                    'name' => '',
                ];
            }

            return $carry;
        }, []);
    }

    /**
     * Adds '|raw' to Twig variables to stop special characters like apostrophes from being escaped.
     * @param string $template
     * @return string
     */
    private static function addRawToTwigVariables(string $template): string
    {
        $result = preg_replace_callback(
            '/{{\s*(.*?)\s*}}/',
            function ($matches) {
                $expression = $matches[1];

                if (preg_match('/\| ?raw\b/', $expression)) {
                    return $matches[0];
                }

                return '{{ ' . $expression . '|raw }}';
            },
            $template
        );

        return $result ?? $template;
    }
}