<?php
namespace Asgard\Validation;

class RulesRegistry {
	protected static $instance;
	protected $messages = [];
	protected $rules = [];
	protected $namespaces = [
		'\\Asgard\\Validation\\Rules\\'
	];

	public static function getInstance() {
		if(!static::$instance)
			static::$instance = new static;
		return static::$instance;
	}

	public function message($rule, $message) {
		$rule = strtolower($rule);
		$this->messages[$rule] = $message;
		return $this;
	}

	public function messages(array $rules) {
		foreach($rules as $rule=>$message)
			$this->message($rule, $message);
		return $this;
	}

	public function getMessage($rule) {
		$rule = strtolower($rule);
		if(isset($this->messages[$rule]))
			return $this->messages[$rule];
	}

	public function register($rule, $object) {
		if($object instanceof \Closure) {
			$reflection = new \ReflectionClass('Asgard\Validation\Rules\Callback');
			$object = $reflection->newInstance($object);
		}
		$this->rules[$rule] = $object;
		return $this;
	}

	public function registerNamespace($namespace) {
		$namespace = '\\'.trim($namespace, '\\').'\\';
		if(!in_array($namespace, $this->namespaces))
			array_unshift($this->namespaces, $namespace);
		return $this;
	}

	public function getRule($rule, $params=[]) {
		if($rule === 'required' || $rule === 'isNull')
			return;

		if(isset($this->rules[$rule])) {
			$rule = $this->rules[$rule];
		}
		else {
			foreach($this->namespaces as $namespace) {
				$class = $namespace.ucfirst($rule);
				if(class_exists($class) && is_subclass_of($class, 'Asgard\Validation\Rule')) {
					$rule = $class;
					break;
				}
			}
		}

		if(is_string($rule) && class_exists($rule)) {
			$reflection = new \ReflectionClass($rule);
			$rule = $reflection->newInstanceArgs($params);
			return $rule;
		}
		elseif(is_object($rule))
			return $rule;

		throw new \Exception('Rule "'.$rule.'" does not exist.');
	}

	public function getRuleName($rule) {
		foreach($this->rules as $name=>$class) {
			if($class === get_class($rule))
				return $name;
		}
		$explode = explode('\\', get_class($rule));
		return $explode[count($explode)-1];
	}
}