<?php
/**
 * Base Custom Database Table Schema Class.
 *
 * @package     Database
 * @subpackage  Schema
 * @copyright   2021-2022 - JJJ and all BerlinDB contributors
 * @license     https://opensource.org/licenses/MIT MIT
 * @since       1.0.0
 */
namespace BerlinDB\Database;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * A base database table schema class, which houses the collection of columns
 * that a table is made out of.
 *
 * This class is intended to be extended for each unique database table,
 * including global tables for multisite, and users tables.
 *
 * @since 1.0.0
 * @since 3.0.0 Added variables for Column & Index
 */
class Schema {

	/**
	 * Use the following traits:
	 *
	 * @since 3.0.0
	 */
	use Traits\Base;
	use Traits\Boot;

	/** Attributes ************************************************************/

	/**
	 * Schema Column class.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	protected $column = __NAMESPACE__ . '\\Column';

	/**
	 * Schema Index class.
	 *
	 * @since 3.0.0
	 * @var   string
	 */
	protected $index = __NAMESPACE__ . '\\Index';

	/** Item Objects **********************************************************/

	/**
	 * Array of database Column objects.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	protected $columns = array();

	/**
	 * Array of database Index objects.
	 *
	 * @since 3.0.0
	 * @var   array
	 */
	protected $indexes = array();

	/** Public Methods ********************************************************/

	/**
	 * Early setup for Legacy $columns support.
	 *
	 * @since 3.0.0
	 */
	protected function sunrise() {
		$this->setup();
	}

	/**
	 * Late setup for modern $columns & $index support.
	 *
	 * @since 3.0.0
	 */
	protected function init() {
		$this->setup();
	}

	/**
	 * Setup the class variables.
	 *
	 * This method includes legacy support for Schema objects that predefined
	 * their array of Columns. This approach will not be removed, as it was the
	 * only way to register Columns in all versions before 3.0.0.
	 *
	 * @since 3.0.0
	 */
	public function setup() {

		// Legacy support for pre-set $columns array
		if ( ! empty( $this->columns ) && is_array( $this->columns ) ) {
			$this->setup_items( 'columns', $this->column, $this->columns );
		}

		// Legacy support for pre-set $indexes array
		if ( ! empty( $this->indexes ) && is_array( $this->indexes ) ) {
			$this->setup_items( 'indexes', $this->index, $this->indexes );
		}
	}

	/**
	 * Clear some part of the schema.
	 *
	 * Will clear all items if nothing is passed.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type The type of items to clear.
	 */
	public function clear( $type = '' ) {

		// Clearing specific
		if ( ! empty( $type ) ) {
			$this->{$type} = array();

		// Clearing everything
		} else {
			$this->columns = array();
			$this->indexes = array();
		}
	}

	/**
	 * Add an item to a specific items array.
	 *
	 * @since 3.0.0
	 *
	 * @param string       $type  Item type to add.
	 * @param string       $class Class to shape item into.
	 * @param array|object $data  Data to pass into class constructor.
	 *
	 * @return object|false
	 */
	public function add_item( $type = 'column', $class = 'Column', $data = array() ) {

		// Default return value
		$retval = false;

		// Bail if no data to add
		if ( empty( $data ) ) {
			return false;
		}

		// Array
		if ( is_array( $data ) ) {
			$retval = new $class( $data );

		// Object
		} elseif ( $data instanceof $class ) {
			$retval = $data;
		}

		// Bail if no item to add
		if ( empty( $retval ) ) {
			return false;
		}

		// Add item to array
		$this->{$type}[] = $retval;

		// Return the item
		return $retval;
	}

	/**
	 * Return the SQL used for all items in a "CREATE TABLE" query.
	 *
	 * This does not include the "CREATE TABLE" directive itself, and is only
	 * used to generate the SQL inside of that kind of query.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_create_table_string() {

		// Get strings
		$strings = array(
			$this->get_items_create_string( 'columns' ),
			$this->get_items_create_string( 'indexes' )
		);

		// Format
		$retval = implode( ",\n", array_filter( $strings ) );

		// Return
		return $retval;
	}

	/** Private Helpers *******************************************************/

	/**
	 * Setup an array of items.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type   Type of items to setup.
	 * @param string $class  Class to use to create objects.
	 * @param array  $values Array of values to convert to objects.
	 *
	 * @return array Array of items that were setup.
	 */
	private function setup_items( $type = 'columns', $class = 'Column', $values = array() ) {

		// Bail if no items
		if ( empty( $this->{$type} ) || ! is_array( $this->{$type} ) ) {
			return array();
		}

		// Bail if no class
		if ( empty( $class ) || ! class_exists( $class ) ) {
			return array();
		}

		// Clear items for type
		$this->clear( $type );

		// Bail if no values
		if ( empty( $values ) || ! is_array( $values ) ) {
			return array();
		}

		// Loop through values and create objects from them
		foreach ( $values as $item ) {
			$this->add_item( $type, $class, $item );
		}

		// Return the items
		return $this->{$type};
	}

	/**
	 * Return the SQL for an item type used in a "CREATE TABLE" query.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Type of item.
	 *
	 * @return string Calls get_create_string() on every item.
	 */
	private function get_items_create_string( $type = 'columns' ) {

		// Bail if no items to get strings from
		if ( empty( $this->{$type} ) || ! is_array( $this->{$type} ) ) {
			return '';
		}

		// Default return value
		$retval  = '';

		// Improve readability
		$indent  = '  ';

		// Default strings
		$strings = array();

		// Loop through items...
		foreach ( $this->{$type} as $item ) {
			if ( method_exists( $item, 'get_create_string' ) ) {
				$strings[] = $indent . $item->get_create_string();
			}
		}

		// Format
		$retval = implode( ",\n", $strings );

		// Return the SQL
		return $retval;
	}

	/** Deprecated ************************************************************/

	/**
	 * Return the columns in string form.
	 *
	 * This method was deprecated in 3.0.0 because in previous versions it only
	 * included Columns and did not include Indexes.
	 *
	 * @since 1.0.0
	 * @deprecated 3.0.0
	 *
	 * @return string
	 */
	protected function to_string() {
		return $this->get_items_create_string( 'columns' );
	}
}
