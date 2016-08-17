# Router

[![Build Status](https://travis-ci.org/AkibaTech/Router.svg?branch=master)](https://travis-ci.org/AkibaTech/Router) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/646acaa9-b90b-4d71-b6e3-ebe9a377b622/mini.png?branch=master)](https://insight.sensiolabs.com/projects/646acaa9-b90b-4d71-b6e3-ebe9a377b622)

Router is a lightweight HTTP resquest router written in PHP.  

It will be your perfect companion if you need a simple and effective library or if you want an **easy to understand routing library**.  
Otherwise, take a look at these awesome libraries [symfony/routing](https://symfony.com/doc/current/components/routing.html) ou [league/route](http://route.thephpleague.com/).  

## Installation

Router can be installed with composer.  
`composer require akibatech/router dev-master`

Also, copy / paste **src/Router.php** where you want and **require it**.  

## Configure Apache

The router works perfectly with any kind of URL, but if you want some url rewriting, you can use this example .htaccess.  

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

## Usage

### Instanciation

Router is singleton based and can't directly constructed. Get the instance like that:  

```php
$router = Akibatech\Router::getInstance();
```

### Adding routes

Once you have the instance, adding routes is childish.  

```php
// Will respond to : /hello (en GET)
$router->get('hello', function () {
	echo 'Hello world!';
});
```

Your routes can follow these HTTP verbs: **POST**, **PUT**, **PATCH** and **DELETE**.  

```php
// POST request
$router->post(...);
// PATCH request
$router->patch(...);
```

A route can use many HTTP verbs:  

```php
// GET and POST requests
$router->add(['GET', 'POST'], 'hello', ...);
// ... Or all HTTP verbs
$router->any('hello', ...);
```

Finally, routes can be chained or added with a callback:  

```php
// Chaining
$router->get('foo', ...)->get('bar', ...);
// ...or via a callback
$router->routes(function ($router) {
	// Votre logique
});
```

If no callback is given to the routes() method, all routes will be returned.  

```php
$router->get('hello', ...);

var_dump($router->routes()); // Returns [1 => ['uri' => 'hello', 'action' => '...']]
```

### Listening requests

For everything to work, Router needs to incoming requests:  

```php
// Will use REQUEST_URI and REQUEST_METHOD
$router->listen();
// You can spoof them with your own logic (Request library for example).
$router->listing('request', 'method');
```

### Dispatching actions

Obviously, for each route, a need. You need to define an action for each of them.  

- A callback :

```php
$router->get('hello', function () {
	echo 'Hello!';
})
```

- A class (controller, ...) :

```php
$router->get('hello', 'MyClass@myMethod');
```

Here, the route, once matched, will instanciate the class named "MyClass" and will call the "myMethod" method.  
Note: Router will **accepts namespaces** if you application can autoload them (PSR-4, ...).  

```php
$router->get('route-namespace', 'App\Http\Controller@myMethod');
```

Besides, you can define a global namespace for all of your actions.  

```php
// Will call App\Http\Controller\Account@resume()
$router->namespaceWith('App\Http\Controller')->get('mon-account', 'Account@resume');
```

You can define a not found action when no routes was matched.  

```php
$router->whenNotFound(function () {
    echo 'Page not found';
});
```

### URL parameters

Your routes can contains dynamic parameters. Usage is simple.  

```php
// Autorise "profile/1" or "profile/12" but not "profile/john".
$router->get('profile/{:num}', ...);
```

Parameters can be:  

- {:num} for a numeric value
- {:alpha} for a alpha value
- {:any} for all kinds of chars
- {:slug} for numbers, alpha and dash (-)

Once matched, parameters are sent to the corresponding action in the URL defined order.  

```php
$router->get('profile/{:num}/{:alpha}', function ($id, $name) {
	echo "My name is $name and my ID is $id !";
});
```

### Named routes

You can **give a name to your routes** to access them later or easily creating links (in a view for example).  

```php
$router->get('/homepage', '...', 'home');
$router->link('home'); // Returns "/homepage"
```

If your route contains parameters, you can build an URI with filled parameters.  
You need to give all parameters expected by the route, otherwise and exception will be rised.  

```php
$router->get('/tag/{:slug}', '...', 'tag');
$router->link('tag', 'wordpress'); // => Returns "/tag/wordpress"

$router->get('/user/{:num}/{:any}', '...', 'profile');
$router->link('profile', [42, 'JohnDoe']); // => Returns "/user/42/JohnDoe"
```

### Caching

Sometimes, when our router contains many routes, it's convenient to have a ready-to-use Router instance for each script execution. 
Router supports **serialization** and **unserialization**. Two helpers exists to assists you.  

- Export the configured instance:

```php
$compiled = $router->getCompiled(); // Retourns a string
```

- Import a configured instance:

```php
$router = Akibatech\Router::fromCompiled($compiled); // Returns the instance previously configured
```

Note: **Routes using a callback** can't be serialized. Only the "MyClass@myMethod" is serializable.  
The router does not provide functionnality to store or read a cache file. It's not its goal.  

## Tests

Tests are with PHPUnit 5.5. You can use the **phpunit** command given at **vendor/bin**.  

```bash
vendor/bin/phpunit
```

Tests are certainly incomplete. Feel free to contribute.  

## Licence

MIT
