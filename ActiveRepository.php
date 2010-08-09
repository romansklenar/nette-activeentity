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

use Doctrine, InvalidStateException;


/**
 * An EntityRepository serves as a repository for entities with generic as well as
 * business specific methods for retrieving entities.
 *
 * This class is designed for inheritance and users can subclass this class to
 * write their own repositories with business-specific methods to locate entities.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @since       2.0
 * @author      Roman Sklenar <mail@romansklenar.cz>
 */
class ActiveRepository extends Doctrine\ORM\EntityRepository
{
	/**
	 * Fetches all records from table like $key => $value pairs.
	 * 
	 * @param  string  associative key
	 * @param  string  value
	 * @return array
	 * @throws InvalidArgumentException
	 */
	final public function fetchPairs($key = NULL, $value = NULL) {
		$alias = 'a';
		$attrs = array($key, $value);
		$attrs = array_map(function ($el) use ($alias) {
			return "$alias.$el";
		}, $attrs);
		
		$qb = $this->createQueryBuilder($alias);
		$qb->add('select', implode(', ', $attrs));
		$result = $qb->getQuery()->getResult();

		$arr = array();
		foreach ($result as $row) {
			$arr[ $row[$key] ] = $row[$value];
		}
		return $arr;
	}


	/**
	 * Fetches all records from table and returns associative array.
	 *
	 * @param  string associative key
	 * @return array
	 * @throws InvalidArgumentException
	 */
	final public function fetchAssoc($key) {
		$result = $this->findAll();
		$arr = array();
		
		foreach ($result as $row) {
			if (array_key_exists($row->$key, $arr)) {
				throw new InvalidStateException("Key value {$row->$key} is duplicit in fetched associative array. Try to use different associative key");
			}
			$arr[ $row->$key ] = $row;
		}
		return $arr;
	}
}