<?php
/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2021 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SALESAGILITY, SALESAGILITY DISCLAIMS THE
 * WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License
 * version 3, these Appropriate Legal Notices must retain the display of the
 * "Supercharged by SuiteCRM" logo. If the display of the logos is not reasonably
 * feasible for technical reasons, the Appropriate Legal Notices must display
 * the words "Supercharged by SuiteCRM".
 */

namespace App\UserPreferences\LegacyHandler;

use Psr\Log\LoggerInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use App\UserPreferences\Entity\UserPreference;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\UserPreferences\Service\UserPreferencesProviderInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use UnexpectedValueException;
use User;

class UserPreferenceHandler extends LegacyHandler implements UserPreferencesProviderInterface
{
    protected const MSG_USER_PREFERENCE_NOT_FOUND = 'Not able to find user preference key: ';
    public const    HANDLER_KEY                   = 'user-preferences';

    /**
     * @var array
     */
    protected array $exposedUserPreferences = [];

    /**
     * @var UserPreferencesMappers
     */
    private UserPreferencesMappers $mappers;

    /**
     * @var array
     */
    private array $userPreferencesKeyMap;
    private LoggerInterface $logger;

    /**
     * UserPreferenceHandler constructor.
     *
     * @param string $projectDir
     * @param string $legacyDir
     * @param string $legacySessionName
     * @param string $defaultSessionName
     * @param LegacyScopeState $legacyScopeState
     * @param array $exposedUserPreferences
     * @param UserPreferencesMappers $mappers
     * @param array $userPreferencesKeyMap
     */
    public function __construct(
        string                 $projectDir,
        string                 $legacyDir,
        string                 $legacySessionName,
        string                 $defaultSessionName,
        LegacyScopeState       $legacyScopeState,
        array                  $exposedUserPreferences,
        UserPreferencesMappers $mappers,
        array                  $userPreferencesKeyMap,
        RequestStack           $session,
        LoggerInterface        $logger
    )
    {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState,
            $session,
            $logger
        );

        $this->logger = $logger;
        $this->exposedUserPreferences = $exposedUserPreferences;
        $this->mappers = $mappers;
        $this->userPreferencesKeyMap = $userPreferencesKeyMap;
    }

    /**
     * @inheritDoc
     */
    public function getHandlerKey() : string
    {
        return self::HANDLER_KEY;
    }

    /**
     * Get all exposed user preferences
     *
     * @return array
     */
    public function getAllUserPreferences() : array
    {
        try {
            $this->init();

            $this->startLegacyApp();

            $userPreferences = [];

            foreach ($this->exposedUserPreferences as $category => $categoryPreferences) {
                $userPreference = $this->loadUserPreferenceCategory($category);
                if ($userPreference !== null) {
                    $userPreferences[] = $userPreference;
                }
            }

            $this->close();

            return $userPreferences;
        } catch (\Throwable $e) {
            $this->logger->error(
                $e->getMessage(),
                [
                    'exception' => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'method'    => $e->getFile(),
                    'line'      => $e->getLine()
                ]
            );
            throw $e;
        }
    }

    /**
     * Load user preference with given $key
     *
     * @param string $category
     *
     * @return UserPreference|null
     */
    protected function loadUserPreferenceCategory(string $category = 'global') : ?UserPreference
    {
        $currentUser = $this->getCurrentUser();

        if (empty($category)) {
            return null;
        }

        if (!isset($currentUser->id)) {
            throw new RuntimeException('No user logged in.');
        }

        if (!isset($this->exposedUserPreferences[$category])) {
            $message = self::MSG_USER_PREFERENCE_NOT_FOUND . "'$category'";
            $this->logger->error($message);
            throw new ItemNotFoundException($message);
        }

        $userPreference = new UserPreference();
        $userPreference->setId($category);

        if (!is_array($this->exposedUserPreferences[$category]) || empty($this->exposedUserPreferences[$category])) {
            return $userPreference;
        }

        $items = [];
        foreach ($this->exposedUserPreferences[$category] as $key => $value) {

            $value = $this->loadUserPreference($key, $category);
            $value = $this->mapValue($key, $value);
            $key = $this->mapKey($key);
            $items[$key] = $value;
        }

        $userPreference->setItems($items);

        return $userPreference;
    }

    /**
     * Get currently logged in user
     *
     * @return User
     */
    protected function getCurrentUser() : User
    {
        global $current_user;

        if ($current_user === null) {
            $message = 'Current user is not loaded';
            $this->logger->error($message);
            throw new UnexpectedValueException($message);
        }

        return $current_user;
    }

    /**
     * Load user preference with given $key
     *
     * @param string $key
     * @param string $category
     *
     * @return mixed|null
     */
    protected function loadUserPreference(string $key, string $category = 'global') : mixed
    {
        if (empty($key)) {
            return null;
        }

        if (!isset($this->exposedUserPreferences[$category]) &&
            !isset($this->exposedUserPreferences[$category][$key])) {
            $message = self::MSG_USER_PREFERENCE_NOT_FOUND . "'$key'";
            $this->logger->error($message);
            throw new ItemNotFoundException($message);
        }

        $currentUser = $this->getCurrentUser();
        $preference = $currentUser->getPreference($key, $category);

        if (empty($preference)) {
            return $preference;
        }

        if (is_array($preference)) {
            $items = $preference;

            if (is_array($this->exposedUserPreferences[$category][$key])) {
                $items = $this->filterItems($preference, $this->exposedUserPreferences[$category][$key]);
            }

            return $items;
        }

        return $preference;
    }

    /**
     * Filter to retrieve only exposed items
     *
     * @param array $allItems
     * @param array $exposed
     *
     * @return array
     */
    protected function filterItems(array $allItems, array $exposed) : array
    {
        $filteredItems = [];

        if (empty($exposed)) {
            return $filteredItems;
        }

        foreach ($allItems as $key => $value) {
            if (!isset($exposed[$key])) {
                continue;
            }

            if (is_array($value)) {
                $subItems = $value;
                if (is_array($exposed[$key])) {
                    $subItems = $this->filterItems($value, $exposed[$key]);
                }
                $filteredItems[$key] = $subItems;
                continue;
            }
            $filteredItems[$key] = $value;
        }

        return $filteredItems;
    }

    /**
     * Map user preference value if mapper defined
     *
     * @param string $key
     * @param $preference
     *
     * @return mixed
     */
    protected function mapValue(string $key, $preference) : mixed
    {
        if ($this->mappers->hasMapper($key)) {
            $mapper = $this->mappers->get($key);
            $preference = $mapper->map($preference);
        }

        return $preference;
    }

    /**
     * Map user preference key if mapper defined
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function mapKey(string $key) : mixed
    {
        if ($key === '') {
            return $key;
        }

        return $this->userPreferencesKeyMap[$key] ?? $key;
    }

    /**
     * Get user preference
     *
     * @param string $key
     *
     * @return UserPreference|null
     */
    public function getUserPreference(string $key) : ?UserPreference
    {
        try {
            $this->init();

            $this->startLegacyApp();

            $userPreference = $this->loadUserPreferenceCategory($key);

            $this->close();

            return $userPreference;
        } catch (\Throwable $e) {
            $this->logger->error(
                $e->getMessage(),
                [
                    'exception' => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                    'method'    => $e->getFile(),
                    'line'      => $e->getLine()
                ]
            );
            throw $e;
        }
    }
}
