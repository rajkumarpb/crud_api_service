# crud_api_service
PHP Service for building simple CRUD APIs

## Installation

In the console:

```
composer require akuehnis/crud_api_service
```

## Basic Usage

Simple Example using a Mysql Database

```php
// index.php

$api = new $api = new \Akuehnis\CrudApiService\Api('localhost', 'database_user', 'user_password', 'name_of_the_database');
$api->setTable('table_name');

$id = isset($_GET['id']) ? $_GET['id'] : null;

if ('GET' == $_SERVER['REQUEST_METHOD']) {
    if (null === $id) {
        list($data, $err) = $api->readAction($_GET);
    } else {
        list($data, $err) = $api->readOneAction($id);
    }
} elseif ('POST' == $_SERVER['REQUEST_METHOD']) {
    if (null === $id) {
        list($data, $err) = $api->createAction($_POST);
    } else {
        list($data, $err) = $api->updateAction($id, $_POST);
    }
} elseif ('DELETE' == $_SERVER['REQUEST_METHOD']) {
    if (null === $id) {
        $data = null;
        $err = 'No Id';
    } else{
        list($data, $err) = $api->deleteAction($id);
    }
} else {
    $data = null;
    $err = 'No Action';
}
if (null === $err) {
    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    http_response_code(400);
    echo $err;
}

```
