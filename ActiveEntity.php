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

use ArrayAccess, Nette\Environment, Nette\Object, Doctrine, Doctrine\Common\Collections\Collection, MemberAccessException; // use with Nette 1.0 for PHP 5.3
// use ArrayAccess, Environment, Object, Doctrine, Doctrine\Common\Collections\Collection, MemberAccessException; // use with Nette 1.0 for PHP 5.2


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
 */
abstract class ActiveEntity extends Object implements ArrayAccess
{
	/** @var Doctrine\ORM\EntityManager */
	private static $entityManager;


	/**
	 * Initializes a new entity. Bypass protected properties encapsulation!
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
		self::getEntityManager()->remove($this);
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
	 * Returns the metadata for a class.
	 *
	 * @return Doctrine\ORM\Mapping\ClassMetadata
	 */
	protected static function getClassMetadata() {
		return self::getEntityManager()->getClassMetadata(get_called_class());
	}


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
				$instance->$key = $value;
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
				$value = $instance->$key;

				if ($value instanceof ActiveEntity) {
					if ($result = $value->toArray()) {
						$array[$key] = $result;
					}

				} else if ($value instanceof Collection) {
					$array[$key] = $this->toArray($value);

				} else {
					$array[$key] = $value;
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

	/**/
}