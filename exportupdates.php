<?php
/**
 * @copyright  Copyright (C) 2018 Blue Flame Digital Solutions Limited / Phil Taylor. All rights reserved.
 * @author     Phil Taylor <phil@phil-taylor.com>
 *
 * @see        https://github.com/PhilETaylor/exportupdates
 *
 * @license    GPL
 */
use ParagonIE\EasyRSA\EasyRSA;
use ParagonIE\EasyRSA\PublicKey;

defined('_JEXEC') or die;

class plgSystemExportupdates extends JPlugin
{
    /**
     * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
     * If you want to support 3.0 series you must override the constructor.
     *
     * @var bool
     *
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Plugin method with the same name as the event will be called automatically.
     */
    public function onAfterInitialise()
    {
        if (!array_key_exists('s8EDEgvFRT', $_GET)) {
            return;
        }

        $this->_init();

        $data = json_encode(array(
            'joomla_core' => $this->getJoomlaUpdates(),
            'extensions'  => $this->getExtensionUpdates(),
        ));

        $encrypt = true;
        if (true === $encrypt) {
            echo base64_encode(EasyRSA::encrypt($data, new PublicKey(file_get_contents(dirname(__FILE__).'/keys/myjoomla_public.key'))));
        } else {
            echo $data;
        }

        // No point in rendering and running the rest of Joomla :-)
        die;
    }

    private function _init()
    {
        require 'vendor/autoload.php';
        require JPATH_ADMINISTRATOR.'/components/com_joomlaupdate/models/default.php';
        // Joomla 1.7.x has to be a pain in the arse!
        if (!class_exists('JUpdater') && file_exists(JPATH_LIBRARIES.'/joomla/updater/updater.php')) {
            require JPATH_LIBRARIES.'/joomla/updater/updater.php';
        }
    }

    private function getJoomlaUpdates()
    {
        $model = new JoomlaupdateModelDefault();
        $model->applyUpdateSite();
        $model->refreshUpdates(true);

        return $model->getUpdateInformation();
    }

    private function getExtensionUpdates()
    {
        if (
            !file_exists(JPATH_LIBRARIES.'/joomla/updater/updater.php')
            &&
            !file_exists(JPATH_LIBRARIES.'/src/Updater/Updater.php')
        ) {
            return false;
        }

        $db = JDatabaseFactory::getInstance();

        // clear cache and enable disabled sites again
        $db = JFactory::getDbo();
        $db->setQuery('update #__update_sites SET last_check_timestamp = 0');
        // $db->setQuery('update #__update_sites SET last_check_timestamp = 0, enabled = 1');
        $db->query();
        $db->setQuery('TRUNCATE #__updates');
        $db->query();

        // Let Joomla to the caching of the latest version of updates available from vendors
        $updater = JUpdater::getInstance();
        $updater->findUpdates();

        // get the resultant list of updates available
        $db->setQuery('SELECT * from #__updates');
        $updates = $db->LoadObjectList();

        // reformat into a useable array with the extension_id as the array key
        $extensionUpdatesAvailable = array();
        foreach ($updates as $update) {
            $extensionUpdatesAvailable[$update->extension_id] = $update;
        }

        // get all the installed extensions from the site
        $db->setQuery('SELECT * from #__extensions');
        $items = $db->LoadObjectList();

        // init what we will return, a neat and tidy array
        $updatesAvailable = array();

        // for all installed items...
        foreach ($items as $item) {
            // merge by inject all known info into this item
            if (!array_key_exists($item->extension_id, $extensionUpdatesAvailable)) {
                continue;
            }

            foreach ($extensionUpdatesAvailable[$item->extension_id] as $k => $v) {
                $item->$k = $v;
            }

            // Crappy Joomla
            $item->current_version = array_key_exists(@$item->extension_id, @$extensionUpdatesAvailable) ? @$extensionUpdatesAvailable[@$item->extension_id]->version : @$item->version;

            // if there is a newer version we want that!
            if (null !== $item->current_version) {
                // compose a nice new class, doesnt matter as we are json_encoding later anyway
                $i                  = new stdClass();
                $i->name            = $item->name;
                $i->eid             = $item->extension_id;
                $i->current_version = $item->current_version;
                $i->infourl         = $item->infourl;

                // inject to our array we will return
                $updatesAvailable[] = $i;
            }
        }

        // Harvest update sites for better features in the future
        $db->setQuery('SELECT * from #__update_sites');
        $updateSites = $db->LoadObjectList();

        $data            = array();
        $data['updates'] = $updatesAvailable;
        $data['sites']   = $updateSites;

        return $data;
    }

    private function keyExample()
    {
//        $keyPair = KeyPair::generateKeyPair(4096);
//
//        $secretKey = $keyPair->getPrivateKey();
//        $publicKey = $keyPair->getPublicKey();
//
//        file_put_contents(dirname(__FILE__).'/keys/my_secret.key', $secretKey->getKey());
//        file_put_contents(dirname(__FILE__).'/keys/my_public.key', $publicKey->getKey());
//
//        $keyPair = KeyPair::generateKeyPair(4096);
//
//        $secretKey = $keyPair->getPrivateKey();
//        $publicKey = $keyPair->getPublicKey();
//
//        file_put_contents(dirname(__FILE__).'/keys/myjoomla_secret.key', $secretKey->getKey());
//        file_put_contents(dirname(__FILE__).'/keys/myjoomla_public.key', $publicKey->getKey());
    }
}
