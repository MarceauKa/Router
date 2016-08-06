<?php

namespace Akibatech;

/**
 * Class Router
 *
 * @package Akiba
 */
class Router
{
    /**
     * @var self
     */
    protected static $router;

    /**
     * @var array
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $names = [];

    /**
     * Stocke l'index de la route courante qui a matchée.
     *
     * @var int
     */
    protected $current;

    /**
     * Stocke les paramètres de routing matchés.
     *
     * @var array
     */
    protected $matched_parameters = [];

    /**
     * Store the default namespace for dispatching actions.
     *
     * @var null|string
     */
    protected $namespace;

    /**
     * Action par défaut quand aucune route n'est matchée.
     *
     * @var string|callable
     */
    protected $dispatch_default;

    /**
     * @var array
     */
    protected $methods = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE'
    ];

    //-------------------------------------------------------------------------

    /**
     * Router constructor.
     *
     * @param   void
     * @return  self
     */
    private function __construct()
    {
        self::$router = $this;
    }

    //-------------------------------------------------------------------------

    /**
     * Retourne le singleton du Router.
     *
     * @param   void
     * @return  Router
     */
    public static function getInstance()
    {
        if (is_null(self::$router) === true)
        {
            self::$router = new self;
        }

        return self::$router;
    }

    //-------------------------------------------------------------------------

    /**
     * Retourne une nouvelle instance du Routeur.
     *
     * @param   void
     * @return  Router
     */
    public static function renewInstance()
    {
        if (is_null(self::$router) === false)
        {
            self::$router = new self;
        }

        return self::getInstance();
    }

    //-------------------------------------------------------------------------

    /**
     * Le routeur écoute...
     *
     * @param   string|null $request_uri    Permet de spoofer l'URI de la requête.
     * @param   string|null $request_method Permet de spoofer la méthode de la requête.
     * @return  self
     */
    public function listen($request_uri = null, $request_method = null)
    {
        $method = $this->getRequestMethod($request_method);
        $uri    = $this->getRequestUri($request_uri);

        // Remet à zéro les routes matchées
        $this->matched_parameters = [];
        $this->current            = null;

        if (count($this->routes) > 0)
        {
            // Boucle sur les routes...
            foreach ($this->routes as $key => $route)
            {
                if ($this->routeMatch($key, $uri, $method) === true)
                {
                    return $this->dispatchCurrent();
                }
            }

            return $this->dispatchDefault();
        }

        return $this;
    }

    //-------------------------------------------------------------------------

    /**
     * Utilise un callback pour charger les routes.
     * Si le callback n'est pas fourni. Retourne les routes.
     *
     * @param   callable|null $callback
     * @return  self
     */
    public function routes(callable $callback = null)
    {
        if (is_null($callback))
        {
            return $this->routes;
        }

        $callback($this);

        return $this;
    }

    //-------------------------------------------------------------------------

    /**
     * Ajoute une nouvelle route.
     *
     * @param   array  $method
     * @param   string $uri
     * @param   string $action
     * @param   string $name
     * @return  self
     */
    public function add(array $methods, $uri, $action, $as = null)
    {
        $methods = $this->validateRouteMethods($methods);
        $uri     = $this->validateRouteUri($uri);
        $index   = count($this->routes) + 1;

        // Ajoute la route.
        $this->routes[$index] = [
            'methods' => $methods,
            'uri'     => $uri,
            'action'  => $action
        ];

        // La route est nommée.
        if (is_null($as) === false)
        {
            $as = $this->validateRouteNamed($as);

            $this->names[$index] = $as;
        }

        return $this;
    }

    //-------------------------------------------------------------------------

    /**
     * Retourne une route par son index.
     *
     * @param   int $index
     * @return  array
     */
    public function getIndexedRoute($index)
    {
        // L'index existe
        if (array_key_exists($index, $this->routes))
        {
            return $this->routes[$index];
        }

        throw new \RuntimeException("No route indexed with \"$index\".");
    }

    //-------------------------------------------------------------------------

    /**
     * Vérifie une méthode ou plusieurs méthodes.
     *
     * @param   string|array $methods
     * @return  string|array
     */
    protected function validateRouteMethods($methods)
    {
        // Tableau de méthode fournie.
        if (is_array($methods))
        {
            foreach ($methods as &$method)
            {
                $method = $this->validateRouteMethods($method);
            }

            unset($method);

            return $methods;
        }
        else
        {
            $method = strtoupper($methods);

            if (in_array($method, $this->methods))
            {
                return $method;
            }
        }

        throw new \InvalidArgumentException("Given method \"$method\" is invalid.");
    }

    //-------------------------------------------------------------------------

    /**
     * Valide une URI, transforme les segments d'URL en regex.
     *
     * @param   string $uri
     * @return  string
     */
    protected function validateRouteUri($uri)
    {
        // Supprime les "/" de début et de fin.
        $uri = trim($uri, '/');
        $uri = str_replace([
            '.',
            '-',
            '?',
            '&',
            '/'
        ], [
            '\.',
            '\-',
            '\?',
            '\&',
            '\/'
        ], $uri);

        // Boucle tant que la chaîne contient { ou }.
        // Attention le != est volontaire : preg_match peut retourner 0 ou false.
        while (false != preg_match('/\{\:(.*)+\}/', $uri))
        {
            // Transforme :num
            $uri = preg_replace('/\{\:num\}/', '([0-9]+)', $uri);

            // Transforme :string
            $uri = preg_replace('/\{\:alpha\}/', '([a-z]+)', $uri);

            // Transforme :any
            $uri = preg_replace('/\{\:any\}/', '([a-z0-9\_\.\:\,\-]+)', $uri);

            // Transforme :slug
            $uri = preg_replace('/\{\:slug\}/', '([a-z0-9\-]+)', $uri);
        }

        return $uri;
    }

    //-------------------------------------------------------------------------

    /**
     * Valide le nom d'une route.
     * Notamment qu'il ne soit pas déjà pris...
     *
     * @param   string $as
     * @return  string
     */
    protected function validateRouteNamed($as)
    {
        if (in_array($as, $this->names) === false)
        {
            return $as;
        }

        throw new \InvalidArgumentException("Duplicate route named \"$as\"");
    }

    //-------------------------------------------------------------------------

    /**
     * Vérifie si une route matche à la méthode et l'URI fournie.
     *
     * @param   int    $index Index de la route.
     * @param   string $uri
     * @param   string $method
     * @return  bool
     */
    protected function routeMatch($index, $uri = '', $method = 'GET')
    {
        // Récupère la route
        $route = $this->getIndexedRoute($index);

        // Construit le pattern
        $pattern = '#^' . $route['uri'] . '$#iu';

        // Stocke temporairement les paramètres matchés
        $matches = [];

        if (preg_match_all($pattern, $uri, $matches) > 0
            AND in_array($method, $route['methods'])
        )
        {
            // Prépare les paramètres matchés.
            if (count($matches) > 1)
            {
                // Supprime le premier élément du tableau des matches regex.
                array_shift($matches);

                foreach ($matches as $match)
                {
                    $this->matched_parameters[] = $match[0];
                }
            }

            $this->current = $index;

            return true;
        }

        return false;
    }

    //-------------------------------------------------------------------------

    /**
     * Exécute une route.
     *
     * @param   null|int $route
     * @return  mixed
     */
    protected function dispatchCurrent()
    {
        // Pas de route courante ?
        if (is_null($this->current))
        {
            throw new \RuntimeException("Trying to dispatch non-matched route.");
        }

        return $this->dispatchFromRoute($this->current);
    }

    //-------------------------------------------------------------------------

    /**
     * Dispatch l'action par défaut si elle existe.
     *
     * @param   void
     * @return  self
     */
    protected function dispatchDefault()
    {
        if (is_null($this->dispatch_default) === false)
        {
            return $this->dispatch($this->dispatch_default);
        }

        return $this;
    }

    //-------------------------------------------------------------------------

    /**
     * Dispatche une action depuis une route.
     * La route peut être un index ou une route directement.
     *
     * @param   int|array $id
     * @return  mixed
     */
    protected function dispatchFromRoute($id)
    {
        $route = is_int($id) ? $this->getIndexedRoute($id) : $id;

        return $this->dispatch($route['action']);
    }

    //-------------------------------------------------------------------------

    /**
     * Execute une action
     *
     * @param   string|callable $dispatch
     * @return  self
     */
    protected function dispatch($action)
    {
        // Callback fournie.
        if (is_callable($action))
        {
            return call_user_func_array($action, $this->matched_parameters);
        }

        $call       = explode('@', $action);
        $className  = $call[0];
        $methodName = $call[1];

        // Ajout le namespace par défaut à la classe.
        if (is_null($this->namespace) === false)
        {
            $className = $this->namespace . $className;
        }

        // Classe + méthode fournie.
        if (class_exists($className))
        {
            // Instancie la classe.
            $class = new $className;

            // La méthode existe sur la classe.
            if (method_exists($class, $methodName))
            {
                return call_user_func_array([
                    $class,
                    $methodName
                ], $this->matched_parameters);
            }
        }

        throw new \RuntimeException("Unable to dispatch router action.");
    }

    //-------------------------------------------------------------------------

    /**
     * Action à dispatcher quand aucune route n'a été matchée.
     *
     * @param   string|callable $callback
     * @return  self
     */
    public function whenNotFound($callback)
    {
        $this->dispatch_default = $callback;

        return $this;
    }

    //-------------------------------------------------------------------------

    /**
     * Définit un namespace par défaut pour dispatcher les actions.
     *
     * @param   string $namespace
     * @return  self
     */
    public function namespaceWith($namespace = '')
    {
        // Pas de namespace.
        if (empty($namespace))
        {
            $namespace = null;
        }
        else if (stripos($namespace, -1, 1) != '\\')
        {
            $namespace = $namespace . '\\';
        }

        $this->namespace = $namespace;

        return $this;
    }

    //-------------------------------------------------------------------------

    /**
     * Retourne la méthode de la requête.
     *
     * @param   void
     * @return  string
     */
    protected function getRequestMethod($default = null)
    {
        // CLI ?
        if ($this->isCliRequest())
        {
            return is_null($default) ? 'GET' : strtoupper($default);
        }

        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    //-------------------------------------------------------------------------

    /**
     * Retourne l'URI de la requête.
     *
     * @param   void
     * @return  string
     */
    protected function getRequestUri($default = null)
    {
        // CLI ?
        if ($this->isCliRequest())
        {
            return is_null($default) ? '' : $default;
        }

        return trim($_SERVER['REQUEST_URI'], '/');
    }

    //-------------------------------------------------------------------------

    /**
     * Vérifie si la requête est de type CLI.
     *
     * @param   void
     * @return  bool
     */
    protected function isCliRequest()
    {
        return (php_sapi_name() == 'cli'
            OR defined('STDIN')
            OR array_key_exists('REQUEST_METHOD', $_SERVER) === false);
    }

    //-------------------------------------------------------------------------

    /**
     * Appel de méthode dynamique.
     * Utile pour les méthodes get(), post(), put() qui sont des alias de add().
     *
     * @param   string $method
     * @param   array  $args
     * @return  self
     */
    public function __call($method, $args = [])
    {
        // Ajout dynamique par verbe HTTP.
        if (in_array(strtoupper($method), $this->methods))
        {
            $as = !empty($args[2]) ? $args[2] : null;

            return $this->add([$method], $args[0], $args[1], $as);
        }
        // Méthode "any" => Correspond à tous les verbes.
        else if ($method === 'any')
        {
            $as = !empty($args[2]) ? $args[2] : null;

            return $this->add($this->methods, $args[0], $args[1], $as);
        }

        throw new \BadMethodCallException("Invalid method \"$method\".");
    }

    //-------------------------------------------------------------------------
}