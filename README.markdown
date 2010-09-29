# ActiveEntity extension for Doctrine 2 and Nette Framework

Abstract class to extend your entities from to give a layer which gives you
the functionality magically offered by Doctrine_Record. Inspired by article at
[doctrine blog](http://www.doctrine-project.org/blog/your-own-orm-doctrine2).


## Requirements
  - Nette Framework 2.0-dev and later
  - Doctrine 2.0 RC and later
  - parts of Symfony2 PR3 and later
  - PHP 5.3.X



## Usage
Define models in your models directory.
For integration into Nette Framework based project, see [demo](http://github.com/romansklenar/ActiveEntity-example).

	<?php

	use DoctrineExtensions\ActiveEntity\ActiveEntity;

	/**
	 * @Entity(repositoryClass="DoctrineExtensions\ActiveEntity\ActiveRepository")
	 * @Table(name="Cities")
	 */
	class City extends ActiveEntity
	{
		/** @Column(type="integer") @Id @GeneratedValue */
		protected $id;

		/**
		 * @Column(type="string", length=128, unique=true)
		 * @Validation({ @NotBlank, @MinLength(2), @MaxLength(128) })
		 */
		protected $name;

	}


Then you can use you model in your application:

	/* @var $em Doctrine\ORM\EntityManager */
	ActiveEntity::setEntitymanager($em);

	$cities = City::findAll();

	$city = City::findOneByName('Ostrava');
	print $city->id;
	print $city->name;
	print $city->toArray();

	$city = City::create(array('name' => 'Los Angeles'));
	$city->save();
	$em->flush();

	$city = City::find(10);
	$city->destroy();
	$em->flush();


Validation of entities:

	/* @var $validator Symfony\Component\Validator\Validator */
	ActiveEntity::setValidator($validator);

	$city = new City;
	$city->name = 'A';

	print $city->isValid(); // FALSE
	print (string) $city->getErrors(); // City.name: This value is too short. It should have 2 characters or more
	$city->validate(); // throws ValidationException with message of first error when entity is not valid


For all possible validation annotations please see [Symfony2 documentation](http://docs.symfony-reloaded.org/guides/validator.html).


## FAQ

### **Q:** How do I create $validator instance?
### **A:** You can use this snippet of code for inspiration.

	use Symfony\Component\Validator;


	$file = LIBS_DIR . '/Symfony/Component/Validator/Resources/i18n/messages.en.xml';
	$loader = new Validator\Mapping\Loader\AnnotationLoader();
	$cache = NULL;

	$metadataFactory = new Validator\Mapping\ClassMetadataFactory($loader, $cache);
	$validatorFactory = new Validator\ConstraintValidatorFactory();
	$messageInterpolator = new Validator\MessageInterpolator\XliffMessageInterpolator($file);

	$validator = new Validator\Validator($metadataFactory, $validatorFactory, $messageInterpolator);