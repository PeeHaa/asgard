<?php
namespace Asgard\Container\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ListCommand extends \Asgard\Console\Command {
	protected $name = 'services';
	protected $description = 'Show all the services loaded in the application';
	protected $root;

	public function __construct($root) {
		$this->root = $root;
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$table = $this->getHelperSet()->get('table');
		$headers = ['Name', 'Type', 'Class'];
		$optRegistered = $this->input->getOption('registered');
		if($optRegistered)
			$headers[] = 'Registered at';
		$optDefined = $this->input->getOption('defined');
		if($optDefined)
			$headers[] = 'Defined in';
		$table->setHeaders($headers);

		$root = $this->root;

		$services = [];
		foreach($this->getContainer()->getRegistry() as $name=>$service) {
			if($service instanceof \Jeremeamia\SuperClosure\SerializableClosure)
				$service = $service->getClosure();

			$r = new \ReflectionFunction($service);
			$registered = \Asgard\File\FileSystem::relativeTo($root, $r->getFileName()).':'.$r->getStartLine();
			
			$defined = '???';
			$class = '???';
			try {
				$obj = $this->getContainer()->make($name);
				$class = gettype($obj)=='object' ? '\\'.get_class($obj):gettype($obj);
				
				if(is_object($service)) {
					$r = new \ReflectionClass($class);
					$defined = \Asgard\File\FileSystem::relativeTo($root, $r->getFileName()).':'.$r->getStartLine();
				}
			} catch(\Exception $e) {} #defined = ??? / class = ???

			$res = [
				'name' => $name,
				'type' => 'callback',
				'class' => $class,
			];
			if($optDefined)
				$res['defined'] = $defined;
			if($optRegistered)
				$res['registered'] = $registered;
			$services[] = $res;

		}
		foreach($this->getContainer()->getInstances() as $name=>$service) {
			foreach($services as $v) {
				if($v['name'] === $name)
					continue 2;
			}

			$class = gettype($service)=='object' ? '\\'.get_class($service):gettype($service);
			$defined = '';
			if(is_object($service)) {
				$r = new \ReflectionClass($class);
				$defined = \Asgard\File\FileSystem::relativeTo($root, $r->getFileName());
			}

			$res = [
				'name' => $name,
				'type' => 'instance',
				'class' => $class,
			];
			if($optDefined)
				$res['defined'] = $defined;
			if($optRegistered)
				$res['registered'] = '???';
			$services[] = $res;
		}

		uasort($services, function($a, $b) {
			return $a['name'] > $b['name'];
		});

		foreach($services as $row)
			$table->addRow($row);

		$table->render($this->output);
	}

	protected function getOptions() {
		return [
			['defined', null, InputOption::VALUE_NONE, 'Show where the class is defined.', null],
			['registered', null, InputOption::VALUE_NONE, 'Show where it was registered.', null],
		];
	}
}