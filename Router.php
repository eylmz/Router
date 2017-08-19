<?php

/**
 * @author      Emre YILMAZ <eylmz058@gmail>
 * @copyright   Copyright (c), 2017 Emre YILMAZ
 * @license     MIT public license
 */

namespace eylmz\Router;

/**
 * Class Router
 * @package eylmz\Router
 */
class Router{
	private static $instance;
	private static $routes = [
		"ANY" => [],
		"GET" => [],
		"POST" => [],
		"PUT" => [],
		"PATCH" => [],
		"DELETE" => [],
		"OPTION" => []
	];
	private static $prefix = [];
	private static $middlewares = [];
	private static $latestMethods = [];
	private static $groups = [];
	private static $names = [];
	private static $current = false;

	private static $controllerNamespace = "App\\Controllers\\";
	private static $controllerNameEnd = "";
	private static $middlewareNamespace = "App\\Middlewares\\";
	private static $middlewareNameEnd = "";
	
	private function __construct(){}
	
	private static function getUrl($url){
		return ltrim(urldecode($url),"/");
	}
	
	private static function getRoutes(){
		$routes = [];
		$method = self::getRequestMethod();
		if(is_array(self::$routes["ANY"]) && is_array(self::$routes[$method]))
			$routes = array_merge(self::$routes["ANY"],self::$routes[$method]);
		return $routes;
	}
	
	private static function controlUrl($url,$rUrl,$where,&$parameters){
		if(count($where)){
			foreach($where as $key=>$value){
				$rUrl = preg_replace("@{".$key."}@","(".$value.")",$rUrl);
				$rUrl = preg_replace("@{".$key."\?}@","(".$value."|)",$rUrl);
			}
		}

		$rUrl = preg_replace("@{([0-9a-zA-Z]+)}@","(.*?)",$rUrl);
		$rUrl = preg_replace("@{([0-9a-zA-Z]+)\?}@","(.*?|)",$rUrl);

		$rUrl = preg_replace("@{/}@","(/?)",$rUrl);

		$result = preg_match("@^".$rUrl."$@",$url,$parameters);
		unset($parameters[0]);
		$parameters = array_values($parameters);
		return $result;
	}
	
	private static function clearParameters(&$parameters,$values = []){
		if(count($values)){
			foreach ($values as $value) {
				unset($parameters[$value]);
			}
			$parameters = array_values($parameters);
		}else {
			for ($i = count($parameters); $i > 0; $i--) {
				if (isset($parameters[$i]) && ($parameters[$i] == "/" || !$parameters[$i])) {
					unset($parameters[$i]);
				}
			}
			$parameters = array_values($parameters);
		}
	}
	
	private static function getController($controller,&$parameters,&$unset){
		if ($controller == "{?}") {
			if (isset($parameters[0])) {
				$controller = ucfirst(strtolower($parameters[0]));
				unset($parameters[0]);
			} else die("Controller parametresi bulunamadi!");
		} else if (preg_match("@{([0-9]+)}@", $controller, $cont)) {
			if (isset($parameters[$cont[1]])) {
				$controller = ucfirst(strtolower($parameters[$cont[1]]));
				$unset[] = $cont[1];
			} else die("Controller parametresi bulunamadi!");
		}
		$parameters = array_values($parameters);
		return  self::$controllerNamespace . $controller . self::$controllerNameEnd;
	}
	
	private static function getMethod($method,&$parameters,&$unset){
		if ($method == "{?}") {
			if (isset($parameters[0])) {
				$method = strtolower($parameters[0]);
				unset($parameters[0]);
			} else die("Method parametresi bulunamadi!");
		} else if (preg_match("@{([0-9]+)}@", $method, $meth)) {
			if (isset($parameters[$meth[1]])) {
				$method = strtolower($parameters[$meth[1]]);
				$unset[] = $meth[1];
			} else die("Method parametresi bulunamadi!");
		}
		return $method;
	}
	
	private static function handleMiddleware($middlewares){
		if(count($middlewares)) {
			foreach ($middlewares as $middleware) {
				$middleware = self::$middlewareNamespace . $middleware . self::$middlewareNameEnd;
				if (class_exists($middleware)) {
					if (method_exists($middleware, "handle")) {
						forward_static_call([$middleware, "handle"]);
					}
				}
			}
		}
	}
	
	private static function handleController($controller,&$parameters){
		if(is_string($controller)) {
			if(preg_match("/^([{?}a-zA-Z0-9]+)@([{?}a-zA-Z0-9]+)$/",$controller,$result)){
				if(isset($result[1]) && isset($result[2])) {
					$unset = [];
					$controller = self::getController($result[1],$parameters,$unset);
					$method = self::getMethod($result[2],$parameters,$unset);
					
					self::clearParameters($parameters,$unset);

					if (class_exists($controller)) {
						if (method_exists($controller, $method)) {
							$controller = new $controller();
							$return = call_user_func_array([$controller,$method],$parameters);
							if(is_array($return))
								echo json_encode($return);
						} else die("<b>" . $controller . "</b> isimli controllerin <b>" . $method . "</b> isimli methodu bulunamadi!");
					} else die("<b>" . $controller . "</b> isimli controller bulunamadi!");
				}else die("Router <b>controller@method</b> sorunu");
			}
		}else if(is_callable($controller)) {
			$return = call_user_func_array($controller, $parameters);
			if(is_array($return))
				echo json_encode($return);
		}
	}
	
	private static function getRequestMethod(){
		$method = $_SERVER['REQUEST_METHOD'];
		if($method == "POST") {
			$headers = [];
			foreach ($_SERVER as $name => $value) {
				if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
					$headers[str_replace([' ', 'Http'], ['-', 'HTTP'], ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
				}
			}
			if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH', 'OPTION'])) {
				$method = $headers['X-HTTP-Method-Override'];
			}
		}
		return $method;
	}
	
	private function setCurrent($name,$url,$pattern,$parameters,$method){
		self::$current = [
			"name" => $name,
			"url" => $url,
			"pattern" => $pattern,
			"parameters" => $parameters,
			"method" => $method
		];
	}
	
	static function routeNow($url){
	    $instance = self::getInstance();

		$url = $instance->getUrl($url);
		$routes = $instance->getRoutes();
		foreach($routes as $route){
			$parameters = [];
			if(!$instance->controlUrl($url,$route["url"],$route["where"],$parameters))
				continue;
			$instance->clearParameters($parameters);
			$instance->handleMiddleware($route["middleware"]);
			$instance->handleController($route["function"],$parameters);
			$instance->setCurrent($route["name"],$url,$route["url"],$parameters,$instance->getRequestMethod());
			break;
		}
	}
	
	static function getInstance(){
		if(self::$instance == null)
			self::$instance = new self;
		return self::$instance;
	}
	
	static function setControllerNamespace($namespace,$nameend=""){
		self::$controllerNamespace = $namespace;
		self::$controllerNameEnd = $nameend;
	}
	
	static function setMiddlewareNamespace($namespace,$nameend=""){
		self::$middlewareNamespace = $namespace;
		self::$middlewareNameEnd = $nameend;
	}
	
	static function prefix($name){
		$instance = self::getInstance();
		array_push(self::$prefix,trim($name,"/"));
		return $instance;
	}
	
	static function middleware($middleware){
		$instance = self::getInstance();
		if(is_array($middleware))
			self::$middlewares = $middleware;
		else
			self::$middlewares[] = $middleware;
		return $instance;
	}
	
	function group($callback){
		array_push(self::$groups,1);
		call_user_func($callback);
		array_pop(self::$groups);
		array_pop(self::$prefix);
	}
	
	static function match($methods,$url,$function){
		$instance = self::getInstance();
		self::$latestMethods = [];
		if(!is_array($methods))
			self::$latestMethods = explode("|",$methods);
		if(is_array(self::$latestMethods) && count(self::$latestMethods)) {
			foreach (self::$latestMethods as $method) {
				$middlewares = [];
				$url = trim($url, "/");
				if(count(self::$groups)) {
					if (is_array(self::$middlewares)) {
						$middlewares = self::$middlewares;
					}
					if(count(self::$prefix)) {
						$prefix = implode("/", self::$prefix);
						$url = $prefix . "/" . $url;
					}
				}
				self::$routes[strtoupper($method)][] = [
					"name" => "",
					"url" => $url,
					"function" => $function,
					"where" => null,
					"middleware" => $middlewares
				];
			}
		}
		return $instance;
	}
	
	static function any($url, $function){
		return self::match("ANY",$url,$function);
	}
	
	static function get($url, $function){
		return self::match("GET",$url,$function);
	}
	
	static function post($url, $function){
		return self::match("POST",$url,$function);
	}
	
	static function put($url, $function){
		return self::match("PUT",$url,$function);
	}
	
	static function patch($url, $function){
		return self::match("PATCH",$url,$function);
	}
	
	static function delete($url, $function){
		return self::match("DELETE",$url,$function);
	}
	
	static function option($url, $function){
		return self::match("OPTION",$url,$function);
	}
	
	function name($name){
		if( count(self::$latestMethods) ) {
			for($i = 0; $i < count(self::$latestMethods); $i++){
				$method = self::$latestMethods[$i];
				$lastID = count(self::$routes[$method]) - 1;
				self::$routes[$method][$lastID]["name"] = $name;
				if($i == 0)
					self::$names[$name] = [$method,$lastID];
			}
		}
		return $this;
	}
	
	function where($name,$where=null){
		for( $i = 0; $i < count(self::$latestMethods); $i++) {
			$method = self::$latestMethods[$i];
			$lastID = count(self::$routes[$method]) - 1;
			if ($where === null && is_array($name))
				self::$routes[$method][$lastID]["where"] = array_merge(self::$routes[$method][$lastID]["where"],$name);
			else
				self::$routes[$method][$lastID]["where"][$name] = $where;
		}
		return $this;
	}
	
	static function route($name,$parameters=null){
		if(array_key_exists($name,self::$names)) {
			$url = self::$routes[ self::$names[$name][0] ][ self::$names[$name][1] ]["url"];
			if(count($parameters)) {
				foreach ($parameters as $key => $value) {
					$url = preg_replace("@{" . $key . "}@", $value, $url);
					$url = preg_replace("@{" . $key . "\?}@", $value, $url);
				}
			}
			$url = preg_replace("@{/}@","/",$url);
			$url = preg_replace("@{([0-9a-zA-Z]+)\?}@","",$url);
			if(!preg_match_all("@{(.*?)}@",$url,$matches))
				return $url;
			else{
				$str = "Eksik Parametre : ";
				foreach ($matches[1] as $id=>$match)
					$str .= ($id != 0?', ':null).$match;
				return $str;
			}
		}
	}
	
	static function currentRoute(){
		return self::$current;
	}
	
	static function currentRouteURL(){
		if(is_array(self::$current))
			return self::$current["url"];
		return false;
	}
	
	static function currentRouteName(){
		if(is_array(self::$current))
			return self::$current["name"];
		return false;
	}
}
