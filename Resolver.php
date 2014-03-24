<?php
namespace Asgard\Core;

class Resolver {
	protected $routes = array();
	protected $results = array();

	public static function formatActionName($action) {
		return preg_replace('/Action$/i', '', $action);
	}

	public function setRoutes($routes) {
		$this->routes = $routes;
	}

	public function addRoutes($routes) {
		foreach($routes as $route)
			$this->addRoute($route);
	}

	public function addRoute($route) {
		$this->routes[] = $route;
	}

	public function getRoute($request) {
		$request_hash = spl_object_hash($request);
		if(!isset($this->results[$request_hash]))
			$this->results[$request_hash] = $this->parseRoutes($request);
		return $this->results[$request_hash];
	}

	public function getCallback($request) {
		$route = $this->getRoute($request);
		if(!$route)
			return null;
		else
			return $route->getCallback();
	}

	public function getArguments($request) {
		$route = $this->getRoute($request);
		if(!$route)
			return null;
		else
			return $route->getArguments();
	}

	public static function formatRoute($route) {
		return trim($route, '/');
	}
	
	public static function matchWith($route, $with, $requirements=array(), $request=null, $method=null) {
		if($method) {
			if(is_array($method)) {
				$good = false;
				foreach($method as $v)
					if(strtolower($server_method) == $v)
						$good = true;
				if(!$good)
					return false;
			}
			elseif($request && strtolower($method) != $request->method())
				return false;
		}
				
		$regex = static::getRegexFromRoute($route, $requirements);
		$matches = array();
		$res = preg_match_all('/^'.$regex.'(?:\.[a-zA-Z0-9]{1,5})?\/?$/', $with, $matches);
		
		if($res == 0)
			return false;
		else {
			$results = array();
			/* EXTRACTS VARIABLES */
			preg_match_all('/:([a-zA-Z0-9_]+)/', $route, $keys);
			for($i=0; $i<sizeof($keys[1]); $i++)
				$results[$keys[1][$i]] = $matches[$i+1][0];
			
			return $results;
		}
	}
	
	public static function match($request, $route, $requirements=array(), $method=null) {
		$with = static::formatRoute($request->url->get());
		return static::matchWith($route, $with, $requirements, $request, $method);
	}
	
	public static function getRegexFromRoute($route, $requirements) {
		preg_match_all('/:([a-zA-Z0-9_]+)/', $route, $symbols);
		$regex = preg_quote($route, '/');
			
		/* REPLACE EACH SYMBOL WITH ITS REGEX */
		foreach($symbols[1] as $symbol) {
			if(is_array($requirements) && array_key_exists($symbol, $requirements)) {
				$requirement = $requirements[$symbol];
				switch($requirement['type']) {
					case 'regex':
						$replacement = $requirement['regex']; break;
					case 'integer':
						$replacement = '[0-9]+'; break;
				}
			}
			else
				$replacement = '[^\/]+';
			
			$regex = preg_replace('/\\\:'.$symbol.'/', '('.$replacement.')', $regex);
		}
		
		return $regex;
	}

	public function parseRoutes($request) {
		$request_key = md5(serialize(array($request->method(), $request->url->get())));

		$routes = $this->routes;
		$results = \Asgard\Core\App::get('cache')->get('Router/requests/'.$request_key, function() use($routes, $request) {
			static::sortRoutes($routes);
			/* PARSE ALL ROUTES */
			foreach($routes as $r) {
				$route = $r->getRoute();
				$requirements = $r->get('requirements');
				$method = $r->get('method');

				/* IF THE ROUTE MATCHES */
				if(($results = static::match($request, $route, $requirements, $method)) !== false)
					return array('route' => $r, 'params' => $results);
			}
		});

		if(!$results)
			return null;

		$request->setParam($results['params']);

		return $results['route'];
	}

	public static function sortRoutes(&$routes) {
		usort($routes, function($r1, $r2) {
			$route1 = $r1->getRoute();
			$route2 = $r2->getRoute();
			
			if($route1 == $route2) {
				if($r1->get('host'))
					return -1;
				else
					return 1;
			}
			
			while(true) {
				if(!$route1)
					return 1;
				if(!$route2)
					return -1;
				$c1 = substr($route1, 0, 1);
				$c2 = substr($route2, 0, 1);
				if($c1 == ':' && $c2 != ':')
					return 1;
				elseif($c1 != ':' && $c2 == ':')
					return -1;
				elseif($c1 != ':' && $c2 != ':') {
					$route1 = substr($route1, 1);
					$route2 = substr($route2, 1);
				}
				elseif($c1 == ':' && $c2 == ':') {
					$route1 = preg_replace('/^:[a-zA-Z0-9_]+/', '', $route1);
					$route2 = preg_replace('/^:[a-zA-Z0-9_]+/', '', $route2);
				}
			}
		});
	}
	
	public static function buildRoute($route, $params=array()) {
		foreach($params as $symbol=>$param) {
			$count = 0;
			$route = str_replace(':'.$symbol, $param, $route, $count);
			if($count)
				unset($params[$symbol]);
		}
		if($params)
			$route .= '?';
		$i=0;
		foreach($params as $symbol=>$param) {
			if($i > 0)
				$route .= '&;';
			$route .= urlencode($symbol).'='.urlencode($param);
			$i++;
		}
			
		if(preg_match('/:([a-zA-Z0-9_]+)/', $route))
			throw new \Exception('Missing parameter for route: '.$route);
			
		return trim($route, '/');
	}

	public function getRoutes() {
		return $this->routes;
	}
}
