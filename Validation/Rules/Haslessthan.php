<?php
namespace Asgard\Validation\Rules;

class Haslessthan extends \Asgard\Validation\Rule {
	public $count;

	public function __construct($count) {
		$this->count = $count;
	}

	public function validate($input, \Asgard\Validation\InputBag $parentInput, \Asgard\Validation\Validator $validator) {
		return count($input) < $this->count;
	}

	public function getMessage() {
		return ':attribute must have less than :count elements.';
	}
}