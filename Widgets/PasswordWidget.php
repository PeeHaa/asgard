<?php
namespace Asgard\Form\Widgets;

class PasswordWidget extends \Asgard\Form\Widgets\HTMLWidget {
	public function render($options=array()) {
		$options = $this->options+$options;
		
		$attrs = array();
		if(isset($options['attrs']))
			$attrs = $options['attrs'];
		return \Asgard\Form\HTMLHelper::tag('input', array(
			'type'	=>	'password',
			'name'	=>	$this->name,
			'value'	=>	$this->value,
			'id'	=>	isset($options['id']) ? $options['id']:null,
		)+$attrs);
	}
}