<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype\Foundation\Entries;

use Atomastic\Arrays\Arrays;

use function array_merge;
use function collect_filter;
use function count;
use function find_filter;
use function ltrim;
use function md5;
use function rtrim;
use function str_replace;

class Entries
{
    /**
     * Entries Storage
     *
     * Used for storing current requested entry(entries) data
     * and maybe changed on fly.
     *
     * @var array
     * @access public
     */
    private $storage = [];

    /**
     * Get storage
     *
     * @param string Key
     */
    public function getStorage(string $key)
    {
        return arrays($this->storage)->get($key);
    }

    /**
     * Set storage
     *
     * @param string Key
     * @param mixed  Value
     */
    public function setStorage(string $key, $value)
    {
        $this->storage = arrays($this->storage)->set($key, $value)->toArray();
    }

    /**
     * Fetch entry(entries)
     *
     * @param string $id         Unique identifier of the entry(entries).
     * @param bool   $collection Set `true` if collection of entries need to be fetched.
     * @param array  $filter     Select items in collection by given conditions.
     *
     * @return array|bool|int
     *
     * @access public
     */
    public function fetch(string $id, bool $collection = false, array $filter = [])
    {
        if ($collection) {
            return $this->fetchCollection($id, $filter);
        }

        return $this->fetchSingle($id);
    }

    /**
     * Fetch single entry
     *
     * @param string $id Unique identifier of the entry(entries).
     *
     * @return array The entry array data.
     *
     * @access public
     */
    public function fetchSingle(string $id): array
    {
        // Store data
        $this->storage['fetch_single']['id'] = $id;

        // Run event: onEntryInitialized
        flextype('emitter')->emit('onEntryInitialized');

        // Get Cache ID for current requested entry
        $entry_cache_id = $this->getCacheID($this->storage['fetch_single']['id']);

        // Try to get current requested entry from cache
        if (flextype('cache')->has($entry_cache_id)) {
            // Fetch entry from cache
            $this->storage['fetch_single']['data'] = flextype('cache')->get($entry_cache_id);

            // Run event: onEntryAfterCacheInitialized
            flextype('emitter')->emit('onEntryAfterCacheInitialized');

            // Return entry from cache
            return $this->storage['fetch_single']['data'];
        }

        // Try to get current requested entry from filesystem
        if ($this->has($this->storage['fetch_single']['id'])) {
            // Get entry file location
            $entry_file = $this->getFileLocation($this->storage['fetch_single']['id']);

            // Try to get requested entry from the filesystem
            $entry_file_content = flextype('filesystem')->file($entry_file)->get();
            if ($entry_file_content === false) {
                return [];
            }

            // Decode entry file content
            $this->storage['fetch_single']['data'] = flextype('frontmatter')->decode($entry_file_content);

            // Run event: onEntryAfterInitialized
            flextype('emitter')->emit('onEntryAfterInitialized');

            // Set cache state
            $cache = flextype('entries')->storage['fetch_single']['data']['cache']['enabled'] ??
                                flextype('registry')->get('flextype.settings.cache.enabled');

            // Save entry data to cache
            if ($cache) {
                flextype('cache')->set($entry_cache_id, $this->storage['fetch_single']['data']);
            }

            // Return entry data
            return $this->storage['fetch_single']['data'];
        }

        // Return empty array if entry is not founded
        return [];
    }

    /**
     * Fetch entries collection
     *
     * @param string $id     Unique identifier of the entry(entries).
     * @param array  $filter Select items in collection by given conditions.
     *
     * @return array|bool|int
     *
     * @access public
     */
    public function fetchCollection(string $id, array $filter = [])
    {
        // Store data
        $this->storage['fetch_collection']['id']   = $this->getDirectoryLocation($id);
        $this->storage['fetch_collection']['data'] = [];

        // Run event: onEntriesInitialized
        flextype('emitter')->emit('onEntriesInitialized');

        // Find entries
        $entries_list = find_filter($this->storage['fetch_collection']['id'], $filter);

        // If entries founded in the entries folder
        // We are checking... Whether the requested entry is an a true entry.
        // Get entry $_id. Remove entries path and remove left and right slashes.
        // Fetch single entry.
        if (count($entries_list) > 0) {
            foreach ($entries_list as $current_entry) {
                if ($current_entry->getType() !== 'file' || $current_entry->getFilename() !== 'entry' . '.' . flextype('registry')->get('flextype.settings.entries.extension')) {
                    continue;
                }

                $_id                                             = ltrim(rtrim(str_replace(PATH['project'] . '/entries/', '', str_replace('\\', '/', $current_entry->getPath())), '/'), '/');
                $this->storage['fetch_collection']['data'][$_id] = $this->fetchSingle($_id);
            }

            // Apply collection filter
            $this->storage['fetch_collection']['data'] = collect_filter($this->storage['fetch_collection']['data'], $filter);

            // Run event: onEntriesAfterInitialized
            flextype('emitter')->emit('onEntriesAfterInitialized');
        }

        // Return entries array
        return $this->storage['fetch_collection']['data'];
    }

    /**
     * Move entry
     *
     * @param string $id     Unique identifier of the entry(entries).
     * @param string $new_id New Unique identifier of the entry(entries).
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function move(string $id, string $new_id): bool
    {
        // Store data
        $this->storage['move']['id']     = $id;
        $this->storage['move']['new_id'] = $new_id;

        // Run event: onEntryMove
        flextype('emitter')->emit('onEntryMove');

        if (! $this->has($this->storage['move']['new_id'])) {
            return flextype('filesystem')->directory($this->getDirectoryLocation($this->storage['move']['id']))->move($this->getDirectoryLocation($this->storage['move']['new_id']));
        }

        return false;
    }

    /**
     * Update entry
     *
     * @param string $id   Unique identifier of the entry(entries).
     * @param array  $data Data to update for the entry.
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function update(string $id, array $data): bool
    {
        // Store data
        $this->storage['update']['id']   = $id;
        $this->storage['update']['data'] = $data;

        // Run event: onEntryUpdate
        flextype('emitter')->emit('onEntryUpdate');

        $entry_file = $this->getFileLocation($this->storage['update']['id']);

        if (flextype('filesystem')->file($entry_file)->exists()) {
            $body  = flextype('filesystem')->file($entry_file)->get();
            $entry = flextype('frontmatter')->decode($body);

            return (bool) flextype('filesystem')->file($entry_file)->put(flextype('frontmatter')->encode(array_merge($entry, $this->storage['update']['data'])));
        }

        return false;
    }

    /**
     * Create entry
     *
     * @param string $id   Unique identifier of the entry(entries).
     * @param array  $data Data to create for the entry.
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function create(string $id, array $data = []): bool
    {
        // Store data
        $this->storage['create']['id']   = $id;
        $this->storage['create']['data'] = $data;

        // Run event: onEntryCreate
        flextype('emitter')->emit('onEntryCreate');

        $entry_dir = $this->getDirectoryLocation($this->storage['create']['id']);

        if (! flextype('filesystem')->directory($entry_dir)->exists()) {
            if (flextype('filesystem')->directory($entry_dir)->create()) {
                if (! flextype('filesystem')->file($entry_file = $entry_dir . '/entry' . '.' . flextype('registry')->get('flextype.settings.entries.extension'))->exists()) {
                    return (bool) flextype('filesystem')->file($entry_file)->put(flextype('frontmatter')->encode($this->storage['create']['data']));
                }

                return false;
            }
        }

        return false;
    }

    /**
     * Delete entry
     *
     * @param string $id Unique identifier of the entry(entries).
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function delete(string $id): bool
    {
        // Store data
        $this->storage['delete']['id'] = $id;

        // Run event: onEntryDelete
        flextype('emitter')->emit('onEntryDelete');

        return flextype('filesystem')->directory($this->getDirectoryLocation($this->storage['delete']['id']))->delete();
    }

    /**
     * Copy entry(s)
     *
     * @param string $id     Unique identifier of the entry(entries).
     * @param string $new_id New Unique identifier of the entry(entries).
     *
     * @return bool|null True on success, false on failure.
     *
     * @access public
     */
    public function copy(string $id, string $new_id): ?bool
    {
        // Store data
        $this->storage['copy']['id']     = $id;
        $this->storage['copy']['new_id'] = $new_id;

        // Run event: onEntryCopy
        flextype('emitter')->emit('onEntryCopy');

        return flextype('filesystem')->directory($this->getDirectoryLocation($this->storage['copy']['id']))->copy($this->getDirectoryLocation($this->storage['copy']['new_id']));
    }

    /**
     * Check whether entry exists
     *
     * @param string $id Unique identifier of the entry(entries).
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function has(string $id): bool
    {
        // Store data
        $this->storage['has']['id'] = $id;

        // Run event: onEntryHas
        flextype('emitter')->emit('onEntryHas');

        return flextype('filesystem')->file($this->getFileLocation($this->storage['has']['id']))->exists();
    }

    /**
     * Get entry file location
     *
     * @param string $id Unique identifier of the entry(entries).
     *
     * @return string entry file location
     *
     * @access public
     */
    public function getFileLocation(string $id): string
    {
        return PATH['project'] . '/entries/' . $id . '/entry' . '.' . flextype('registry')->get('flextype.settings.entries.extension');
    }

    /**
     * Get entry directory location
     *
     * @param string $id Unique identifier of the entry(entries).
     *
     * @return string entry directory location
     *
     * @access public
     */
    public function getDirectoryLocation(string $id): string
    {
        return PATH['project'] . '/entries/' . $id;
    }

    /**
     * Get Cache ID for entry
     *
     * @param  string $id Unique identifier of the entry(entries).
     *
     * @return string Cache ID
     *
     * @access public
     */
    public function getCacheID(string $id): string
    {
        if (flextype('registry')->get('flextype.settings.cache.enabled') === false) {
            return '';
        }

        $entry_file = $this->getFileLocation($id);

        if (flextype('filesystem')->file($entry_file)->exists()) {
            return md5('entry' . $entry_file . (flextype('filesystem')->file($entry_file)->lastModified() ?: ''));
        }

        return md5('entry' . $entry_file);
    }
}
