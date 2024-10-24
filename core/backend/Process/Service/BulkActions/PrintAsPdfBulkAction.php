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


namespace App\Process\Service\BulkActions;

use ApiPlatform\Exception\InvalidArgumentException;
use App\Module\Service\ModuleNameMapperInterface;
use App\Process\Entity\Process;
use App\Process\Service\ProcessHandlerInterface;

class PrintAsPdfBulkAction implements ProcessHandlerInterface
{
    protected const MSG_OPTIONS_NOT_FOUND = 'Process options is not defined';
    protected const PROCESS_TYPE = 'bulk-print-as-pdf';

    /**
     * @var ModuleNameMapperInterface
     */
    private $moduleNameMapper;

    /**
     * PrintAsPdfBulkAction constructor.
     * @param ModuleNameMapperInterface $moduleNameMapper
     */
    public function __construct(ModuleNameMapperInterface $moduleNameMapper)
    {
        $this->moduleNameMapper = $moduleNameMapper;
    }

    /**
     * @inheritDoc
     */
    public function getProcessType(): string
    {
        return self::PROCESS_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function requiredAuthRole(): string
    {
        return 'ROLE_USER';
    }

    /**
     * @inheritDoc
     */
    public function getRequiredACLs(Process $process): array
    {
        $options = $process->getOptions();
        $module = $options['module'] ?? '';
        $ids = $options['ids'] ?? [];


        return [
            $module => [
                [
                    'action' => 'view'
                ],
                [
                    'action' => 'export',
                    'ids' => $ids
                ]
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function configure(Process $process): void
    {
        //This process is synchronous
        //We aren't going to store a record on db
        //thus we will use process type as the id
        $process->setId(self::PROCESS_TYPE);
        $process->setAsync(false);
    }

    /**
     * @inheritDoc
     */
    public function validate(Process $process): void
    {
        if (empty($process->getOptions())) {
            throw new InvalidArgumentException(self::MSG_OPTIONS_NOT_FOUND);
        }

        $options = $process->getOptions();
        [
            'module' => $baseModule,
            'ids' => $baseIds
        ] = $options;

        ['modalRecord' => $modalRecord] = $options;
        [
            'module' => $modalModule,
            'id' => $modalId
        ] = $modalRecord;

        if (empty($baseModule) || empty($baseIds) || empty($modalModule) || empty($modalId)) {
            throw new InvalidArgumentException(self::MSG_OPTIONS_NOT_FOUND);
        }
    }

    /**
     * @inheritDoc
     */
    public function run(Process $process): void
    {
        $options = $process->getOptions();

        $responseData = $this->getDownloadData($options);

        $process->setStatus('success');
        $process->setMessages([]);
        $process->setData($responseData);
    }

    /**
     * @param array|null $options
     * @return array
     */
    protected function getDownloadData(?array $options): array
    {

        ['modalRecord' => $modalRecord] = $options;
        [
            'id' => $modalId
        ] = $modalRecord;

        $responseData = [
            'handler' => 'export',
            'params' => [
                'url' => 'legacy/index.php?templateID='.$modalId.'&entryPoint=formLetter',
                'formData' => []
            ]
        ];

        if (!empty($options['ids'])) {
            $responseData = $this->getIdBasedRequestData($options, $responseData);

            return $responseData;
        }

        return $responseData;
    }

    /**
     * Get request data based on a list of ids
     * @param array|null $options
     * @param array $responseData
     * @return array
     */
    protected function getIdBasedRequestData(?array $options, array $responseData): array
    {

        [
            'module' => $baseModule,
            'ids' => $baseIds
        ] = $options;

        $responseData['params']['formData'] = [
            'uid' => implode(',', $baseIds),
            'module' => $this->moduleNameMapper->toLegacy($baseModule),
            'action' => 'index'
        ];

        return $responseData;
    }

}
