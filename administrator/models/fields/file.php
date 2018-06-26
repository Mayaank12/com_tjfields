<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * Form Field class for the Joomla Platform.
 * Provides an input field for files
 *
 * @link   http://www.w3.org/TR/html-markup/input.file.html#input.file
 * @since  11.1
 */
class JFormFieldFile extends JFormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'File';

	/**
	 * The accepted file type list.
	 *
	 * @var    mixed
	 * @since  3.2
	 */
	protected $accept;

	/**
	 * Name of the layout being used to render the field
	 *
	 * @var    string
	 * @since  3.6
	 */
	protected $layout = 'joomla.form.field.file';

	/**
	 * Method to get certain otherwise inaccessible properties from the form field object.
	 *
	 * @param   string  $name  The property name for which to the the value.
	 *
	 * @return  mixed  The property value or null.
	 *
	 * @since   3.2
	 */
	public function __get($name)
	{
		require_once JPATH_SITE . '/components/com_tjfields/helpers/tjfields.php';

		switch ($name)
		{
			case 'accept':
				return $this->accept;
		}

		return parent::__get($name);
	}

	/**
	 * Method to set certain otherwise inaccessible properties of the form field object.
	 *
	 * @param   string  $name   The property name for which to the the value.
	 * @param   mixed   $value  The value of the property.
	 *
	 * @return  void
	 *
	 * @since   3.2
	 */
	public function __set($name, $value)
	{
		switch ($name)
		{
			case 'accept':
				$this->accept = (string) $value;
				break;

			default:
				parent::__set($name, $value);
		}
	}

	/**
	 * Method to attach a JForm object to the field.
	 *
	 * @param   SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
	 * @param   mixed             $value    The form field value to validate.
	 * @param   string            $group    The field name group control value. This acts as as an array container for the field.
	 *                                      For example if the field has name="foo" and the group value is set to "bar" then the
	 *                                      full field name would end up being "bar[foo]".
	 *
	 * @return  boolean  True on success.
	 *
	 * @see     JFormField::setup()
	 * @since   3.2
	 */
	public function setup(SimpleXMLElement $element, $value, $group = null)
	{
		$return = parent::setup($element, $value, $group);

		if ($return)
		{
			$this->accept = (string) $this->element['accept'];
		}

		return $return;
	}

	/**
	 * Method to get the field input markup for the file field.
	 * Field attributes allow specification of a maximum file size and a string
	 * of accepted file extensions.
	 *
	 * @return  string  The field input markup.
	 *
	 * @note    The field does not include an upload mechanism.
	 * @see     JFormFieldMedia
	 * @since   11.1
	 */
	protected function getInput()
	{
		$layoutData = $this->getLayoutData();
		$html = $this->getRenderer($this->layout)->render($layoutData);
		$tjFieldHelper = new TjfieldsHelper;

		$app =JFactory::getApplication();
		$mediaPath = $app->input->get('storegePath','','string');
		$clientForm = $app->input->get('client','','string');
		$client = explode('.', $clientForm);

		// Load backend language file
		$lang = JFactory::getLanguage();
		$lang->load('com_tjfields', JPATH_SITE);

		if (!empty($layoutData["value"]))
		{
			$html .= '<input fileFieldId="' . $layoutData["id"] . '" type="hidden" name="'. $layoutData["name"]
			. '"' . 'id="' . $layoutData["id"] . '"' . 'value="' . $layoutData["value"] . '" />';
			$html .= '<div class="control-group">';
			$fileInfo = new SplFileInfo($layoutData["value"]);
			$extension = $fileInfo->getExtension();

			// Access based actions
			$user = JFactory::getUser();

			$db = JFactory::getDbo();
			JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_tjfields/tables');
			$tjFieldFieldTable = JTable::getInstance('field', 'TjfieldsTable', array('dbo', $db));
			$tjFieldFieldTable->load(array('name' => $layoutData['field']->fieldname));

			// Get Field value details
			JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tjfields/tables');
			$fields_value_table = JTable::getInstance('Fieldsvalue', 'TjfieldsTable');
			$fields_value_table->load(array('value' => $layoutData['value']));

			$file_extension = strtolower(substr(strrchr($layoutData['value'], "."), 1));
			$ctype = $tjFieldHelper->getMime($file_extension);
			$type = explode('/', $ctype);

			$mediaLink = $tjFieldHelper->getMediaUrl(array('id' => $fields_value_table->id, 'mediaPath' => $mediaPath . '/' . $type[0] . '/' . $layoutData["value"]));
			
			$canView = 0;

			if ($user->authorise('core.field.viewfieldvalue', 'com_tjfields.group.' . $tjFieldFieldTable->group_id))
			{
				$canView = $user->authorise('core.field.viewfieldvalue', 'com_tjfields.field.' . $tjFieldFieldTable->id);
			}

			// Download file
			if (!empty($mediaLink) && $canView)
			{
				$html .= '<div><a href="' . $mediaLink
				. '">' . JText::_("COM_TJFIELDS_FILE_DOWNLOAD") . '</a>';
			}

			$canEdit = 0;

			if ($user->authorise('core.field.editfieldvalue', 'com_tjfields.group.' . $tjFieldFieldTable->group_id))
			{
				$canEdit = $user->authorise('core.field.editfieldvalue', 'com_tjfields.field.' . $tjFieldFieldTable->id);
			}

			$canEditOwn = 0;

			if ($user->authorise('core.field.editownfieldvalue', 'com_tjfields.group.' . $tjFieldFieldTable->group_id))
			{
				$canEditOwn = $user->authorise('core.field.editownfieldvalue', 'com_tjfields.field.' . $tjFieldFieldTable->id);

				if ($canEditOwn && ($user->id != $fields_value_table->user_id))
				{
					$canEditOwn = 0;
				}
			}

			// Delete file
			if (!empty($mediaLink) && ($canEdit || $canEditOwn) && $layoutData['required'] == '')
			{
				$html .= ' <span class="btn btn-remove"> <a id="remove_' . $layoutData["id"] . '" href="javascript:void(0);"
					onclick="deleteFile(\'' . base64_encode($layoutData["value"]) . '\', \'' . base64_encode($mediaPath) . '\', \'' .$layoutData["id"] . '\');">'
					. JText::_("COM_TJFIELDS_FILE_DELETE") . '</a> </span>';
			}

			$html .= '</div>';

			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Method to get the data to be passed to the layout for rendering.
	 *
	 * @return  array
	 *
	 * @since 3.6
	 */
	protected function getLayoutData()
	{
		$data = parent::getLayoutData();

		$extraData = array(
			'accept'   => $this->accept,
			'multiple' => $this->multiple,
		);

		return array_merge($data, $extraData);
	}
}
