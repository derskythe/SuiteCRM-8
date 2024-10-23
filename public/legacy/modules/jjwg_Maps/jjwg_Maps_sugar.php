<?php

#[\AllowDynamicProperties]
class jjwg_Maps_sugar extends Basic
{
    public bool $new_schema = true;
    public string $module_dir = 'jjwg_Maps';
    public string $object_name = 'jjwg_Maps';
    public string $table_name = 'jjwg_maps';
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
    public string $assigned_user_name;
    public $assigned_user_link;
    public $distance;
    public $unit_type;
    public $module_type;
    public string $parent_name;
    public $parent_type;
    public $parent_id;

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
