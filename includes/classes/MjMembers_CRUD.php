<?php

if (!class_exists('MjMembers_CRUD', false)) {
    if (!class_exists('Mj\\Member\\Classes\\Crud\\MjMembers_CRUD', false)) {
        require_once __DIR__ . '/crud/MjMembers_CRUD.php';
    }

    class_alias('Mj\\Member\\Classes\\Crud\\MjMembers_CRUD', 'MjMembers_CRUD');
}
