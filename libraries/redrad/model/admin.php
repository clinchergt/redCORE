<?php
/**
 * @package     RedRad
 * @subpackage  Model
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_REDRAD') or die;

/**
 * redRAD Base Model Admin
 *
 * @package     RedRad
 * @subpackage  Model
 * @since       1.0
 */
class RModelAdmin extends JModelAdmin
{
	/**
	 * Context for session
	 *
	 * @var  string
	 */
	protected $context = null;

	/**
	 * The form name.
	 *
	 * @var string
	 */
	protected $formName;

	/**
	 * Table Name
	 *
	 * @var  string
	 */
	protected $tableType = null;

	/**
	 * Table Prefix
	 *
	 * @var  string
	 */
	protected $tablePrefix = 'RTable';

	/**
	 * Constructor.
	 *
	 * @param   array  $config  Configuration array
	 *
	 * @throws  RuntimeException
	 */
	public function __construct($config = array())
	{
		if (is_null($this->context))
		{
			$this->context = strtolower($this->option . '.' . $this->getName());
		}

		if (is_null($this->formName))
		{
			$this->formName = strtolower($this->getName());
		}

		parent::__construct($config);
	}

	/**
	 * Method for getting the form from the model.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm(
			$this->context . '.' . $this->formName, $this->formName,
			array(
				'control' => 'jform',
				'load_data' => $loadData
			)
		);

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Get the associated JTable
	 *
	 * @param   string  $type    Table name
	 * @param   string  $prefix  Table prefix
	 * @param   array   $config  Configuration array
	 *
	 * @return JTable
	 */
	public function getTable($type = null, $prefix = 'RTable', $config = array())
	{
		return JTable::getInstance($this->tableType, $this->tablePrefix, $config);
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  array  The default data is an empty array.
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState(
			$this->context . '.edit.' . $this->formName . '.data',
			array()
		);

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Method to get a form object.
	 *
	 * @param   string   $name     The name of the form.
	 * @param   string   $source   The form source. Can be XML string if file flag is set to false.
	 * @param   array    $options  Optional array of options for the form creation.
	 * @param   boolean  $clear    Optional argument to force load a new form.
	 * @param   mixed    $xpath    An optional xpath to search for the fields.
	 *
	 * @return  mixed  JForm object on success, False on error.
	 */
	protected function loadForm($name, $source = null, $options = array(), $clear = false, $xpath = false)
	{
		// Handle the optional arguments.
		$options['control'] = JArrayHelper::getValue($options, 'control', false);

		// Create a signature hash.
		$hash = md5($source . serialize($options));

		// Check if we can use a previously loaded form.
		if (isset($this->_forms[$hash]) && !$clear)
		{
			return $this->_forms[$hash];
		}

		// Get the form.
		RForm::addFormPath(JPATH_COMPONENT . '/models/forms');
		RForm::addFieldPath(JPATH_COMPONENT . '/models/fields');

		try
		{
			$form = RForm::getInstance($name, $source, $options, false, $xpath);

			if (isset($options['load_data']) && $options['load_data'])
			{
				// Get the data for the form.
				$data = $this->loadFormData();
			}
			else
			{
				$data = array();
			}

			// Allow for additional modification of the form, and events to be triggered.
			// We pass the data because plugins may require it.
			$this->preprocessForm($form, $data);

			// Load the data into the form after the plugins have operated.
			$form->bind($data);

		}
		catch (Exception $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		// Store the form for later.
		$this->_forms[$hash] = $form;

		return $form;
	}

	/**
	 * Method to validate the form data.
	 * Each field error is stored in session and can be retrieved with getFieldError().
	 * Once getFieldError() is called, the error is deleted from the session.
	 *
	 * @param   JForm   $form   The form to validate against.
	 * @param   array   $data   The data to validate.
	 * @param   string  $group  The name of the field group to validate.
	 *
	 * @return  mixed  Array of filtered data if valid, false otherwise.
	 *
	 * @see     JFormRule
	 * @see     JFilterInput
	 */
	public function validate($form, $data, $group = null)
	{
		// Filter and validate the form data.
		$data = $form->filter($data);
		$return = $form->validate($data, $group);

		// Check for an error.
		if ($return instanceof Exception)
		{
			$this->setError($return->getMessage());

			return false;
		}

		// Check the validation results.
		if ($return === false)
		{
			$session = JFactory::getSession();

			// Get the validation messages from the form.
			foreach ($form->getErrors() as $key => $message)
			{
				$this->setError($message);

				// Store the field error in session.
				$session->set($this->context . '.error.' . $key, $message->getMessage());
			}

			return false;
		}

		return $data;
	}

	/**
	 * Method to get the error for a field input.
	 *
	 * @param   string  $name   The name of the form field.
	 * @param   string  $group  The optional dot-separated form group path on which to find the field.
	 *
	 * @return  string  The form field error.
	 */
	public function getFieldError($name, $group = null)
	{
		if ($group)
		{
			$name = $group . '.' . $name;
		}

		// Check if we have an error in session.
		$session = JFactory::getSession();
		$error = $session->get($this->context . '.error.' . $name);

		// If we have an error.
		if (!is_null($error))
		{
			// Flash the session error.
			$session->set($this->context . '.error.' . $name, null);

			// Return the error.
			return $error;
		}

		return '';
	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * Fields recognized :
	 *
	 * - created_date
	 * - modified_date
	 * - created_by
	 * - modified_by
	 *
	 * @param   JTable  $table  A reference to a JTable object.
	 *
	 * @return  void
	 */
	protected function prepareTable($table)
	{
		$now = JDate::getInstance();
		$nowFormatted = $now->toSql();

		if (property_exists($table, 'created_date')
			&& (is_null($table->created_date) || empty($table->created_date)))
		{
			$table->created_date = $nowFormatted;
		}

		if (property_exists($table, 'modified_date'))
		{
			$table->modified_date = $nowFormatted;
		}

		$userId = JFactory::getUser()->id;

		if (property_exists('created_by', $table)
			&& (is_null($table->created_by) || empty($table->created_by)))
		{
			$table->created_by = $userId;
		}

		if (property_exists($table, 'modified_by'))
		{
			$table->modified_by = $userId;
		}
	}
}
