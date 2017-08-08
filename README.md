# Restmodel

This Laravel package has 2 goals

* Transform a simple associative array to an object with Laravel model capacities.
* Bind a Restful API to a model.

## Installation

Get the package from composer

```bash
composer require marcoboom/restmodel
```

If you use Laravel 5.4 or below, add the service provider to your app.php config file.

```bash
Restmodel\RestmodelServiceProvider::class,
```

Publish the config file restmodel.php to your local folder:

```bash
php artisan vendor:publish --provider="Restmodel\RestmodelServiceProvider"
```

## Basic model

Creating a basic model is easyq. Just create a php class which extends the Restmodel class.

```php
namespace App;

use Restmodel\Restmodel;

class Blog extends Restmodel
{
}
```

Now you can create a Restmodel from an associative array:

```php
$arr = ['title'=>'My title', 'body'=>'My body'];

$model = new Blog($arr);

echo $model->title;
echo $model->body;
```

### Date mutators

You can define a $dates property in your model as an array with attributes. These  will to instances of Carbon, which extends the PHP DateTime class to provide an assortment of helpful methods.

```php
namespace App;

use Restmodel\Restmodel;

class Blog extends Restmodel
{
    protected $dates = ['start_date', 'end_date'];
}
```

### Date format
By default, timestamps are formatted as 'Y-m-d H:i:s'. If you need to customize the timestamp format, set the $dateFormat property on your model. This property determines how date attributes are converted to Carbon objects.

### Accessors
To define an accessor, create a getFooAttribute method on your model where Foo is the "studly" cased name of the column you wish to access. In this example, we'll define an accessor for the  slug attribute. The accessor will automatically be called when attempting to retrieve the value of the slug attribute:

```php
namespace App;

use Restmodel\Restmodel;

class Blog extends Model
{
    public function getSlugAttribute()
    {
        return str_slug($this->title);
    }
}
```

### Appending values to JSON

Occasionally, when casting models to an array or JSON, you may wish to add attributes that do not have a corresponding column in your database. To do so, first define an accessor for the value. After creating the accessor, add the attribute name to the appends property on the model. Note that attribute names are typically referenced in "snake case", even though the accessor is defined using "camel case":

```php
namespace App;

use Restmodel\Restmodel;

class Blog extends Model
{
    protected $appends = ['slug'];

    public function getSlugAttribute()
    {
        return str_slug($this->title);
    }
}
```

### Format the data before creating model
It's possible that the data you fetched to create a model is not nice formatted as you would like. Herefore you can create a formatData method in your model. This will receive the original data and returns a formatted array.

```php
namespace App;

use Restmodel\Restmodel;

class Blog extends Model
{
    protected function formatData(array $data)
    {
        return [
            'title' => $data['meta']['blog_title'],
            'body' => $data['contents']['main'],
            'user' => new User($data['user']),
        ];
    }
}
```

## Enable API

This package makes it possible to create instances of your models by an api. Just like Eloquent models you can build 'queries' from a static class. A set of instructions we want to achieve:

```php
$results = App\Blog::where('user', 3)
        ->orderBy('created_date')
        ->take(3)
        ->get();
```

The value of results should be a Collection of instances of a Blog model.

### Configure your API

Open your restmodel.php config file and register your API's within the connections section. Each API needs at lease a URL. Below all options:

|Property   | Explanation   |
|---|---|
|url   | The baseurl of the API, like https://us9.api.mailchimp.com   |
|version   | The version of the API, like 3.0   |
|options   | An array of options, compatible with the options of a Guzzle* request  |   

>Note: [http://docs.guzzlephp.org/en/stable/request-options.html](All Guzzle Request options)

### Understanding the URL structure of the API.

The URL which will be called is built by 5 segment:

* The URL defined in the connection
* The version of the API defined in the connection
* The namespace defined in the model as property
* The ID of the resource which can be send through the id() method of the Builder class
* The subaction of the API resource like 'comments'.

Except the URL all segments can be null. The endpoint can be something like:

```
https://us9.api.mailchimp.com/3.0/lists/34/members
```

### Enable the API in your model

Add the following trait to your model: Restmodel\Traits\HasApi;


```php
namespace App;

use Restmodel\Restmodel;
use Restmodel\Traits\HasApi;

class Blog extends Model
{
    use HasApi;
}
```

After that you can append the following protected properties to your model:

|Property   | Explanation   |
|---|---|
|connection   | On which API connection does the model depends, defined in the config, will use the default connection if not defined.  |
|namespace   | The namespace segment of the API url. If not defined, as default will be the snake cased version of the model class used.    |
|useNamespace   | Set false if your API does not contains namespacing or resources.  |
|root   | The location of the root element when fetching a list. You can use the dotted array notation like 'data.results'  |
|scopes   | An array of scope methods (without prefix) to execute in every call.  |
|take   | The query parameter of the the API which represents the limiter of the amount of results |
|orderBy   | The query parameter of the the API which represents the order by |

## Start a request

You can start a request by calling a model static. The first method has to be a method from the Builder class:

```php
Blog::get();
```

### Wheres
With the where methods you can add query parameters to the request to filter th results.

```php
Blog::where('user', 1)->get();
```

### Scopes
You can create scope methods in your model to combine some actions.

```php
public function scopeHighlighted($builder, $user)
{
    $builder->where('user', $user);
    $builder->where('highlighted', 1);
}
```

You can access this by:

```php
Blog::highlighted(1);
```

## ID
When you are in a subset of the API resource, you can set the id of the resource with this method

```
Blog::id(1334);
```

## Action
When you are in a subset of the API resource, you can set the action of the resource with this method

```php
Blog::action('comments');
```

### Get results

Use the get method to exectute the API request and get the results

```php
Blog::get();
```

This will return a Laravel Collection, each item is an instance of the current model.

### Response
You can access the original response from the API with the getResponse() method. This response is an instance of the Guzzle PSR7 response.

```php
$builder = Blog::where('user', 1);
$results = $builder->get();
$response = $builder->getResponse();
```

### Paginate
If the API accepts pagination, it's possible to enable pagination on the model. Herefore you have to implement your model with Restmodel\Contracts\Paginate, this contract will require to public methods on your model:

```php
public function setPagination(Builder $builder, $perPage, $currentPage);

public function getTotal(Builder $builder);
```

The setPagination method will be some kind of scope method to give the perPage and currentPage parameters to your API:

```php
public function setPagination(Builder $builder, $perPage, $currentPage)
{
    $builder->where('page_limit', $perPage);
    $builder->where('page', $currentPage);
}
```

The getTotal method needs the total amount or record found by the API call:

```php
public function getTotal(Builder $builder) {
    $response = json_decode($builder->getResponse()->getBody(), true);

    return $response['metadata']['total_records'];
}
```

>Note: More to come.....
