<?php

namespace Restmodel;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Create an Eloquent-like model from an Restful API
 */
class Restmodel implements Arrayable
{
    /**
     * Which connection does the model use. The default is defined in the config
     * @var string
     */
    protected $connection;

    /**
     * The namespace / path after the url of the API call
     * @var string
     */
    protected $namespace;

    /**
     * Does the API make use of a namespace?
     * @var [type]
     */
    protected $useNamespace = true;

    /**
     * The root element of list actions
     * @var string
     */
    protected $root;

    /**
     * The original set of data returned from the api
     * @var array
     */
    protected $original = [];

    /**
     * Mutatable set of data which represents a single result of the api
     * @var array
     */
    protected $attributes = [];

    /**
     * Define the attributes which has to be casted to
     * @var array
     */
    protected $dates = [];

    /**
     * The default date format for casting dates to carbon objects
     * @var string
     */
    protected $dateFormat = 'Y-m-d';

    /**
     * Array of scope methods to load by default
     * @var array
     */
    protected $scopes = [];

    /**
     * Accessors to add to the attributes array
     * @var array
     */
    protected $appends = [];

    protected $take = 'take';
    protected $sortBy = 'sort';

    /**
     * Constructor:  Convert incoming API data to an instance of this model
     * @param array $data
     */
    public function __construct(array $data=[])
    {
        if (!$data) {
            return;
        }

        $data = $this->formatData($data);

        // Save the data to the original property to concist the original data.
        $this->original = $data;

        // Loop through each attribute and check for casting
        foreach ($data as $key=>$value) {
            $this->attributes[$key] = $this->setAttribute($key, $value);
        }

        $this->appendAccessors();
    }

    /**
     * Check and cast an attribute for the model
     * @param string $key
     * @param mixed $value
     */
    protected function setAttribute($key, $value)
    {
        //  Has the attribute to be casted to a date?
        if (in_array($key, $this->dates) !== false) {
            return $this->createDate($value);
        }

        // Otherwise just return the value
        return $value;
    }

    /**
     * Add accessors to the attributes array
     * @return void
     */
    protected function appendAccessors()
    {
        foreach ($this->appends as $item) {
            $this->attributes[$item] = $this->$item;
        }
    }

    /**
     * Create a Carbon date from the given value
     * In the given format
     * @param  string $value
     * @param  string $format = null
     * @return \Carbon\Carbon
     */
    protected function createDate($value, $format=null)
    {
        return \Carbon\Carbon::createFromFormat($format ?: $this->dateFormat, $value);
    }

    /**
     * Get attribute of the model, checks also for accessors
     * @param  string $attribute
     * @return mixed
     */
    public function __get($attribute)
    {
        $accessor = "get".studly_case($attribute)."Attribute";

        if (method_exists($this, $accessor)) {
            return call_user_func([$this, $accessor]);
        }

        if (array_key_exists($attribute, $this->attributes)) {
            return $this->attributes[$attribute];
        }

        return null;
    }

    public function __call($method, $attributes)
    {
        if (starts_with($method, "get")) {
            $getter = camel_case(substr($method, 3));

            if (property_exists($this, $getter)) {
                return $this->$getter;
            }
        }

        throw new \InvalidArgumentException("Method {$method} does not exists");
    }

    /**
     * Format the incoming data before creating an instance
     * @param  array  $data
     * @return array
     */
    public function formatData(array $data)
    {
        return $data;
    }

    /**
     * Representation of the model when casting to an array
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }
}
