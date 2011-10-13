<?php
/**
* Podcast Manager for Joomla!
*
* @package     PodcastManager
* @subpackage  com_podcastmanager
*
* @copyright   Copyright (C) 2011 Michael Babker. All rights reserved.
* @license     GNU/GPL - http://www.gnu.org/copyleft/gpl.html
*
* Podcast Manager is based upon the ideas found in Podcast Suite created by Joe LeBlanc
* Original copyright (c) 2005 - 2008 Joseph L. LeBlanc and released under the GPLv2 license
*/

defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * Feed list model class.
 *
 * @package     PodcastManager
 * @subpackage  com_podcastmanager
 * @since       1.8
 */
class PodcastManagerModelFeed extends JModelList
{
	/**
	 * Model context string.
	 *
	 * @var    string
	 * @since  1.8
	 */
	public $_context = 'com_podcastmanager.feed';

	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @return  PodcastManagerModelFeed
	 *
	 * @since   1.8
	 * @see     JController
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'title', 'a.title',
				'publish_up', 'a.publish_up',
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to get a feed's parameters.
	 *
	 * @return  object  An object containing the feed record
	 *
	 * @since   1.8
	 */
	public function getFeed()
	{
		// Create a new query object.
		$db		= $this->getDbo();
		$query	= $db->getQuery(true);

		// Select required fields
		$query->select($this->getState('list.select', 'a.*'));
		$query->from($db->quoteName('#__podcastmanager_feeds').' AS a');

		$feedId = $this->getState('feed.id');
		$query->where($db->quoteName('a.id').' = '.(int) $feedId);

		$db->setQuery($query);
		$feed = $db->loadObject();

		return $feed;
	}

	/**
	 * Method to get a list of items.
	 *
	 * @return  mixed  An array of objects on success, false on failure.
	 *
	 * @since   1.8
	 */
	public function getItems()
	{
		// Invoke the parent getItems method to get the main list
		$items = parent::getItems();

		return $items;
	}

	/**
	 * Method to get a JDatabaseQuery object for retrieving the data set from a database.
	 *
	 * @return  JDatabaseQuery  A JDatabaseQuery object to retrieve the data set.
	 *
	 * @since   1.8
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db		= $this->getDbo();
		$query	= $db->getQuery(true);

		// Select required fields
		$query->select($this->getState('list.select', 'a.*'));
		$query->from($db->quoteName('#__podcastmanager').' AS a');

		// Join over the users for the modified_by name.
		$query->join('LEFT', $db->quoteName('#__users').' AS uam ON '.$db->quoteName('uam.id').' = '.$db->quoteName('a.modified_by'));

		// Filter by feed
		$feed = $this->getState('feed.id');
		if (is_numeric($feed))
		{
			$query->where($db->quoteName('a.feedname').' = '.(int) $feed);
		}

		// Filter by state
		$state = $this->getState('filter.published');
		if (is_numeric($state))
		{
			$query->where($db->quoteName('a.published').' = '.(int) $state);
		}

		// Filter by start date.
		$nullDate = $db->Quote($db->getNullDate());
		$nowDate = $db->Quote(JFactory::getDate()->toMySQL());

		if ($this->getState('filter.publish_date'))
		{
			$query->where('('.$db->quoteName('a.publish_up').' = '.$nullDate.' OR '.$db->quoteName('a.publish_up').' <= '.$nowDate.')');
		}

		// Filter by language
		if ($this->getState('filter.language'))
		{
			$query->where($db->quoteName('a.language').' in ('.$db->quote(JFactory::getLanguage()->getTag()).','.$db->quote('*').')');
		}

		// Handle the list ordering.
		$ordering	= $this->getState('list.ordering', 'a.publish_up');
		$direction	= $this->getState('list.direction', 'DESC');
		if (!empty($ordering))
		{
			$query->order($db->escape($ordering).' '.$db->escape($direction));
		}

		return $query;
	}

	/**
	 * Method to auto-populate the model state.  Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   1.8
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initialise variables.
		$app	= JFactory::getApplication();
		$input	= $app->input;
		$params	= JComponentHelper::getParams('com_podcastmanager');

		// List state information
		$feed = $input->get('feedname', '', 'int');
		$this->setState('feed.id', $feed);

		$limit = $input->get('limit', '', 'int');
		$this->setState('list.limit', $limit);

		$limitstart = $input->get('limitstart', 0, 'int');
		$this->setState('list.start', $limitstart);

		$orderCol	= $input->get('filter_order', 'a.publish_up', 'cmd');
		if (!in_array($orderCol, $this->filter_fields))
		{
			$orderCol = 'a.publish_up';
		}
		$this->setState('list.ordering', $orderCol);

		$listOrder	= $input->get('filter_order_Dir', 'DESC', 'cmd');
		if (!in_array(strtoupper($listOrder), array('ASC', 'DESC', '')))
		{
			$listOrder = 'DESC';
		}
		$this->setState('list.direction', $listOrder);

		$user = JFactory::getUser();
		if ((!$user->authorise('core.edit.state', 'com_podcastmanager')) &&  (!$user->authorise('core.edit', 'com_podcastmanager')))
		{
			// Limit to published for people who can't edit or edit.state.
			$this->setState('filter.published',	1);

			// Filter by published dates.
			$this->setState('filter.publish_date', true);
		}

		$this->setState('filter.language', $app->getLanguageFilter());

		// Load the parameters.
		$this->setState('params', $params);
	}
}
