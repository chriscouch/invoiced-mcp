<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\ApprovalWorkflow;
use App\AccountsPayable\Traits\SaveWorkflowsApiRouteTrait;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;

/**
 * @extends AbstractEditModelApiRoute<ApprovalWorkflow>
 */
class EditWorkflowApiRoute extends AbstractEditModelApiRoute
{
    use SaveWorkflowsApiRouteTrait;
}
