<?php
namespace Asgard\Form\Widgets;

class DateWidget extends \Asgard\Form\Widget {
	public function render(array $options=[]) {
		$options = $this->options+$options;

		$day = $this->field->getDay();
		$month = $this->field->getMonth();
		$year = $this->field->getYear();

		$class = $this->field->getParent()->getWidgetsManager()->getWidget('select');

		return $this->field->getParent()->getWidget($class, $this->field->name().'[day]', $day, ['id'=>$this->field->getID().'-day', 'choices'=>array_combine(range(1, 31), range(1, 31))])->render().
			$this->field->getParent()->getWidget($class, $this->field->name().'[month]', $month, ['id'=>$this->field->getID().'-month', 'choices'=>array_combine(range(1, 12), range(1, 12))])->render().
			$this->field->getParent()->getWidget($class, $this->field->name().'[year]', $year, ['id'=>$this->field->getID().'-year', 'choices'=>array_combine(range(date('Y'), date('Y')-50), range(date('Y'), date('Y')-50))])->render();
	}
}