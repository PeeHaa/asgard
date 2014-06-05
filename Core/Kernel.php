<?php
namespace Asgard\Core;

class Kernel implements \ArrayAccess {
	const VERSION = 0.1;
	protected $params = array();
	protected $app;
	protected $config;
	protected $addedBundles = array();
	protected $bundles;
	protected $loaded = false;

	public function __construct($root=null) {
		$this['root'] = $root;
	}

	public function getApp() {
		if(!$this->app) {
			$this->app = $this->buildApp($this->getConfig()['cache']);
			$this->app['kernel'] = $this;
			\Asgard\Core\App::setInstance($this->app);
		}
		return $this->app;
	}

	public function getConfig() {
		if(!$this->config) {
			$config = new \Asgard\Core\Config();
			$config->loadConfigDir($this['root'].'/config', $this->getEnv());
			$this->config = $config;
		}
		return $this->config;
	}

	public function getEnv() {
		if(!isset($this->params['env']))
			$this->setDefaultEnvironment();
		return $this->params['env'];
	}

	public function load() {
		if($this->loaded)
			return;

		if(file_exists($this['root'].'/storage/compiled.php'))
			include_once $this['root'].'/storage/compiled.php';

		$this->bundles = $this->doGetBundles($this->getConfig()['cache']);
		$app = $this->getApp();

		$app['config'] = $this->getConfig();

		if($this['env']) {
			if(file_exists($this['root'].'/app/bootstrap_'.strtolower($this['env']).'.php'))
				include $this['root'].'/app/bootstrap_'.strtolower($this['env']).'.php';
		}
		if(file_exists($this['root'].'/app/bootstrap_all.php'))
			include $this['root'].'/app/bootstrap_all.php';

		$this->runBundles();

		$this->loaded = true;

		return $this;
	}

	protected function setDefaultEnvironment() {
		global $argv;

		if(isset($this['env']))
			return;
		if(defined('_ENV_'))
			$this['env'] = _ENV_;
		elseif($this['consoleMode']) {
			foreach($argv as $k=>$v) {
				if($v === '--env' && isset($argv[$k+1])) {
					$this['env'] = $argv[$k+1];
					return;
				}
			}
			$this['env'] = 'dev';
		}
		else {
			if(isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == '127.0.0.1' || $_SERVER['HTTP_HOST'] == 'localhost'))
				$this['env'] = 'dev';
			else
				$this['env'] = 'prod';
		}
	}

	protected function getCache() {
		return new \Doctrine\Common\Cache\FilesystemCache($this['root'].'/storage/cache/');
	}

	protected function buildApp($cache=false) {
		if($cache) {
			$c = $this->getCache();
			if(($res = $c->fetch('app')) !== false)
				return $this->app = $res;
		}

		$bundles = $this->getAllBundles();
		$app = $this->app = \Asgard\Core\App::instance();

		foreach($bundles as $bundle)
			$bundle->buildApp($app);

		$app['hooks'] = new \Asgard\Hook\HooksManager($app);

		if($cache)
			$c->save('app', $app);

		return $app;
	}

	protected function runBundles() {
		$bundles = $this->getAllBundles();

		foreach($bundles as $bundle)
			$bundle->run($this->app);
	}

	public function getAllBundles() {
		if($this->bundles === null)
			$this->bundles = $this->doGetBundles();
		return $this->bundles;
	}

	protected function doGetBundles($cache=false) {
		if($cache) {
			#todo use config cache driver?
			$c = $this->getCache();
			if(($res = $c->fetch('bundles')) !== false)
				return $res;
		}

		$bundles = array_merge($this->addedBundles, $this->getBundles());

		$newBundles = false;
		foreach($bundles as $k=>$v) {
			if(is_string($v)) {
				$bundle = realpath($v);
				if(!$bundle)
					throw new \Exception('Bundle '.$v.' does not exist.');
				unset($bundles[$k]);
				$bundles[$bundle] = null;
				$newBundles = true;

				if(file_exists($bundle.'/Bundle.php'))
					require_once $bundle.'/Bundle.php';
			}
		}
		if($newBundles) {
			foreach(get_declared_classes() as $class) {
				if(!is_subclass_of($class, 'Asgard\Core\BundleLoader'))
					continue;
				$reflector = new \ReflectionClass($class);
				$dir = dirname($reflector->getFileName());
				if(array_key_exists($dir, $bundles) && $bundles[$dir] === null) {
					unset($bundles[$dir]);
					$bundles[] = new $class;
				}
			}
		}
		foreach($bundles as $bundle=>$obj) {
			if($obj === null) {
				$obj = new BundleLoader;
				$obj->setPath($bundle);
				$bundles[$bundle] = $obj;
			}
		}

		#Remove duplicates
		foreach($bundles as $k=>$b) {
			for($i=$k+1; isset($bundles[$i]); $i++) {
				if($b->getPath() === $bundles[$i]->getPath())
					unset($bundles[$i]);
			}
		}

		if($cache)
			$c->save('bundles', $bundles);

		return $bundles;
	}

	protected function getBundles() {
		return array();
	}

	public function addBundles($bundles) {
		$this->addedBundles = array_merge($this->addedBundles, $bundles);
	}

	public static function getVersion() {
		return static::VERSION;
	}

    public function offsetSet($offset, $value) {
        if(is_null($offset))
            throw new \LogicException('Offset must not be null.');
        else
       		$this->params[$offset] = $value;
    }

    public function offsetExists($offset) {
       	return isset($this->params[$offset]);
    }

    public function offsetUnset($offset) {
       	unset($this->params[$offset]);
    }

    public function offsetGet($offset) {
    	if(!isset($this->params[$offset]))
    		return;
       	return $this->params[$offset];
    }
}