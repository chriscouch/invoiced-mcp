<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\ApprovalWorkflow;
use App\AccountsPayable\Traits\SaveWorkflowsApiRouteTrait;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;

/**
 * @extends AbstractCreateModelApiRoute<ApprovalWorkflow>
 */
class CreateWorkflowApiRoute extends AbstractCreateModelApiRoute
{
    use SaveWorkflowsApiRouteTrait;
}
