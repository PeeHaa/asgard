<?php
namespace Asgard\Core;

class Bundle extends BundleLoader {
	public function load($queue) {
		$queue->addBundle(new \Asgard\Utils\Bundle);

		if(\Asgard\Core\App::get('autoloader'))
			\Asgard\Core\App::get('autoloader')->preloadDir(__dir__);

		#Entities
		\Asgard\Core\App::instance()->register('entitiesmanager', function() { return new \Asgard\Core\EntitiesManager; } );
		#Http
		\Asgard\Core\App::instance()->register('request', function() { return \Asgard\Core\Request::createFromGlobals(); } );
		\Asgard\Core\App::instance()->register('response', function() { return new \Asgard\Core\Response; } );
		\Asgard\Core\App::instance()->register('url', function() { return \Asgard\Core\App::instance()->get('request')->url; } );
		\Asgard\Core\App::instance()->register('resolver', function() { return new \Asgard\Core\Resolver; } );
		\Asgard\Core\App::instance()->register('session', function() { return \Asgard\Core\App::instance()->get('request')->session; } );
		\Asgard\Core\App::instance()->register('get', function() { return \Asgard\Core\App::instance()->get('request')->get; } );
		\Asgard\Core\App::instance()->register('post', function() { return \Asgard\Core\App::instance()->get('request')->post; } );
		\Asgard\Core\App::instance()->register('file', function() { return \Asgard\Core\App::instance()->get('request')->file; } );
		\Asgard\Core\App::instance()->register('cookie', function() { return \Asgard\Core\App::instance()->get('request')->cookie; } );
		\Asgard\Core\App::instance()->register('server', function() { return \Asgard\Core\App::instance()->get('request')->server; } );

		#Cli
		\Asgard\Core\App::instance()->register('clirouter', function() { return new \Asgard\Cli\Router; } );

		parent::load($queue);
	}

	public static function install() {
		if(file_exists('config/config.php'))
			echo 'File "config/config.php" already exists.'."\n";
		else {
			$config = file_get_contents(__DIR__.'/config.php.sample');
			$key = \Asgard\Utils\Tools::randStr(10);
			$config = str_replace('_KEY_', $key, $config);

			if(\Asgard\Utils\FileManager::put('config/config.php', $config) !== false)
				echo 'Configuration created with success.'."\n";
			else
				echo 'Configuration creation failed.'."\n";			
		}

	}
}