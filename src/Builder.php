<?php

namespace Restmodel;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client as Guzzle;
use \Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Build queries / restful api calls on an eloquent based way
 */
class Builder
{
    /**
     * A prototype or represenation of the model
     * @var Model
     */
    protected $model;

    /**
     * The API connection where are running on
     * @var string
     */
    protected $connection;

    /**
     * Container for holding query parameters
     * @var array
     */
    protected $query = [];

    /**
     * Payload of post action
     * @var array
     */
    protected $payload;

    /**
     * Container for the extra url part of the API endpoint
     * @var string
     */
    protected $action;

    /**
     * The ID of the API endpoint, for when it's a show, edit, delete or update action
     * @var mixed
     */
    protected $id;

    /**
     * The latest API response
     * @var array
     */
    protected $response;

    /**
     * Container for a fake dataset
     * @var array
     */
    protected $dataset;

    /**
     * Limit the results
     * @var int
     */
    protected $limit;

    /**
     * Base config of a connection
     * @var array
     */
    protected $config = [
        'url' => null,
        'version' => null,
    ];

    /**
     * Constructor, get called by the first static method of the model
     * @param Model $model
     */
    public function __construct(Restmodel $model)
    {
        $this->model = $model;

        //  Initialize the API connection
        $this->on($this->model->getConnection() ?: config('restmodel.default'));

        //  Call the scopes which had to be executed by default
        $this->initScopes();
    }

    /**
     * Get direct all results
     * @return [type] [description]
     */
    public function all()
    {
        throw new \Exception('The all method is not yet implemented');
    }

    /**
     * Get the results from the query builder
     * @return \Illuminate\Support\Collection
     */
    public function get()
    {
        //  Execute the request
        $result = $this->doRequest();

        //  The response has to be an array,
        //  otherwise return an empty collection
        if (!is_array($result)) {
            return new Collection();
        }

        //  Try to find the root element of the result
        $ns = $this->getNamespace();
        $root = $this->model->getRoot();

        //  Is the root element defined in the model?
        if ($root && array_has($result, $root)) {
            $result = array_get($result, $root);
        //  Is the root element defined in the model?
        } elseif ($root && array_has($result, str_singular($root))) {
            $result = array_get($result, str_singular($root));
        //  Is the root element the snake case version of the namespace?
        } elseif ($ns && isset($result[snake_case($ns)])) {
            $result = $result[snake_case($ns)];

        //  It can happen that the response contains one
        //  root element, if so, set the response to the contents of the root element
        } elseif (count($result) == 1) {
            $result = reset($result);
        }

        //  Check again if the result is an array
        if (!$result || !is_array($result)) {
            return new Collection();
        }


        $return = new Collection();
        foreach ($result as $item) {
            $return->add($this->model->newInstance($item));
        }

        return $return;
    }

    public function paginate($perPage, $currentPage=null)
    {
        $currentPage = $currentPage ?: Paginator::resolveCurrentPage();

        if (method_exists($this->model, 'setPagination')) {
            $this->model->setPagination($this, $perPage, $currentPage);
        }

        $results = $this->get();

        if (method_exists($this->model, 'getTotal')) {
            $total = $this->model->getTotal($this);
        }

        if (!$total) {
            $total = $results->count();
        }

        return new Paginator($results, $total, $perPage, $currentPage);
    }

    /**
     * Find a specific record
     * @param  mixed $id
     * @return null
     */
    public function find($id)
    {
        $this->id($id);
        if ($results = $this->get()) {
            return $results->first();
        }

        return null;
    }

    /**
     * Create a record
     * @param  array $data
     * @return null
     */
    public function create(array $data=[])
    {
        $this->payload = $data;

        if ($response = $this->doRequest('POST')) {
            return $this->model->newInstance($response);
        }

        return null;
    }

    /**
     * Add query parameter
     * @param  string $key
     * @param  mixed $value
     * @return Builder
     */
    public function where($key, $value)
    {
        $this->query[$key] = $value;

        return $this;
    }

    public function take($take)
    {
        if ($field = $this->model->getTake()) {
            return $this->where($field, $take);
        }

        throw new \RuntimeException("Take method not implemented for this API");
    }

    public function orderBy($orderBy)
    {
        if ($field = $this->model->getOrderBy()) {
            return $this->where($field, $orderBy);
        }

        throw new \RuntimeException("OrderBy method not implemented for this API");
    }

    /**
     * Set the specific action for the API call
     * @param  string $action
     * @return Builder
     */
    public function action($action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Set the specific action for the API call
     * @param  string $action
     * @return Builder
     */
    public function id($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Run APi call on alternative connection
     * @param  string $connection
     * @return Builder
     */
    public function on($connection)
    {
        if ($config = config('restmodel.connections.'.$connection)) {
            $this->config = $config;
        } else {
            throw new \InvalidArgumentException("Connection $connection not configured");
        }

        return $this;
    }

    /**
     * Receive JSON as a dummy data
     * @param mixed $json
     * @return Builder
     */
    public function dataset($json)
    {
        if (is_array($json)) {
            $this->dataset = $json;
        } elseif ($result = json_decode($json, true)) {
            $this->dataset = $result;
        } else {
            throw new \InvalidArgumentException("Invalid json send as dataset");
        }

        return $this;
    }

    /**
     * Create the endpoint based on url, version, namespace, action and id
     * @return string
     */
    public function getEndpoint()
    {
        return collect([
            $this->getConfig('url'),
            $this->getConfig('version'),
            $this->getNamespace(),
            $this->id,
            $this->action,
        ])->filter()->implode("/");
    }

    /**
     * Get the builded query
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Get the latest response
     * @return GuzzleHttp\Psr7\Response
     */
    public function getResponse()
    {
        if (!$this->response) {
            $this->doRequest();
        }

        return $this->response;
    }

    /**
     * Get the config or a specific item of it
     * @param  string $key
     * @return mixed
     */
    protected function getConfig($key=null)
    {
        if (!$key) {
            return $this->config;
        }

        return array_get($this->config, $key, null);
    }

    /**
     * Get the namespace of the API call
     * @return string
     */
    protected function getNamespace()
    {
        $ns = $this->model->getNamespace();

        if ($ns === false) {
            return null;
        } elseif ($ns) {
            return $ns;
        }

        return snake_case(class_basename(get_class($this->model)));
    }

    /**
     * Excute all scopes on init
     * @return void
     */
    protected function initScopes()
    {
        foreach ($this->model->getScopes() as $method) {
            $this->callScope($method);
        }
    }

    /**
     * Call a scope method
     * @param  string $method
     * @param  array  $attributes
     * @return bool
     */
    public function callScope($method, $attributes=[])
    {
        if (method_exists($this->model, $scope = 'scope'.ucfirst($method))) {
            array_unshift($attributes, $this);

            call_user_func_array([$this->model, $scope], $attributes);

            return true;
        }

        return false;
    }

    /**
     * Fetch all methods which are not defined
     * At this moment only ment for fetching scope methods
     * @param  string $method
     * @param  array $attributes
     * @return Builder
     */
    public function __call($method, $attributes)
    {
        $this->callScope($method, $attributes);

        return $this;
    }

    /**
     * Perform the request with the API
     * @param string $method
     * @return array|null
     */
    public function doRequest($method="GET")
    {
        //	Create new Guzzle instance
        $client = new Guzzle();

        $options = array_merge($this->getConfig('options') ?: [], [
            'query' => $this->getQuery(),
            'json' => $this->payload
        ]);

        //	Try to perform a post request with the API
        try {
            $this->response = $client->request($method, $this->getEndpoint(), $options);
        //	IF request fails, save the response and return null
        } catch (RequestException $e) {
            $this->response = $e->getResponse();
            return null;
        }

        //	Check the returned status code
        $code = $this->response->getStatusCode();

        //	Status code has to be lower then 300 to be valid
        if ($code >= 300) {
            return null;
        }

        //	Return the JSON body as an array
        return json_decode($this->response->getBody(), true);
    }
}
