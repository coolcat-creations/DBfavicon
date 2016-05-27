<?php
/**
 * @Copyright    (c) Elisa Foltyn - All rights reserved.
 * @package      Plg_System_DBFavicon
 * @author       Elisa Foltyn
 * @link         http://www.designbengel.de
 *
 * @license      GPL v3
 **/
defined('_JEXEC') or die;

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

/**
 * Class PlgSystemDbfavicon Generates different App Icons in Joomla! header
 *
 * @since  1.0.0
 */
class PlgSystemDbfavicon extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Base path to favicons
	 *
	 * @var    string
	 * @since   1.0.0
	 */
	protected $basePath;

	/**
	 * Check if update of imagefiles is needed
	 *
	 * @var    boolean
	 *
	 * @since   1.0.0
	 */
	protected $update = true;

	/**
	 * Constructor - Initialize basePath
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   1.0.1
	 */
	public function __construct(&$subject, $config = array())
	{
		$this->basePath = JPATH_BASE . '/images/favicons/';

		parent::__construct($subject, $config);
	}


	/**
	 * Main entry point for the plugin
	 *
	 * @throws  Exception
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onAfterDispatch()
	{
		$doc = JFactory::getApplication()->getDocument();

		if (!JFactory::getApplication()->isSite() || $doc->getType() !== 'html')
		{
			return;
		}

		$mainImage     = $this->params->get('mainimage', '');
		$mainImagePath = JPATH_BASE . "/" . $mainImage;

		if (empty($mainImage) || !JFile::exists($mainImagePath))
		{
			return;
		}

		if (JFile::getExt($mainImagePath) != 'png')
		{
			JFactory::getApplication()->enqueueMessage(JText::_('PLG_SYSTEM_DBFAVICON_WARNING_IMGTYPE'), 'warning');

			return;
		}

		// Checks if this file already exist
		$md5 = md5_file($mainImagePath);

		if ($md5 == $this->params->get('md5sum'))
		{
			$this->update = false;
		}

		$jimage = new JImage($mainImage);

		$sizes = $this->generateSizes();

		if (!count($sizes))
		{
			return;
		}

		$imgFile = basename($mainImage);
		$imgFile = str_replace(".png", "", $imgFile);

		foreach ($sizes as $key => $thumbSizes)
		{
			if ($this->update)
			{
				$jimage->createThumbs($thumbSizes, JImage::CROP_RESIZE, JPATH_BASE . '/images/favicons');
			}

			$this->generateFaviconlink($thumbSizes, $key, $imgFile);
		}

		if (!$this->update)
		{
			return;
		}

		$this->updateParams($md5);
	}

	/**
	 * Generate different appicon sizes
	 *
	 * @throws  Exception
	 *
	 * @return  array  The different sizes
	 *
	 * @since   1.0.0
	 */
	protected function generateSizes()
	{
		$sizes = array();

		if (($this->params->get('dbfavicon_enable_basic') == "1"))
		{
			$sizes["favicon"] = array('16x16', '32x32', '96x96', '194x194');
		}

		if (($this->params->get('dbfavicon_enable_ios') == "1"))
		{
			$sizes["apple-touch-icon"] = array('57x57', '60x60', '72x72', '76x76', '114x114', '120x120', '144x144', '152x152', '180x180');
		}

		if (($this->params->get('dbfavicon_enable_android') == "1"))
		{
			$sizes["android-chrome"] = array('36x36', '48x48', '72x72', '96x96', '144x144', '192x192');
		}

		if (($this->params->get('dbfavicon_enable_windows') == "1"))
		{
			$sizes["mstiles"] = array('70x70', '70x270', '144x144', '150x150', '310x150', '310x310');
		}

		return $sizes;
	}

	/**
	 * Generate Link Tags in Joomla! header
	 *
	 * @param   array   $thumbSizes  All images to generate
	 * @param   string  $key         Identifies the device
	 * @param   string  $imgFile     Path to the image
	 *
	 * @throws  Exception
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function generateFaviconlink($thumbSizes, $key, $imgFile)
	{
		// Create json
		$doc = JFactory::getDocument();
		$json = new stdClass;
		$json->name = JFactory::getApplication()->get("sitename");
		$json->icons = array();

		$density  = array(0.75, 1, 1.5, 2, 3, 4);
		$cnt      = 0;
		$msTiles  = "";
		$msActive = false;

		foreach ($thumbSizes as $i => $faviconSize)
		{
			$faviconPath = $this->basePath . $key . "-" . $faviconSize . ".png";

			if ($this->update)
			{
				JFile::move($this->basePath . $imgFile . "_" . $faviconSize . ".png", $faviconPath);
			}

			$faviconPath = str_replace(JPATH_BASE . "/", "", $faviconPath);

			if ($key == "favicon")
			{
				$attribs = array('type' => 'image/png', 'sizes' => $faviconSize);
				$doc->addHeadLink($faviconPath, $key, 'rel', $attribs);
			}
			elseif ($key == "apple-touch-icon")
			{
				$attribs = array('type' => 'image/png', 'sizes' => $faviconSize);
				$doc->addHeadLink($faviconPath, $key, 'rel', $attribs);
			}
			elseif ($key == "android-chrome")
			{
				$json->icons[] = array('src' => $faviconPath, 'sizes' => $faviconSize,
					'type' => "image/png", 'density' => $density[$cnt]
				);
				$cnt++;
			}
			elseif ($key == "msTiles")
			{
				$msTiles .= '<square' . $faviconSize . 'logo src="/mstile-' . $faviconSize . ".png\"/>\n";
				$msActive = true;
			}
		}

		// Creates a JSON file for android
		if ($cnt > 0)
		{
			$alink   = $this->params->get("androidoptionslink", "");
			$aorient = $this->params->get("androidoptionsorientation", "");

			if ($alink != "")
			{
				$json->display = $alink;
			}

			if ($aorient != "")
			{
				$json->orientation = $aorient;
			}

			$manifestPath = str_replace(JPATH_BASE . "/", "", $this->basePath . "manifest.json");

			if ($this->update)
			{
				file_put_contents($this->basePath . "/manifest.json", json_encode($json));
			}

			$doc->addHeadLink($manifestPath, "manifest", 'rel');
			$doc->setMetaData('theme-color', $this->params->get("colorandroid", "#fff"));
		}

		// Create xml for Windows tiles in the root (browserconfig.xml)
		if ($msActive)
		{
			if ($this->update)
			{
				$xmlcontainer = '<?xml version="1.0" encoding="utf-8"?>
				<browserconfig>
					<msapplication>
						<tile>
						' . $msTiles . '
						<TileColor>' . $this->params->get("colorwindows") . '</TileColor>
						</tile>
					</msapplication>
				</browserconfig>';

				file_put_contents(JPATH_BASE . "/browserconfig.xml", $xmlcontainer);
			}

			$doc->setMetaData('msapplication-TileColor', $this->params->get("colorwindows", "#fff"));
			$doc->setMetaData('msapplication-TileImage', 'mstile-144x144.png');
		}
	}

	/**
	 * Updates plugin params in the database for update check
	 *
	 * @param   string  $md5  Md5 hash of the image
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function updateParams($md5)
	{
		$this->params->set('md5sum', $md5);
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . '=' . $db->q(json_encode($this->params)))
			->where($db->qn('element') . '=' . $db->q('dbfavicon'))
			->where($db->qn('type') . '=' . $db->q('plugin'));
		$db->setQuery($query);
		$db->execute();
	}
}
