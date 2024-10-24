<?php
/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2022 SalesAgility Ltd.
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

namespace App\Install\Service\LegacyMigration\Steps;

use Throwable;
use App\Engine\Model\Feedback;
use App\Engine\Model\ProcessStepTrait;
use App\Install\LegacyHandler\InstallHandler;
use App\Install\Service\Installation\InstallStepTrait;
use App\Install\Service\LegacyMigration\LegacyMigrationStepInterface;

/**
 * Class ChangeTheme
 * @package App\Install\Service\LegacyMigration\Steps;
 */
class ChangeTheme implements LegacyMigrationStepInterface
{
    use ProcessStepTrait;
    use InstallStepTrait;

    public const HANDLER_KEY = 'change-theme';
    public const POSITION = 300;

    /**
     * @var InstallHandler
     */
    private InstallHandler $handler;

    /**
     * ChangeTheme constructor.
     * @param InstallHandler $handler
     */
    public function __construct(InstallHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::HANDLER_KEY;
    }

    /**
     * @inheritDoc
     */
    public function getOrder(): int
    {
        return self::POSITION;
    }

    /**
     * @inheritDoc
     * @throws Throwable
     */
    public function execute(array &$context): Feedback
    {
        $config = $this->handler->loadLegacyConfig();

        $this->handler->initLegacy();

        require_once $this->handler->getLegacyDir().'include/utils/file_utils.php';

        $config['default_theme'] = 'suite8';

        write_array_to_file('sugar_config', $config, 'config.php');

        $this->handler->closeLegacy();

        $feedback = new Feedback();
        $feedback->setSuccess(true);
        $feedback->setMessages(['Set suite8 theme as default theme']);

        return $feedback;
    }
}
