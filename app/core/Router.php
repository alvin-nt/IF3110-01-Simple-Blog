<?php namespace App\Core;

/**
 * Class Router
 * A singleton that handles and registers routes
 *
 * Inspired by dannyvankooten/AltoRouter
 * @package App\Core
 */
class Router
{
    private static $instance;

    /**
     * @var array array of all routes
     */
    private $routes = [];

    /**
     * @var array array of all named routes
     */
    private $namedRoutes = [];

    /**
     * @var array holds regex types
     */
    protected $matchTypes = [
        'integer' => '[0-9]++',
        'alphanumeric' => '[0-9A-Za-z]++',
        'hexadecimal' => '[0-9A-Fa-f]++',

    ];

    /**
     * Gets the router instance
     *
     * @return Router
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Router;
        }

        return self::$instance;
    }

    private function __construct() {

    }

    /**
     * Retrieves all routes
     *
     * @return array
     */
    public function getRoutes() {
        return $this->routes;
    }

    /**
     * Maps a method to a target.
     *
     * @param string $method the HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $route the route regex
     * @param callable|string $target the target of which the route should point to.
     * @param string|null $name Optional name of the route.
     */
    public function map($method, $route, $target, $name = null) {
        if ($name) {
            if (isset ($this->namedRoutes[$name])) {
                throw new \RuntimeException("Cannot redeclare route '{$name}");
            } else {
                $this->namedRoutes[$name] = $route;
            }
        }

        $this->routes[] = [$method, $route, $target, $name];
    }

    /**
     * Convenient wrapper for map('GET', ...)
     *
     * @param string $route the route regex
     * @param callable|string $target the target of which the route should point to.
     * @param string|null $name Optional name of the route.
     */
    public function get($route, $target, $name = null) {
        $this->map('GET', $route, $target, $name);
    }

    /**
     * Convenient wrapper for map('POST', ...)
     *
     * @param string $route the route regex
     * @param callable|string $target the target of which the route should point to.
     * @param string|null $name Optional name of the route.
     */
    public function post($route, $target, $name = null) {
        $this->map('POST', $route, $target, $name);
    }

    /**
     * Convenient wrapper for map('PUT', ...)
     *
     * @param string $route the route regex
     * @param callable|string $target the target of which the route should point to.
     * @param string|null $name Optional name of the route.
     */
    public function put($route, $target, $name = null) {
        $this->map('PUT', $route, $target, $name);
    }

    /**
     * Convenient wrapper for map('PATCH', ...)
     *
     * @param string $route the route regex
     * @param callable|string $target the target of which the route should point to.
     * @param string|null $name Optional name of the route.
     */
    public function patch($route, $target, $name = null) {
        $this->map('PATCH', $route, $target, $name);
    }

    /**
     * Convenient wrapper for map('DELETE', ...)
     *
     * @param string $route the route regex
     * @param callable|string $target the target of which the route should point to.
     * @param string|null $name Optional name of the route.
     */
    public function delete($route, $target, $name = null) {
        $this->map('DELETE', $route, $target, $name);
    }

    /**
     * Match a given Request URL against stored routes
     *
     * @param string $requestUrl
     * @param string $requestMethod
     * @return array|boolean array with route information on success, false on failure (no match)
     */
    public function match($requestUrl = '', $requestMethod = '') {
        $params = [];
        $result = false;

        // set Request URL if not given in the parameter
        if (empty($requestUrl)) {
            $requestUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        }

        // set Request Method if not given in the parameter
        if (empty($requestMethod)) {
            $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        }

        foreach($this->routes as $handle) {
            list($method, $_route, $target, $name) = $handle;

            { // check the method
                $methods = explode('|', $method);
                $method_match = false;

                foreach ($methods as $method) {
                    if (strcasecmp($requestMethod, $method) === 0) {
                        $method_match = true;
                        break;
                    }
                }

                // skip if method does not match
                if (!$method_match) continue;
            }

            { // check the route
                if ($_route === '*') { // check wildcard
                    $result = true;
                } elseif (isset($_route[0]) && $_route[0] === '@') { // check for custom regex pattern
                    $pattern = '`' . substr($_route, 1) . '`';
                    $result = preg_match($pattern, $requestUrl, $params);
                } else { // parse the route
                    $route = null;
                    $regex = false;
                    $j = 0;
                    $i = 0;
                    $n = isset($_route[0]) ? $_route[0] : null;

                    // Find the longest non-regex substring and match it against the URI
                    while (true) {
                        if (!isset($_route[$i])) {
                            break;
                        } elseif (false === $regex) {
                            $c = $n;
                            $regex = $c === '[' || $c === '(' || $c === '.';
                            if (false === $regex && false !== isset($_route[$i+1])) {
                                $n = $_route[$i + 1];
                                $regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
                            }
                            if (false === $regex && $c !== '/' && (!isset($requestUrl[$j]) || $c !== $requestUrl[$j])) {
                                continue 2;
                            }
                            $j++;
                        }
                        $route .= $_route[$i++];
                    }

                    $regex = $this->compileRoute($route);
                    $result = preg_match($regex, $requestUrl, $params);
                }
            }

            if ($result == true || $result > 0) {
                if ($params) {
                    foreach ($params as $key => $value) {
                        if (is_numeric($key)) {
                            unset($params[$key]);
                        }
                    }
                }

                $result = [
                    'target' => $target,
                    'params' => $params,
                    'name' => $name
                ];
            }
        }

        // no match
        return $result;
    }

    /**
     * Compile the regex for a given route (EXPENSIVE)
     *
     * @param string $route the route being compared against
     * @return string
     */
    private function compileRoute($route) {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {

            $matchTypes = $this->matchTypes;
            foreach($matches as $match) {
                list($block, $pre, $type, $param, $optional) = $match;

                if (isset($matchTypes[$type])) {
                    $type = $matchTypes[$type];
                }
                if ($pre === '.') {
                    $pre = '\.';
                }

                // Older versions of PCRE require the 'P' in (?P<named>)
                $pattern = '(?:'
                    . ($pre !== '' ? $pre : null)
                    . '('
                    . ($param !== '' ? "?P<$param>" : null)
                    . $type
                    . '))'
                    . ($optional !== '' ? '?' : null);

                $route = str_replace($block, $pattern, $route);
            }

        }
        return "`^$route$`u";
    }

    /**
     * Dispatches a request to the respective controller or callable.
     *
     * @param string $requestUrl
     * @param string $requestMethod
     */
    public function dispatch($requestUrl = '', $requestMethod = '') {
        $match = $this->match($requestUrl, $requestMethod);

        try
        {
            if ($match) {
                $target = $match['target'];
                $params = $match['params'];

                if (is_string($target)) { // pattern: Controller@method
                    assert(count($target) > 0);

                    list($controllerName, $controllerMethod) = explode('@', $target);
                    $controllerClass = "App\\Controller\\$controllerName";

                    $controller = new $controllerClass;

                    if (method_exists($controller, $controllerMethod)) {
                        call_user_func_array([$controller, $controllerMethod], $params);
                    } else {
                        throw new \HttpRuntimeException("Missing controller method: $target", 500);
                    }

                } elseif (is_callable($target)) {
                    call_user_func($target, $params);
                } else {
                    throw new \HttpRuntimeException("Unknown type of target bound to route $requestMethod $requestUrl", 500);
                }

            } else {
                throw new \HttpRuntimeException("No matching method found for $requestMethod $requestUrl", 404);
            }
        }
        catch (\Exception $e)
        {
            http_response_code($e->getCode());

            die($e->getTraceAsString());
        }
    }
}