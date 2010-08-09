ActiveEntity extension for Doctrine 2 and Nette Framework
=========================================================

Abstract class to extend your entities from to give a layer which gives you
the functionality magically offered by Doctrine_Record. Inspired by article at
doctrine blog: http://www.doctrine-project.org/blog/your-own-orm-doctrine2.


Requirements
------------
Nette Framework 1.0-alpha
Doctrine 2.0
PHP 5.3.X



Usage
-----
Define models in your models directory.
See demo at http://github.com/romansklenar/ActiveEntity-example.


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

	/** @Column(type="string", length=128, unique=true) */
	protected $name;

}

?>


Then you can use you model in your application:

$em = Nette\Environment::getEntityManager();
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