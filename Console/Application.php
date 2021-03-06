<?php
namespace Asgard\Console;

use Symfony\Component\Console\Input\InputOption;

/**
 * The Asgard Console Application
 * 
 * @author Michel Hognerud <michel@hognerud.net>
*/
class Application extends \Symfony\Component\Console\Application {
	use \Asgard\Container\ContainerAware;
	
	/**
	 * Constructor.
	 * 
	 * @param \Asgard\Container\Container container The application container
	*/
	public function __construct($name, $version=null, $container=null) {
		$this->container = $container;
		parent::__construct($name, $version);
		$this->setCatchExceptions(false);

	}

	/**
	 * Returns the default input definition.
	 * 
	 * @return \Symfony\Component\Console\Input\InputDefinition
	*/
	protected function getDefaultInputDefinition() {
		$definition = parent::getDefaultInputDefinition();
		$definition->addOption(new InputOption('--env', null, InputOption::VALUE_OPTIONAL, 'The environment the console should run under.'));

		return $definition;
	}
}