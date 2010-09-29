<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace DoctrineExtensions\ActiveEntity;

use ArrayAccess,
	MemberAccessException,
	InvalidStateException,
	Nette,
	Nette\Object,
	Doctrine,
	Doctrine\ORM\EntityManager,
	Doctrine\Common\Collections\Collection;


/**
 * Abstract class to extend your entities from to give a layer which gives you
 * the functionality magically offered by Doctrine_Record. Inspired by article at
 * doctrine blog @link http://www.doctrine-project.org/blog/your-own-orm-doctrine2 .
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @since   2.0
 * @author  Roman Sklenar <mail@romansklenar.cz>
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 *
 * @method ActiveEntity createQueryBuilder() createQueryBuilder(string $alias)
 * @method ActiveEntity clear() clear()
 * @method ActiveEntity count() count(array $criteria)
 * @method ActiveEntity exists() exists(array $criteria)
 * @method ActiveEntity find() find(mixed $id)
 * @method ActiveEntity findOneBy() findOneBy(array $criteria)
 * @method array<ActiveEntity> findBy() findBy(array $criteria)
 * @method array<ActiveEntity> findAll() findAll()
 * @method array<ActiveEntity> findByAttribute() findByAttribute(mixed $value)
 * @method ActiveEntity findOneByAttribute() findOneByAttribute(mixed $value)
 *
 * @MappedSuperclass
 * @HasLifecycleCallbacks
 */
abstract class ActiveEntity extends Object implements ArrayAccess
{
	/** @var Doctrine\ORM\EntityManager */
	private static $entityManager;


	/**
	 * Initializes a new entity. Bypass properties encapsulation!
	 *
	 * @param array $values
	 */
	public function __construct(array $values = array()) {
		foreach ($values as $key => $value) {
			$this->$key = $value;
		}
	}


	/**
	 * Entity factory.
	 *
	 * @param array $values
	 * @return DoctrineExtensions\ActiveEntity\ActiveEntity
	 */
	public static function create(array $values = array()) {
		return self::fromArray($values);
	}


	/**
	 * Entity becomes managed and persisted by EntityManager.
	 *
	 * @return DoctrineExtensions\ActiveEntity\ActiveEntity
	 */
	public function save() {
		self::getEntityManager()->persist($this);
		return $this;
	}


	/**
	 * Detach entity from persistent an EntityManager and thus no longer be managed.
	 */
	public function detach() {
		self::getEntityManager()->detach($this);
	}


	/**
	 * Remove entity from persistent storage.
	 */
	public function destroy() {
		self::getEntityManager()->remove($this);
	}



	/********************* entity behaviour *********************/



	/**
	 * Gets the state of an entity within the current unit of work.
	 *
	 * @return int
	 */
	public function getEntityState() {
		return self::getEntityManager()->getUnitOfWork()->getEntityState($this);
	}


	/**
	 * Gets the identifier of an entity.
	 * The returned value is always an array of identifier values. If the entity
	 * has a composite identifier then the identifier values are in the same
	 * order as the identifier field names as returned by ClassMetadata#getIdentifierFieldNames().
	 *
	 * @return array The identifier values.
	 */
	public function getEntityIdentifier() {
		return self::getEntityManager()->getUnitOfWork()->getEntityIdentifier($this);
	}



	/********************* static shortcuts *********************/



	/**
	 * Gets entity manager.
	 *
	 * @return Doctrine\ORM\EntityManager
	 */
	protected static function getEntityManager() {
		if (self::$entityManager === NULL) {
			self::$entityManager = Environment::getEntityManager();
		}
		return self::$entityManager;
	}


	/**
	 * Gets the repository for an entity class.
	 *
	 * @return Doctrine\ORM\EntityRepository|DoctrineExtensions\ActiveEntity\ActiveRepository
	 */
	protected static function getRepository() {
		return self::getEntityManager()->getRepository(get_called_class());
	}


	/**
	 * Gets the metadata for a class.
	 *
	 * @return Doctrine\ORM\Mapping\ClassMetadata
	 */
	protected static function getClassMetadata() {
		return self::getEntityManager()->getClassMetadata(get_called_class());
	}


	/**
	 * Magic call allows to invoke repository methods like:
	 * $user = User::find($id);
	 * $users = User::findBy(array("name" => "beberlei"));
	 * $beberlei = User::findOneBy(array("name" => "beberlei"));
	 *
	 * @param mixed $method
	 * @param mixed $arguments
	 * @return mixed
	 */
	public static function __callStatic($method, $arguments) {
		try {
			$cb = callback(self::getRepository(), $method);
			return $cb->invokeArgs($arguments);

		} catch (InvalidStateException $e) {
			return parent::__callStatic($method, $arguments);
		}
	}



	/********************* conversion *********************/



	/**
	 * Creates a new entity from array.
	 *
	 * @param array $array
	 * @param static $instance
	 * @return static
	 */
	public static function fromArray(array $array = array(), $instance = NULL) {
		if ($instance === NULL) {
			$instance = new static;
		}

		foreach ($array as $key => $value) {
			if (is_array($value)) {
				self::fromArray($value, $instance->$key);
			} else {
				$instance[$key] = $value;
			}
		}
		return $instance;
	}


	/**
	 * Gets array representation of entity.
	 *
	 * @param static|Doctrine\Common\Collections\Collection $instance
	 * @return array
	 */
	public function toArray($instance = NULL) {
		if ($instance === NULL) {
			$instance = $this;
		}

		$array = array();
		if ($instance instanceof ActiveEntity) {
			foreach (self::getClassMetadata()->reflFields as $key => $reflField) {
				if (isset($instance[$key])) {
					$value = $instance[$key];

					if ($value instanceof ActiveEntity) {
						if ($result = $value->toArray()) {
							$array[$key] = $result;
						}

					} else if ($value instanceof Collection) {
						// @todo: implement and debug
						// $array[$key] = $this->toArray($value);

					} else {
						$array[$key] = $value;
					}
				}
			}

		} else if ($instance instanceof Collection) {
			foreach ($instance as $key => $value) {
				if ($result = $this->toArray($value)) {
					$array[$key] = $result;
				}
			}
		}

		return $array;
	}



	/********************* magic getters & setters *********************/



	/**
	 * Returns property value. Do not call directly.
	 * Tries to use dynamic getter for protected properties (reflection fields) if getter is not defined.
	 *
	 * @param  string  property name
	 * @return mixed   property value
	 * @throws MemberAccessException if the property is not defined.
	 */
	public function &__get($name) {
		try {
			$value = parent::__get($name);
			return $value;

		} catch (MemberAccessException $e) {
			if ($this->hasProtectedReflectionField($name)) { // intentionally not used __isset
				$value = $this->$name;
				return $value;
			} else {
				$class = get_called_class();
				throw new MemberAccessException($e->getMessage()
					. " If you want to use dynamic getter for this property, make sure that property has visibility 'protected'.");
			}
		}
	}


	/**
	 * Sets value of a property. Do not call directly.
	 * Tries to use dynamic setter for protected properties (reflection fields) if setter is not defined.
	 *
	 * @param  string  property name
	 * @param  mixed   property value
	 * @return void
	 * @throws MemberAccessException if the property is not defined or is read-only
	 */
	public function __set($name, $value) {
		try {
			parent::__set($name, $value);

		} catch(MemberAccessException $e) {
			if ($this->hasProtectedReflectionField($name)) { // intentionally not used __isset
				$this->$name = $value;
			} else {
				throw new MemberAccessException($e->getMessage()
					. " If you want to use dynamic setter for this property, make sure that property has visibility 'protected'.");
			}
		}
	}


	/**
	 * Is property defined?
	 * Tries to use dynamic getter for protected properties (reflection fields).
	 *
	 * @param  string  property name
	 * @return bool
	 */
	public function __isset($name) {
		if (parent::__isset($name)) {
			return TRUE;
		} else {
			return $this->hasProtectedReflectionField($name);
		}
	}


	protected function hasProtectedReflectionField($name) {
		$rc = $this->getReflection();
		return $rc->hasProperty($name) && $rc->getProperty($name)->isProtected()
				&& array_key_exists($name, self::getClassMetadata()->reflFields);
	}



	/********************* interface ArrayAccess *********************/



	/**
	 * Returns attribute value. Do not call directly.
	 *
	 * @param  string $name  attribute name
	 * @return mixed           attribute value
	 * @throws MemberAccessException if the attribute is not defined.
	 */
	final public function offsetGet($name) {
		return $this->__get($name);
	}


	/**
	 * Sets value of an attribute. Do not call directly.
	 *
	 * @param  string $name  attribute name
	 * @param  mixed  $value   attribute value
	 * @return void
	 * @throws MemberAccessException if the attribute is not defined or is read-only
	 */
	final public function offsetSet($name, $value) {
		$this->__set($name, $value);
	}


	/**
	 * Is attribute defined?
	 *
	 * @param  string $name  attribute name
	 * @return bool
	 */
	final public function offsetExists($name) {
		return $this->__isset($name);
	}


	/**
	 * Unset of attribute.
	 *
	 * @param  string $name  attribute name
	 * @return void
	 * @throws MemberAccessException
	 */
	final public function offsetUnset($name) {
		$this->__unset($name);
	}

}