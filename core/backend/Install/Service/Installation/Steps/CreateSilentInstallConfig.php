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

namespace App\Install\Service\Installation\Steps;

use App\Engine\Model\Feedback;
use App\Engine\Model\ProcessStepTrait;
use App\Install\LegacyHandler\InstallHandler;
use App\Install\Service\Installation\InstallStatus;
use App\Install\Service\Installation\InstallStepInterface;
use App\Install\Service\Installation\InstallStepTrait;

/**
 * Class CreateSilentInstallConfig
 *
 * @package App\Install\Service\Installation\Steps;
 */
class CreateSilentInstallConfig implements InstallStepInterface
{
    use ProcessStepTrait;
    use InstallStepTrait;

    public const HANDLER_KEY = 'create-config';
    public const POSITION    = 500;

    /**
     * @var InstallHandler
     */
    private InstallHandler $handler;

    /**
     * CreateSilentInstallConfig constructor.
     *
     * @param InstallHandler $handler
     */
    public function __construct(InstallHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @inheritDoc
     */
    public function getKey() : string
    {
        return self::HANDLER_KEY;
    }

    /**
     * @inheritDoc
     */
    public function getOrder() : int
    {
        return self::POSITION;
    }

    /**
     * @inheritDoc
     */
    public function execute(array &$context) : Feedback
    {
        $inputs = $this->getInputs($context);
        $feedback = new Feedback();

        if (!$this->validateInputs($inputs)) {
            return $feedback->setSuccess(false)->setMessages([ 'Missing inputs' ]);
        }

        if (!$this->handler->createConfig($inputs)) {
            $feedback->setSuccess(false);
            $feedback->setMessages([ 'Could not create silent install config: config_si.php' ]);
            $feedback->setStatusCode(InstallStatus::FAILED);
        } else {
            $feedback->setSuccess(true);
            $feedback->setMessages([ 'Created silent install config: config_si.php' ]);
        }

        return $feedback;
    }

}
