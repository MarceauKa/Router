<?php

namespace MarceauKa;

/**
 * Class Router
 * @package MarceauKa
 */
class Router
{
    protected array $routes = [];
    protected array $names = [];
    protected ?int $current = null;
    protected array $matched_parameters = [];
    protected string $parameters_pattern = '/{:\w+}/';
    protected array $parameters = [
        '{:num}' => '([0-9]+)',
        '{:alpha}' => '([a-z]+)',
        '{:any}' => '([a-z0-9\_\.\:\,\-\@]+)',
        '{:slug}' => '([a-z0-9\-]+)',
    ];
    protected string $namespace = '';
    protected $dispatch_default;
    protected array $methods = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE'
    ];

    /**
     * Create instance from a serialized instance.
     * @param  string  $compiled
     * @return  Router
     */
    public static function fromCompiled(string $compiled)
    {
        $router = unserialize($compiled);

        // Invalid serialized data.
        if ($router === false) {
            throw new \RuntimeException("Given compiled data is invalid.");
        }

        return $router;
    }

    /**
     * Get the serialized current instance.
     * @param  void
     * @return  string
     */
    public function getCompiled(): string
    {
        return serialize($this);
    }

    /**
     * Start listening request...
     * @param  string|null  $request_uri  Spoof the request uri.
     * @param  string|null  $request_method  Spoof the request method.
     * @return  mixed
     */
    public function listen(?string $request_uri = null, ?string $request_method = null)
    {
        $method = $this->getRequestMethod($request_method);
        $uri = $this->getRequestUri($request_uri);

        // Remet à zéro les routes matchées
        $this->matched_parameters = [];
        $this->current = null;

        if (count($this->routes) > 0) {
            // Boucle sur les routes...
            foreach ($this->routes as $key => $route) {
                if ($this->routeMatch($key, $uri, $method) === true) {
                    return $this->dispatchCurrent();
                }
            }

            return $this->dispatchDefault();
        }

        return $this;
    }

    /**
     * Returns a link to a named route. Pass the route name or its ID.
     * Second parameters is to pass variables to the route.
     * @param  string|int  $name
     * @param  array|mixed  $params
     * @return  string
     */
    public function link($name, $params = []): string
    {
        // Indexed route or named route?
        $route = is_int($name) ? $this->getIndexedRoute($name) : $this->getNamedRoute($name);

        $uri = $route['uri'];

        // This route contains bindings.
        if ($route['bindings'] === true) {
            $uri = $route['original'];
            $params = is_array($params) ? $params : [$params];

            while ($this->routeHasBindings($uri) === true || count($params) > 0) {
                $uri = preg_replace($this->parameters_pattern, $params[0], $uri, 1);
                array_shift($params);
            }

            // Il reste des paramètres dans l'URI
            if ($this->routeHasBindings($uri) === true || count($params) > 0) {
                throw new \InvalidArgumentException("Route params count not match given parents count.");
            }
        }

        return '/'.$uri;
    }

    /**
     * Load routes configuration with a callback.
     * With no callback, all compiled routes are returned.
     * @param  callable|null  $callback
     * @return  self|array
     */
    public function routes(callable $callback = null)
    {
        if (is_null($callback)) {
            return $this->routes;
        }

        $callback($this);

        return $this;
    }

    /**
     * Add a new route.
     * @param  array  $methods  Methods to match. Ex: ['GET'], ['GET', 'POST'], ...
     * @param  string  $uri  URI to match.
     * @param  string|callable  $action  Action when the route is matched.
     * @param  string  $as  Human name for the route
     * @return  self
     */
    public function add(array $methods, string $uri, $action, ?string $as = null): self
    {
        $original = $uri;
        $methods = $this->validateRouteMethods($methods);
        $bindings = $this->routeHasBindings($uri);
        $uri = $this->validateRouteUri($uri);
        $index = count($this->routes) + 1;

        // Add the route to the compiled routes.
        $this->routes[$index] = [
            'methods' => $methods,
            'uri' => $uri,
            'original' => $original,
            'action' => $action,
            'bindings' => $bindings
        ];

        // Given route as a name.
        if (is_null($as) === false) {
            $as = $this->validateRouteNamed($as);
            $this->names[$index] = $as;
        }

        return $this;
    }

    /**
     * Returns a route by its name.
     * @param  string  $name
     * @return  array
     */
    public function getNamedRoute(string $name): array
    {
        if (false !== $index = array_search($name, $this->names)) {
            return $this->getIndexedRoute($index);
        }

        throw new \RuntimeException("No route named with \"$name\".");
    }

    /**
     * Returns a route by its index.
     * @param  int  $index
     * @return  array
     */
    public function getIndexedRoute($index): array
    {
        if (array_key_exists($index, $this->routes)) {
            return $this->routes[$index];
        }

        throw new \RuntimeException("No route indexed with \"$index\".");
    }

    /**
     * Validate a method (or many method).
     *
     * @param  string|array  $methods
     * @return  string|array
     */
    protected function validateRouteMethods($methods)
    {
        if (is_array($methods)) {
            foreach ($methods as &$method) {
                $method = $this->validateRouteMethods($method);
            }

            unset($method);

            return $methods;
        } else {
            $method = strtoupper($methods);

            if (in_array($method, $this->methods)) {
                return $method;
            }
        }

        throw new \InvalidArgumentException("Given method \"$method\" is invalid.");
    }

    /**
     * Checks if a route contains any bindings.
     * A string URI can be passed or an array containing a compiled route.
     * @param  string|array  $uri
     * @return  bool
     */
    protected function routeHasBindings($uri): bool
    {
        if (is_array($uri)) {
            return $uri['bindings'] === true;
        }

        return preg_match($this->parameters_pattern, $uri) >= 1;
    }

    /**
     * Validate an URI and transforms URI params.
     * @param  string  $uri
     * @return  string
     */
    protected function validateRouteUri(string $uri): string
    {
        // Delete leading and trailing slashes.
        $uri = trim($uri, '/');
        $uri = str_replace([
            '.',
            '?',
            '&',
        ], [
            '\.',
            '\?',
            '\&',
        ], $uri);

        // While the URI contains {:...}
        while ($this->routeHasBindings($uri) === true) {
            // Apply parameters patterns
            foreach ($this->parameters as $key => $pattern) {
                $uri = preg_replace("/$key/iu", $pattern, $uri);
            }
        }

        return $uri;
    }

    /**
     * Validate a route name.
     * Exception when the name is already taken.
     * @param  string  $as
     * @return  string
     */
    protected function validateRouteNamed(string $as)
    {
        if (in_array($as, $this->names) === false) {
            return $as;
        }

        throw new \InvalidArgumentException("Duplicate route named \"$as\"");
    }

    /**
     * Check if a route match.
     * @param  int  $index  The route index.
     * @param  string  $uri  The request uri.
     * @param  string  $method  The request method.
     * @return  bool
     */
    protected function routeMatch(int $index, string $uri = '', string $method = 'GET')
    {
        // Get the route by its index.
        $route = $this->getIndexedRoute($index);
        // Build regex pattern.
        $pattern = '#^'.$route['uri'].'$#iu';
        // Prepare var for matched parameters.
        $matches = [];

        if (preg_match_all($pattern, $uri, $matches) > 0
            && in_array($method, $route['methods'])) {
            // There's matched parameters.
            if (count($matches) > 1) {
                // Delete the first item.
                array_shift($matches);

                foreach ($matches as $match) {
                    $this->matched_parameters[] = $match[0];
                }
            }

            $this->current = $index;

            return true;
        }

        return false;
    }

    /**
     * Dispatch the current matched route.
     * @return  mixed
     */
    protected function dispatchCurrent()
    {
        // There's no matched route.
        if (is_null($this->current)) {
            throw new \RuntimeException("Trying to dispatch non-matched route.");
        }

        return $this->dispatchFromRoute($this->current);
    }

    /**
     * Dispatch the default action.
     * @return  mixed
     */
    protected function dispatchDefault()
    {
        if (is_null($this->dispatch_default) === false) {
            return $this->dispatch($this->dispatch_default);
        }

        return $this;
    }

    /**
     * Dispatch an action by a route or a route index.
     * @param  int|array  $id
     * @return  mixed
     */
    protected function dispatchFromRoute($id)
    {
        $route = is_int($id) ? $this->getIndexedRoute($id) : $id;

        return $this->dispatch($route['action']);
    }

    /**
     * Dispatch an action.
     * @param  string|callable  $action
     * @return  mixed
     */
    protected function dispatch($action)
    {
        // Action is callable.
        if (is_callable($action)) {
            return call_user_func_array($action, $this->matched_parameters);
        }

        $call = explode('@', $action);
        $className = $call[0];
        $methodName = $call[1];

        // Adds the default namespace if present.
        if (is_null($this->namespace) === false) {
            $className = $this->namespace.$className;
        }

        // Dispatch a class + method.
        if (class_exists($className)) {
            $class = new $className;

            if (method_exists($class, $methodName)) {
                return call_user_func_array([
                    $class,
                    $methodName
                ], $this->matched_parameters);
            }
        }

        throw new \RuntimeException("Unable to dispatch router action.");
    }

    /**
     * Action to dispatch when there's no route match.
     * @param  string|callable  $callback
     * @return  self
     */
    public function whenNotFound($callback)
    {
        $this->dispatch_default = $callback;

        return $this;
    }

    /**
     * Define a default namespace for actions.
     * @param  string  $namespace
     * @return  self
     */
    public function namespaceWith(string $namespace = ''): self
    {
        // Disable namespace.
        if (empty($namespace)) {
            $namespace = '';
        } elseif (stripos($namespace, -1, 1) != '\\') {
            $namespace = $namespace.'\\';
        }

        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Returns the request method.
     * @param  ?string  $default
     * @return  string
     */
    protected function getRequestMethod(?string $default = null): string
    {
        $method = is_null($default) ? $_SERVER['REQUEST_METHOD'] : $default;

        if ($method == 'POST') {
            if (isset($_POST['_method'])) {
                $method = $_POST['_method'];
            }
        }

        return strtoupper($method);
    }

    /**
     * Returns the request URI.
     * @param  ?string  $default
     * @return  string
     */
    protected function getRequestUri($default = null): string
    {
        $uri = is_null($default) ? $_SERVER['REQUEST_URI'] : $default;

        return trim($uri, '/');
    }

    /**
     * Dynamic call.
     * Used for get(), post(), put(), patch(), delete() and any() (alias of add())
     * @param  string  $method
     * @param  array  $args
     * @return  self
     */
    public function __call(string $method, array $args = [])
    {
        if (in_array(strtoupper($method), $this->methods)) {
            $as = !empty($args[2]) ? $args[2] : null;

            return $this->add([$method], $args[0], $args[1], $as);
        } elseif ($method === 'any') {
            $as = !empty($args[2]) ? $args[2] : null;

            return $this->add($this->methods, $args[0], $args[1], $as);
        }

        throw new \BadMethodCallException("Invalid method \"$method\".");
    }

    /**
     * When sleeping...
     * @return  array
     */
    public function __sleep()
    {
        return ['routes', 'names', 'namespace'];
    }
}
