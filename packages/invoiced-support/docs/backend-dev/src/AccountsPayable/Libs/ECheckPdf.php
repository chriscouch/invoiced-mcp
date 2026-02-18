<?php

namespace App\AccountsPayable\Libs;

class ECheckPdf extends AbstractCheckPdf
{
    public function getTemplate(int $perPage = 1): string
    {
        return '/pdf/checks/bill_e_check.twig';
    }
}
