<?php

namespace Kanboard\Plugin\GotifyDue;

use Kanboard\Core\Plugin\Base;
use Kanboard\Plugin\GotifyDue\Action\TaskGotifyDue;
use Kanboard\Core\Translator;

class Plugin extends Base

{   
	public function initialize()
	{
		if (!file_exists('plugins/Gotify')) 
		{
			print "\nGotify Plugin for Kanboard (https://github.com/bhopmann/kanboard-plugin-gotify) is not available! Please install it and set it up to use the GotifyDue plugin!\n\n";
			return false;
		}
        
		$this->actionManager->register(new TaskGotifyDue($this->container));

	}
	
	public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }
    
	public function getPluginName()	
	{ 		 
		return 'GotifyDue'; 
	}

	public function getPluginAuthor() 
	{ 	 
		return 'Craig Crosby/Benedikt Hopmann'; 
	}

	public function getPluginVersion() 
	{ 	 
		return '1.0.0'; 
	}

	public function getPluginDescription() 
	{ 
		return t('This Automatic Action will allow you to send Gotify notifications of impending due date for tasks. It depends on the Gotify Plugin for Kanboard.'); 
	}

	public function getPluginHomepage() 
	{ 	 
		return 'https://github.com/bhopmann/kanboard-plugin-gotifydue'; 
	}
}
