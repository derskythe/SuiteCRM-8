<?php

#[\AllowDynamicProperties]
class jjwg_Areas_sugar extends Basic
{
    public bool $new_schema = true;
    public string $module_dir = 'jjwg_Areas';
    public string $object_name = 'jjwg_Areas';
    public string $table_name = 'jjwg_areas';
    public bool $importable = true;
    public $disable_row_level_security = true;
    public string $id;
    public string $name;
    public string $date_entered;
    public string $date_modified;
    public string $modified_user_id;
    public string $modified_by_name;
    public string $created_by;
    public string $created_by_name;
    public string $description;
    public int $deleted;
    public $created_by_link;
    public $modified_user_link;
    public string $assigned_user_id;
    public $assigned_user_name;
    public $assigned_user_link;
    public $city;
    public $state;
    public $country;
    public $coordinates;

    public function __construct()
    {
        parent::__construct();
    }




    public function bean_implements($interface) : bool
    {
        switch ($interface) {
            case 'ACL': return true;
        }
        return false;
    }
}
