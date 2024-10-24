<?php
/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2024 SalesAgility Ltd.
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
 * along with this program.  If not, see http://www.gnu.org/licenses.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License
 * version 3, these Appropriate Legal Notices must retain the display of the
 * "Supercharged by SuiteCRM" logo. If the display of the logos is not reasonably
 * feasible for technical reasons, the Appropriate Legal Notices must display
 * the words "Supercharged by SuiteCRM".
 */

namespace App\Logging\Services;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class LoggingEnvVarProcessor implements EnvVarProcessorInterface
{
    protected string $projectDir;

    public function __construct(
        string $projectDir = ''
    )
    {
        $this->projectDir = $projectDir;
    }

    /**
     * @param string $prefix
     * @param string $name
     * @param \Closure $getEnv
     *
     * @return string
     */
    public function getEnv(string $prefix, string $name, \Closure $getEnv) : string
    {
        $env = $getEnv($name);

        if ($env === null) {
            return '';
        }

        if ($env[0] === '/') {
            return $env;
        }

        $baseDir = __DIR__ . '/../../../../../';

        if (!empty($this->projectDir)) {
            $baseDir = $this->projectDir . '/';
        }

        return $baseDir . $env;
    }

    public static function getProvidedTypes() : array
    {
        return [
            'env.logs' => 'string',
        ];
    }
}
