<?php

if (!class_exists('MjMembers', false)) {
    if (!class_exists('Mj\\Member\\Classes\\Crud\\MjMembers', false)) {
        require_once __DIR__ . '/crud/MjMembers.php';
    }

    class_alias('Mj\\Member\\Classes\\Crud\\MjMembers', 'MjMembers');
}

if (!class_exists('MjMembers_CRUD', false)) {
    class_alias('Mj\\Member\\Classes\\Crud\\MjMembers', 'MjMembers_CRUD');
}
