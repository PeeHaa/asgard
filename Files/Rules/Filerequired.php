<?php
namespace Asgard\Files\Rules;

die('filerequired.php should not be used');

class Filerequired extends \Asgard\Validation\Rule {
	public function validate($input, \Asgard\Validation\InputBag $parentInput, \Asgard\Validation\Validator $validator) {
		if(!$input instanceof \Asgard\Files\EntityFile)
			return false;
		return $input->exists();
	}

	public function getMessage() {
		return 'The file :attribute is required.';
	}
}