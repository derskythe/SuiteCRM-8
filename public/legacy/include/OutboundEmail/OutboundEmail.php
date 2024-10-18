<?php
/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once(__DIR__ . '/../../include/utils/encryption_utils.php');

use \SuiteCRM\database\DatabasePDOManager;

/**
 * Outbuound email management
 * @api
 */
#[\AllowDynamicProperties]
class OutboundEmail
{
    /**
     * Necessary
     */
    public null|DBManager $db;
    public null|DatabasePDOManager $pdo;
    public array $field_defs = [
        'id',
        'name',
        'type',
        'user_id',
        'mail_sendtype',
        'mail_smtptype',
        'mail_smtpserver',
        'mail_smtpport',
        'mail_smtpuser',
        'mail_smtppass',
        'mail_smtpauth_req',
        'mail_smtpssl',
        'smtp_from_name',
        'smtp_from_addr',
    ];
    public ArrayObject $mailSettings;

    /*
      public $id;
      public $name;
      public $type;
      public $user_id;
      public $mail_sendtype;
      public $mail_smtptype;
      public $mail_smtpserver;
      public $mail_smtpport = 25;
      public $mail_smtpuser;
      public $mail_smtppass;
      public $smtp_from_name;
      public $smtp_from_addr;
      public $mail_smtpauth_req;
      public $mail_smtpssl; // bool
      public $mail_smtpdisplay;
      public $new_with_id = false;*/

    /**
     * Sole constructor
     */
    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
        $this->pdo = \SuiteCRM\database\DatabasePDOManager::getInstance();
        $default_settings = [
            'id' => '',
            'name' => '',
            'type' => 'system',  // user or system
            'user_id' => '',  // owner
            'mail_sendtype' => 'SMTP',  // SMTP
            'mail_smtptype' => '',
            'mail_smtpserver' => '',
            'mail_smtpport' => '25',
            'mail_smtpuser' => '',
            'mail_smtppass' => '',
            'smtp_from_name' => '',
            'smtp_from_addr' => '',
            'mail_smtpauth_req' => '0',
            'mail_smtpssl' => '0',
            'mail_smtpdisplay' => '',  // calculated value, not in DB
            'new_with_id' => '0'
        ];
        $this->mailSettings = new ArrayObject($default_settings, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Retrieves the mailer for a user if they have overridden the username
     * and password for the default system account.
     *
     * @param string $user_id
     * @return ArrayObject
     * @throws Exception
     */
    public function getUsersMailerForSystemOverride(string $user_id): ArrayObject
    {
        $settings = $this->mailSettings->getArrayCopy();
        if (DatabasePDOManager::isInit()) {
            $query = 'SELECT id FROM outbound_email WHERE user_id = :id AND type = :type AND deleted = 0 ORDER BY name';
            $result = $this->pdo->executeQueryResult($query, ['id' => $user_id, 'type' => 'system-override']);
            $row = $this->pdo->fetchAssoc($result);
        } else {
            $query = 'SELECT id FROM outbound_email WHERE user_id = \'' . $user_id . '\' AND type = \'system-override\' AND deleted = 0 ORDER BY name';
            $result = $this->db->query($query);
            $row = $this->db->fetchByAssoc($result);
        }

        if (!empty($row['id'])) {
            return $this->retrieve($row['id']);
        }

        return new ArrayObject($settings, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Duplicate the system account for a user, setting new parameters specific to the user.
     *
     * @param string $user_id
     * @param string $user_name
     * @param string $user_pass
     * @return ArrayObject
     * @throws Exception
     */
    public function createUserSystemOverrideAccount(string $user_id, string $user_name = '', string $user_pass = ''): ArrayObject
    {
        $current = $this->getSystemMailerSettings();
        $current->offsetSet('id', create_guid());
        $current->offsetSet('new_with_id', '1');
        $current->offsetSet('user_id', $user_id);
        $current->offsetSet('type', 'system-override');
        $current->offsetSet('mail_smtpuser', $user_name);
        $current->offsetSet('mail_smtppass', $user_pass);
        $this->save($current);

        return $current;
    }

    /**
     * Determines if a user needs to set their user name/password for their system
     * override account.
     *
     * @param string $user_id
     * @return bool
     * @throws Exception
     */
    public function doesUserOverrideAccountRequireCredentials(string $user_id): bool
    {
        $system_settings = $this->getSystemMailerSettings();
        //If auth for system account is disabled or user can use system outbound account return false.
        if ($system_settings->offsetGet('mail_smtpauth_req') === '0'
            || $this->isAllowUserAccessToSystemDefaultOutbound()
            || $this->mailSettings->offsetGet('mail_sendtype') === 'sendmail') {
            return false;
        }

        $user_override = $this->getUsersMailerForSystemOverride($user_id);
        if (!$user_override->offsetExists('mail_smtpuser')
            || empty($user_override->offsetGet('mail_smtpuser')
                || empty($user_override->offsetGet('mail_smtpuser')))) {
            return true;
        }

        return false;
    }

    /**
     * Retrieves name value pairs for opts lists
     * @param $user
     * @return array
     * @throws Exception
     */
    public function getUserMailers($user): array
    {
        global $app_strings;
        $ret = array();
        $system = $this->getSystemMailerSettings();

        //Now add the system default or user override default to the response.
        if (!empty($system->offsetGet('id'))) {
            if (isSmtp($system->offsetGet('mail_sendtype') ?? '')) {
                $system_errors = '';
                $user_system_override = $this->getUsersMailerForSystemOverride($user->id);

                //If the user is required to to provide a username and password but they have not done so yet,
                //create the account for them.
                $auto_create_user_system_override = false;
                if ($this->doesUserOverrideAccountRequireCredentials($user->id)) {
                    $system_errors = $app_strings['LBL_EMAIL_WARNING_MISSING_USER_CREDS'];
                    $auto_create_user_system_override = true;
                }

                // Substitute in the users system override if its available.
                if (!empty($user_system_override->offsetGet('user_id'))) {
                    $system = $user_system_override;
                } else {
                    if ($auto_create_user_system_override) {
                        $system = $this->createUserSystemOverrideAccount($user->id, '', '');
                    }
                }
                // User overrides can be edited.
                $is_editable = !($system->offsetGet('type') === 'system' || $system->offsetGet('type') === 'system-override');

                if (!empty($system->offsetGet('mail_smtpserver'))) {
                    $system->offsetSet('is_editable', $is_editable);
                    $system->offsetSet('errors', $system_errors);
                }
            } else {
                // use sendmail
                $system->offsetSet('name', "{$system->name} - sendmail");
                $system->offsetSet('is_editable', false);
                $system->offsetSet('errors', '');
            }
        }

        $query = "SELECT * FROM outbound_email WHERE user_id = '{$user->id}' AND type = 'user' ORDER BY name";
        $result = $this->db->query($query);


        while ($a = $this->db->fetchByAssoc($result)) {
            $oe = array();
            if (isSmtp($a['mail_sendtype'] ?? '')) {
                continue;
            }
            $oe['id'] = $a['id'];
            $oe['name'] = $a['name'];
            $oe['type'] = $a['type'];
            $oe['is_editable'] = true;
            $oe['errors'] = '';
            if (!empty($a['mail_smtptype'])) {
                $oe['mail_smtpserver'] = $this->formatOutboundServerDisplay($a['mail_smtptype'], $a['mail_smtpserver']);
            } else {
                $oe['mail_smtpserver'] = $a['mail_smtpserver'];
            }

            $ret[] = $oe;
        }

        return $ret;
    }

    /**
     * Retrieves a cascading mailer set
     * @param object user
     * @param string mailer_id
     * @return object
     * @throws Exception
     * @throws Exception
     */
    public function getUserMailerSettings($user, string $mailer_id = '', string $id = '') : ArrayObject
    {
        $mailer = '';

        if (!empty($mailer_id)) {
            $mailer = "AND id = '{$mailer_id}'";
        } elseif (!empty($id)) {
            $q = "SELECT stored_options FROM inbound_email WHERE id = '{$id}'";
            $r = $this->db->query($q);
            $a = $this->db->fetchByAssoc($r);

            if (!empty($a)) {
                $opts = sugar_unserialize(base64_decode($a['stored_options']));

                if (isset($opts['outbound_email'])) {
                    $mailer = "AND id = '{$opts['outbound_email']}'";
                }
            }
        }

        $q = "SELECT id FROM outbound_email WHERE user_id = '{$user->id}' {$mailer}";
        $r = $this->db->query($q);
        $a = $this->db->fetchByAssoc($r);

        if (empty($a)) {
            $ret = $this->getSystemMailerSettings();
        } else {
            $ret = $this->retrieve($a['id']);
        }

        return $ret;
    }

    /**
     * Retrieve an array containing inbound emails ids for all inbound email accounts which have
     * their outbound account set to this object.
     *
     * @param SugarBean $user
     * @param string $outbound_id
     * @return array
     */
    public function getAssociatedInboundAccounts($user)
    {
        $query = "SELECT id,stored_options FROM inbound_email WHERE is_personal='1' AND deleted='0' AND created_by = '{$user->id}'";
        $rs = $this->db->query($query);

        $results = array();
        while ($row = $this->db->fetchByAssoc($rs)) {
            $opts = sugar_unserialize(base64_decode($row['stored_options']));
            if (isset($opts['outbound_email']) && $opts['outbound_email'] == $this->id) {
                $results[] = $row['id'];
            }
        }

        return $results;
    }

    /**
     * Retrieves a cascading mailer set
     * @param object user
     * @param string mailer_id
     * @return object
     */
    public function getInboundMailerSettings($user, $mailer_id = '', $ieId = '')
    {
        $mailer = '';

        if (!empty($mailer_id)) {
            $mailer = "id = '{$mailer_id}'";
        } elseif (!empty($ieId)) {
            $q = "SELECT stored_options FROM inbound_email WHERE id = '{$ieId}'";
            $r = $this->db->query($q);
            $a = $this->db->fetchByAssoc($r);

            if (!empty($a)) {
                $opts = sugar_unserialize(base64_decode($a['stored_options']));

                if (isset($opts['outbound_email'])) {
                    $mailer = "id = '{$opts['outbound_email']}'";
                } else {
                    $mailer = "id = '{$ieId}'";
                }
            } else {
                // its possible that its an system account
                $mailer = "id = '{$ieId}'";
            }
        }

        if (empty($mailer)) {
            $mailer = "type = 'system'";
        } // if

        $q = "SELECT id FROM outbound_email WHERE {$mailer}";
        $r = $this->db->query($q);
        $a = $this->db->fetchByAssoc($r);

        if (empty($a)) {
            $ret = $this->getSystemMailerSettings();
        } else {
            $ret = $this->retrieve($a['id']);
        }

        return $ret;
    }

    /**
     *  Determine if the user is allowed to use the current system outbound connection.
     * @throws Exception
     */
    public function isAllowUserAccessToSystemDefaultOutbound(): bool
    {
        $access_granted = false;
        $query = 'SELECT id FROM outbound_email WHERE type = \'system\' ORDER BY name';
        if (DatabasePDOManager::isInit()) {
            $result = $this->pdo->executeQueryResult($query);
            $row = $this->pdo->fetchAssoc($result);
        } else {
            $result = $this->db->query($query);
            $row = $this->db->fetchByAssoc($result);
        }
        // first check that row system default exists
        if (!empty($row) && count($row) > 0) {
            // next see if the admin preference for using the system outbound is set
            $admin = BeanFactory::newBean('Administration');
            $admin->retrieveSettings('', true);
            if (isset($admin->settings['notify_allow_default_outbound'])
                && $admin->settings['notify_allow_default_outbound'] === 2
            ) {
                $access_granted = true;
            }
        }

        return $access_granted;
    }

    /**
     * Retrieves the system's Outbound options
     * @return ArrayObject
     * @throws Exception
     */
    public function getSystemMailerSettings(): ArrayObject
    {
        $query = "SELECT id FROM outbound_email WHERE type = 'system' AND deleted = 0";
        if (DatabasePDOManager::isInit()) {
            $result = $this->pdo->executeQueryResult($query);
            $row = $this->pdo->fetchAssoc($result);
        } else {
            $result = $this->db->query($query);
            $row = $this->db->fetchByAssoc($result);
        }

        if (empty($row) || count($row) === 0) {
            $default_settings = [
                'id' => '',
                'name' => 'system',
                'type' => 'system',  // user or system
                'user_id' => '1',  // owner
                'mail_sendtype' => 'SMTP',  // smtp
                'mail_smtptype' => 'other',
                'mail_smtpserver' => '',
                'mail_smtpport' => '25',
                'mail_smtpuser' => '',
                'mail_smtppass' => '',
                'smtp_from_name' => '',
                'smtp_from_addr' => '',
                'mail_smtpauth_req' => '1',
                'mail_smtpssl' => '0',
                'mail_smtpdisplay' => $this->formatOutboundServerDisplay(
                    $this->mailSettings->offsetGet('mail_smtptype'),
                    $this->mailSettings->offsetGet('mail_smtpserver')),  // calculated value, not in DB
                'new_with_id' => '0'
            ];
            $settings = new ArrayObject($default_settings, ArrayObject::ARRAY_AS_PROPS);
            $this->save($settings);

            return $settings;
        }

        return $this->retrieve($row['id']);
    }

    /**
     * Populates this instance
     * @param string $id
     * @return ArrayObject
     * @throws Exception
     */
    public function retrieve(string $id): ArrayObject
    {
        $settings = $this->mailSettings->getArrayCopy();
        if (DatabasePDOManager::isInit()) {
            $query = 'SELECT * FROM outbound_email WHERE id = :id AND deleted = 0';
            $result = $this->pdo->executeQueryResult($query, ['id' => $id]);
            $row = $this->pdo->fetchAssoc($result);


        } else {
            $q = 'SELECT * FROM outbound_email WHERE id = \'' . $id . '\' AND deleted = 0';
            $r = $this->db->query($q);
            $row = $this->db->fetchByAssoc($r);
        }
        if (!empty($row)) {
            foreach ($row as $key => $value) {
                if ($key === 'mail_smtppass' && $value !== '') {
                    $settings[$key] = blowfishDecode(blowfishGetKey('OutBoundEmail'), $value);
                } else {
                    $settings[$key] = $value;
                }
            }

            if (!empty($row['mail_smtptype'])) {
                $settings['mail_smtptype'] = $this->formatOutboundServerDisplay($row['mail_smtptype'], $row['mail_smtpserver']);
            } else {
                $settings['mail_smtptype'] = $row['mail_smtpserver'];
            }
        }

        return new ArrayObject($settings, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @return void
     */
    public function populateFromPost(): void
    {
        foreach ($this->field_defs as $def) {
            if (isset($_POST[$def])) {
                $this->mailSettings->offsetSet($def, $_POST[$def]);
            } else {
                if ($def !== 'mail_smtppass') {
                    $this->mailSettings->offsetSet($def, '');
                }
            }
        }
    }

    /**
     * Generate values for saving into outbound_emails table
     * @param ArrayObject $keys
     * @return array
     */
    protected function getValues(ArrayObject &$keys): array
    {
        $values = array();
        $valid_keys = array();

        $iterator = $keys->getIterator();

        while ($iterator->valid()) {
            $def = $iterator->key();
            $current = $iterator->current();
            if ($def === 'mail_smtppass' && !empty($current)) {
                $current = blowfishEncode(blowfishGetKey('OutBoundEmail'), $current);
            }
            if ($def === 'mail_smtpauth_req' || $def === 'mail_smtpssl' || $def === 'mail_smtpport' || isset($current)) {
                if (empty($current)) {
                    $current = '0';
                }
                $values[] = $current;
                $valid_keys[] = $def;
            }

            $iterator->next();
        }
        $keys = new ArrayObject($valid_keys);

        return $values;
    }

    /**
     * saves an instance
     * @param ArrayObject $settings
     * @throws Exception
     */
    public function save(ArrayObject $settings): void
    {
        $this->checkSavePermissions($settings);

        if (empty($settings->offsetGet('id')) || $settings->offsetGet('id') === '') {
            $settings->offsetSet('id', create_guid());
            $settings->offsetSet('new_with_id', '1');
        }

        $cols = $this->field_defs;
        $values = $this->getValues($settings);

        if (DatabasePDOManager::isInit()) {
            $query = '';
            if ($settings->offsetGet('new_with_id') === '1') {
                $query = <<<EOF
INSERT INTO outbound_email (
                            'id',
                            'name',
                            'type',
                            'user_id',
                            'mail_sendtype',
                            'mail_smtptype',
                            'mail_smtpserver',
                            'mail_smtpport',
                            'mail_smtpuser',
                            'mail_smtppass',
                            'mail_smtpauth_req',
                            'mail_smtpssl',
                            'smtp_from_name',
                            'smtp_from_addr',
                            'deleted'
                        ) VALUES (
                            :id,
                            :type,
                            :user_id,
                            :mail_sendtype,
                            :mail_smtptype,
                            :mail_smtpserver,
                            :mail_smtpport,
                            :mail_smtpuser,
                            :mail_smtppass,
                            :mail_smtpauth_req,
                            :mail_smtpssl,
                            :smtp_from_name,
                            :smtp_from_addr,
                            0
                        );
EOF;
            } else {
                $query = <<<EOF
UPDATE outbound_email
                SET
                    name = :name,
                    type = :type,
                    user_id = :user_id,
                    mail_sendtype = :mail_sendtype,
                    mail_smtptype = :mail_smtptype,
                    mail_smtpserver = :mail_smtpserver,
                    mail_smtpport = :mail_smtpport,
                    mail_smtpuser = :mail_smtpuser,
                    mail_smtppass = :mail_smtppass,
                    mail_smtpauth_req = :mail_smtpauth_req,
                    mail_smtpssl = :mail_smtpssl,
                    smtp_from_name = :smtp_from_name,
                    smtp_from_addr = :smtp_from_addr
                WHERE id = :id AND deleted = 0;
EOF;
            }
            $result = $this->pdo->executeNonQuery($query, [
                'id' => $values['id'],
                'name' => $values['name'],
                'type' => $values['type'],
                'user_id' => $values['user_id'],
                'mail_sendtype' => $values['mail_sendtype'],
                'mail_smtptype' => $values['mail_smtptype'],
                'mail_smtpserver' => $values['mail_smtpserver'],
                'mail_smtpport' => $values['mail_smtpport'],
                'mail_smtpuser' => $values['mail_smtpuser'],
                'mail_smtppass' => $values['mail_smtppass'],
                'mail_smtpauth_req' => $values['mail_smtpauth_req'],
                'mail_smtpssl' => $values['mail_smtpssl'],
                'smtp_from_name' => $values['smtp_from_name'],
                'smtp_from_addr' => $values['smtp_from_addr']
            ]);


            assert($result >= 0);

            return;
        }

        if ($settings->offsetGet('new_with_id') === '1') {
            $q = sprintf('INSERT INTO outbound_email (%s) VALUES (%s)', implode(',', $cols), implode(',', $values));
        } else {
            $updated_values = array();
            foreach ($values as $k => $val) {
                $updated_values[] = "{$cols[$k]} = $val";
            }
            $q = 'UPDATE outbound_email SET ' . implode(
                    ', ',
                    $updated_values
                ) . ' WHERE id = ' . $this->db->quoted($this->id);
        }

        try {
            $this->db->query($q, true);
        } catch (Exception $exp) {
            global $log;
            $arr = [];
            $arr[0] = $exp->getMessage();
            $arr[1] = $exp->getFile();
            $arr[2] = $exp->getLine();
            $arr[3] = $exp->getTraceAsString();

            $log->error(message: $arr);
        }
    }

    /**
     * Saves system mailer.  Presumes all values are filled.
     */
    public function saveSystem()
    {
        $query = "SELECT id FROM outbound_email WHERE type = 'system' AND deleted = 0";
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);

        if (empty($row)) {
            $row['id'] = ''; // trigger insert
        }

        $this->id = $row['id'];
        $this->name = 'system';
        $this->type = 'system';
        $this->user_id = '1';

        if (isset($_REQUEST['notify_fromname']) && $_REQUEST['notify_fromaddress']) {
            $this->smtp_from_name = $_REQUEST['notify_fromname'];
            $this->smtp_from_addr = $_REQUEST['notify_fromaddress'];
        }

        $this->save();

        $this->updateUserSystemOverrideAccounts();
    }

    /**
     * Update the user system override accounts with the system information if anything has changed.
     *
     */
    public function updateUserSystemOverrideAccounts()
    {
        $fields_to_update = array(
            'mail_smtptype',
            'mail_sendtype',
            'mail_smtpserver',
            'mail_smtpport',
            'mail_smtpauth_req',
            'mail_smtpssl'
        );

        // Update the username ans password for the override accounts if access granted.
        if ($this->isAllowUserAccessToSystemDefaultOutbound()) {
            $fields_to_update[] = 'mail_smtpuser';
            $fields_to_update[] = 'mail_smtppass';
        }
        $values = $this->getValues($fields_to_update);
        $values_to_update = array();
        foreach ($values as $k => $val) {
            $values_to_update[] = "{$fields_to_update[$k]} = $val";
        }
        $query = "UPDATE outbound_email set " . implode(', ', $values_to_update) . " WHERE type='system-override' ";

        $this->db->query($query);
    }

    /**
     * Remove all the user override accounts.
     *
     * @return bool
     * @throws Exception
     */
    public function removeUserOverrideAccounts(): bool
    {
        $query = 'UPDATE outbound_email SET deleted = 1 WHERE type = \'system-override\'';

        if (DatabasePDOManager::isInit()) {
            $result = $this->pdo->executeNonQuery($query, []);
            return $result >= 0;
        }

        $statement = $this->db->query($query);
        return is_bool($statement) ? $statement : true;
    }

    /**
     * Deletes an instance
     * @param string $id
     * @return bool
     * @throws Exception
     */
    public function delete(string $id): bool
    {
        if (empty($id)) {
            return false;
        }

        if (DatabasePDOManager::isInit()) {
            $query = 'UPDATE outbound_email SET deleted = 1 WHERE id = :id';
            $result = $this->pdo->executeNonQuery($query, ['id' => $id]);

            return $result >= 0;
        }

        $query = 'UPDATE outbound_email SET deleted = 1 WHERE id = \'' . $id . '\'';
        $statement = $this->db->query($query);
        return is_bool($statement) ? $statement : true;
    }

    /**
     * @param string $smtp_type
     * @param string $smtp_server
     * @return string
     */
    private function formatOutboundServerDisplay(
        string $smtp_type,
        string $smtp_server
    ): string
    {
        global $app_strings;

        return match ($smtp_type) {
            'yahoomail' => $app_strings['LBL_SMTPTYPE_YAHOO'],
            'gmail' => $app_strings['LBL_SMTPTYPE_GMAIL'],
            'exchange' => $smtp_server . ' - ' . $app_strings['LBL_SMTPTYPE_EXCHANGE'],
            default => $smtp_server,
        };
    }

    /**
     * Get mailer for current user by name
     * @param User $user
     * @param string $name
     * @return OutboundEmail|false
     * @throws Exception
     */
    public function getMailerByName(\User $user, string $name): mixed
    {
        if ($name === 'system' && !$this->isAllowUserAccessToSystemDefaultOutbound()) {
            $oe = $this->getUsersMailerForSystemOverride($user->id);
            if ($oe !== null && !empty($oe->id)) {
                return $oe;
            }

            return $this->getSystemMailerSettings();
        }
        $res = $this->db->query("SELECT id FROM outbound_email WHERE user_id = '{$user->id}' AND name='" . $this->db->quote($name) . "'");
        $a = $this->db->fetchByAssoc($res);
        if (!isset($a['id'])) {
            return false;
        }

        return $this->retrieve($a['id']);
    }

    /**
     * @param ArrayObject $settings
     * @return void
     * @throws Exception
     */
    protected function checkSavePermissions(ArrayObject $settings): void
    {
        global $log;

        $original = null;

        if ($settings->offsetExists('id') && !empty($settings->offsetGet('id'))) {
            $original = $this->retrieve($settings->offsetGet('id'));
        }

        if ($original === null) {
            $original = $this->mailSettings;
        }

        $type = $this->mailSettings->offsetGet('type') ?? '';

        $authenticated_user = get_authenticated_user();
        if ($authenticated_user === null) {
            $log->security('OutboundEmail::checkSavePermissions - not logged in - skipping check');
            return;
        }

        if ($type === 'system' && !is_admin($authenticated_user)) {
            $log->security('OutboundEmail::checkSavePermissions - trying to save a system outbound email with non-admin user');
            throw new RuntimeException('Access denied');
        }

        $user_id = $original->offsetGet('user_id') ?? '';

        if (!empty($user_id) && $user_id !== $authenticated_user->id && !is_admin($authenticated_user)) {
            $log->security('OutboundEmail::checkSavePermissions - trying to save a outbound email for another user');
            throw new RuntimeException('Access denied');
        }
    }
}
