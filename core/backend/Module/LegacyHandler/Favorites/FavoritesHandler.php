<?php

namespace App\Module\LegacyHandler\Favorites;

use Psr\Log\LoggerInterface;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Module\Service\FavoriteProviderInterface;
use App\Module\Service\ModuleNameMapperInterface;
use FavoritesManagerPort;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class FavoritesHandler
 * @package App\Module\Favorites\RecentlyViewed
 */
class FavoritesHandler extends LegacyHandler implements FavoriteProviderInterface
{
    protected const HANDLER_KEY = 'favorites-handler';

    /**
     * @var ModuleNameMapperInterface
     */
    protected ModuleNameMapperInterface $moduleNameMapper;

    /**
     * FavoritesHandler constructor.
     * @param string $projectDir
     * @param string $legacyDir
     * @param string $legacySessionName
     * @param string $defaultSessionName
     * @param LegacyScopeState $legacyScopeState
     * @param RequestStack $session
     * @param ModuleNameMapperInterface $moduleNameMapper
     */
    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $session,
        ModuleNameMapperInterface $moduleNameMapper,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState,
            $session,
            $logger
        );
        $this->moduleNameMapper = $moduleNameMapper;
    }

    /**
     * @inheritDoc
     */
    public function getHandlerKey(): string
    {
        return self::HANDLER_KEY;
    }

    /**
     * @inheritDoc
     */
    public function isFavorite(string $module, string $id): bool
    {
        $this->init();
        $this->startLegacyApp();

        $legacyModule = $this->moduleNameMapper->toLegacy($module);

        require_once $this->legacyDir.'/include/portability/Services/Favorites/FavoritesManagerPort.php';

        $favoritesManager = new FavoritesManagerPort();

        $result = $favoritesManager->isFavorite($legacyModule, $id);

        $this->close();

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getModuleFavorites(string $module): ?array
    {
        $this->init();
        $this->startLegacyApp();

        $legacyModule = $this->moduleNameMapper->toLegacy($module);

        require_once $this->legacyDir.'/include/portability/Services/Favorites/FavoritesManagerPort.php';

        $favoritesManager = new FavoritesManagerPort();

        $result = $favoritesManager->getModuleFavorites($legacyModule);

        $this->close();

        return $result;
    }

}
