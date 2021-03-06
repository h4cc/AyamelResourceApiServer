<?php
namespace Ayamel\ApiBundle\Controller\V1;

use Ayamel\ApiBundle\Controller\ApiController;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

class GetResources extends ApiController
{
    /**
     * Returns multiple resources based on some query parameters.  Unless otherwise specified only Resources owned by the requesting
     * client are returned.  Only resources visible to the requesting client are returned.
     *
     * Query filters can be comma-delimited strings for multiple values.
     *
     * @ApiDoc(
     *      resource=true,
     *      description="Retrieve multiple resources",
     *      filters={
     *          {"name"="_format", "default"="json", "description"="Return format, can be one of xml, yml or json."},
     *          {"name"="id", "description"="Comma separated list of IDs for specific Resources to fetch."},
     *          {"name"="client", "description"="Comma separated list of API client owners. By default query returns resources owned by requesting client."},
     *          {"name"="type", "description"="Limit returned Resources to a certain type."},
     *          {"name"="clientUser", "description"="Limit returned Resources to those owned by a specific user an API client."},
     *          {"name"="languages", "description"="Limit returned Resources to those containing a specific language.  This can be specified in either the ISO 639-3 format or BCP47 format."},
     *          {"name"="limit", "default"="20", "max": "100", "description"="Limit the number of ids to return."},
     *          {"name"="skip", "default"="0", "description"="Number of results to skip. Use this for paginating results."}
     *      }
     * )
     *
     */
    public function executeAction(Request $req)
    {

        //create filters
        $filters = array();
        if ($ids = $req->query->get('id', false)) {
            $filters['id'] = explode(',', $ids);
        }
        if ($type = $req->query->get('type', false)) {
            $filters['type'] = explode(',', $type);
        }
        if ($clients = $req->query->get('client', false)) {
            $filters['client.id'] = explode(',', $clients);
        } else {
            if ($c = $this->getApiClient()) {
                $filters['client.id'] = $c->id;
            } else {
                //TODO: Force a client filter?
            }
        }
        if ($clientUsers = $req->query->get('clientUser', false)) {
            $filters['clientUser.id'] = explode(',', $clientUsers);
        }

        //get query builder
        $qb = $this->getRepo('AyamelResourceBundle:Resource')->getQBForResources($filters);

        //langs filter is an "or", unless we decide we really have to force the client
        //to choose exactly which language standard they want to filter on
        if ($languages = $req->query->get('languages', false)) {
            $langs = explode(',', $languages);
            $qb->addOr($qb->expr()->field('languages.iso639_3')->in($langs));
            $qb->addOr($qb->expr()->field('languages.bcp47')->in($langs));
        }

        $limit = (($l = $req->query->get('limit', 20)) <= 100) ? $l : 1000;
        $qb->limit($limit);
        $qb->skip($req->query->get('skip', 0));

        $results = $qb->getQuery()->execute();

        //assemble final content structure
        return $this->createServiceResponse(array('resources' => $this->getResourcesAsArray($results)), 200);
    }

    /**
     * Converts result set to normal array, and only includes Resources that are visible
     * to the requesting client
     */
    protected function getResourcesAsArray($results)
    {
        $resources = array();
        $client = $this->getApiClient();
        $id = ($client) ? $client->id : false;

        foreach ($results as $resource) {
            if (is_null($resource->getVisibility()) || ($id && in_array($id, $resource->getVisibility())) ) {
                $resources[] = $resource;
            }
        }

        return $resources;
    }
}
