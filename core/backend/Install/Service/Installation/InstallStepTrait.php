<?php

namespace App\Install\Service\Installation;

trait InstallStepTrait
{

    /**
     * @param array $context
     *
     * @return array|mixed
     */
    protected function getInputs(array &$context) : mixed
    {
        return $context['inputs'] ?? [];
    }

    /**
     * @param array $inputs
     *
     * @return bool
     */
    protected function validateInputs(array $inputs) : bool
    {
        return
            !empty($inputs) &&
            !empty($inputs['db_host']) &&
            !empty($inputs['db_username']) &&
            !empty($inputs['db_password']);
    }
}
