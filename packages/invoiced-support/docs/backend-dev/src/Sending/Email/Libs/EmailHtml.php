<?php

namespace App\Sending\Email\Libs;

/**
 * Helper to build various HTML components for use
 * in email templates.
 */
class EmailHtml
{
    /**
     * Builds a pretty HTML button for use in emails.
     */
    public static function button(?string $text, ?string $url, string $color = '#348eda', bool $withPlainTextLink = true): string
    {
        if (!$text || !$url) {
            return '';
        }

        $button = '<center style="width: 100%; min-width: 532px;" class="">';
        $button .= '<table class="button radius" align="center" style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: auto; margin: 0 0 16px 0; Margin: 0 0 16px 0;">';
        $button .= '<tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class="">';
        $button .= '<td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; color: #ffffff; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 16px; line-height: 1.3;" class="">';
        $button .= '<table style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: 100%;" class="">';
        $button .= '<tbody class="">';
        $button .= '<tr style="padding: 0; vertical-align: top; text-align: left;" class="">';
        $button .= '<td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 16px; line-height: 1.3; text-align: left; color: #ffffff; background: '.$color.'; border-radius: 3px; border: none;" class="">';
        $button .= '<a href="'.$url.'" style="margin: 0; Margin: 0; text-align: left; line-height: 1.3; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; padding: 8px 16px 8px 16px; border: 0 solid '.$color.'; border-radius: 3px;" class="">';
        $button .= $text;
        // Any contents in <plainTextOnly> tags will be stripped in the HTML version.
        // This is done to include the button URL in the plain-text version of the
        // email only.
        if ($withPlainTextLink) {
            $button .= '<plainTextOnly>: '.$url.'</plainTextOnly>';
        }
        $button .= '</a></td></tr></tbody></table>';
        $button .= '</td></tr></tbody></table>';
        $button .= '</center>';

        return $button;
    }

    /**
     * Builds a schema.org view action button for use in emails.
     */
    public static function schemaOrgViewAction(string $text, ?string $url, string $description): string
    {
        if (!$url) {
            return '';
        }

        $schema = '<span itemscope itemtype="http://schema.org/EmailMessage">';
        $schema .= '<meta itemprop="description" content="'.$description.'"/>';
        $schema .= '<span itemprop="action" itemscope itemtype="http://schema.org/ViewAction">';
        $schema .= '<meta itemprop="url" content="'.$url.'"/>';
        $schema .= '<meta itemprop="name" content="'.$text.'"/>';
        $schema .= '</span>';
        $schema .= '<span itemprop="publisher" itemscope itemtype="http://schema.org/Organization">';
        $schema .= '<meta itemprop="name" content="Invoiced"/>';
        $schema .= '<meta itemprop="url" content="https://invoiced.com"/>';
        $schema .= '</span>';
        $schema .= '</span>';

        return $schema;
    }

    /**
     * Builds the HTML for an invisible tracking pixel.
     */
    public static function trackingPixel(string $url): string
    {
        return '<img src="'.$url.'" alt="" width="1" height="1" border="0" style="height:1px !important;width:1px !important;border-width:0 !important;margin-top:0 !important;margin-bottom:0 !important;margin-right:0 !important;margin-left:0 !important;padding-top:0 !important;padding-bottom:0 !important;padding-right:0  !important;padding-left:0 !important;" />';
    }
}
