<?php

namespace Restmodel\Traits;

use Restmodel\Builder;

trait HasApi
{

    /**
     * Create a new instance of this model
     * @param  array $data
     * @return Restmodel
     */
    public function newInstance(array $data=[])
    {
        return new static($data);
    }

    /**
     * Get the namespace of the API call
     * @return bool|string|null
     */
    public function getNamespace()
    {
        return $this->useNamespace ? $this->namespace : false;
    }

    /**
     * Get the API connection to use
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the scopes method to run on load
     * @return array
     */
    public function getScopes()
    {
        return $this->scopes;
    }

    /**
     * Get the scopes method to run on load
     * @return array
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * Fetch all static method calls, this method created an instance
     * of the query builder and calls the requested method
     * @param  string $method
     * @param  array $attributes
     * @return Builder
     */
    public static function __callStatic($method, $attributes)
    {
        $builder = new Builder(new static());

        if ($result = $builder->callScope($method, $attributes)) {
            return $builder;
        } elseif (method_exists($builder, $method)) {
            return call_user_func_array([$builder, $method], $attributes);
        }

        throw new \InvalidArgumentException("Method $method not available");
    }
}
