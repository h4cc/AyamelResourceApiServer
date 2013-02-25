<?php
namespace Ayamel\ApiBundle\Controller\V1;

use Ayamel\ApiBundle\Controller\ApiController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Accepts data to create multiple objects.
 *
 * @author Evan Villemez
 */
class CreateResources extends ApiController
{
    public function executeAction(Request $request)
    {
        throw $this->createHttpException(501);
    }

}
