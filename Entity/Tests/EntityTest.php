<?php
namespace Asgard\Entity\Tests;

class EntityTest extends \PHPUnit_Framework_TestCase {
	protected static $container;

	public static function setUpBeforeClass() {
		$container = new \Asgard\Container\Container;
		$container['config'] = new \Asgard\Config\Config;
		$container['config']->set('locale', 'en');
		$container['hooks'] = new \Asgard\Hook\HooksManager($container);
		$container['cache'] = new \Asgard\Cache\NullCache;
		$container['rulesregistry'] = new \Asgard\Validation\RulesRegistry;
		$container['entitiesmanager'] = new \Asgard\Entity\EntitiesManager($container);
		\Asgard\Entity\Entity::setContainer($container);
		static::$container = $container;
	}

	public function test() {
		$news = new Classes\News([
			'title' => 'Test Title',
			'content' => 'Test Content',
			'published' => \Carbon\Carbon::create(2009, 9, 9),
		]);
		$this->assertEquals('Test Title', $news->title);
		$this->assertEquals('Test Content', $news->content);
		$this->assertTrue(isset($news->title));
		$news->title = 'bla';
		$this->assertEquals('bla', $news->title);
		unset($news->title);
		$this->assertNull($news->title);

		#hook call static
		$this->assertEquals('bla', Classes\News::test1());

		#hook call
		$news->title = 'bla';
		$this->assertEquals('bla', $news->test2());

		#configure
		$definition = Classes\News::getDefinition();
		$this->assertTrue($definition->hasProperty('another_property'));

		#i18n
		$this->assertTrue(Classes\Newsi18n::isI18N());
		$this->assertFalse(Classes\News::isI18N());

		#property
		$property = Classes\News::property('title');
		$this->assertTrue($property instanceof \Asgard\Entity\Properties\TextProperty);
		$this->assertEquals('title', $property->getName());
		$this->assertEquals('text', $property->type);

		#properties
		$properties = Classes\News::properties();
		$this->assertTrue(is_array($properties));
		$this->assertEquals($properties['title'], $property);

		#propertyNames
		$names = Classes\News::propertyNames();
		$this->assertContains('title', $names);
		$this->assertContains('content', $names);

		#getEntityName
		$this->assertEquals('news', Classes\News::getShortName());

		#toJSON
		$this->assertEquals('{"title":"bla","content":"Test Content","published":"2009-09-09","another_property":null}', $news->toJSON());

		#toArray
		$this->assertEquals(
			[
				'title' => 'bla',
				'content' => 'Test Content',
				'published' => '2009-09-09',
				'another_property' => ''
			],
			$news->toArray()
		);

		#toArrayRaw
		$newsArray = $news->toArrayRaw();
		unset($newsArray['published']);
		$this->assertEquals(
			[
				'title' => 'bla',
				'content' => 'Test Content',
				#'published' => \Carbon\Carbon::create(2009, 9, 9),
				'another_property' => ''
			],
			$newsArray
		);

		#arrayToJSON
		$news = [
			new Classes\News([
				'title' => 'Title 1',
				'content' => 'Content 1',
				'published' => \Carbon\Carbon::create(2009, 9, 9),
			]),
			new Classes\News([
				'title' => 'Title 2',
				'content' => 'Content 2',
				'published' => \Carbon\Carbon::create(2009, 9, 9),
			]),
			new Classes\News([
				'title' => 'Title 3',
				'content' => 'Content 3',
				'published' => \Carbon\Carbon::create(2009, 9, 9),
			]),
		];
		$this->assertEquals(
			'[{"title":"Title 1","content":"Content 1","published":"2009-09-09","another_property":null},{"title":"Title 2","content":"Content 2","published":"2009-09-09","another_property":null},{"title":"Title 3","content":"Content 3","published":"2009-09-09","another_property":null}]',
			Classes\News::arrayToJSON($news)
		);

		#valid
		$news = new Classes\News([
			'title' => 'Test Title',
			'content' => 'Test Content',
		]);
		$this->assertTrue($news->valid());
		$news = new Classes\News([
			'title' => null,
			'content' => 'Test Content',
		]);
		$this->assertFalse($news->valid());

		#errors
		$this->assertEquals(
			[
				'title' => [
					'required' => 'Title is required.'
				]
			],
			$news->errors()
		);

		#set/get
		$news = new Classes\News([
			'title' => 'Test Title',
		]);
		$this->assertEquals('Test Title', $news->title);
		$news->title = 'bla';
		$this->assertEquals('bla', $news->title);

		$this->assertNull($news->something);
		$news->something = 'bla';
		$this->assertEquals('bla', $news->something);

		#setHook
		$news = new Classes\NewsHook([
			'title' => 'Test Title',
		]);
		$this->assertEquals(strrev('Test Title'), $news->title);
	}
}