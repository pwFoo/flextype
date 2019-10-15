<?php

declare(strict_types=1);

/**
 * Flextype (http://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype;

use Flextype\Component\Arr\Arr;
use Flextype\Component\Filesystem\Filesystem;
use Flextype\Component\I18n\I18n;
use RuntimeException;
use function array_merge;
use function count;
use function filemtime;
use function is_array;
use function md5;

class Plugins
{
    /**
     * Flextype Dependency Container
     */
    private $flextype;

    /**
     * Locales array
     *
     * @var array
     */
    private $locales = [];

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct($flextype, $app)
    {
        $this->flextype = $flextype;
        $this->locales  = $this->flextype['parser']->decode(Filesystem::read(ROOT_DIR . '/flextype/config/locales.yaml'), 'yaml');
    }

    /**
     * Get locales
     *
     * @return array
     *
     * @access public
     */
    public function getLocales()
    {
        return $this->locales;
    }

    /**
     * Init Plugins
     *
     * @access private
     */
    public function init($flextype, $app) : void
    {
        // Set empty plugins item
        $this->flextype['registry']->set('plugins', []);

        // Set locale
        $locale = $this->flextype['registry']->get('settings.locale');

        // Get plugins list
        $plugins_list = $this->getPluginsList();

        // Get plugins Cache ID
        $plugins_cache_id = $this->getPluginsCacheID($plugins_list);

        // If Plugins List isnt empty then continue
        if (! is_array($plugins_list) || count($plugins_list) <= 0) {
            return;
        }

        // Get plugins from cache or scan plugins folder and create new plugins cache item
        if ($this->flextype['cache']->contains($plugins_cache_id)) {

            $this->flextype['registry']->set('plugins', $this->flextype['cache']->fetch($plugins_cache_id));

            if ($this->flextype['cache']->contains($locale)) {
                I18n::add($this->flextype['cache']->fetch($locale), $locale);
            } else {
                // Save plugins dictionary
                $dictionary = $this->getPluginsDictionary($plugins_list, $locale);
                $this->flextype['cache']->save($locale, $dictionary[$locale]);
            }


        } else {
            // If Plugins List isnt empty
            if (is_array($plugins_list) && count($plugins_list) > 0) {
                // Init plugin configs
                $plugins         = [];
                $plugin_settings = [];
                $plugin_manifest = [];
                $default_plugin_settings = [];
                $site_plugin_settings = [];
                $default_plugin_manifest = [];
                $site_plugin_manifest = [];

                // Go through...
                foreach ($plugins_list as $plugin) {

                    $default_plugin_settings_file = PATH['plugins'] . '/' . $plugin['dirname'] . '/settings.yaml';
                    $default_plugin_manifest_file = PATH['plugins'] . '/' . $plugin['dirname'] . '/plugin.yaml';

                    $site_plugin_settings_file = PATH['config']['site'] . '/plugins/' . $plugin['dirname'] . '/settings.yaml';
                    $site_plugin_manifest_file = PATH['config']['site'] . '/plugins/' . $plugin['dirname'] . '/plugin.yaml';

                    if (Filesystem::has($default_plugin_settings_file)) {
                        $default_plugin_settings_file_content = Filesystem::read($default_plugin_settings_file);
                        $default_plugin_settings = $this->flextype['parser']->decode($default_plugin_settings_file_content, 'yaml');

                        if (Filesystem::has($site_plugin_settings_file)) {
                            $site_plugin_settings_file_content = Filesystem::read($site_plugin_settings_file);
                            $site_plugin_settings = $this->flextype['parser']->decode($site_plugin_settings_file_content, 'yaml');
                        }
                    } else {
                        throw new RuntimeException('Load ' . $plugin['dirname'] . ' plugin settings - failed!');
                    }

                    if (Filesystem::has($default_plugin_manifest_file)) {
                        $default_plugin_manifest_file_content = Filesystem::read($default_plugin_manifest_file);
                        $default_plugin_manifest = $this->flextype['parser']->decode($default_plugin_manifest_file_content, 'yaml');

                        if (Filesystem::has($site_plugin_manifest_file)) {
                            $site_plugin_manifest_file_content = Filesystem::read($site_plugin_manifest_file);
                            $site_plugin_manifest = $this->flextype['parser']->decode($site_plugin_manifest_file_content, 'yaml');
                        }
                    } else {
                        throw new RuntimeException('Load ' . $plugin['dirname'] . ' plugin manifest - failed!');
                    }

                    $plugins[$plugin['dirname']] = array_merge(array_replace_recursive($default_plugin_settings, $site_plugin_settings),
                                                               array_replace_recursive($default_plugin_manifest, $site_plugin_manifest));

                    // Set default plugin priority 0
                    if (isset($plugins[$plugin['dirname']]['priority'])) {
                        continue;
                    }

                    $plugins[$plugin['dirname']]['priority'] = 0;
                }

                // Sort plugins list by priority.
                $plugins = Arr::sort($plugins, 'priority', 'DESC');

                // Save plugins list
                $this->flextype['registry']->set('plugins', $plugins);
                $this->flextype['cache']->save($plugins_cache_id, $plugins);

                // Save plugins dictionary
                $dictionary = $this->getPluginsDictionary($plugins_list, $locale);
                $this->flextype['cache']->save($locale, $dictionary[$locale]);
            }
        }

        $this->includeEnabledPlugins($flextype, $app);

        $this->flextype['emitter']->emit('onPluginsInitialized');
    }

    /**
     * Create plugins dictionary
     *
     * @param  array $plugins_list Plugins list
     *
     * @access protected
     */
    private function getPluginsDictionary(array $plugins_list, string $locale) : array
    {
        foreach ($plugins_list as $plugin) {
            $language_file = PATH['plugins'] . '/' . $plugin['dirname'] . '/lang/' . $locale . '.yaml';

            if (! Filesystem::has($language_file)) {
                continue;
            }

            if (($content = Filesystem::read($language_file)) === false) {
                throw new RuntimeException('Load file: ' . $language_file . ' - failed!');
            }

            I18n::add($this->flextype['parser']->decode($content, 'yaml'), $locale);
        }

        return I18n::$dictionary;
    }

    /**
     * Get plugins cache ID
     *
     * @param  array $plugins_list Plugins list
     *
     * @access protected
     */
    private function getPluginsCacheID(array $plugins_list) : string
    {
        // Plugin cache id
        $_plugins_cache_id = '';

        // Go through...
        if (is_array($plugins_list) && count($plugins_list) > 0) {
            foreach ($plugins_list as $plugin) {

                $default_plugin_settings_file = PATH['plugins'] . '/' . $plugin['dirname'] . '/settings.yaml';
                $default_plugin_manifest_file = PATH['plugins'] . '/' . $plugin['dirname'] . '/plugin.yaml';
                $site_plugin_settings_file = PATH['config']['site'] . '/plugins/' . $plugin['dirname'] . '/settings.yaml';
                $site_plugin_manifest_file = PATH['config']['site'] . '/plugins/' . $plugin['dirname'] . '/plugin.yaml';

                $f1 = Filesystem::has($default_plugin_settings_file) ? filemtime($default_plugin_settings_file) : '' ;
                $f2 = Filesystem::has($default_plugin_manifest_file) ? filemtime($default_plugin_manifest_file) : '' ;
                $f3 = Filesystem::has($site_plugin_settings_file) ? filemtime($site_plugin_settings_file) : '' ;
                $f4 = Filesystem::has($site_plugin_manifest_file) ? filemtime($site_plugin_manifest_file) : '' ;

                $_plugins_cache_id .= $f1 . $f2 . $f3 . $f4;
            }
        }

        // Create Unique Cache ID for Plugins
        $plugins_cache_id = md5('plugins' . PATH['plugins'] . '/' . $_plugins_cache_id);

        // Return plugin cache id
        return $plugins_cache_id;
    }

    /**
     * Get plugins list
     *
     * @access public
     */
    public function getPluginsList() : array
    {
        // Get Plugins List
        $plugins_list = [];

        foreach (Filesystem::listContents(PATH['plugins']) as $plugin) {
            if ($plugin['type'] !== 'dir') {
                continue;
            }

            $plugins_list[] = $plugin;
        }

        return $plugins_list;
    }

    /**
     * Include enabled plugins
     *
     * @access protected
     */
    private function includeEnabledPlugins($flextype, $app) : void
    {
        if (! is_array($this->flextype['registry']->get('plugins')) || count($this->flextype['registry']->get('plugins')) <= 0) {
            return;
        }

        foreach ($this->flextype['registry']->get('plugins') as $plugin_name => $plugin) {
            if (! $this->flextype['registry']->get('plugins.' . $plugin_name . '.enabled')) {
                continue;
            }

            include_once PATH['plugins'] . '/' . $plugin_name . '/bootstrap.php';
        }
    }
}
